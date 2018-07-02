<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/04/2018
 * Time: 14:57
 */

namespace Features;

use API\V2\Json\ProjectUrls;
use Features\Airbnb\Utils\Email\ConfirmedQuotationEmail;
use Features\Airbnb\Utils\Email\ErrorQuotationEmail;
use Klein\Klein;
use \Features\Outsource\Traits\Translated as TranslatedTrait;
use Features\Outsource\Constants\ServiceTypes;

class Airbnb extends BaseFeature {

    use TranslatedTrait;

    const FEATURE_CODE = "airbnb";

    protected static $service_types = [ 'standard', 'priority' ];

    const REFERENCE_QUOTE_METADATA_KEY = "append_to_pid";

    public static $dependencies = [];

    public static function loadRoutes( Klein $klein ) {
        //route( '/job/[:id_job]/[:password]/sign_off', 'GET', 'Features\Airbnb\Controller\SignOffController', 'signedOffCallback' );
    }

    public function afterTMAnalysisCloseProject( $project_id, $_analyzed_report ) {

        $metadataDao = new \Projects_MetadataDao();
        $quote_pid_append = $metadataDao->get( $project_id, Airbnb::REFERENCE_QUOTE_METADATA_KEY )->value;

        if( !empty( $quote_pid_append ) ){
            $this->setExternalParentProjectId( $quote_pid_append );
        }

        $this->setSuccessMailSender( new ConfirmedQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/confirmed_quotation.html' ) );
        $this->setFailureMailSender( new ErrorQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/error_quotation.html' ) );
        $this->requestProjectQuote( $project_id, $_analyzed_report, ServiceTypes::SERVICE_TYPE_PREMIUM );
    }

    public function projectUrls( ProjectUrls $formatted ) {
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

    public function filterNewProjectInputFilters( $filter_args ) {
        $filter_args[ Airbnb::REFERENCE_QUOTE_METADATA_KEY ] = [ 'filter' => FILTER_SANITIZE_NUMBER_INT ];
        return $filter_args;
    }

}