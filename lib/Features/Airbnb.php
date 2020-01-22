<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/04/2018
 * Time: 14:57
 */

namespace Features;

use API\V2\Json\ProjectUrls;
use Exceptions\ValidationError;
use Features;
use Features\Airbnb\Utils\SubFiltering\Filters\SmartCounts;
use Features\Airbnb\Utils\SubFiltering\Filters\Variables;
use Klein\Klein;
use Predis\Connection\ConnectionException;
use ReflectionException;
use Segments_SegmentStruct;
use SubFiltering\Commons\Pipeline;
use SubFiltering\Filters\HtmlToPh;
use SubFiltering\Filters\HtmlToPhToLayer2;
use SubFiltering\Filters\LtGtDoubleDecode;
use SubFiltering\Filters\PlaceHoldXliffTags;
use Users_UserStruct;

class Airbnb extends BaseFeature {

    const FEATURE_CODE = "airbnb";

    protected static $service_types = [ 'standard', 'priority' ];

    const REFERENCE_QUOTE_METADATA_KEY = "append_to_pid";

    const BATCH_WORD_COUNT_METADATA_KEY = "batch_word_count";

    const MANUAL_APPROVE_METADATA_KEY = "manual_setup";

    const DELIVERY_COOKIE_PREFIX ='airbnb_session_'  ;

    public static $dependencies = [
        Features::TRANSLATION_VERSIONS,
        Features::REVIEW_EXTENDED
    ];

    public static function loadRoutes( Klein $klein ) {
        route( '/job/[:id_job]/[:password]/segment_delivery/[:id_segment]/session', 'POST', 'Features\Airbnb\Controller\SegmentDeliveryController', 'auth' );
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

    public function filterRevisionChangeNotificationList( $emails ) {
        // TODO: add custom email recipients here
        $config = self::getConfig();

        if ( isset( $config['revision_change_notification_recipients'] ) ) {
            foreach( $config['revision_change_notification_recipients'] as $recipient ) {
                list($firstName, $lastName, $email) = explode(',', $recipient ) ;
                $emails[] = [
                        'recipient' => new Users_UserStruct([
                                'email'      => $email,
                                'first_name' => $firstName,
                                'last_name'  => $lastName
                        ]),
                        'isPreviousChangeAuthor' => false
                ];

            }
        }

        return $emails ;
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

        if ( isset( $__postInput[ Airbnb::MANUAL_APPROVE_METADATA_KEY ] ) && !empty( $__postInput[ Airbnb::MANUAL_APPROVE_METADATA_KEY ] ) ) {
            $metadata[ Airbnb::MANUAL_APPROVE_METADATA_KEY ] = $__postInput[ Airbnb::MANUAL_APPROVE_METADATA_KEY ];
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
        $filter_args[ Airbnb::MANUAL_APPROVE_METADATA_KEY ]   = [ 'filter' => FILTER_VALIDATE_BOOLEAN | FILTER_NULL_ON_FAILURE ];
        return $filter_args;
    }

    /**
     * Entry point for project data validation for this feature.
     *
     * @param $projectStructure
     *
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function validateProjectCreation( $projectStructure )  {
        //override Revise Improved qa Model
        $qa_mode_file = realpath( self::getPluginBasePath() . "/../qa_model.json" );
        ReviewExtended::loadAndValidateModelFromJsonFile( $projectStructure, $qa_mode_file );
    }

    public function fromLayer0ToLayer1( Pipeline $channel ) {
        $channel->addAfter( new HtmlToPh(), new Variables() );
        $channel->addAfter( new Variables(), new SmartCounts() );
        return $channel;
    }

    public function fromRawXliffToLayer0( Pipeline $channel ){
        $channel->addAfter( new PlaceHoldXliffTags(), new LtGtDoubleDecode() ); // Fix source &amp;lt;&gt; // Hope and Pray
        return $channel;
    }

    public function checkTagMismatch( $errorType, \QA $QA ){
        //check for smart count sign ( base64 encoded "||||" === "fHx8fA==" )
        if( strpos( $QA->getSourceSeg(), "equiv-text=\"base64:fHx8fA==\"" ) !== false ){
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
