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
use SimpleJWT;

class SegmentDeliveryController extends KleinController {

    /** @var  Chunks_ChunkStruct */
    protected $chunk ;


    /**
     * dobbiamo fare in modo che se si apre un altro link di MateCat non ci siano problemi.
     *
     * - validare la chiamata con una secret key
     * - settare un cookie _aibnb_sd_[id_job]
     *
     * in questo modo sappiamo per quali job attivare la funzione.
     * mettere una scadenza molto rapida per il cookie in modo che non ci dobbiamo
     * preoccupare di pulire i cookie quando il numero di job cresce.
     *
     */
    public function startSession() {
        $expire   = strtotime('+1 hours');
        $redirect = $this->request->param('redirect_url') ;

        $jwt = new SimpleJWT(['id_job' => $this->chunk->id ] );
        $signed = $jwt->jsonSerialize();

        setcookie( 'airbnb_session_' . $this->request->param('id_job'), $signed, $expire, '/' ) ;

        if ( !$redirect ) {
            $this->response->code( 201 ) ;
        }
        else {
            $this->response->redirect( $redirect ) ;
        }
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

