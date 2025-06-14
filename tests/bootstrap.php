<?php
require_once __DIR__ . '/../includes/class-category-importer.php';
require_once __DIR__ . '/../includes/class-product-category-importer.php';
require_once __DIR__ . '/../includes/class-product-category-generator.php';

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
