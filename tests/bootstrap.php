<?php
namespace {
require_once __DIR__ . '/../includes/class-category-importer.php';
require_once __DIR__ . '/../includes/class-product-category-importer.php';
require_once __DIR__ . '/../includes/class-product-category-generator.php';
require_once __DIR__ . '/../includes/class-renderer.php';

// Minimal WP_Error class for tests.
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $errors = [];
        public function __construct( $code = '', $message = '' ) {
            if ( $code ) {
                $this->errors[ $code ] = [ $message ];
            }
        }
        public function get_error_message() {
            $error = reset( $this->errors );
            return $error ? $error[0] : '';
        }
    }
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

function __( $text, $domain = null ) {
    return $text;
}

// Globals for stubbed WordPress functions.
$GLOBALS['gm2_test_terms'] = [];
$GLOBALS['gm2_next_id'] = 1;
$GLOBALS['gm2_insert_calls'] = [];
$GLOBALS['gm2_meta_updates'] = [];
$GLOBALS['gm2_products'] = [];
$GLOBALS['gm2_set_terms_calls'] = [];

function gm2_test_reset_terms() {
    $GLOBALS['gm2_test_terms'] = [];
    $GLOBALS['gm2_next_id'] = 1;
    $GLOBALS['gm2_insert_calls'] = [];
    $GLOBALS['gm2_meta_updates'] = [];
    $GLOBALS['gm2_products'] = [];
    $GLOBALS['gm2_set_terms_calls'] = [];
}

gm2_test_reset_terms();

function term_exists( $name, $taxonomy = null, $parent = 0 ) {
    $terms = $GLOBALS['gm2_test_terms'];
    if ( isset( $terms[ $parent ][ $name ] ) ) {
        return [ 'term_id' => $terms[ $parent ][ $name ] ];
    }
    return false;
}

function wp_insert_term( $name, $taxonomy, $args = [] ) {
    $parent = $args['parent'] ?? 0;
    $id = $GLOBALS['gm2_next_id']++;
    if ( ! isset( $GLOBALS['gm2_test_terms'][ $parent ] ) ) {
        $GLOBALS['gm2_test_terms'][ $parent ] = [];
    }
    $GLOBALS['gm2_test_terms'][ $parent ][ $name ] = $id;
    $GLOBALS['gm2_insert_calls'][] = [ 'name' => $name, 'parent' => $parent, 'id' => $id ];
    return [ 'term_id' => $id ];
}

function update_term_meta( $term_id, $key, $value ) {
    $GLOBALS['gm2_meta_updates'][] = [
        'term_id' => $term_id,
        'key'     => $key,
        'value'   => $value,
    ];
}

function get_term_by( $field, $value, $taxonomy ) {
    if ( $field === 'name' && $taxonomy === 'product_cat' ) {
        foreach ( $GLOBALS['gm2_test_terms'] as $parent => $terms ) {
            foreach ( $terms as $name => $id ) {
                if ( $name === $value ) {
                    return (object) [ 'term_id' => $id ];
                }
            }
        }
    }
    return false;
}

function wc_get_product_id_by_sku( $sku ) {
    return $GLOBALS['gm2_products'][ $sku ] ?? 0;
}

function wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false ) {
    $GLOBALS['gm2_set_terms_calls'][] = [
        'object_id' => $object_id,
        'terms'     => $terms,
        'taxonomy'  => $taxonomy,
        'append'    => $append,
    ];
    return $terms;
}

if ( ! function_exists( 'delete_option' ) ) {
    function delete_option( $name ) {
        unset( $GLOBALS['gm2_options'][ $name ] );
        return true;
    }
}

if ( ! function_exists( 'wp_count_posts' ) ) {
    function wp_count_posts( $post_type ) {
        $count = isset( $GLOBALS['gm2_product_objects'] ) ? count( $GLOBALS['gm2_product_objects'] ) : 0;
        return (object) [ 'publish' => $count ];
    }
}

// Basic stubs for renderer tests and others.
if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $args ) {
        $parent  = isset( $args['parent'] ) ? (int) $args['parent'] : null;
        $include = isset( $args['include'] ) ? (array) $args['include'] : null;
        $terms   = [];
        foreach ( $GLOBALS['gm2_test_terms'] as $p => $cats ) {
            foreach ( $cats as $name => $id ) {
                if ( $parent !== null && (int) $p !== $parent ) {
                    continue;
                }
                if ( $include && ! in_array( $id, $include, true ) ) {
                    continue;
                }
                $terms[] = (object) [
                    'term_id' => $id,
                    'parent'  => $p,
                    'name'    => $name,
                ];
            }
        }
        return $terms;
    }
}

if ( ! function_exists( 'get_term_meta' ) ) {
    function get_term_meta( $term_id, $key, $single = true ) {
        foreach ( $GLOBALS['gm2_meta_updates'] as $meta ) {
            if ( $meta['term_id'] === $term_id && $meta['key'] === $key ) {
                return $meta['value'];
            }
        }
        return '';
    }
}

if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return $text; }
}

if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return $text; }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    function add_query_arg( $params ) {
        return '?' . http_build_query( $params );
    }
}

}

namespace Elementor {
    class Icons_Manager {
        public static function render_icon( $icon, $attrs = [] ) {
            $value = $icon['value'] ?? '';
            $attr_str = '';
            foreach ( $attrs as $k => $v ) {
                $attr_str .= ' ' . $k . '="' . $v . '"';
            }
            if ( isset( $icon['library'] ) && $icon['library'] === 'svg' ) {
                return '<svg' . $attr_str . '><path d="' . $value . '"></path></svg>';
            }
            return '<i class="' . $value . '"' . $attr_str . '></i>';
        }
        public static function enqueue_shim( $icon ) {}
    }
}

