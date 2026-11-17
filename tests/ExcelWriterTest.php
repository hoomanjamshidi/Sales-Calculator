<?php
/** فایل XLSX بدون کتابخانهٔ صفحه‌گسترده ساخته و ساختار OOXML آن بازخوانی می‌شود. */
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SalesCalculator\Export\ExcelWriter;
final class ExcelWriterTest extends TestCase {
    private string $dir;
    protected function setUp():void {$this->dir=sys_get_temp_dir().'/sc-test-'.bin2hex(random_bytes(4));mkdir($this->dir);}
    protected function tearDown():void {foreach(glob($this->dir.'/{,temp/}*',GLOB_BRACE)?:[] as $f){if(is_file($f))unlink($f);}@rmdir($this->dir.'/temp');@rmdir($this->dir);}
    private function report():array {return ['filters'=>['from'=>'2026-03-21','to'=>'2026-03-22','calendar'=>'jalali','currency'=>'IRT'],'summary'=>['quantity'=>'3.00000000','amount'=>'490000.00000000','orders'=>2,'unallocated'=>'-20.50000000','asOf'=>'2026-03-22T10:00:00+00:00','timezone'=>'Asia/Tehran']];}
    /** متن خطرناک فرمول و نویسهٔ کنترلی، متن ساده و امن باقی می‌ماند و اعداد عددی ذخیره می‌شوند. */
    public function test_writes_valid_workbook_with_typed_cells():void {
        $rows=[['product_id'=>'17','variation_id'=>'27','title'=>'=HYPERLINK("x")','attributes'=>"رنگ: آبی\x01",'sold'=>'4.00000000','returned'=>'1.00000000','quantity'=>'3.00000000','amount'=>'490000.50000000']];
        $path=$this->dir.'/sales.xlsx';
        (new ExcelWriter())->write($this->report(),$rows,$path,$this->dir.'/temp');
        $zip=new ZipArchive();
        self::assertTrue($zip->open($path));
        foreach(['[Content_Types].xml','_rels/.rels','xl/workbook.xml','xl/styles.xml','xl/worksheets/sheet1.xml','xl/worksheets/sheet2.xml','xl/_rels/workbook.xml.rels'] as $part){
            $xml=$zip->getFromName($part);
            self::assertNotFalse($xml,$part);
            self::assertNotFalse(simplexml_load_string($xml),$part.' is not well-formed');
        }
        $sheet=simplexml_load_string($zip->getFromName('xl/worksheets/sheet2.xml'));
        $sheet->registerXPathNamespace('m','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        self::assertSame('inlineStr',(string)$sheet->xpath('//m:c[@r="C2"]')[0]['t']);
        self::assertSame('=HYPERLINK("x")',(string)$sheet->xpath('//m:c[@r="C2"]/m:is/m:t')[0]);
        self::assertSame('رنگ: آبی',(string)$sheet->xpath('//m:c[@r="D2"]/m:is/m:t')[0]);
        self::assertSame('490000.5',(string)$sheet->xpath('//m:c[@r="H2"]/m:v')[0]);
        self::assertSame('A1:I2',(string)$sheet->autoFilter['ref']);
        self::assertStringContainsString('&lt;آزمون&gt; &amp;',$zip->getFromName('xl/worksheets/sheet1.xml'));
        self::assertStringContainsString('<v>20.5</v>',$zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        self::assertFileDoesNotExist($this->dir.'/temp/sheet2.xml');
    }
    /** گزارش خالی هم فایل معتبر با سربرگ می‌سازد. */
    public function test_empty_report_is_valid():void {
        $path=$this->dir.'/empty.xlsx';
        (new ExcelWriter())->write($this->report(),[],$path,$this->dir.'/temp');
        $zip=new ZipArchive();$zip->open($path);
        self::assertStringContainsString('<autoFilter ref="A1:I1"/>',$zip->getFromName('xl/worksheets/sheet2.xml'));
        $zip->close();
    }
}
