<?php
namespace {
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) { return $GLOBALS['gm2_options'][ $name ] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) { $GLOBALS['gm2_options'][ $name ] = $value; return true; }
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
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $str ) { return $str; }
}
if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( $str ) { $s = strtolower( $str ); $s = preg_replace( '/[^a-z0-9]+/', '-', $s ); return trim( $s, '-' ); }
}
}

namespace {
use PHPUnit\Framework\TestCase;

class ProductCategoryGeneratorTest extends TestCase {

    protected function setUp(): void {
        gm2_test_reset_terms();
        $GLOBALS['gm2_options'] = [];
        $upload = wp_upload_dir();
        $dir = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        if ( is_dir( $dir ) ) {
            foreach ( glob( "$dir/*" ) as $f ) { unlink( $f ); }
        }
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
                'Wheel Simulators',
                'Brands',
                'Eagle Flight Wheel Simulators',
                'By Wheel Size',
                '19.5"',
                'By Lug/Hole Configuration',
                '10 Lug 5 Hole',
            ],
            $cats
        );
    }

    public function test_wheel_size_prefix_category() {
        $root = wp_insert_term( 'By Wheel Size', 'product_cat' );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5" wheel simulator for trucks';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertContains( 'By Wheel Size', $cats );
        $this->assertContains( '19.5"', $cats );
    }

    public function test_wheel_size_prefix_single_quote() {
        $root = wp_insert_term( 'By Wheel Size', 'product_cat' );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = "19.5' rim liner kit";

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertContains( 'By Wheel Size', $cats );
        $this->assertContains( '19.5"', $cats );
    }

    public function test_wheel_size_prefix_no_symbol() {
        $root = wp_insert_term( 'By Wheel Size', 'product_cat' );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5 wheel cover';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertContains( 'By Wheel Size', $cats );
        $this->assertContains( '19.5"', $cats );
    }

    public function test_wheel_size_prefix_various_sizes() {
        $root  = wp_insert_term( 'By Wheel Size', 'product_cat' );
        $sizes = [ '15', '16', '16.5', '17', '17.5', '22.5', '24.5' ];
        foreach ( $sizes as $s ) {
            wp_insert_term( $s . '"', 'product_cat', [ 'parent' => $root['term_id'] ] );
        }

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();

        foreach ( $sizes as $s ) {
            foreach ( [ $s . '" wheel cover', $s . "' wheel cover" ] as $text ) {
                $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );
                $this->assertContains( 'By Wheel Size', $cats );
                $this->assertContains( $s . '"', $cats );
            }
        }
    }

    public function test_wheel_size_category_curly_quote() {
        $root = wp_insert_term( 'By Wheel Size', 'product_cat' );
        wp_insert_term( "19.5\xE2\x80\xB3", 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5" rim liner kit';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertContains( 'By Wheel Size', $cats );
        $this->assertContains( "19.5\xE2\x80\xB3", $cats );
    }

    public function test_wheel_size_category_in_subtree() {
        $wheel  = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $brands = wp_insert_term( 'Brands', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        wp_insert_term( 'Eagle Flight Wheel Simulators', 'product_cat', [ 'parent' => $brands['term_id'] ] );

        $acc   = wp_insert_term( 'Accessories', 'product_cat' );
        $root  = wp_insert_term( 'By Wheel Size', 'product_cat', [ 'parent' => $acc['term_id'] ] );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5" wheel simulator cover';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame(
            [
                'Wheel Simulators',
                'Brands',
                'Eagle Flight Wheel Simulators',
                'Accessories',
                'By Wheel Size',
                '19.5"',
            ],
            $cats
        );
    }
  
    public function test_wheel_size_with_x_after_quote() {
        $root = wp_insert_term( 'By Wheel Size', 'product_cat' );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = 'Premium 19.5"x8 wheel simulator';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertContains( 'By Wheel Size', $cats );
        $this->assertContains( '19.5"', $cats );
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

        $size_root = wp_insert_term( 'By Wheel Size', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $size_root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5" Dodge Ram 4500 5500 2008 Wheel Rim Liner Hubcap Covers';

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame(
            [
                'Wheel Simulators',
                'Brands',
                'Eagle Flight Wheel Simulators',
                'By Brand & Model',
                'Dodge',
                'Ram 4500',
                'Ram 5500',
                'By Wheel Size',
                '19.5"',
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
        $this->assertFileExists( $dir . '/wheel-sizes.csv' );

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

        $sizes = array_map( 'str_getcsv', file( $dir . '/wheel-sizes.csv' ) );
        $header = array_shift( $sizes );
        $this->assertSame( [ 'Size', 'Terms' ], $header );

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

    public function test_exports_brand_model_csv_with_alternate_root() {
        $wheel  = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $branch = wp_insert_term( 'Brands', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        $dodge  = wp_insert_term( 'Dodge', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( 'Ram 4500', 'product_cat', [ 'parent' => $dodge['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $dir = sys_get_temp_dir() . '/gm2_csv_alt_root';
        if ( file_exists( $dir ) ) {
            foreach ( glob( $dir . '/*' ) as $f ) { unlink( $f ); }
            rmdir( $dir );
        }

        Gm2_Category_Sort_Product_Category_Generator::export_brand_model_csv( $mapping, $dir );

        $brands = array_map( 'str_getcsv', file( $dir . '/brands.csv' ) );
        $header = array_shift( $brands );
        $this->assertSame( [ 'Brand', 'Terms' ], $header );
        $found = false;
        foreach ( $brands as $row ) {
            if ( $row[0] === 'Dodge' ) {
                $found = true;
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
            }
        }
        $this->assertTrue( $found );

        $this->assertFileExists( $dir . '/wheel-sizes.csv' );
        $sizes = array_map( 'str_getcsv', file( $dir . '/wheel-sizes.csv' ) );
        $header = array_shift( $sizes );
        $this->assertSame( [ 'Size', 'Terms' ], $header );
    }

    public function test_branch_rules_include_assigns_categories() {
        $parent = wp_insert_term( 'Branch', 'product_cat' );
        wp_insert_term( 'Leaf', 'product_cat', [ 'parent' => $parent['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();

        $upload = wp_upload_dir();
        $dir = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
        file_put_contents( $dir . '/branch-leaf.csv', "Branch,Leaf\n" );

        $GLOBALS['gm2_options']['gm2_branch_rules'] = [
            'branch-leaf' => [ 'include' => 'foo', 'exclude' => '' ],
        ];

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( 'Great foo product', $mapping );

        $this->assertSame( [ 'Branch', 'Leaf' ], $cats );
    }

    public function test_branch_rules_exclude_prevents_assignment() {
        $parent = wp_insert_term( 'Branch', 'product_cat' );
        wp_insert_term( 'Leaf', 'product_cat', [ 'parent' => $parent['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();

        $upload = wp_upload_dir();
        $dir = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
        file_put_contents( $dir . '/branch-leaf.csv', "Branch,Leaf\n" );

        $GLOBALS['gm2_options']['gm2_branch_rules'] = [
            'branch-leaf' => [ 'include' => 'foo', 'exclude' => 'bar' ],
        ];

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( 'foo bar thing', $mapping );

        $this->assertSame( [], $cats );
    }

    public function test_branch_rules_apply_to_direct_match() {
        $parent = wp_insert_term( 'Branch', 'product_cat' );
        $child  = wp_insert_term( 'Leaf', 'product_cat', [ 'parent' => $parent['term_id'] ] );
        update_term_meta( $child['term_id'], 'gm2_synonyms', 'LeafSyn' );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();

        $upload = wp_upload_dir();
        $dir    = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
        file_put_contents( $dir . '/branch-leaf.csv', "Branch,Leaf\n" );

        $GLOBALS['gm2_options']['gm2_branch_rules'] = [
            'branch-leaf' => [ 'include' => 'foo', 'exclude' => 'bar' ],
        ];

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( 'LeafSyn foo item', $mapping );
        $this->assertSame( [ 'Branch', 'Leaf' ], $cats );

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( 'LeafSyn item', $mapping );
        $this->assertSame( [], $cats );

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( 'LeafSyn foo bar', $mapping );
        $this->assertSame( [], $cats );
    }

    public function test_branch_rules_include_with_quote() {
        $parent = wp_insert_term( 'Branch', 'product_cat' );
        wp_insert_term( 'Leaf', 'product_cat', [ 'parent' => $parent['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();

        $upload = wp_upload_dir();
        $dir = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
        file_put_contents( $dir . '/branch-leaf.csv', "Branch,Leaf\n" );

        $GLOBALS['gm2_options']['gm2_branch_rules'] = [
            'branch-leaf' => [ 'include' => '19"', 'exclude' => '' ],
        ];

        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( 'Nice 19" accessory', $mapping );

        $this->assertSame( [ 'Branch', 'Leaf' ], $cats );
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

    public function test_assign_categories_uses_csv_lists() {
        $wheel  = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $branch = wp_insert_term( 'By Brand & Model', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        $dodge  = wp_insert_term( 'Dodge', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( 'Ram 3500', 'product_cat', [ 'parent' => $dodge['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $dir = sys_get_temp_dir() . '/gm2_csv_assign';
        if ( file_exists( $dir ) ) {
            foreach ( glob( "$dir/*" ) as $f ) { unlink( $f ); }
            rmdir( $dir );
        }

        Gm2_Category_Sort_Product_Category_Generator::export_brand_model_csv( $mapping, $dir );

        $text = 'Dodge Ram 3500 wheel simulator';
        $cats = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping, false, 85, $dir );

        $this->assertContains( 'Dodge', $cats );
        $this->assertContains( 'Ram 3500', $cats );
    }

    public function test_helper_check_wheel_simulators() {
        $ref    = new ReflectionClass( Gm2_Category_Sort_Product_Category_Generator::class );
        $method = $ref->getMethod( 'check_wheel_simulators' );
        $method->setAccessible( true );
        $lower = Gm2_Category_Sort_Product_Category_Generator::normalize_text( 'Chrome hubcap' );
        $cats  = $method->invoke( null, $lower );
        $this->assertSame( [ 'Wheel Simulators', 'Brands', 'Eagle Flight Wheel Simulators' ], $cats );
    }

    public function test_helper_check_lug_hole() {
        $root = wp_insert_term( 'By Lug/Hole Configuration', 'product_cat' );
        wp_insert_term( '10 Lug', 'product_cat', [ 'parent' => $root['term_id'] ] );
        wp_insert_term( '10 Lug 4 Hole', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $ref     = new ReflectionClass( Gm2_Category_Sort_Product_Category_Generator::class );
        $method  = $ref->getMethod( 'check_lug_hole' );
        $method->setAccessible( true );
        $lower = Gm2_Category_Sort_Product_Category_Generator::normalize_text( 'Fits 10 lug 4 hole wheels' );
        $words = preg_split( '/\s+/', $lower );
        $cats  = $method->invoke( null, $lower, $words, $mapping, false, 85 );
        $this->assertSame( [ 'By Lug/Hole Configuration', '10 Lug 4 Hole' ], $cats );
    }

    public function test_wheel_size_respects_branch_rules() {
        $root = wp_insert_term( 'By Wheel Size', 'product_cat' );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $root['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();

        $upload = wp_upload_dir();
        $dir    = trailingslashit( $upload['basedir'] ) . 'gm2-category-sort/categories-structure';
        if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
        file_put_contents( $dir . '/by-wheel-size-19-5.csv', "By Wheel Size,19.5\"\n" );

        $GLOBALS['gm2_options']['gm2_branch_rules'] = [
            'by-wheel-size-19-5' => [ 'include' => 'truck', 'exclude' => '' ],
        ];

        $ref    = new ReflectionClass( Gm2_Category_Sort_Product_Category_Generator::class );
        $method = $ref->getMethod( 'check_wheel_size' );
        $method->setAccessible( true );

        $lower = Gm2_Category_Sort_Product_Category_Generator::normalize_text( '19.5" wheel simulator' );
        $cats  = $method->invoke( null, $lower, $mapping, '19.5', '19.5"', true );
        $this->assertSame( [], $cats );

        $lower = Gm2_Category_Sort_Product_Category_Generator::normalize_text( '19.5" truck wheel simulator' );
        $cats  = $method->invoke( null, $lower, $mapping, '19.5', '19.5"', true );
        $this->assertSame( [ 'By Wheel Size', '19.5"' ], $cats );
    }

    public function test_helper_priority_order() {
        $wheel   = wp_insert_term( 'Wheel Simulators', 'product_cat' );
        $branch  = wp_insert_term( 'By Brand & Model', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        $dodge   = wp_insert_term( 'Dodge', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( 'Ram 4500', 'product_cat', [ 'parent' => $dodge['term_id'] ] );
        $size    = wp_insert_term( 'By Wheel Size', 'product_cat', [ 'parent' => $wheel['term_id'] ] );
        wp_insert_term( '19.5"', 'product_cat', [ 'parent' => $size['term_id'] ] );

        $mapping = Gm2_Category_Sort_Product_Category_Generator::build_mapping_from_globals();
        $text    = '19.5" Dodge Ram 4500 wheel simulator';
        $cats    = Gm2_Category_Sort_Product_Category_Generator::assign_categories( $text, $mapping );

        $this->assertSame(
            [ 'Wheel Simulators', 'Brands', 'Eagle Flight Wheel Simulators', 'By Brand & Model', 'Dodge', 'Ram 4500', 'By Wheel Size', '19.5"' ],
            $cats
        );
    }

    public function test_normalize_text_converts_primes() {
        $norm = Gm2_Category_Sort_Product_Category_Generator::normalize_text( "Size 19\xE2\x80\xB3 x 8\xE2\x80\xB2" );
        $this->assertSame( 'size 19" x 8\'', $norm );
    }

    public function test_slugify_segment_encodes_quotes() {
        $this->assertSame( '19d', Gm2_Category_Sort_Product_Category_Generator::slugify_segment( '19"' ) );
        $this->assertSame( '19s', Gm2_Category_Sort_Product_Category_Generator::slugify_segment( "19'" ) );
    }
}
}
