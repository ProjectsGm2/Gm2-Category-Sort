<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/class-branch-rules.php';

class BranchSlugMapTest extends TestCase {
    protected function setUp(): void {
        gm2_test_reset_terms();
    }

    public function test_map_includes_leaf_parents() {
        $root   = wp_insert_term('Root','product_cat');
        $branch = wp_insert_term('Branch','product_cat',['parent'=>$root['term_id']]);
        wp_insert_term('Leaf','product_cat',['parent'=>$branch['term_id']]);
        wp_insert_term('Solo','product_cat');

        $dir = sys_get_temp_dir() . '/gm2_slug_map';
        if (file_exists($dir)) {
            foreach (glob("$dir/*") as $f) { unlink($f); }
            rmdir($dir);
        }

        Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv($dir);

        $map = Gm2_Category_Sort_Branch_Rules::build_slug_path_map($dir . '/category-tree.csv');

        $this->assertArrayHasKey('solo', $map);
        $this->assertSame('Solo', $map['solo']);
        $this->assertArrayHasKey('root-branch', $map);
        $this->assertSame('Root > Branch', $map['root-branch']);
        $this->assertArrayHasKey('root-branch-leaf', $map);
        $this->assertSame('Root > Branch > Leaf', $map['root-branch-leaf']);
    }
}
