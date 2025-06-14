<?php
use PHPUnit\Framework\TestCase;

class ProductCategoryGeneratorTest extends TestCase {

    protected function setUp(): void {
        gm2_test_reset_terms();
    }

    private function create_categories() {
        // Parent category with synonym
        $parent = wp_insert_term( 'Parent', 'product_cat' );
        update_term_meta( $parent['term_id'], 'gm2_synonyms', 'Main' );

        // Child category with its own synonym
        $child = wp_insert_term( 'Child', 'product_cat', [ 'parent' => $parent['term_id'] ] );
        update_term_meta( $child['term_id'], 'gm2_synonyms', 'Alt' );
    }

    public function test_assigns_using_synonyms_and_hierarchy() {
        $this->create_categories();

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'This product matches alt keyword';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [ 'Parent', 'Child' ], $cats );
    }

    public function test_assigns_parent_synonym() {
        $this->create_categories();

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'A main category item';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [ 'Parent' ], $cats );
    }

    public function test_ignores_negative_phrases() {
        $this->create_categories();

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'This item is not for alt usage.';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [], $cats );
    }

    public function test_ignores_except_for_phrases() {
        $this->create_categories();

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'All models except for alt type';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [], $cats );
    }

    public function test_matches_morphological_variants() {
        // Single category with variant terms
        $wheel = wp_insert_term( 'Wheel', 'product_cat' );
        update_term_meta( $wheel['term_id'], 'gm2_synonyms', '10 lug 2 hole' );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'Fits 10 lugs 2 hh trucks';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [ 'Wheel' ], $cats );
    }

    public function test_replacement_variants_are_normalized() {
        $hub = wp_insert_term( 'Hubcap', 'product_cat' );
        update_term_meta( $hub['term_id'], 'gm2_synonyms', 'Wheel Cover' );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'Premium wheelcovers and hub caps included';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [ 'Hubcap' ], $cats );
    }

    public function test_ignores_additional_negation_patterns() {
        $this->create_categories();

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $phrases = [
            'This is not compatible with alt parts',
            'Item not recommended for alt equipment',
            'Package not intended for alt usage',
        ];

        foreach ( $phrases as $phrase ) {
            $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $phrase, $mapping );
            $this->assertSame( [], $cats, $phrase );
        }
    }

    public function test_over_the_lug_synonym() {
        $cat = wp_insert_term( 'Over-Lug', 'product_cat' );
        update_term_meta( $cat['term_id'], 'gm2_synonyms', 'Over Lug,Over the Lug' );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'Works with over the lug wheels';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [ 'Over-Lug' ], $cats );
    }
}
