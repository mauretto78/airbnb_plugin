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
use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Constants_JobStatus;
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
    protected $chunk ;

    public function startSession() {

        $jwt = new SimpleJWT( [
                'id_job' => $this->chunk->id,
                'ontool' => $this->request->param('ontool')
        ] );

        $signed = $jwt->jsonSerialize();
        $expire = strtotime('+5 minutes');
        setcookie( Airbnb::DELIVERY_COOKIE_PREFIX . $this->request->param('id_job'), $signed, $expire, '/' ) ;

        if ( $this->chunk->status_owner == Constants_JobStatus::STATUS_ARCHIVED ) {
            updateJobsStatus( 'job', $this->chunk->id, Constants_JobStatus::STATUS_ACTIVE, $this->chunk->password );
            $this->_saveActivity();
        }


        $project = $this->chunk->getProject();
        $redirect_url = Routes::translate(
                $project->name, $this->chunk->id, $this->chunk->password, $this->chunk->source, $this->chunk->target
        ) ;
        $this->response->header('Cache-Control', 'max-age=0');
        $this->response->redirect( $redirect_url . '#' . $this->request->param('id_segment') ) ;
    }

    public function send() {
        $segment_translation = Translations_SegmentTranslationDao::findBySegmentAndJob($this->request->param('id_segment'), $this->chunk->id);
        // rebuild the trans-unit and post to the external API

        $segment = ( new Segments_SegmentDao() )->getById( $segment_translation->id_segment ) ;
        $notes   = array_map( function ( Segments_SegmentNoteStruct $note ) {
            return $note->note ;
        }, Segments_SegmentNoteDao::getBySegmentId( $segment->id ) );

        $postableData = [
                'trans-unit-id' => $segment->internal_id,
                'source'        => $segment->segment ,
                'target'        => $segment_translation->translation,
                'notes'         => $notes,
                'source_lang'   => $this->chunk->source,
                'target_lang'   => $this->chunk->target
                ] ;

        $this->response->json([
            'translation' => $segment_translation->translation
        ]);

        return $this;
    }

    public function setChunk( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;
        return $this;
    }

    protected function afterConstruct() {
        $validator = new ChunkPasswordValidator($this) ;
        $controller = $this ;

        $validator->onSuccess( function () use ( $validator, $controller ) {
            $controller->setChunk( $validator->getChunk() );
        } );

        $this->appendValidator( $validator );
    }

    protected function _saveActivity() {
        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->chunk->id;
        $activity->id_project = $this->chunk->id_project;
        $activity->action     = ActivityLogStruct::JOB_UNARCHIVED;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
    }
}

