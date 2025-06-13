<?php
use PHPUnit\Framework\TestCase;

class ProductCategoryImporterTest extends TestCase {

    protected function setUp(): void {
        gm2_test_reset_terms();
        // Setup some terms for lookup
        $GLOBALS['gm2_test_terms'][0] = [ 'Cat1' => 1, 'Cat2' => 2 ];
        $GLOBALS['gm2_products'] = [ 'SKU1' => 10, 'SKU2' => 20 ];
    }

    private function createCsv(string $contents): string {
        $file = tempnam(sys_get_temp_dir(), 'gm2_prod');
        file_put_contents($file, $contents);
        return $file;
    }

    public function test_appends_categories() {
        $csv = "SKU1,Cat1\n";
        $file = $this->createCsv($csv);

        Gm2_Category_Sort_Product_Category_Importer::import_from_csv($file, false);
        unlink($file);

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount(1, $calls);
        $this->assertTrue($calls[0]['append']);
        $this->assertSame(10, $calls[0]['object_id']);
        $this->assertSame([1], $calls[0]['terms']);
    }

    public function test_overwrites_categories() {
        $csv = "SKU2,Cat2\n";
        $file = $this->createCsv($csv);

        Gm2_Category_Sort_Product_Category_Importer::import_from_csv($file, true);
        unlink($file);

        $calls = $GLOBALS['gm2_set_terms_calls'];
        $this->assertCount(1, $calls);
        $this->assertFalse($calls[0]['append']);
        $this->assertSame(20, $calls[0]['object_id']);
        $this->assertSame([2], $calls[0]['terms']);
    }
}
