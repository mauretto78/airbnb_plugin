<?php

namespace Features\Airbnb\Decorator;


use AbstractDecorator;
use Features\Airbnb\Utils\Routes;

class CatDecorator extends AbstractDecorator {
    /**
     * @var \PHPTALWithAppend
     */
    protected $template;

    public function decorate() {
        $this->template->append( 'footer_js', Routes::staticBuild( '/airbnb-core-build.js' ) );
//        $this->template->append( 'css_resources', Routes::staticBuild( '/microsoft-build.css' ) );
    }

}