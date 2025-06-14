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

    public function test_expand_button_contains_icon_markup() {
        $root = wp_insert_term( 'Root', 'product_cat' );
        wp_insert_term( 'Child', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $renderer = new Gm2_Category_Sort_Renderer([
            'filter_type'   => 'simple',
            'widget_id'     => '1',
            'expand_icon'   => [ 'value' => 'fas fa-plus', 'library' => 'fa-solid' ],
            'collapse_icon' => [ 'value' => 'fas fa-minus', 'library' => 'fa-solid' ],
        ]);

        $ref = new ReflectionClass( $renderer );
        $method = $ref->getMethod( 'render_category_node' );
        $method->setAccessible( true );

        ob_start();
        $term = (object) [ 'term_id' => $root['term_id'], 'name' => 'Root', 'gm2_synonyms' => [] ];
        $method->invoke( $renderer, $term, 0 );
        $html = ob_get_clean();

        $this->assertMatchesRegularExpression( '/<(?:i|span)[^>]*fas fa-plus/', $html );
        $this->assertMatchesRegularExpression( '/<(?:i|span)[^>]*fas fa-minus/', $html );
    }

    public function test_expand_button_icon_toggle_logic() {
        $root = wp_insert_term( 'Root', 'product_cat' );
        wp_insert_term( 'Child', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $renderer = new Gm2_Category_Sort_Renderer([
            'filter_type'   => 'simple',
            'widget_id'     => '1',
            'expand_icon'   => [ 'value' => 'fas fa-plus', 'library' => 'fa-solid' ],
            'collapse_icon' => [ 'value' => 'fas fa-minus', 'library' => 'fa-solid' ],
        ]);

        $ref = new ReflectionClass( $renderer );
        $method = $ref->getMethod( 'render_category_node' );
        $method->setAccessible( true );

        ob_start();
        $term = (object) [ 'term_id' => $root['term_id'], 'name' => 'Root', 'gm2_synonyms' => [] ];
        $method->invoke( $renderer, $term, 0 );
        $html = ob_get_clean();

        $dom   = new DOMDocument();
        @$dom->loadHTML( '<div>' . $html . '</div>' );
        $xpath = new DOMXPath( $dom );

        $button       = $xpath->query( '//button[contains(@class,"gm2-expand-button")]' )->item( 0 );
        $expand_icon  = $xpath->query( './/*[contains(@class,"gm2-expand-icon")]', $button )->item( 0 );
        $collapse_icon = $xpath->query( './/*[contains(@class,"gm2-collapse-icon")]', $button )->item( 0 );

        $this->assertNotNull( $expand_icon, 'Expand icon missing' );
        $this->assertNotNull( $collapse_icon, 'Collapse icon missing' );

        // Initial state.
        $this->assertSame( 'false', $button->getAttribute( 'data-expanded' ) );
        $this->assertStringContainsString( 'display:none', $collapse_icon->getAttribute( 'style' ) );
        $this->assertStringNotContainsString( 'display:none', $expand_icon->getAttribute( 'style' ) );

        // Simulate expand click.
        $button->setAttribute( 'data-expanded', 'true' );
        $expand_icon->setAttribute( 'style', 'display:none;' );
        $collapse_icon->setAttribute( 'style', '' );

        $this->assertSame( 'true', $button->getAttribute( 'data-expanded' ) );
        $this->assertStringContainsString( 'display:none', $expand_icon->getAttribute( 'style' ) );
        $this->assertSame( '', $collapse_icon->getAttribute( 'style' ) );

        // Simulate collapse click.
        $button->setAttribute( 'data-expanded', 'false' );
        $expand_icon->setAttribute( 'style', '' );
        $collapse_icon->setAttribute( 'style', 'display:none;' );

        $this->assertSame( 'false', $button->getAttribute( 'data-expanded' ) );
        $this->assertStringContainsString( 'display:none', $collapse_icon->getAttribute( 'style' ) );
        $this->assertSame( '', $expand_icon->getAttribute( 'style' ) );
    }
}
