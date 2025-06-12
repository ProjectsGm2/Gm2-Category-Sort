<?php
require_once __DIR__ . '/../includes/class-category-importer.php';

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

function gm2_test_reset_terms() {
    $GLOBALS['gm2_test_terms'] = [];
    $GLOBALS['gm2_next_id'] = 1;
    $GLOBALS['gm2_insert_calls'] = [];
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
