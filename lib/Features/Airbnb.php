<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/04/2018
 * Time: 14:57
 */

namespace Features;

use Analysis\Workers\TMAnalysisWorker;
use API\V2\Json\ProjectUrls;
use Exceptions\ValidationError;
use Features;
use Features\Airbnb\Utils\Email\ConfirmedQuotationEmail;
use Features\Airbnb\Utils\Email\ErrorQuotationEmail;
use Features\Airbnb\Utils\SubFiltering\Filters\Variables;
use Features\Outsource\Constants\ServiceTypes;
use Features\Outsource\Traits\Translated as TranslatedTrait;
use Klein\Klein;
use Segments_SegmentStruct;
use SubFiltering\Commons\Pipeline;
use SubFiltering\Filters\HtmlToPh;
use SubFiltering\Filters\HtmlToPhToLayer2;
use SubFiltering\Filters\LtGtDoubleDecode;
use SubFiltering\Filters\PlaceHoldXliffTags;

class Airbnb extends BaseFeature {

    use TranslatedTrait;

    const FEATURE_CODE = "airbnb";

    protected static $service_types = [ 'standard', 'priority' ];

    const REFERENCE_QUOTE_METADATA_KEY = "append_to_pid";

    const BATCH_WORD_COUNT_METADATA_KEY = "batch_word_count";

    const DELIVERY_COOKIE_PREFIX ='airbnb_session_'  ;

    public static $dependencies = [
        Features::TRANSLATION_VERSIONS,
        Features::REVIEW_EXTENDED
    ];

    public static function loadRoutes( Klein $klein ) {
        route( '/job/[:id_job]/[:password]/segment_delivery/[:id_segment]/session', 'GET', 'Features\Airbnb\Controller\SegmentDeliveryController', 'startSession' );
        route( '/job/[:id_job]/[:password]/segment_delivery', 'POST', 'Features\Airbnb\Controller\SegmentDeliveryController', 'send' );
    }

    /**
     * @see \ProjectManager::_storeSegments()
     *
     * @param $_segment_metadata array
     * @param $projectStructure
     *
     * @return array
     */
    public function appendFieldToAnalysisObject( $_segment_metadata, \ArrayObject $projectStructure ){

        if ( $projectStructure[ 'notes' ]->offsetExists( $_segment_metadata[ 'internal_id' ] ) ) {

            foreach( $projectStructure[ 'notes' ][ $_segment_metadata[ 'internal_id' ] ][ 'entries' ] as $k => $entry ){

                if( strpos( $entry, 'phrase_key|¶|' ) !== false ){
                    $_segment_metadata[ 'additional_params' ][ 'spice' ] = md5( str_replace( 'phrase_key|¶|', '', $entry ) . $_segment_metadata[ 'segment' ] );
                }

            }

        }

        return $_segment_metadata;

    }

    /**
     * @see \Engines_MyMemory::get()
     * @see Airbnb::appendFieldToAnalysisObject()
     *
     * @param $parameters
     *
     * @param $original_config
     *
     * @return mixed
     */
    public function filterMyMemoryGetParameters( $parameters, $original_config ){

        /*
         * From analysis we will have additional params and spice field
         */
        if( isset( $original_config[ 'additional_params' ][ 'spice' ] ) ){
            $parameters[ 'context_before' ] = $original_config[ 'additional_params' ][ 'spice' ];
            $parameters[ 'context_after' ]  = null;
        }

        $parameters[ 'cid' ] = Airbnb::FEATURE_CODE;

        return $parameters;

    }

    /**
     * @see \getContributionController::doAction()
     * @see \setTranslationController
     *
     * @param $segmentsList Segments_SegmentStruct[]
     * @param $postInput
     */
    public function rewriteContributionContexts( $segmentsList, $postInput ){
        $segmentsList->id_before->segment = md5( str_replace( 'phrase_key|¶|', '', $postInput[ 'context_before' ] ) . $segmentsList->id_segment->segment );
        $segmentsList->id_after = null;
    }


    /**
     * @param                         $urls
     * @param \Projects_ProjectStruct $project
     *
     * @return string
     */
    protected function prepareConfirmUrl( $urls, \Projects_ProjectStruct $project ) {

        return "http://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'                => 'confirm',
                        'cid'              => $this->config[ 'translated_username' ],
                        'p'                => $this->config[ 'translated_password' ],
                        'pid'              => $this->external_project_id,
                        'c'                => 1,
                        'of'               => "json",
                        'urls'             => json_encode( $urls ),
                        'append_to_pid'    => ( !empty( $this->external_parent_project_id ) ? $this->external_parent_project_id : null ),
                        'batch_word_count' => ( !empty( $this->total_batch_word_count ) ? $this->total_batch_word_count : null ),
                        'matecat_host'     => parse_url( \INIT::$HTTPHOST, PHP_URL_HOST ),
                        'on_tool'          => !in_array( $project->id_customer, $this->config[ 'airbnb_translated_internal_user' ] )
                ], PHP_QUERY_RFC3986 );

    }

    /**
     * @param \Jobs_JobStruct         $job
     * @param                         $eq_word
     * @param \Projects_ProjectStruct $project
     * @param string                  $service_type
     *
     * @return string
     */
    protected function prepareQuoteUrl( \Jobs_JobStruct $job, $eq_word, \Projects_ProjectStruct $project, $service_type = ServiceTypes::SERVICE_TYPE_PROFESSIONAL ){

        return "http://www.translated.net/hts/index.php?" . http_build_query( [
                        'f'                => 'quote',
                        'cid'              => $this->config[ 'translated_username' ],
                        'p'                => $this->config[ 'translated_password' ],
                        's'                => $job->source,
                        't'                => $job->target,
                        'pn'               => $project->name,
                        'w'                => ( is_null( $eq_word ) ? 0 : $eq_word ),
                        'df'               => 'matecat',
                        'matecat_pid'      => $project->id,
                        'matecat_ppass'    => $project->password,
                        'matecat_pname'    => $project->name,
                        'subject'          => $job->subject,
                        'jt'               => $service_type,
                        'fd'               => 0,
                        'of'               => 'json',
                        'matecat_raw'      => $job->total_raw_wc,
                        'batch_word_count' => ( !empty( $this->total_batch_word_count ) ? $this->total_batch_word_count : null ),
                        'on_tool'          => !in_array( $project->id_customer, $this->config[ 'airbnb_translated_internal_user' ] )
                ], PHP_QUERY_RFC3986 );

    }

    /**
     * @see TMAnalysisWorker::_tryToCloseProject()
     *
     * @param $project_id
     * @param $_analyzed_report
     *
     * @throws \Exception
     */
    public function afterTMAnalysisCloseProject( $project_id, $_analyzed_report ) {

        $config = self::getConfig();
        $projectStruct = \Projects_ProjectDao::findById( $project_id );
        $internal_users = $config[ 'airbnb_translated_internal_user' ];

        if( !in_array( $projectStruct->id_customer, $internal_users ) ){

            $metadataDao            = new \Projects_MetadataDao();
            $quote_pid_append       = @$metadataDao->get( $project_id, Airbnb::REFERENCE_QUOTE_METADATA_KEY )->value;
            $total_batch_word_count = @$metadataDao->get( $project_id, Airbnb::BATCH_WORD_COUNT_METADATA_KEY )->value;

            if( !empty( $quote_pid_append ) ){
                $this->setExternalParentProjectId( $quote_pid_append );
            }

            if( !empty( $total_batch_word_count ) ){
                $this->setTotalBatchWordCount( $total_batch_word_count );
            }

            $this->setSuccessMailSender( new ConfirmedQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/confirmed_quotation.html' ) );
            $this->setFailureMailSender( new ErrorQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/error_quotation.html' ) );
            $this->requestProjectQuote( $projectStruct, $_analyzed_report, ServiceTypes::SERVICE_TYPE_PREMIUM );

        }

    }

    /**
     * @param ProjectUrls $formatted
     *
     * @return \API\V2\JSON\ProjectUrls
     */
    public static function projectUrls( ProjectUrls $formatted ) {
        return $formatted;
    }

    /**
     * Add options to project metadata
     *
     * @see \NewController::__validateMetadataParam()
     *
     * @param $metadata
     * @param $__postInput
     *
     * @return mixed
     * @throws \Exception
     */
    public function filterProjectMetadata( $metadata, $__postInput ) {

        if ( isset( $__postInput[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ] ) && !empty( $__postInput[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ] ) ) {
            if ( !is_numeric( $__postInput[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ] ) ) {
                throw new ValidationError( "Quote PID '{$__postInput[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ]}' is not allowed. Only numbers allowed." );
            }
            $metadata[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ] = $__postInput[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ];
        }

        if ( isset( $__postInput[ Airbnb::BATCH_WORD_COUNT_METADATA_KEY ] ) && !empty( $__postInput[ Airbnb::BATCH_WORD_COUNT_METADATA_KEY ] ) ) {
            if ( !is_numeric( $__postInput[ Airbnb::BATCH_WORD_COUNT_METADATA_KEY ] ) ) {
                throw new ValidationError( "Quote PID '{$__postInput[ Airbnb::BATCH_WORD_COUNT_METADATA_KEY ]}' is not allowed. Only numbers allowed." );
            }
            $metadata[ Airbnb::BATCH_WORD_COUNT_METADATA_KEY ] = $__postInput[ Airbnb::BATCH_WORD_COUNT_METADATA_KEY ];
        }

        return $metadata;
    }

    /**
     * @see \NewController::__construct()
     * @param $filter_args
     *
     * @return mixed
     */
    public function filterNewProjectInputFilters( $filter_args ) {
        $filter_args[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ]  = [ 'filter' => FILTER_SANITIZE_NUMBER_INT ];
        $filter_args[ Airbnb::BATCH_WORD_COUNT_METADATA_KEY ] = [ 'filter' => FILTER_SANITIZE_NUMBER_INT ];
        return $filter_args;
    }

    /**
     * Entry point for project data validation for this feature.
     *
     * @param $projectStructure
     */
    public function validateProjectCreation( $projectStructure )  {
        //override Revise Improved qa Model
        $qa_mode_file = realpath( self::getPluginBasePath() . "/../qa_model.json" );
        ReviewExtended::loadAndValidateModelFromJsonFile( $projectStructure, $qa_mode_file );
    }

    public function fromLayer0ToLayer1( Pipeline $channel ) {
        $channel->addAfter( new HtmlToPh(), new Variables() );
        return $channel;
    }

    public function fromLayer0ToLayer2( Pipeline $channel ) {
        $channel->addAfter( new HtmlToPhToLayer2(), new Variables() );
        return $channel;
    }

    public function fromRawXliffToLayer0( Pipeline $channel ){
        $channel->addAfter( new PlaceHoldXliffTags(), new LtGtDoubleDecode() ); // Fix source &amp;lt;&gt; // Hope and Pray
        return $channel;
    }

    public function checkTagMismatch( $errorType, \QA $QA ){
        if( strpos( $QA->getSourceSeg(), "|||" ) !== false ){
            $QA->addCustomError( [
                    'code'  => 2000,
                    'debug' => 'Smart Count variable missing',
                    'tip'   => 'Check your language specific configuration.'
            ] );
            return 2000;
        }
        return $errorType;
    }

}
