<?php
use PHPUnit\Framework\TestCase;

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
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) { return $GLOBALS['gm2_options'][ $name ] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) { $GLOBALS['gm2_options'][ $name ] = $value; return true; }
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/' );
}

require_once __DIR__ . '/../gm2-category-sort.php';

class InitMigrationTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['gm2_options'] = [];
    }

    public function test_migrates_string_allow_multi() {
        $GLOBALS['gm2_options']['gm2_branch_rules'] = [
            'branch' => [ 'allow_multi' => 'true' ],
            'other'  => [ 'allow_multi' => '0' ],
        ];

        gm2_category_sort_init();

        $rules = $GLOBALS['gm2_options']['gm2_branch_rules'];
        $this->assertTrue( $rules['branch']['allow_multi'] );
        $this->assertFalse( $rules['other']['allow_multi'] );
    }
}
