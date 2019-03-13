<?php

namespace Features\Airbnb\Decorator;


use AbstractCatDecorator;
use Features\Airbnb;
use Features\Airbnb\Utils\Routes;

class CatDecorator extends AbstractCatDecorator {
    /**
     * @var \PHPTALWithAppend
     */
    protected $template;

    public function decorate() {
        // read cookies, find airbnb_session cookies
        // validate cookie content
        // in case validation is ok then add variables to the config,
        // variables should include  a URL template to allow browser to make calls
        $this->_checkSessionCookie();

        $this->template->append( 'footer_js', Routes::staticBuild( '/airbnb-core-build.js' ) );
        $this->template->append( 'css_resources', Routes::staticBuild( '/airbnb-build.css' ) );
    }

    protected function _checkSessionCookie() {
        $chunk = $this->controller->getChunk();

        if ( !isset($_COOKIE[ Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id ]) ) {
            return ;
        }

        $cookie = $_COOKIE[ Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id ] ;
        $payload = \SimpleJWT::getValidPayload( $cookie ) ;

        if ( $payload['id_job'] == $chunk->id ) {
            $this->template->append('config_js', [
                    'airbnb_delivery_button' => 1
            ] );
        }
    }
}