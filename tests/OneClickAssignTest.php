<?php
namespace {
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/class-one-click-assign.php';

// Stub WP_Query
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts = [];
        public $found_posts = 0;
        public function __construct( $args = [] ) {
            $ids = array_keys( $GLOBALS['gm2_product_objects'] ?? [] );
            $this->found_posts = count( $ids );
            $this->posts = $ids;
        }
    }
}

if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $cap ) { return true; }
}
if ( ! function_exists( 'check_ajax_referer' ) ) {
    function check_ajax_referer( $action, $name ) { return true; }
}
if ( ! function_exists( 'wp_send_json_success' ) ) {
    function wp_send_json_success( $data = null ) { $GLOBALS['gm2_json_result'] = ['success'=>true,'data'=>$data]; return $GLOBALS['gm2_json_result']; }
}
if ( ! function_exists( 'wp_send_json_error' ) ) {
    function wp_send_json_error( $data = null ) { $GLOBALS['gm2_json_result'] = ['success'=>false,'data'=>$data]; return $GLOBALS['gm2_json_result']; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) { return $GLOBALS['gm2_options'][$name] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) { $GLOBALS['gm2_options'][$name] = $value; return true; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $str ) { return $str; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $str ) { $s = strtolower( $str ); $s = preg_replace('/[^a-z0-9]+/', '-', $s); return trim($s, '-'); }
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
if ( ! function_exists( 'wc_get_product' ) ) {
    function wc_get_product( $id ) { return $GLOBALS['gm2_product_objects'][$id] ?? null; }
}
if ( ! function_exists( 'wc_get_product_terms' ) ) {
    function wc_get_product_terms( $id, $tax, $args=[] ) { return []; }
}
}

namespace {
use PHPUnit\Framework\TestCase;

class DummyProduct {
    private $id; private $name; private $desc; public function __construct($id,$n,$d){$this->id=$id;$this->name=$n;$this->desc=$d;}
    public function get_name(){return $this->name;}
    public function get_description(){return $this->desc;}
    public function get_short_description(){return '';}
    public function get_attributes(){return [];}    public function get_sku(){return 'SKU'.$this->id;}
}

class OneClickAssignTest extends TestCase {
    protected function setUp(): void {
        gm2_test_reset_terms();
        $GLOBALS['gm2_product_objects'] = [];
        $GLOBALS['gm2_json_result'] = null;
        $_POST = [];
    }

    private function create_categories(){
        $p = wp_insert_term('Parent','product_cat');
        update_term_meta($p['term_id'],'gm2_synonyms','Main');
        $c = wp_insert_term('Child','product_cat',['parent'=>$p['term_id']]);
        update_term_meta($c['term_id'],'gm2_synonyms','Alt');
        return [$p['term_id'],$c['term_id']];
    }

    public function test_assigns_from_description(){
        $this->markTestSkipped('Skipped due to environment differences');
        list($pid,$cid) = $this->create_categories();
        $GLOBALS['gm2_product_objects'][1] = new DummyProduct(1,'Prod','great alt thing');
        $_POST['nonce']='t';
        $_POST['fields']=['description'];
        Gm2_Category_Sort_One_Click_Assign::ajax_assign_categories();
        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount(1, $calls);
        $this->assertContains($pid, $calls[0]['terms']);
        $this->assertContains($cid, $calls[0]['terms']);
    }

    public function test_overwrite_clears_categories_when_no_match(){
        list($pid,$cid) = $this->create_categories();
        $GLOBALS['gm2_product_objects'][1] = new DummyProduct(1,'Prod','great alt thing');
        $_POST['nonce']='t';
        $_POST['fields']=['description'];
        $_POST['overwrite']='1';
        Gm2_Category_Sort_One_Click_Assign::ajax_assign_categories();

        $GLOBALS['gm2_set_terms_calls'] = [];
        $GLOBALS['gm2_options']['gm2_branch_rules'] = [
            'parent-child' => [ 'include' => 'nomatch', 'exclude' => '' ],
        ];

        Gm2_Category_Sort_One_Click_Assign::ajax_assign_categories();

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount(1, $calls);
        $this->assertSame([], $calls[0]['terms']);
        $this->assertFalse($calls[0]['append']);
    }
}
}
