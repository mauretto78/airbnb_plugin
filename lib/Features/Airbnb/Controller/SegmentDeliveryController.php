<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/03/2019
 * Time: 13:03
 */

namespace Features\Airbnb\Controller;

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use API\V2\Exceptions\AuthenticationError;
use API\V2\Exceptions\ExternalServiceException;
use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Constants_JobStatus;
use Exceptions\ValidationError;
use Features\Airbnb;
use Routes;
use Segments_SegmentDao;
use Segments_SegmentNoteDao;
use Segments_SegmentNoteStruct;
use SimpleJWT;
use Translations_SegmentTranslationDao;
use Utils;

class SegmentDeliveryController extends KleinController {

    /** @var  Chunks_ChunkStruct */
    protected $chunk;

    public function startSession() {

        $jwt = new SimpleJWT( [
                'id_job' => $this->chunk->id,
                'ontool' => $this->request->param( 'ontool' )
        ] );

        $signed = $jwt->jsonSerialize();
        $expire = strtotime( '+60 minutes' );

        //by setting the cookie this endpoint is not stateless and MUST be used by clients
        setcookie( Airbnb::DELIVERY_COOKIE_PREFIX . $this->request->param( 'id_job' ), $signed, $expire, '/', \INIT::$COOKIE_DOMAIN );

        if ( $this->chunk->isArchiveable() || $this->chunk->status_owner == Constants_JobStatus::STATUS_ARCHIVED ) {
            updateJobsStatus( 'job', $this->chunk->id, Constants_JobStatus::STATUS_ACTIVE, $this->chunk->password );
            $this->_saveActivity( ActivityLogStruct::JOB_UNARCHIVED );
        }

        $project      = $this->chunk->getProject();
        $redirect_url = Routes::translate(
                $project->name, $this->chunk->id, $this->chunk->password, $this->chunk->source, $this->chunk->target
        );
        $this->response->header( 'Cache-Control', 'max-age=0' );
        $this->response->redirect( $redirect_url . '#' . $this->request->param( 'id_segment' ) );
    }

    /**
     * @return $this
     * @throws AuthenticationError
     * @throws ExternalServiceException
     */
    public function send() {
        $segment_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->request->param( 'id_segment' ), $this->chunk->id );
        // rebuild the trans-unit and post to the external API

        try {
            SimpleJWT::getValidPayload( $this->request->param( 'jwt' ) );
        } catch ( \Exception $e ) {
            throw new AuthenticationError( $e->getMessage(), $e->getCode() );
        }

        $segment = ( new Segments_SegmentDao() )->getById( $segment_translation->id_segment );

        $notes = [];
        foreach( Segments_SegmentNoteDao::getBySegmentId( $segment->id ) as $note ){
            if( strpos( $note->note, '|¶|' ) !== false ){
                $k_v = explode( '|¶|', $note->note );
                $notes[ $k_v[ 0 ] ] = $k_v[ 1 ];
            }
        }

        $this->_makeDelivery( $segment, $segment_translation, $notes );

        $this->response->json( [
                'translation' => $segment_translation->translation
        ] );

        return $this;
    }

    protected function _makeDelivery( $segment, $segment_translation, $notes ) {

        $config = Airbnb::getConfig();

        $mh                     = new \MultiCurlHandler();

        $portParams = array_merge( [  // Redundant, the URL is enough
                'trans_unit_id' => $segment->internal_id,
                'source'        => $segment->segment,
                'target'        => $segment_translation->translation,
                'source_lang'   => $this->chunk->source,
                'target_lang'   => $this->chunk->target,
                'id_segment'    => $segment->id,
                'user_email'    => $this->user->email
        ], $notes );


        $curl_additional_params = [
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => \INIT::MATECAT_USER_AGENT . \INIT::$BUILD_NUMBER,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                ],
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => json_encode( $portParams /* Redundant, the URL is enough, add to pass more info to the service */ )
        ];

        $resource = $mh->createResource( $config[ 'delivery_endpoint' ] . implode( "/", [ $this->chunk->id, $this->chunk->password, $segment->internal_id ] ), $curl_additional_params );
        $mh->multiExec();
        $mh->multiCurlCloseAll();

        $log = $mh->getSingleLog( $resource );
        $log[ 'options' ][ 'post_parameters' ] = $portParams; //decoded parameters for logging
        \Log::doJsonLog( $log );

        if ( $mh->hasError( $resource ) ) {
            throw new ExternalServiceException( $mh->getError( $resource )[ 'error' ] );
        }

        $this->_saveActivity( ActivityLogStruct::TRANSLATION_DELIVERED );

    }

    public function setChunk( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;

        return $this;
    }

    protected function afterConstruct() {
        $validator  = new ChunkPasswordValidator( $this );
        $controller = $this;

        $validator->onSuccess( function () use ( $validator, $controller ) {
            $controller->setChunk( $validator->getChunk() );
        } );

        $this->appendValidator( $validator );
    }

    protected function _saveActivity( $action ) {
        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->chunk->id;
        $activity->id_project = $this->chunk->id_project;
        $activity->action     = $action;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
    }
}

