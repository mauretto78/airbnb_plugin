<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/04/2018
 * Time: 18:41
 */

namespace Features\Airbnb\Utils\Email;

use Email\AbstractEmail;
use Features\Microsoft;
use INIT;

class ConfirmedQuotationEmail extends AbstractEmail {

    protected $title = 'Confirmed Quotation';
    protected $internal_project_id;
    protected $internal_job_id;
    protected $external_project_id;


    public function __construct( $templatePath ) {

        $this->_setLayout( 'skeleton.html' );
        $this->_setTemplateByPath( $templatePath );
    }

    public function send() {
        $config = Microsoft::getConfig();
        $this->sendTo( $config[ 'success_quotation_email_address' ], "Translated Team" );
    }

    public function setInternalIdProject( $id ) {
        $this->internal_project_id = $id;
    }

    public function setInternalJobId( $id ) {
        $this->internal_job_id = $id;
    }

    public function setExternalProjectId( $id ) {
        $this->external_project_id = $id;
    }

    protected function _getTemplateVariables() {
        return [
                'internal_project_id' => $this->internal_project_id,
                'internal_job_id'     => $this->internal_job_id,
                'external_project_id' => $this->external_project_id
        ];
    }

    protected function _getLayoutVariables() {
        $vars            = parent::_getLayoutVariables();
        $vars[ 'title' ] = $this->title;

        return $vars;
    }


    protected function _getDefaultMailConf() {
        $mailConf = parent::_getDefaultMailConf();

        $mailConf[ 'from' ]       = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'sender' ]     = INIT::$MAILER_RETURN_PATH;
        $mailConf[ 'returnPath' ] = INIT::$MAILER_RETURN_PATH;

        return $mailConf;
    }
}
