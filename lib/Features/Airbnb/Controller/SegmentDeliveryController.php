<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/03/2019
 * Time: 13:03
 */

namespace Features\Airbnb\Controller;

use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use API\V2\Validators\JobPasswordValidator;
use Chunks_ChunkStruct;
use Exception;
use Features\TranslationVersions\Model\SegmentTranslationEventDao;
use INIT;
use Log;
use MultiCurlHandler;
use Routes;
use SimpleJWT;
use Translations_SegmentTranslationDao;

class SegmentDeliveryController extends KleinController {

    /** @var  Chunks_ChunkStruct */
    protected $chunk ;

    public function startSession() {

        $jwt = new SimpleJWT(['id_job' => $this->chunk->id ] );
        $signed = $jwt->jsonSerialize();
        $expire = strtotime('+5 minutes');

        setcookie( 'airbnb_session_' . $this->request->param('id_job'), $signed, $expire, '/' ) ;

        $project = $this->chunk->getProject();
        $redirect_url = Routes::translate(
                $project->name, $this->chunk->id, $this->chunk->password, $this->chunk->source, $this->chunk->target
        ) ;

       $this->response->redirect( $redirect_url . '#' . $this->request->param('id_segment') ) ;
    }

    protected function afterConstruct() {
        $validator = new ChunkPasswordValidator($this) ;
        $controller = $this ;

        $validator->onSuccess( function () use ( $validator, $controller ) {
            $controller->setChunk( $validator->getChunk() );
        } );

        $this->appendValidator( $validator );
    }

    public function setChunk( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk ;
        return $this;
    }
}

