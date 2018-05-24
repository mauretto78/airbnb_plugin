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

class Airbnb extends BaseFeature {

    use TranslatedTrait;

    const FEATURE_CODE = "airbnb";

    public static $dependencies = [];

    public static function loadRoutes( Klein $klein ) {
        //route( '/job/[:id_job]/[:password]/sign_off', 'GET', 'Features\Airbnb\Controller\SignOffController', 'signedOffCallback' );
    }

    public function afterTMAnalysisCloseProject( $project_id , $_analyzed_report) {
        $this->setSuccessMailSender( new ConfirmedQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/confirmed_quotation.html' ) );
        $this->setFailureMailSender( new ErrorQuotationEmail( self::getPluginBasePath() . '/Features/Airbnb/View/Emails/error_quotation.html' ) );
        $this->requestProjectQuote( $project_id, $_analyzed_report );
    }

    public function projectUrls( ProjectUrls $formatted ){
        return $formatted;
    }

}