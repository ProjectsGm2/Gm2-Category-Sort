<?php
namespace {
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/class-ajax.php';
require_once __DIR__ . '/../includes/utilities.php';

if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = [];
        public $found_posts = 0;
        public $max_num_pages = 1;
        private $index = 0;
        public function __construct( $args = [] ) {
            $ids = array_keys( $GLOBALS['gm2_product_objects'] ?? [] );
            $this->found_posts = count( $ids );
            $offset = $args['offset'] ?? 0;
            $limit  = $args['posts_per_page'] ?? count( $ids );
            $slice  = ($limit === -1 || $limit === 0) ? array_slice( $ids, $offset ) : array_slice( $ids, $offset, $limit );
            $this->posts = $slice;
            $this->max_num_pages = $limit > 0 ? (int) ceil( $this->found_posts / $limit ) : 1;
        }
        public function have_posts() { return $this->index < count( $this->posts ); }
        public function the_post() { $this->index++; }
    }
}

if (!function_exists('wc_setup_loop')) {
    function wc_setup_loop($args) { $GLOBALS['gm2_wc_loop'] = $args; }
}
if (!function_exists('wc_set_loop_prop')) {
    function wc_set_loop_prop($prop, $value) { $GLOBALS['gm2_wc_loop'][$prop] = $value; }
}
if (!function_exists('wc_get_loop_prop')) {
    function wc_get_loop_prop($prop) { return $GLOBALS['gm2_wc_loop'][$prop] ?? ''; }
}
if (!function_exists('wc_reset_loop')) { function wc_reset_loop() { $GLOBALS['gm2_wc_loop'] = []; } }
if (!function_exists('wc_clean')) { function wc_clean($v) { return $v; } }
if (!function_exists('absint')) { function absint($v) { return abs(intval($v)); } }
if (!function_exists('wc_get_template_part')) { function wc_get_template_part($slug, $name='') { echo '<li class="product">item</li>'; } }
if (!function_exists('woocommerce_product_loop_start')) { function woocommerce_product_loop_start() { echo '<ul class="products">'; } }
if (!function_exists('woocommerce_product_loop_end')) { function woocommerce_product_loop_end() { echo '</ul>'; } }
if (!function_exists('woocommerce_no_products_found')) { function woocommerce_no_products_found() { echo '<div class="woocommerce-info">No products</div>'; } }
if (!function_exists('woocommerce_result_count')) { function woocommerce_result_count() { echo '<span class="count">0</span>'; } }
if (!function_exists('woocommerce_pagination')) { function woocommerce_pagination() { echo '<nav class="woocommerce-pagination"></nav>'; } }
if (!function_exists('wp_reset_postdata')) { function wp_reset_postdata() {} }
if (!function_exists('current_user_can')) { function current_user_can($cap) { return true; } }
if (!function_exists('check_ajax_referer')) { function check_ajax_referer($a,$b) {} }
if (!function_exists('sanitize_key')) { function sanitize_key($str) { return $str; } }
if (!function_exists('sanitize_title')) { function sanitize_title($str){ $s=strtolower($str); $s=preg_replace('/[^a-z0-9]+/','-', $s); return trim($s, '-'); } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success($data=null){ $GLOBALS['gm2_json_result']=['success'=>true,'data'=>$data]; return $GLOBALS['gm2_json_result']; } }
if (!function_exists('wp_send_json_error')) { function wp_send_json_error($data=null){ $GLOBALS['gm2_json_result']=['success'=>false,'data'=>$data]; return $GLOBALS['gm2_json_result']; } }
}

namespace {
use PHPUnit\Framework\TestCase;

class AjaxFilterTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['gm2_product_objects'] = [];
        for ($i=1;$i<=10;$i++){ $GLOBALS['gm2_product_objects'][$i] = new \stdClass(); }
        $GLOBALS['gm2_json_result'] = null;
        $GLOBALS['gm2_wc_loop'] = [];
        $GLOBALS['wp_query'] = null;
        $_POST = [];
    }

    public function test_filter_preserves_rows_columns_count() {
        $_POST = [
            'gm2_cat' => '',
            'gm2_filter_type' => 'simple',
            'gm2_simple_operator' => 'IN',
            'gm2_paged' => '1',
            'gm2_per_page' => 6,
            'gm2_columns' => 3,
            'orderby' => '',
            'gm2_nonce' => 't'
        ];

        Gm2_Category_Sort_Ajax::filter_products();
        $this->assertNotNull($GLOBALS['gm2_json_result']);
        $html = $GLOBALS['gm2_json_result']['data']['html'];
        preg_match_all('/<li class="product">item<\/li>/', $html, $matches);
        $this->assertCount(6, $matches[0]);
    }

    public function test_uses_rows_and_columns_when_per_page_missing() {
        wc_setup_loop(['per_page' => 3]);

        $_POST = [
            'gm2_cat' => '',
            'gm2_filter_type' => 'simple',
            'gm2_simple_operator' => 'IN',
            'gm2_paged' => '1',
            'gm2_per_page' => 0,
            'gm2_columns' => 2,
            'gm2_rows' => 2,
            'orderby' => '',
            'gm2_nonce' => 't'
        ];

        Gm2_Category_Sort_Ajax::filter_products();
        $this->assertNotNull($GLOBALS['gm2_json_result']);
        $html = $GLOBALS['gm2_json_result']['data']['html'];
        preg_match_all('/<li class="product">item<\/li>/', $html, $matches);
        $this->assertCount(4, $matches[0]);
    }

    public function test_falls_back_to_loop_per_page_when_rows_missing() {
        wc_setup_loop(['per_page' => 3]);

        $_POST = [
            'gm2_cat' => '',
            'gm2_filter_type' => 'simple',
            'gm2_simple_operator' => 'IN',
            'gm2_paged' => '1',
            'gm2_per_page' => 0,
            'gm2_columns' => 2,
            'orderby' => '',
            'gm2_nonce' => 't'
        ];

        Gm2_Category_Sort_Ajax::filter_products();
        $this->assertNotNull($GLOBALS['gm2_json_result']);
        $html = $GLOBALS['gm2_json_result']['data']['html'];
        preg_match_all('/<li class="product">item<\/li>/', $html, $matches);
        $this->assertCount(3, $matches[0]);
    }
}
}
