<?php

namespace Features\Airbnb\Utils\SubFiltering\Filters;

use SubFiltering\Commons\AbstractHandler;

class NewLine extends AbstractHandler {

    /**
     * @inheritDoc
     */
    public function transform( $segment ) {
        /*
         * Examples:
         * - [AIRBNB] This is a string.\n\n With some newlines.\n They must be converted into the corresponding tag
         */
        $base64 = "XG4=";
        $base64Placeholder = "__XG4=__";

        $segment = str_replace("\\n", $base64Placeholder, $segment);

        preg_match_all( '/('.$base64Placeholder.')/', $segment, $html, PREG_SET_ORDER );
        foreach ( $html as $pos => $variable ) {
            //replace subsequent elements excluding already encoded
            $segment = preg_replace(
                    '/' . preg_quote( $variable[0], '/' ) . '/',
                    '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:'.$base64.'"/>',
                    $segment,
                    1
            );
        }
        
        return $segment;
    }
}