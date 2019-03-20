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
use Routes;
use SimpleJWT;
use Translations_SegmentTranslationDao;
use Utils;

class SegmentDeliveryController extends KleinController {

    /** @var  Chunks_ChunkStruct */
    protected $chunk ;

    public function startSession() {

        $jwt = new SimpleJWT(['id_job' => $this->chunk->id ] );
        $signed = $jwt->jsonSerialize();
        $expire = strtotime('+5 minutes');

        setcookie( 'airbnb_session_' . $this->request->param('id_job'), $signed, $expire, '/' ) ;

        $this->_saveActivity();

        updateJobsStatus( 'job', $this->chunk->id, Constants_JobStatus::STATUS_ACTIVE, $this->chunk->password );

        $project = $this->chunk->getProject();
        $redirect_url = Routes::translate(
                $project->name, $this->chunk->id, $this->chunk->password, $this->chunk->source, $this->chunk->target
        ) ;
        $this->response->header('Cache-Control', 'max-age=0');
        $this->response->redirect( $redirect_url . '#' . $this->request->param('id_segment') ) ;
    }

    public function send(  ) {
        $segment = Translations_SegmentTranslationDao::findBySegmentAndJob($this->request->param('id_segment'), $this->chunk->id);
        $this->response->json([
            'translation' => $segment->translation
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

