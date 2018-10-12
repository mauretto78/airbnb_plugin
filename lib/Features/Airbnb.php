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
use Contribution\ContributionStruct;
use Engines\Traits\HotSwap;
use Engines_AbstractEngine;
use Engines_MMT;
use Features\Airbnb\Utils\Email\ConfirmedQuotationEmail;
use Features\Airbnb\Utils\Email\ErrorQuotationEmail;
use Klein\Klein;
use Features;
use \Features\Outsource\Traits\Translated as TranslatedTrait;
use Features\Outsource\Constants\ServiceTypes;
use Segments_SegmentStruct;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\ReQueueException;

class Airbnb extends BaseFeature {

    use TranslatedTrait, HotSwap;

    const FEATURE_CODE = "airbnb";

    protected static $service_types = [ 'standard', 'priority' ];

    const REFERENCE_QUOTE_METADATA_KEY = "append_to_pid";

    public static $dependencies = [
//            Features::TRANSLATION_VERSIONS,
//            Features::REVIEW_EXTENDED
    ];

    public static function loadRoutes( Klein $klein ) {
        //route( '/job/[:id_job]/[:password]/sign_off', 'GET', 'Features\Airbnb\Controller\SignOffController', 'signedOffCallback' );
    }

    /**
     * Allow plugins to force to send requests to MMT even if in analysis
     * @see Engines_MMT::get()
     */
//    public function forceMMTAcceptAnalysisRequests( $bool ){
////        return true;
//        return $bool; //false
//    }

    /**
     * @see TMAnalysisWorker::_getMatches()
     *
     * @param                        $config
     * @param Engines_AbstractEngine $engine
     * @param QueueElement           $queueElement
     *
     * @return mixed
     * @throws \Exception
     */
//    public function analysisBeforeMTGetContribution( $config, Engines_AbstractEngine $engine, QueueElement $queueElement ){
//
//        if( $engine instanceof Engines_MMT ){
//
//            $config[ 'keys' ] = array_values( $config[ 'id_user' ] );
//            $mt_context = @array_pop( ( new MetadataDao() )->setCacheTTL( 60 * 60 * 24 * 30 )->getByIdJob( $queueElement->params->id_job, 'mt_context' ) );
//            $config[ 'mt_context' ] = ( !empty( $mt_context ) ? $mt_context->value : "" );
//            $config[ 'job_id' ] = $queueElement->params->id_job;
//
//        }
//
//        return $config;
//    }

    /**
     * @see TMAnalysisWorker::_getMatches()
     *
     * @param QueueElement           $queueElement
     *
     * @param Engines_AbstractEngine $mt
     *
     * @throws ReQueueException
     */
//    public function handleMTAnalysisRetry( QueueElement $queueElement, Engines_AbstractEngine $mt ){
//
//        if( $mt instanceof Engines_MMT ){
//            $queueElement->params->id_mt_engine = 1;
//            throw new ReQueueException( "Error from MMT. Empty field received even if MT was requested.", TMAnalysisWorker::ERR_REQUEUE );
//        }
//
//    }

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
     * @see \ProjectManager::_createJobs()
     *
     * @param \Jobs_JobStruct $jobStruct
     *
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    public function beforeInsertJobStruct( \Jobs_JobStruct $jobStruct ){
        $this->swapOn( $jobStruct );
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

        if( $projectStruct->id_customer != $config[ 'airbnb_Translated_internal_user' ] ){

            $metadataDao = new \Projects_MetadataDao();
            $quote_pid_append = @$metadataDao->get( $project_id, Airbnb::REFERENCE_QUOTE_METADATA_KEY )->value;

            if( !empty( $quote_pid_append ) ){
                $this->setExternalParentProjectId( $quote_pid_append );
            }

            $this->setSuccessMailSender( new ConfirmedQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/confirmed_quotation.html' ) );
            $this->setFailureMailSender( new ErrorQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/error_quotation.html' ) );
            $this->requestProjectQuote( $projectStruct, $_analyzed_report, ServiceTypes::SERVICE_TYPE_PREMIUM );

        }

        $this->swapOff( $project_id );

    }

    public function projectUrls( ProjectUrls $formatted ) {
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
                throw new \Exception( "Quote PID '{$__postInput[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ]}' is not allowed. Only numbers allowed." );
            }
            $metadata[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ] = $__postInput[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ];
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
        $filter_args[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ] = [ 'filter' => FILTER_SANITIZE_NUMBER_INT ];
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

}