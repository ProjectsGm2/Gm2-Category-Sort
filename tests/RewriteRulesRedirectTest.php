<?php
namespace {
require_once __DIR__ . '/../includes/class-rewrite-rules.php';

if ( ! defined( 'OBJECT' ) ) {
    define( 'OBJECT', 'OBJECT' );
}

if ( ! function_exists( 'get_query_var' ) ) {
    function get_query_var( $key ) {
        return $GLOBALS['gm2_query_vars'][ $key ] ?? '';
    }
}

class RedirectException extends \Exception {}

if ( ! function_exists( 'get_term_link' ) ) {
    function get_term_link( $term ) {
        return 'http://example.com/' . $term->slug;
    }
}

if ( ! function_exists( 'wp_redirect' ) ) {
    function wp_redirect( $location, $status = 302 ) {
        $GLOBALS['gm2_wp_redirect'] = [ 'location' => $location, 'status' => $status ];
        throw new RedirectException();
    }
}

if ( ! function_exists( 'get_page_by_path' ) ) {
    function get_page_by_path( $slug, $output = OBJECT, $post_type = 'page' ) {
        return $GLOBALS['gm2_products'][ $slug ] ?? null;
    }
}

if ( ! function_exists( 'get_permalink' ) ) {
    function get_permalink( $post ) {
        $slug = is_object( $post ) ? $post->post_name : $post;
        return 'http://example.com/product/' . $slug;
    }
}
}

namespace {
use PHPUnit\Framework\TestCase;

class RewriteRulesRedirectTest extends TestCase {
    protected function setUp(): void {
        gm2_test_reset_terms();
        $GLOBALS['gm2_query_vars'] = [];
        $GLOBALS['gm2_wp_redirect'] = null;
        $GLOBALS['gm2_products'] = [];
    }

    public function test_redirects_when_alt_base_present() {
        wp_insert_term( 'Valid Cat', 'product_cat' );
        $GLOBALS['gm2_query_vars']['gm2_alt_base'] = 'alt';
        $GLOBALS['gm2_query_vars']['product_cat'] = 'valid-cat';

        try {
            Gm2_Category_Sort_Rewrite_Rules::maybe_redirect();
            $this->fail( 'RedirectException not thrown' );
        } catch ( RedirectException $e ) {
            // Expected.
        }

        $this->assertSame( 'http://example.com/valid-cat', $GLOBALS['gm2_wp_redirect']['location'] );
        $this->assertSame( 301, $GLOBALS['gm2_wp_redirect']['status'] );
    }

    public function test_redirects_with_nested_path() {
        $parent = wp_insert_term( 'Parent', 'product_cat' );
        wp_insert_term( 'Child', 'product_cat', [ 'parent' => $parent['term_id'] ] );

        $GLOBALS['gm2_query_vars']['gm2_alt_base'] = 'shop';
        $GLOBALS['gm2_query_vars']['product_cat'] = 'parent/child';

        try {
            Gm2_Category_Sort_Rewrite_Rules::maybe_redirect();
            $this->fail( 'RedirectException not thrown' );
        } catch ( RedirectException $e ) {
            // Expected.
        }

        $this->assertSame( 'http://example.com/child', $GLOBALS['gm2_wp_redirect']['location'] );
        $this->assertSame( 301, $GLOBALS['gm2_wp_redirect']['status'] );
    }

    public function test_redirects_product_alt_base() {
        wp_insert_term( 'Cat', 'product_cat' );
        $GLOBALS['gm2_products']['sample-product'] = (object) [ 'post_name' => 'sample-product' ];

        $GLOBALS['gm2_query_vars']['gm2_alt_base'] = 'alt';
        $GLOBALS['gm2_query_vars']['product_cat']  = 'cat';
        $GLOBALS['gm2_query_vars']['product']      = 'sample-product';

        try {
            Gm2_Category_Sort_Rewrite_Rules::maybe_redirect();
            $this->fail( 'RedirectException not thrown' );
        } catch ( RedirectException $e ) {
            // Expected.
        }

        $this->assertSame( 'http://example.com/product/sample-product', $GLOBALS['gm2_wp_redirect']['location'] );
        $this->assertSame( 301, $GLOBALS['gm2_wp_redirect']['status'] );
    }
}
}
