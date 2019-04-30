<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/11/18
 * Time: 12.18
 *
 */

namespace Features\Airbnb\Utils\SubFiltering\Filters;


use SubFiltering\Commons\AbstractHandler;

class Variables extends AbstractHandler {

    /**
     * @param $segment
     *
     * @return string
     */
    public function transform( $segment ) {
        /*
         * Examples:
         * - %{# }
         * - %{\n}$spaces=2%{\n}
         * - %{{(text-align=center)}}
         * - %{vars}
         * - [AIRBNB] Reminder: Reply to %{guest}’s inquiry. |||| [AIRBNB] Reminder: Reply to %{guest}’s inquiry.
         * - Airbnb account.%{\n}%{<ph id="__mtc_0" equiv-text="base64:Jmx0O2JyJmd0Ow=="/>}%{\n}1) From    // Exclude re-filtering
         */
//        preg_match_all( '/%{{[^}]*?}}|(%{[^}]*?})[^\|]+?\1|(%{[^}]*?})/', $segment, $html, PREG_SET_ORDER );
        preg_match_all( '/%{{(?!<ph )[^}]*?}}|(%{(?!<ph )[^}]*?})/', $segment, $html, PREG_SET_ORDER );
        foreach ( $html as $pos => $variable ) {
            //replace subsequent elements excluding already encoded
            $segment = preg_replace(
                    '/' . preg_quote( $variable[0], '/' ) . '/',
                    '<ph id="__mtc_' . $this->getPipeline()->getNextId() . '" equiv-text="base64:' . base64_encode( $variable[ 0 ] ) . "\"/>",
                    $segment,
                    1
            );
        }
        return $segment;
    }

}