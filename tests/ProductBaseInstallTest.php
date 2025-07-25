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

class ProductBaseInstallTest extends TestCase {
    protected function setUp(): void {
        $GLOBALS['gm2_options'] = [];
        $GLOBALS['gm2_flushed'] = false;
    }

    public function test_segment_recorded_on_initial_install() {
        $GLOBALS['gm2_options']['woocommerce_permalinks'] = [ 'product_base' => 'store/%product_cat%' ];
        Gm2_Category_Sort_Rewrite_Rules::maybe_track_base_change();

        $this->assertSame( 'store', $GLOBALS['gm2_options']['gm2_rewrite_prev_product_segment'] );
        $this->assertSame( [ 'store' ], $GLOBALS['gm2_options']['gm2_rewrite_bases'] );
        $this->assertTrue( $GLOBALS['gm2_flushed'] );
    }
}
}
