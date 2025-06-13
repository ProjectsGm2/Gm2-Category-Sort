<?php
use PHPUnit\Framework\TestCase;

class CategoryImporterTest extends TestCase {

    protected function setUp(): void {
        gm2_test_reset_terms();
    }

    private function createCsvFile(string $contents): string {
        $file = tempnam(sys_get_temp_dir(), 'gm2_csv');
        file_put_contents($file, $contents);
        return $file;
    }

    public function test_import_creates_category_hierarchy() {
        $csv = "Parent,Child1,Grandchild\nParent,Child2\n";
        $file = $this->createCsvFile($csv);

        $result = Gm2_Category_Sort_Category_Importer::import_from_csv($file);
        unlink($file);

        $this->assertTrue($result);
        $calls = $GLOBALS['gm2_insert_calls'];
        $this->assertCount(4, $calls);

        $parent_id = $calls[0]['id'];
        $this->assertSame('Parent', $calls[0]['name']);
        $this->assertSame(0, $calls[0]['parent']);

        $this->assertSame('Child1', $calls[1]['name']);
        $this->assertSame($parent_id, $calls[1]['parent']);
        $child1_id = $calls[1]['id'];

        $this->assertSame('Grandchild', $calls[2]['name']);
        $this->assertSame($child1_id, $calls[2]['parent']);

        $this->assertSame('Child2', $calls[3]['name']);
        $this->assertSame($parent_id, $calls[3]['parent']);
    }

    public function test_import_skips_duplicate_rows() {
        $csv = "Cat1,Sub\nCat1,Sub\n";
        $file = $this->createCsvFile($csv);
        $result = Gm2_Category_Sort_Category_Importer::import_from_csv($file);
        unlink($file);

        $this->assertTrue($result);
        $calls = $GLOBALS['gm2_insert_calls'];
        $this->assertCount(2, $calls);
        $cat_id = $calls[0]['id'];
        $this->assertSame('Cat1', $calls[0]['name']);
        $this->assertSame(0, $calls[0]['parent']);
        $this->assertSame('Sub', $calls[1]['name']);
        $this->assertSame($cat_id, $calls[1]['parent']);
    }

    public function test_import_ignores_empty_lines() {
        $csv = "Cat1,Sub1\n\nCat2\n";
        $file = $this->createCsvFile($csv);
        $result = Gm2_Category_Sort_Category_Importer::import_from_csv($file);
        unlink($file);

        $this->assertTrue($result);
        $calls = $GLOBALS['gm2_insert_calls'];
        $this->assertCount(3, $calls);
        $this->assertSame('Cat1', $calls[0]['name']);
        $this->assertSame('Sub1', $calls[1]['name']);
        $this->assertSame('Cat2', $calls[2]['name']);
    }

    public function test_import_sets_synonyms() {
        $csv = "\"Parent (SynA,SynB)\",\"Child (Alt)\"\n";
        $file = $this->createCsvFile($csv);
        $result = Gm2_Category_Sort_Category_Importer::import_from_csv($file);
        unlink($file);

        $this->assertTrue($result);
        $calls = $GLOBALS['gm2_insert_calls'];
        $this->assertCount(2, $calls);
        $parent_id = $calls[0]['id'];
        $child_id  = $calls[1]['id'];

        $meta = $GLOBALS['gm2_meta_updates'];
        $this->assertCount(2, $meta);
        $this->assertSame($parent_id, $meta[0]['term_id']);
        $this->assertSame('gm2_synonyms', $meta[0]['key']);
        $this->assertSame('SynA,SynB', $meta[0]['value']);
        $this->assertSame($child_id, $meta[1]['term_id']);
        $this->assertSame('gm2_synonyms', $meta[1]['key']);
        $this->assertSame('Alt', $meta[1]['value']);
    }
}
