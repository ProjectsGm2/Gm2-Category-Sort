<?php
namespace {
require_once __DIR__ . '/../includes/class-rewrite-rules.php';

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) { $GLOBALS['gm2_options'][ $name ] = $value; return true; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) { return $GLOBALS['gm2_options'][ $name ] ?? $default; }
}
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
    function flush_rewrite_rules() { $GLOBALS['gm2_flushed'] = true; }
}
}

namespace {
use PHPUnit\Framework\TestCase;

class PermalinkChangeTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['gm2_options'] = [];
        $GLOBALS['gm2_flushed'] = false;
    }

    public function test_segment_added_on_permalink_change() {
        $GLOBALS['gm2_options']['gm2_rewrite_prev_product_segment'] = 'shop';
        $GLOBALS['gm2_options']['gm2_rewrite_bases'] = [];

        $new_value = [ 'product_base' => 'store/%product_cat%' ];
        Gm2_Category_Sort_Rewrite_Rules::permalinks_updated( [], $new_value );

        $this->assertSame( [ 'shop' ], $GLOBALS['gm2_options']['gm2_rewrite_bases'] );
        $this->assertTrue( $GLOBALS['gm2_flushed'] );
        $this->assertSame( 'store', $GLOBALS['gm2_options']['gm2_rewrite_prev_product_segment'] );
    }
}
}
