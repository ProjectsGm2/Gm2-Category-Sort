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
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $str ) { return $str; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $str ) { return $str; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        if ( is_array( $value ) ) { return array_map( 'wp_unslash', $value ); }
        return stripslashes( $value );
    }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $str ) { return $str; }
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
        $_POST = [];
    }

    private function create_categories() {
        $parent = wp_insert_term( 'Parent', 'product_cat' );
        update_term_meta( $parent['term_id'], 'gm2_synonyms', 'Main' );
        $child  = wp_insert_term( 'Child', 'product_cat', [ 'parent' => $parent['term_id'] ] );
        update_term_meta( $child['term_id'], 'gm2_synonyms', 'Alt' );
        return [ $parent['term_id'], $child['term_id'] ];
    }

    private function create_products() {
        $p1 = new TestProduct( 1, 'Prod One Alt', 'Contains alt keyword', 'SKU1' );
        $p2 = new TestProduct( 2, 'Prod Two Main', 'Some main text', 'SKU2' );
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
        $this->assertTrue( $calls[0]['append'] );
        $this->assertTrue( $calls[1]['append'] );

        $result = $GLOBALS['gm2_json_result'];
        $this->assertTrue( $result['success'] );
        $this->assertSame( [ 'Parent', 'Child' ], $result['data']['items'][0]['cats'] );
        $this->assertSame( [ 'Parent' ], $result['data']['items'][1]['cats'] );
    }

    public function test_ajax_handler_overwrites_categories() {
        list( $parent_id, $child_id ) = $this->create_categories();
        $this->create_products();

        $_POST['nonce'] = 't';
        $_POST['reset'] = '1';
        $_POST['overwrite'] = '1';

        Gm2_Category_Sort_Auto_Assign::ajax_step();

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 2, $calls );
        $this->assertFalse( $calls[0]['append'] );
        $this->assertFalse( $calls[1]['append'] );
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
        $this->assertTrue( $calls[0]['append'] );
        $this->assertTrue( $calls[1]['append'] );

        $this->assertContains( 'Auto assign complete.', \WP_CLI::$success_messages );
    }

    public function test_cli_handles_negatives_and_variants() {
        list( $parent_id, $child_id ) = $this->create_categories();
        $wheel = wp_insert_term( 'Wheel', 'product_cat' );
        update_term_meta( $wheel['term_id'], 'gm2_synonyms', '10 lug 2 hole' );

        $GLOBALS['gm2_product_objects'][1] = new TestProduct( 1, 'Prod A Alt', 'Contains alt keyword', 'S1' );
        $GLOBALS['gm2_product_objects'][2] = new TestProduct( 2, 'Prod B not for alt items', 'Not for alt items', 'S2' );
        $GLOBALS['gm2_product_objects'][3] = new TestProduct( 3, 'Prod C 10 lugs 2 hh kit', '10 lugs 2 hh kit', 'S3' );

        Gm2_Category_Sort_Auto_Assign::cli_run( [], [] );

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 2, $calls );
        $this->assertContains( $child_id, $calls[0]['terms'] );
        $this->assertContains( $parent_id, $calls[0]['terms'] );
        $this->assertSame( [ $wheel['term_id'] ], $calls[1]['terms'] );
    }

    public function test_cli_overwrites_categories() {
        list( $parent_id, $child_id ) = $this->create_categories();
        $this->create_products();

        Gm2_Category_Sort_Auto_Assign::cli_run( [], [ 'overwrite' => 1 ] );

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 2, $calls );
        $this->assertFalse( $calls[0]['append'] );
        $this->assertFalse( $calls[1]['append'] );
    }

    public function test_cli_recognizes_over_the_lug_synonym() {
        $term = wp_insert_term( 'Over-Lug', 'product_cat' );
        update_term_meta( $term['term_id'], 'gm2_synonyms', 'Over Lug,Over the Lug' );

        $GLOBALS['gm2_product_objects'][1] = new TestProduct( 1, 'Prod X works over the lug', 'Works over the lug', 'S3' );

        Gm2_Category_Sort_Auto_Assign::cli_run( [], [] );

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 1, $calls );
        $this->assertSame( [ $term['term_id'] ], $calls[0]['terms'] );
    }

    public function test_cli_fuzzy_matching() {
        $wheel = wp_insert_term( 'Wheel', 'product_cat' );

        $GLOBALS['gm2_product_objects'][1] = new TestProduct( 1, 'Prod F Stylish wheell kit', 'Stylish wheell kit', 'S1' );

        Gm2_Category_Sort_Auto_Assign::cli_run( [], [ 'fuzzy' => 1 ] );

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 1, $calls );
        $this->assertSame( [ $wheel['term_id'] ], $calls[0]['terms'] );
    }

    public function test_ajax_search_products() {
        $this->create_products();

        $_POST['nonce'] = 't';
        $_POST['fields'] = [ 'title' ];
        $_POST['search'] = 'Prod One';

        Gm2_Category_Sort_Auto_Assign::ajax_search_products();

        $result = $GLOBALS['gm2_json_result'];
        $this->assertTrue( $result['success'] );
        $this->assertCount( 1, $result['data']['items'] );
        $this->assertSame( 'SKU1', $result['data']['items'][0]['sku'] );
    }

    public function test_ajax_assign_selected() {
        $term = wp_insert_term( 'NewCat', 'product_cat' );
        $this->create_products();

        $_POST['nonce'] = 't';
        $_POST['products'] = [1];
        $_POST['categories'] = [ $term['term_id'] ];

        Gm2_Category_Sort_Auto_Assign::ajax_assign_selected();

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount( 1, $calls );
        $this->assertSame( [ $term['term_id'] ], $calls[0]['terms'] );
        $this->assertTrue( $calls[0]['append'] );
    }

    public function test_reset_clears_progress_option() {
        $GLOBALS['gm2_options']['gm2_auto_assign_progress'] = [ 'offset' => 1 ];
        $GLOBALS['gm2_options']['gm2_auto_assign_log'] = [ 'a' ];

        $_POST['nonce'] = 't';
        $_POST['reset'] = '1';

        Gm2_Category_Sort_Auto_Assign::ajax_reset_product_categories();

        $this->assertTrue( $GLOBALS['gm2_json_result']['success'] );
        $this->assertArrayNotHasKey( 'gm2_auto_assign_progress', $GLOBALS['gm2_options'] );
        $this->assertArrayNotHasKey( 'gm2_auto_assign_log', $GLOBALS['gm2_options'] );
    }

    public function test_log_persists_after_run() {
        list( $parent_id, $child_id ) = $this->create_categories();
        $this->create_products();

        $_POST['nonce'] = 't';
        $_POST['reset'] = '1';

        Gm2_Category_Sort_Auto_Assign::ajax_step();

        $this->assertArrayNotHasKey( 'gm2_auto_assign_progress', $GLOBALS['gm2_options'] );
        $log = $GLOBALS['gm2_options']['gm2_auto_assign_log'] ?? [];
        $this->assertCount( 2, $log );
        $this->assertStringContainsString( 'SKU1', $log[0] );
        $this->assertStringContainsString( 'SKU2', $log[1] );
    }
}
}
