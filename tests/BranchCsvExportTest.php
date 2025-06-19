<?php
use PHPUnit\Framework\TestCase;

class BranchCsvExportTest extends TestCase {
    protected function setUp(): void {
        gm2_test_reset_terms();
    }

    public function test_branch_csvs_include_leaf_nodes() {
        $root   = wp_insert_term( 'Root', 'product_cat' );
        $branch = wp_insert_term( 'Branch', 'product_cat', [ 'parent' => $root['term_id'] ] );
        wp_insert_term( 'Leaf', 'product_cat', [ 'parent' => $branch['term_id'] ] );
        wp_insert_term( 'Solo', 'product_cat' );

        $dir = sys_get_temp_dir() . '/gm2_branch_csvs';
        if ( file_exists( $dir ) ) {
            foreach ( glob( "$dir/*" ) as $f ) { unlink( $f ); }
            rmdir( $dir );
        }

        Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv( $dir );

        $ref    = new ReflectionClass( Gm2_Category_Sort_One_Click_Assign::class );
        $method = $ref->getMethod( 'export_branch_csvs' );
        $method->setAccessible( true );
        $method->invoke( null, $dir );

        $this->assertFileExists( "$dir/root-branch.csv" );
        $this->assertFileExists( "$dir/root-branch-leaf.csv" );
        $this->assertFileExists( "$dir/solo.csv" );

        $branch_rows = array_map( 'str_getcsv', file( "$dir/root-branch.csv" ) );
        $leaf_rows   = array_map( 'str_getcsv', file( "$dir/root-branch-leaf.csv" ) );
        $solo_rows   = array_map( 'str_getcsv', file( "$dir/solo.csv" ) );

        $this->assertContains( [ 'Root', 'Branch', 'Leaf' ], $branch_rows );
        $this->assertSame( [ [ 'Root', 'Branch', 'Leaf' ] ], $leaf_rows );
        $this->assertSame( [ [ 'Solo' ] ], $solo_rows );
    }
}
