<?php
namespace {
require_once __DIR__ . '/../includes/class-rewrite-rules.php';

if ( ! function_exists( 'check_admin_referer' ) ) {
    function check_admin_referer( $action, $name ) { return true; }
}
if ( ! function_exists( 'wp_unslash' ) ) {
    function wp_unslash( $value ) {
        if ( is_array( $value ) ) { return array_map( 'wp_unslash', $value ); }
        return stripslashes( $value );
    }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $str ) { return $str; }
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
class SaveRedirectException extends \Exception {}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
    function wp_safe_redirect( $location ) { $GLOBALS['gm2_safe_redirect'] = $location; throw new SaveRedirectException(); }
}
if ( ! function_exists( 'menu_page_url' ) ) {
    function menu_page_url( $slug, $echo = true ) { return '/admin.php?page=' . $slug; }
}
if ( ! function_exists( 'add_rewrite_rule' ) ) {
    function add_rewrite_rule( $regex, $query, $position = 'bottom' ) {
        $GLOBALS['gm2_added_rules'][] = [ 'regex' => $regex, 'query' => $query, 'position' => $position ];
    }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $cap ) { return true; }
}
}

namespace {
use PHPUnit\Framework\TestCase;

class RewriteRulesSaveTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['gm2_options'] = [];
        $GLOBALS['gm2_added_rules'] = [];
        $GLOBALS['gm2_safe_redirect'] = null;
        $GLOBALS['gm2_flushed'] = false;
        $_POST = [];
    }

    public function test_windows_line_breaks_create_rule() {
        $_POST['gm2_rewrites_nonce'] = 't';
        $_POST['gm2_rewrite_bases'] = "alt\r\n";

        try {
            Gm2_Category_Sort_Rewrite_Rules::save_rules();
            $this->fail('SaveRedirectException not thrown');
        } catch ( SaveRedirectException $e ) {
            // Expected.
        }

        $this->assertSame( [ 'alt' ], $GLOBALS['gm2_options']['gm2_rewrite_bases'] );

        $this->assertCount( 2, $GLOBALS['gm2_added_rules'] );
        $cat_rule   = $GLOBALS['gm2_added_rules'][0];
        $product_rule = $GLOBALS['gm2_added_rules'][1];

        $this->assertSame( '^alt/(.+?)/?$', $cat_rule['regex'] );
        $this->assertSame( 'index.php?product_cat=$matches[1]&gm2_alt_base=alt', $cat_rule['query'] );
        $this->assertSame( 'top', $cat_rule['position'] );

        $this->assertSame( '^alt/(.+?)/([^/]+)/?$', $product_rule['regex'] );
        $this->assertSame( 'index.php?product_cat=$matches[1]&product=$matches[2]&gm2_alt_base=alt', $product_rule['query'] );
        $this->assertSame( 'top', $product_rule['position'] );
    }

    public function test_multiple_windows_line_breaks_create_rules() {
        $_POST['gm2_rewrites_nonce'] = 't';
        $_POST['gm2_rewrite_bases'] = "alt\r\nshop\r\n";

        try {
            Gm2_Category_Sort_Rewrite_Rules::save_rules();
            $this->fail('SaveRedirectException not thrown');
        } catch ( SaveRedirectException $e ) {
            // Expected.
        }

        $this->assertSame( [ 'alt', 'shop' ], $GLOBALS['gm2_options']['gm2_rewrite_bases'] );

        $this->assertCount( 4, $GLOBALS['gm2_added_rules'] );

        $rule1 = $GLOBALS['gm2_added_rules'][0];
        $this->assertSame( '^alt/(.+?)/?$', $rule1['regex'] );
        $this->assertSame( 'index.php?product_cat=$matches[1]&gm2_alt_base=alt', $rule1['query'] );

        $rule2 = $GLOBALS['gm2_added_rules'][1];
        $this->assertSame( '^alt/(.+?)/([^/]+)/?$', $rule2['regex'] );
        $this->assertSame( 'index.php?product_cat=$matches[1]&product=$matches[2]&gm2_alt_base=alt', $rule2['query'] );

        $rule3 = $GLOBALS['gm2_added_rules'][2];
        $this->assertSame( '^shop/(.+?)/?$', $rule3['regex'] );
        $this->assertSame( 'index.php?product_cat=$matches[1]&gm2_alt_base=shop', $rule3['query'] );
        $this->assertSame( 'top', $rule3['position'] );

        $rule4 = $GLOBALS['gm2_added_rules'][3];
        $this->assertSame( '^shop/(.+?)/([^/]+)/?$', $rule4['regex'] );
        $this->assertSame( 'index.php?product_cat=$matches[1]&product=$matches[2]&gm2_alt_base=shop', $rule4['query'] );
        $this->assertSame( 'top', $rule4['position'] );
    }
}
}
