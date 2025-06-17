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

        $this->assertSame(
            [ 'Hubcap', 'Wheel Simulators', 'Brands', 'Eagle Flight Wheel Simulators' ],
            $cats
        );
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

    public function test_fuzzy_matches_misspelling() {
        $wheel = wp_insert_term( 'Wheel', 'product_cat' );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'Heavy duty wheell cover';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping, true );

        $this->assertSame( [ 'Wheel' ], $cats );
    }

    public function test_only_one_lug_hole_category_matches() {
        $root = wp_insert_term( 'By Lug/Hole Configuration', 'product_cat' );
        wp_insert_term( '10 Lug', 'product_cat', [ 'parent' => $root['term_id'] ] );
        wp_insert_term( '10 Lug 2 Hole', 'product_cat', [ 'parent' => $root['term_id'] ] );
        wp_insert_term( '10 Lug 4 Hole', 'product_cat', [ 'parent' => $root['term_id'] ] );
        wp_insert_term( '10 Lug 5 Hole', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5" Dodge Ram 4500 5500 2008 Wheel Rim Liner Hubcap Covers 10 Lug 5 Hole';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame(
            [
                'By Lug/Hole Configuration',
                '10 Lug 5 Hole',
                'Wheel Simulators',
                'Brands',
                'Eagle Flight Wheel Simulators',
            ],
            $cats
        );
    }

    public function test_eagle_flight_brand_rule() {
        $wheel  = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $brands = wp_insert_term( 'Brands', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        wp_insert_term( 'Eagle Flight Wheel Simulators', 'product_cat', [ 'parent' => $brands['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'Premium rim liner kit for trucks';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [ 'Wheel Simulators', 'Brands', 'Eagle Flight Wheel Simulators' ], $cats );
    }

    public function test_brand_model_requires_brand_word() {
        $wheel  = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $branch = wp_insert_term( 'By Brand & Model', 'product_cat', [ 'parent' => $wheel['term_id'] ] );

        $dodge = wp_insert_term( 'Dodge', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( 'Ram 4500', 'product_cat', [ 'parent' => $dodge['term_id'] ] );
        wp_insert_term( 'Ram 5500', 'product_cat', [ 'parent' => $dodge['term_id'] ] );

        $gmc = wp_insert_term( 'GMC', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( '4500', 'product_cat', [ 'parent' => $gmc['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5" Dodge Ram 4500 5500 2008 Wheel Rim Liner Hubcap Covers';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame(
            [
                'Wheel Simulators',
                'By Brand & Model',
                'Dodge',
                'Ram 4500',
                'Ram 5500',
                'Brands',
                'Eagle Flight Wheel Simulators',
            ],
            $cats
        );
    }

    public function test_model_not_assigned_when_brand_far_apart() {
        $wheel  = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $branch = wp_insert_term( 'By Brand & Model', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        $dodge  = wp_insert_term( 'Dodge', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( 'Ram 4500', 'product_cat', [ 'parent' => $dodge['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'Dodge ... ' . str_repeat( 'x ', 30 ) . 'Ram 4500 truck';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame( [ 'Wheel Simulators', 'By Brand & Model', 'Dodge' ], $cats );
    }

    public function test_exports_brand_and_model_csv() {
        $wheel  = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $branch = wp_insert_term( 'By Brand & Model', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        $dodge  = wp_insert_term( 'Dodge', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( 'Ram 4500', 'product_cat', [ 'parent' => $dodge['term_id'] ] );
        $gmc = wp_insert_term( 'GMC', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( '4500', 'product_cat', [ 'parent' => $gmc['term_id'] ] );
        update_term_meta( $dodge['term_id'], 'gm2_synonyms', 'Dodge Truck' );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $dir = sys_get_temp_dir() . '/gm2_csv_test';
        if ( file_exists( $dir ) ) {
            foreach ( glob( $dir . '/*' ) as $file ) { unlink( $file ); }
            rmdir( $dir );
        }

        Gm2_Category_Sort_Product_Category_Generator::export_brand_model_csv( $mapping, $dir );

        $this->assertFileExists( $dir . '/brands.csv' );
        $this->assertFileExists( $dir . '/models.csv' );

        $brands = array_map( 'str_getcsv', file( $dir . '/brands.csv' ) );
        $header = array_shift( $brands );
        $this->assertSame( [ 'Brand', 'Terms' ], $header );
        $found = false;
        foreach ( $brands as $row ) {
            if ( $row[0] === 'Dodge' ) {
                $found = true;
                $this->assertStringContainsString( 'dodge truck', strtolower( $row[1] ) );
            }
        }
        $this->assertTrue( $found );

        $models = array_map( 'str_getcsv', file( $dir . '/models.csv' ) );
        $header = array_shift( $models );
        $this->assertSame( [ 'Brand', 'Model', 'Terms' ], $header );
        $found = false;
        foreach ( $models as $row ) {
            if ( $row[0] === 'Dodge' && $row[1] === 'Ram 4500' ) {
                $found = true;
                $this->assertStringContainsString( 'ram 4500', strtolower( $row[2] ) );
            }
        }
        $this->assertTrue( $found );
    }

    public function test_exports_category_tree_csv() {
        $parent = wp_insert_term( 'Top', 'product_cat' );
        update_term_meta( $parent['term_id'], 'gm2_synonyms', 'T' );
        $child = wp_insert_term( 'Sub', 'product_cat', [ 'parent' => $parent['term_id'] ] );
        update_term_meta( $child['term_id'], 'gm2_synonyms', 'S1,S2' );

        $dir = sys_get_temp_dir() . '/gm2_csv_tree';
        if ( file_exists( $dir ) ) {
            foreach ( glob( $dir . '/*' ) as $f ) { unlink( $f ); }
            rmdir( $dir );
        }

        Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv( $dir );

        $this->assertFileExists( $dir . '/category-tree.csv' );
        $rows = array_map( 'str_getcsv', file( $dir . '/category-tree.csv' ) );
        $this->assertContains( [ 'Top (T)', 'Sub (S1,S2)' ], $rows );
    }
}
