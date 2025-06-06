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

if ( ! function_exists( 'gm2_get_orderby_args' ) ) {
    /**
     * Translate WooCommerce orderby values to WP_Query arguments.
     *
     * @param string $orderby Orderby string from the request, e.g. "price-desc".
     * @return array Arguments for WP_Query.
     */
    function gm2_get_orderby_args( $orderby ) {
        $orderby_value = $orderby;
        $order_dir     = '';

        if ( gm2_str_contains( $orderby, '-' ) ) {
            list( $orderby_value, $order_dir ) = array_pad( explode( '-', $orderby ), 2, '' );
        }

        $order_dir = strtoupper( $order_dir );
        $args      = [];

        switch ( $orderby_value ) {
            case 'price':
                $args['meta_key'] = '_price';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = $order_dir ? $order_dir : 'ASC';
                break;

            case 'popularity':
                $args['meta_key'] = 'total_sales';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;

            case 'rating':
                $args['meta_key'] = '_wc_average_rating';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;

            case 'date':
                $args['orderby'] = 'date';
                $args['order']   = $order_dir ? $order_dir : 'DESC';
                break;

            case 'rand':
                $args['orderby'] = 'rand';
                break;

            case 'id':
                $args['orderby'] = 'ID';
                $args['order']   = $order_dir ? $order_dir : 'ASC';
                break;

            case 'title':
                $args['orderby'] = 'title';
                $args['order']   = $order_dir ? $order_dir : 'ASC';
                break;

            default:
                $args['orderby'] = 'menu_order title';
                $args['order']   = $order_dir ? $order_dir : 'ASC';
                break;
        }

        return $args;
    }
}
