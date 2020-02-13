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
use Features\Airbnb\Utils\SmartCount\Pluralization;
use Features\Airbnb\Utils\SubFiltering\Filters\SmartCounts;
use Features\Airbnb\Utils\SubFiltering\Filters\Variables;
use Klein\Klein;
use Predis\Connection\ConnectionException;
use ReflectionException;
use Segments_SegmentStruct;
use SubFiltering\Commons\Pipeline;
use SubFiltering\Filters\HtmlToPh;
use SubFiltering\Filters\LtGtDoubleDecode;
use SubFiltering\Filters\PlaceHoldXliffTags;
use Users_UserStruct;

class Airbnb extends BaseFeature {

    const FEATURE_CODE = "airbnb";

    protected static $service_types = [ 'standard', 'priority' ];

    const REFERENCE_QUOTE_METADATA_KEY = "append_to_pid";

    const BATCH_WORD_COUNT_METADATA_KEY = "batch_word_count";

    const MANUAL_APPROVE_METADATA_KEY = "manual_setup";

    const DELIVERY_COOKIE_PREFIX = 'airbnb_session_';

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
     * @param $_segment_metadata array
     * @param $projectStructure
     *
     * @return array
     * @see \ProjectManager::_storeSegments()
     *
     */
    public function appendFieldToAnalysisObject( $_segment_metadata, \ArrayObject $projectStructure ) {

        if ( $projectStructure[ 'notes' ]->offsetExists( $_segment_metadata[ 'internal_id' ] ) ) {

            foreach ( $projectStructure[ 'notes' ][ $_segment_metadata[ 'internal_id' ] ][ 'entries' ] as $k => $entry ) {

                if ( strpos( $entry, 'phrase_key|¶|' ) !== false ) {
                    $_segment_metadata[ 'additional_params' ][ 'spice' ] = md5( str_replace( 'phrase_key|¶|', '', $entry ) . $_segment_metadata[ 'segment' ] );
                }

            }

        }

        return $_segment_metadata;

    }

    /**
     * @param $parameters
     *
     * @param $original_config
     *
     * @return mixed
     * @see Airbnb::appendFieldToAnalysisObject()
     *
     * @see \Engines_MyMemory::get()
     */
    public function filterMyMemoryGetParameters( $parameters, $original_config ) {

        /*
         * From analysis we will have additional params and spice field
         */
        if ( isset( $original_config[ 'additional_params' ][ 'spice' ] ) ) {
            $parameters[ 'context_before' ] = $original_config[ 'additional_params' ][ 'spice' ];
            $parameters[ 'context_after' ]  = null;
        }

        $parameters[ 'cid' ] = Airbnb::FEATURE_CODE;

        return $parameters;

    }

    public function filterRevisionChangeNotificationList( $emails ) {
        // TODO: add custom email recipients here
        $config = self::getConfig();

        if ( isset( $config[ 'revision_change_notification_recipients' ] ) ) {
            foreach ( $config[ 'revision_change_notification_recipients' ] as $recipient ) {
                list( $firstName, $lastName, $email ) = explode( ',', $recipient );
                $emails[] = [
                        'recipient'              => new Users_UserStruct( [
                                'email'      => $email,
                                'first_name' => $firstName,
                                'last_name'  => $lastName
                        ] ),
                        'isPreviousChangeAuthor' => false
                ];

            }
        }

        return $emails;
    }

    /**
     * @param $segmentsList Segments_SegmentStruct[]
     * @param $postInput
     *
     * @see \getContributionController::doAction()
     * @see \setTranslationController
     *
     */
    public function rewriteContributionContexts( $segmentsList, $postInput ) {
        $segmentsList->id_before->segment = md5( str_replace( 'phrase_key|¶|', '', $postInput[ 'context_before' ] ) . $segmentsList->id_segment->segment );
        $segmentsList->id_after           = null;
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
     * @param $metadata
     * @param $__postInput
     *
     * @return mixed
     * @throws \Exception
     * @see \NewController::__validateMetadataParam()
     *
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
     * @param $filter_args
     *
     * @return mixed
     * @see \NewController::__construct()
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
    public function validateProjectCreation( $projectStructure ) {
        //override Revise Improved qa Model
        $qa_mode_file = realpath( self::getPluginBasePath() . "/../qa_model.json" );
        ReviewExtended::loadAndValidateModelFromJsonFile( $projectStructure, $qa_mode_file );
    }

    public function fromLayer0ToLayer1( Pipeline $channel ) {
        $channel->addAfter( new HtmlToPh(), new Variables() );
        $channel->addAfter( new Variables(), new SmartCounts() );

        return $channel;
    }

    public function fromRawXliffToLayer0( Pipeline $channel ) {
        $channel->addAfter( new PlaceHoldXliffTags(), new LtGtDoubleDecode() ); // Fix source &amp;lt;&gt; // Hope and Pray

        return $channel;
    }

    /**
     * Check tag mismatch / smartcount consistency
     * -------------------------------------------------
     * NOTE 09/01/2020
     * -------------------------------------------------
     * This function does the following:
     *
     * - obtain the count of |||| separators in target raw text, and then sum 1 to calculate the expected tag count;
     * - obtain the number of possible plural forms of the target language (ex: "ar-SA --> 6");
     * - check for the equivalence of these two numbers, and in case return an error code 2000.
     *
     * Example:
     *
     * [source:en-US] - House |||| Houses
     * [target:ar-SA] - XXXX |||| XXXX |||| XXXX |||| XXXX |||| XXXX |||| XXXX
     *
     * $separatorCount = 5
     * $expectedTagCount = 6
     * $pluralizationCountForTargetLang = Pluralization::getCountFromLang('ar-SA') = 6
     *
     * No error will be produced.
     *
     * @param     $errorType
     * @param \QA $QA
     *
     * @return int
     */
    public function checkTagMismatch( $errorType, \QA $QA ) {

        //check for smart count separator sign ( base64 encoded "||||" === "fHx8fA==" )
        if ( strpos( $QA->getSourceSeg(), "equiv-text=\"base64:fHx8fA==\"" ) !== false ) {

            $separatorCount                  = substr_count( $QA->getTargetSeg(), "equiv-text=\"base64:fHx8fA==\"" );
            $pluralizationCountForTargetLang = Pluralization::getCountFromLang( $QA->getTargetSegLang() );

            // check for |||| count correspondence
            if ( (1 + $separatorCount) !== $pluralizationCountForTargetLang ) {
                $QA->addCustomError( [
                        'code'  => \QA::SMART_COUNT_PLURAL_MISMATCH,
                        'debug' => 'Smart Count rules not compliant with target language',
                        'tip'   => 'Check your language specific configuration.'
                ] );

                return \QA::SMART_COUNT_PLURAL_MISMATCH;
            }

            //
            // check the count of smart_count tags in the target
            //
            // $targetSegMatch example:
            //
            // Array
            // (
            //    [0] => Array
            //        (
            //            [0] => <ph id="mtc_1" equiv-text="base64:JXtzbWFydF9jb3VudH0="/>
            //            [1] => <ph id="mtc_2" equiv-text="base64:fHx8fA=="/>
            //            [2] => <ph id="mtc_3" equiv-text="base64:JXtzbWFydF9jb3VudH0="/>
            //            [3] => <ph id="mtc_2" equiv-text="base64:fHx8fA=="/>
            //        )
            //
            // )
            preg_match_all( '/<ph id ?= ?[\'"]mtc_[0-9]{1,9}?[\'"] equiv-text="base64:[a-zA-Z0-9=]{1,}"\/>/', $QA->getSourceSeg(), $sourceSegMatch );
            preg_match_all( '/<ph id ?= ?[\'"]mtc_[0-9]{1,9}?[\'"] equiv-text="base64:[a-zA-Z0-9=]{1,}"\/>/', $QA->getTargetSeg(), $targetSegMatch );

            $sourceTagMap  = $this->getTagMap($sourceSegMatch[ 0 ]);
            $targetTagMap  = $this->getTagMap($targetSegMatch[ 0 ]);

            foreach ($sourceTagMap as $key => $count){

                //
                // calculate the expected tag count
                //
                // Example:
                //
                // Suppose that there are 4 tags %{new_line_break} in the source
                //
                // if target has 1 plural forms the expected tag count is 2
                // if target has 2 plural forms the expected tag count is 4
                // if target has 3 plural forms the expected tag count is 6
                //
                $expectedTagCount = ( $count / 2 ) * $pluralizationCountForTargetLang;

                if( false === isset($targetTagMap[$key]) or $targetTagMap[$key] !== $expectedTagCount  ){
                    $QA->addCustomError( [
                            'code'  => \QA::SMART_COUNT_MISMATCH,
                            'debug' => '%{smart_count} tag count mismatch',
                            'tip'   => 'Check the count of %{smart_count} tags in the source.'
                    ] );

                    return \QA::SMART_COUNT_MISMATCH;
                }

            }

            $QA->addCustomError( [
                    'code' => 0,
            ] );

            return 0;
        }

        return $errorType;
    }

    /**
     * Extract the tags and creates a map like that:
     *
     * Array
     * (
     *    [JXtzbWFydF9jb3VudH0=] => 2
     * )
     *
     * @param $array
     *
     * @return array
     */
    protected function getTagMap( $array){
        $srcTagMap = [];

        foreach ( $array as $tag ) {

            preg_match_all('/equiv-text="base64:[a-zA-Z0-9=]{1,}/', $tag, $output_array);

            $output_array = str_replace('equiv-text="base64:','', $output_array[0][0]);

            if( $output_array !== 'fHx8fA=='  ){
                $srcTagMap[$output_array] = (isset($srcTagMap[$output_array])) ? $srcTagMap[$output_array] + 1 : 1;
            }
        }

        return $srcTagMap;
    }
}
