<?php

use API\V2\Exceptions\AuthenticationError;
use API\V2\Validators\LoginValidator;

/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 25/06/19
 * Time: 10.57
 *
 */

class AirbnbTOSAuthLoginValidator extends LoginValidator {

    public function _validate() {

        $user = $this->controller->getUser();
        $this->controller->getRequest()->method();
        if( empty( $user )){
            if( $this->controller->getRequest()->method() === 'GET' ){
                $controllerInstance = new CustomPage();
                $controllerInstance->setTemplate( "401.html" );
                $controllerInstance->setCode( 401 );
                $controllerInstance->doAction();
                die(); // do not complete klein response, set 401 header
            } else {
                throw new AuthenticationError( "Invalid Login. You must be logged in MateCat in order to access the resource.", 401 );
            }
        }

    }

}