<?php
use PHPUnit\Framework\TestCase;

class RendererTest extends TestCase {
    protected function setUp(): void {
        gm2_test_reset_terms();
    }

    public function test_depth_classes_rendered() {
        $root = wp_insert_term( 'Root', 'product_cat' );
        $child = wp_insert_term( 'Child', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $renderer = new Gm2_Category_Sort_Renderer( [ 'filter_type' => 'simple', 'widget_id' => '1' ] );

        $ref = new ReflectionClass( $renderer );
        $method = $ref->getMethod( 'render_category_node' );
        $method->setAccessible( true );

        ob_start();
        $term = (object) [ 'term_id' => $root['term_id'], 'name' => 'Root', 'gm2_synonyms' => [] ];
        $method->invoke( $renderer, $term, 0 );
        $html = ob_get_clean();

        $this->assertStringContainsString( 'gm2-category-node depth-0', $html );
        $this->assertStringContainsString( 'gm2-category-name depth-0', $html );
        $this->assertStringContainsString( 'depth-1', $html );
    }
}
