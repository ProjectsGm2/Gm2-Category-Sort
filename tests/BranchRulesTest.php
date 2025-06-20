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
        gm2_test_reset_terms();
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

    public function test_ajax_save_rules_stores_attr_arrays() {
        $_POST['nonce'] = 't';
        $_POST['rules'] = [
            'branch-leaf' => [
                'include'       => '',
                'exclude'       => '',
                'include_attrs' => [ 'pa_Color' => [ 'Red', 'Blue' ] ],
                'exclude_attrs' => [ 'pa_Size' => [ 'Large' ] ],
            ],
        ];

        Gm2_Category_Sort_Branch_Rules::ajax_save_rules();
        $saved = get_option( 'gm2_branch_rules' );

        $this->assertSame( [ 'pa_Color' => [ 'Red', 'Blue' ] ], $saved['branch-leaf']['include_attrs'] );
        $this->assertSame( [ 'pa_Size' => [ 'Large' ] ], $saved['branch-leaf']['exclude_attrs'] );
    }

    public function test_ajax_get_rules_returns_multiple_attributes() {
        $_POST['nonce'] = 't';
        $_POST['rules'] = [
            'branch-leaf' => [
                'include'       => 'foo',
                'exclude'       => 'bar',
                'include_attrs' => [
                    'pa_Color' => [ 'Red' ],
                    'pa_Size'  => [ 'Large', 'Small' ],
                ],
                'exclude_attrs' => [
                    'pa_Material' => [ 'Steel' ],
                    'pa_Type'     => [ 'OEM' ],
                ],
            ],
        ];

        $expected = $_POST['rules'];

        Gm2_Category_Sort_Branch_Rules::ajax_save_rules();

        $_POST = [ 'nonce' => 't' ];
        Gm2_Category_Sort_Branch_Rules::ajax_get_rules();
        $result = $GLOBALS['gm2_json_result'];

        $this->assertTrue( $result['success'] );
        $this->assertSame( $expected, $result['data'] );
    }

    public function test_stub_attribute_creation() {
        wc_create_attribute( [ 'slug' => 'color', 'name' => 'Color' ] );
        $tax = wc_attribute_taxonomy_name( 'color' );
        wp_insert_term( 'Red', $tax );

        $attrs = wc_get_attribute_taxonomies();
        $this->assertCount( 1, $attrs );
        $this->assertSame( 'color', $attrs[0]->attribute_name );

        $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
        $this->assertCount( 1, $terms );
    }
}
