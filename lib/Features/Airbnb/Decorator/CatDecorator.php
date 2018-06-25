<?php

namespace Features\Microsoft\Decorator;


use AbstractDecorator;
use Features\Microsoft\Utils\Routes;

class CatDecorator extends AbstractDecorator {
    /**
     * @var \PHPTALWithAppend
     */
    protected $template;

    public function decorate() {
        $this->template->append( 'footer_js', Routes::staticBuild( '/microsoft-core-build.js' ) );
        $this->template->append( 'css_resources', Routes::staticBuild( '/microsoft-build.css' ) );

        $this->template->segment_filter_size  = '50' ;
        $this->template->segment_filter_type  = 'regular_intervals' ;
        $this->template->footer_show_translate_link = false;

    }

}