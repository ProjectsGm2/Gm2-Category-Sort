<?php
namespace {
require_once __DIR__ . '/../includes/class-canonical.php';

if ( ! function_exists( 'get_query_var' ) ) {
    function get_query_var( $key ) {
        return $GLOBALS['gm2_query_vars'][ $key ] ?? '';
    }
}

if ( ! function_exists( 'get_term_link' ) ) {
    function get_term_link( $term ) {
        return 'http://example.com/' . $term->slug;
    }
}
}

namespace {
use PHPUnit\Framework\TestCase;

class CanonicalAltBaseSlugTest extends TestCase {
    protected function setUp(): void {
        gm2_test_reset_terms();
        $GLOBALS['gm2_query_vars'] = [];
    }

    public function test_outputs_canonical_link_when_alt_base_and_slug_present() {
        wp_insert_term( 'Sample Cat', 'product_cat' );
        $GLOBALS['gm2_query_vars']['gm2_alt_base'] = 'alt';
        $GLOBALS['gm2_query_vars']['product_cat'] = 'sample-cat';

        ob_start();
        Gm2_Category_Sort_Canonical::maybe_output_canonical();
        $html = ob_get_clean();

        $this->assertSame(
            '<link rel="canonical" href="http://example.com/sample-cat" />\n',
            $html
        );
    }
}
}
