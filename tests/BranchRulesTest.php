<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/class-branch-rules.php';

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $cap ) { return true; }
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action, $name ) { return true; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        if ( is_array( $value ) ) { return array_map( 'wp_unslash', $value ); }
        return stripslashes( $value );
    }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $str ) { return $str; }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
    function wp_kses_post( $str ) { return $str; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $str ) { return $str; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) { $GLOBALS['gm2_options'][$name] = $value; return true; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) { return $GLOBALS['gm2_options'][$name] ?? $default; }
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null ) { $GLOBALS['gm2_json_result'] = ['success'=>true,'data'=>$data]; return $GLOBALS['gm2_json_result']; }
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null ) { $GLOBALS['gm2_json_result'] = ['success'=>false,'data'=>$data]; return $GLOBALS['gm2_json_result']; }
}

class BranchRulesTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['gm2_options'] = [];
        $_POST = [];
        $GLOBALS['gm2_json_result'] = null;
    }

    public function test_ajax_save_rules_preserves_quotes() {
        $_POST['nonce'] = 't';
        $_POST['rules'] = [ 'branch-leaf' => [ 'include' => '19\\"', 'exclude' => "19\\'" ] ];

        Gm2_Category_Sort_Branch_Rules::ajax_save_rules();
        $result = $GLOBALS['gm2_json_result'];
        $this->assertTrue( $result['success'] );
        $saved = get_option( 'gm2_branch_rules' );
        $this->assertSame( '19"', $saved['branch-leaf']['include'] );
        $this->assertSame( "19'", $saved['branch-leaf']['exclude'] );
    }
}
