<?php
namespace {
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/class-auto-assign.php';

// WordPress stub functions and classes for auto-assign tests.
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = [];
        public $found_posts = 0;
        public function __construct( $args = [] ) {
            $ids = array_keys( $GLOBALS['gm2_product_objects'] ?? [] );
            $this->found_posts = count( $ids );
            $offset = $args['offset'] ?? 0;
            $limit  = $args['posts_per_page'] ?? count( $ids );
            $slice  = ($limit === -1 || $limit === 0) ? array_slice( $ids, $offset ) : array_slice( $ids, $offset, $limit );
            $this->posts = $slice;
        }
    }
}

if ( ! function_exists( 'get_terms' ) ) {
    function get_terms( $args ) {
        $terms = [];
        foreach ( $GLOBALS['gm2_test_terms'] as $parent => $cats ) {
            foreach ( $cats as $name => $id ) {
                $terms[] = (object) [ 'term_id' => $id, 'parent' => $parent, 'name' => $name ];
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

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $cap ) { return true; }
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action, $name ) { return true; }
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null ) {
        $GLOBALS['gm2_json_result'] = [ 'success' => true, 'data' => $data ];
        return $GLOBALS['gm2_json_result'];
    }
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null ) {
        $GLOBALS['gm2_json_result'] = [ 'success' => false, 'data' => $data ];
        return $GLOBALS['gm2_json_result'];
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) {
        return $GLOBALS['gm2_options'][ $name ] ?? $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) {
        $GLOBALS['gm2_options'][ $name ] = $value;
        return true;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return $str; }
}

if ( ! function_exists( 'wc_get_product' ) ) {
    function wc_get_product( $id ) {
        return $GLOBALS['gm2_product_objects'][ $id ] ?? null;
    }
}

if ( ! function_exists( 'wc_get_product_terms' ) ) {
    function wc_get_product_terms( $product_id, $taxonomy, $args = [] ) { return []; }
}

if ( ! class_exists( 'WP_CLI' ) ) {
    class WP_CLI {
        public static $success_messages = [];
        public static function success( $msg ) { self::$success_messages[] = $msg; }
        public static function add_command( $name, $callable ) {}
    }
}
}

namespace WP_CLI {
if ( ! function_exists( '\\WP_CLI\\Utils\\make_progress_bar' ) ) {
    function make_progress_bar( $msg, $count ) {
        return new ProgressBarStub();
    }
    class ProgressBarStub {
        public function tick() {}
        public function finish() {}
    }
}
}

namespace {
use PHPUnit\Framework\TestCase;

class TestProduct {
    private $id; private $name; private $description; private $short = ''; private $sku;
    public function __construct( $id, $name, $description, $sku ) {
        $this->id = $id; $this->name = $name; $this->description = $description; $this->sku = $sku;
    }
    public function get_name() { return $this->name; }
    public function get_description() { return $this->description; }
    public function get_short_description() { return $this->short; }
    public function get_attributes() { return []; }
    public function get_sku() { return $this->sku; }
}

class AutoAssignTest extends TestCase {
    protected function setUp(): void {
        gm2_test_reset_terms();
        $GLOBALS['gm2_options'] = [];
        $GLOBALS['gm2_product_objects'] = [];
        $GLOBALS['gm2_json_result'] = null;
        \WP_CLI::$success_messages = [];
    }

    private function create_categories() {
        $parent = wp_insert_term( 'Parent', 'product_cat' );
        update_term_meta( $parent['term_id'], 'gm2_synonyms', 'Main' );
        $child  = wp_insert_term( 'Child', 'product_cat', [ 'parent' => $parent['term_id'] ] );
        update_term_meta( $child['term_id'], 'gm2_synonyms', 'Alt' );
        return [ $parent['term_id'], $child['term_id'] ];
    }

    private function create_products() {
        $p1 = new TestProduct( 1, 'Prod One', 'Contains alt keyword', 'SKU1' );
        $p2 = new TestProduct( 2, 'Prod Two', 'Some main text', 'SKU2' );
        $GLOBALS['gm2_product_objects'][1] = $p1;
        $GLOBALS['gm2_product_objects'][2] = $p2;
    }

    public function test_ajax_handler_assigns_categories() {
        list( $parent_id, $child_id ) = $this->create_categories();
        $this->create_products();

        $_POST['nonce'] = 't';
        $_POST['reset'] = '1';

        Gm2_Category_Sort_Auto_Assign::ajax_step();

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 2, $calls );
        $this->assertContains( $parent_id, $calls[0]['terms'] );
        $this->assertContains( $child_id, $calls[0]['terms'] );
        $this->assertSame( [ $parent_id ], $calls[1]['terms'] );

        $result = $GLOBALS['gm2_json_result'];
        $this->assertTrue( $result['success'] );
        $this->assertSame( [ 'Parent', 'Child' ], $result['data']['items'][0]['cats'] );
        $this->assertSame( [ 'Parent' ], $result['data']['items'][1]['cats'] );
    }

    public function test_cli_assigns_categories() {
        list( $parent_id, $child_id ) = $this->create_categories();
        $this->create_products();

        Gm2_Category_Sort_Auto_Assign::cli_run( [], [] );

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 2, $calls );
        $this->assertContains( $parent_id, $calls[0]['terms'] );
        $this->assertContains( $child_id, $calls[0]['terms'] );
        $this->assertSame( [ $parent_id ], $calls[1]['terms'] );

        $this->assertContains( 'Auto assign complete.', \WP_CLI::$success_messages );
    }
}
}
