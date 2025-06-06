<?php
/**
 * Utility functions for Gm2 Category Sort
 */

if ( ! function_exists( 'gm2_str_contains' ) ) {
    /**
     * Polyfill for PHP\u2019s str_contains.
     *
     * Uses native str_contains on PHP 8+, otherwise falls back to strpos.
     *
     * @param string $haystack Full string to search.
     * @param string $needle   Substring to look for.
     * @return bool True if $needle is found within $haystack.
     */
    function gm2_str_contains( $haystack, $needle ) {
        if ( function_exists( 'str_contains' ) ) {
            return str_contains( $haystack, $needle );
        }

        return $needle === '' || strpos( $haystack, $needle ) !== false;
    }
}
