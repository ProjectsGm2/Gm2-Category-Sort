<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../includes/class-branch-rules.php';

class BranchRulesCsvTest extends TestCase {
    private $dir;

    protected function setUp(): void {
        gm2_test_reset_terms();
        $GLOBALS['gm2_options'] = [];
        $root = wp_insert_term('Parent','product_cat');
        wp_insert_term('Child','product_cat',['parent'=>$root['term_id']]);
        $upload = wp_upload_dir();
        $this->dir = trailingslashit($upload['basedir']) . 'gm2-category-sort/categories-structure';
        if (!is_dir($this->dir)) {
            mkdir($this->dir,0777,true);
        }
        foreach (glob("{$this->dir}/*") as $f) { unlink($f); }
        Gm2_Category_Sort_Product_Category_Generator::export_category_tree_csv($this->dir);
    }

    private function sample_rules(): array {
        return [
            'parent-child' => [
                'include'       => 'foo',
                'exclude'       => 'bar',
                'include_attrs' => [ 'pa_color' => [ 'red','blue' ] ],
                'exclude_attrs' => [ 'pa_size' => [ 'large' ] ],
                'allow_multi'   => true,
            ],
        ];
    }

    public function test_export_to_csv_creates_expected_rows() {
        $GLOBALS['gm2_options']['gm2_branch_rules'] = $this->sample_rules();
        $file = tempnam(sys_get_temp_dir(), 'gm2_rules');
        Gm2_Category_Sort_Branch_Rules::export_to_csv($file);
        $rows = array_map('str_getcsv', file($file));
        unlink($file);

        $this->assertSame(['slug','path','include','exclude','include_attrs','exclude_attrs','allow_multi'], $rows[0]);
        $this->assertSame('parent-child', $rows[1][0]);
        $this->assertSame('Parent > Child', $rows[1][1]);
        $this->assertSame('foo', $rows[1][2]);
        $this->assertSame('bar', $rows[1][3]);
        $this->assertSame('pa_color:red|blue', $rows[1][4]);
        $this->assertSame('pa_size:large', $rows[1][5]);
        $this->assertSame('1', $rows[1][6]);
    }

    public function test_import_from_csv_recreates_option_array() {
        $rules = $this->sample_rules();
        $GLOBALS['gm2_options']['gm2_branch_rules'] = $rules;
        $file = tempnam(sys_get_temp_dir(), 'gm2_rules');
        Gm2_Category_Sort_Branch_Rules::export_to_csv($file);

        $imported = Gm2_Category_Sort_Branch_Rules::import_from_csv($file);
        unlink($file);

        $this->assertSame($rules, $imported);
    }
}
