<?php
namespace {
if ( ! function_exists( 'plugin_dir_path' ) ) {
    function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
    function plugin_dir_url( $file ) { return 'http://example.com/' . basename( dirname( $file ) ) . '/'; }
}
if ( ! function_exists( 'register_activation_hook' ) ) {
    function register_activation_hook( $file, $callback ) {}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
    function register_deactivation_hook( $file, $callback ) {}
}
if ( ! function_exists( 'add_action' ) ) {
    function add_action( $hook, $callback, $priority = 10, $args = 1 ) {}
}
if ( ! function_exists( 'add_filter' ) ) {
    function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {}
}
if ( ! function_exists( 'did_action' ) ) {
    function did_action( $hook ) { return false; }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
    function load_plugin_textdomain( $domain, $deprecated = false, $path = '' ) {}
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) { $GLOBALS['gm2_options'][ $name ] = $value; return true; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) { return $GLOBALS['gm2_options'][ $name ] ?? $default; }
}
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
    function flush_rewrite_rules() { $GLOBALS['gm2_flushed'] = true; }
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
    function wp_next_scheduled( $hook ) { return false; }
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
    function wp_schedule_event( $timestamp, $recurrence, $hook ) { $GLOBALS['gm2_scheduled'] = true; return true; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) {
    function add_rewrite_rule( $regex, $query, $position = 'bottom' ) {
        $GLOBALS['gm2_added_rules'][] = [ 'regex' => $regex, 'query' => $query, 'position' => $position ];
    }
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/' );
}
require_once __DIR__ . '/../gm2-category-sort.php';
}

namespace {
use PHPUnit\Framework\TestCase;

class ProductBaseActivateTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['gm2_options'] = [];
        $GLOBALS['gm2_flushed'] = false;
        $GLOBALS['gm2_scheduled'] = false;
        $GLOBALS['gm2_added_rules'] = [];
    }

    public function test_segment_recorded_on_activation() {
        $GLOBALS['gm2_options']['woocommerce_permalinks'] = [ 'product_base' => 'store/%product_cat%' ];
        gm2_category_sort_activate();

        $this->assertSame( 'store', $GLOBALS['gm2_options']['gm2_rewrite_prev_product_segment'] );
        $this->assertSame( [ 'store' ], $GLOBALS['gm2_options']['gm2_rewrite_bases'] );
        $this->assertTrue( $GLOBALS['gm2_flushed'] );
    }
}
}
