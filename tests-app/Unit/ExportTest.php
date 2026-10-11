<?php

namespace AppTests\Unit;

use App\Export\Cell;
use App\Export\CsvWriter;
use App\Export\Exporter;
use App\Export\XlsxWriter;
use DOMDocument;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class ExportTest extends TestCase
{
    /** @var array<int, string> */
    private $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $file) {
            @unlink($file);
        }
    }

    private function csv(array $rows): string
    {
        $stream = fopen('php://memory', 'w+b');
        $w = new CsvWriter($stream);
        foreach ($rows as $row) {
            $w->row($row);
        }
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * @return array{0: ZipArchive, 1: string}
     */
    private function open(string $path): array
    {
        $this->temp[] = $path;
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'the file is a zip');

        return [$zip, (string) $zip->getFromName('xl/worksheets/sheet1.xml')];
    }

    // ---- CSV ---------------------------------------------------------

    public function testCsvStartsWithAByteOrderMarkAndQuotesCells(): void
    {
        $out = $this->csv([['a', 'b,c', "line1\nline2", 'say "hi"']]);

        $this->assertSame("\xEF\xBB\xBF", substr($out, 0, 3));
        $this->assertSame("a,\"b,c\",\"line1\nline2\",\"say \"\"hi\"\"\"\r\n", substr($out, 3));
    }

    public function testCsvNeutralisesFormulaStarters(): void
    {
        $this->assertSame("'=1+1", CsvWriter::cell('=1+1'));
        $this->assertSame("'+cmd", CsvWriter::cell('+cmd'));
        $this->assertSame("'-2+3", CsvWriter::cell('-2+3'));
        $this->assertSame("'@SUM(A1)", CsvWriter::cell('@SUM(A1)'));
        $this->assertSame("'\tx", CsvWriter::cell("\tx"));
        $this->assertSame('-5', CsvWriter::cell('-5'), 'a real negative number is left alone');
        $this->assertSame('-5', CsvWriter::cell(-5));
        $this->assertSame('1.5', CsvWriter::cell(1.5));
        $this->assertSame('', CsvWriter::cell(null));
        $this->assertSame('1', CsvWriter::cell(true));
    }

    public function testCsvCountsRows(): void
    {
        $stream = fopen('php://memory', 'w+b');
        $w = new CsvWriter($stream);
        $w->row(['h']);
        $w->row(['x']);
        $this->assertSame(2, $w->rows());
    }

    // ---- XLSX --------------------------------------------------------

    public function testColumnNames(): void
    {
        $this->assertSame('A', XlsxWriter::columnName(1));
        $this->assertSame('Z', XlsxWriter::columnName(26));
        $this->assertSame('AA', XlsxWriter::columnName(27));
        $this->assertSame('AZ', XlsxWriter::columnName(52));
        $this->assertSame('BA', XlsxWriter::columnName(53));
        $this->assertSame('XFD', XlsxWriter::columnName(16384));
    }

    public function testXlsxHasAllPartsAndEveryPartIsWellFormedXml(): void
    {
        $x = new XlsxWriter('Orders');
        $x->header(['Number', 'Total'], [10, 12]);
        $x->row(['A-1', 19.5]);
        [$zip] = $this->open($x->finish());

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $part) {
            $xml = $zip->getFromName($part);
            $this->assertTrue(is_string($xml), $part.' exists');
            $doc = new DOMDocument();
            $this->assertTrue(@$doc->loadXML($xml), $part.' is well-formed');
        }
    }

    public function testXlsxKeepsNumbersAsNumbersAndTextAsText(): void
    {
        $x = new XlsxWriter();
        $x->header(['Text', 'Int', 'Float']);
        $x->row(['=1+1', 7, 2.5]);
        [, $sheet] = $this->open($x->finish());

        $this->assertStringContainsString('<c r="A2" t="inlineStr"><is><t xml:space="preserve">=1+1</t></is></c>', $sheet, 'a formula-looking string is stored as text, so it can never run');
        $this->assertStringContainsString('<c r="B2"><v>7</v></c>', $sheet);
        $this->assertStringContainsString('<c r="C2"><v>2.5</v></c>', $sheet);
        $this->assertStringContainsString('<autoFilter ref="A1:C2"/>', $sheet);
        $this->assertStringContainsString('state="frozen"', $sheet);
    }

    public function testXlsxEscapesXmlAndDropsCharactersXmlCannotCarry(): void
    {
        $x = new XlsxWriter();
        $x->header(['H']);
        $x->row(["<b>&\"'</b>\x01\x0B ok é日本"]);
        [, $sheet] = $this->open($x->finish());

        $doc = new DOMDocument();
        $this->assertTrue(@$doc->loadXML($sheet), 'still well-formed');
        $this->assertStringContainsString('&lt;b&gt;&amp;&quot;&apos;&lt;/b&gt; ok é日本', $sheet);
        $this->assertStringNotContainsString("\x01", $sheet);
    }

    public function testXlsxSheetTitleIsCleaned(): void
    {
        $x = new XlsxWriter('Q1: [2026] / sales?*\\ and a very long title that goes on');
        $x->header(['a']);
        [$zip] = $this->open($x->finish());

        $this->assertSame(1, preg_match('/<sheet name="([^"]+)"/', (string) $zip->getFromName('xl/workbook.xml'), $m));
        $this->assertLessThanOrEqual(31, mb_strlen($m[1]));
        $this->assertSame(0, preg_match('/[\\\\\/?*\[\]:]/', $m[1]));
    }

    public function testXlsxCutsOverlongCellsAndCountsRows(): void
    {
        $x = new XlsxWriter();
        $x->header(['H']);
        $x->row([str_repeat('x', 40000)]);
        $x->row([null]);
        $this->assertSame(2, $x->rows(), 'the header is not a data row');
        [, $sheet] = $this->open($x->finish());

        $this->assertSame(1, preg_match('/<t[^>]*>(x+)<\/t>/', $sheet, $m));
        $this->assertSame(32767, strlen($m[1]));
    }

    public function testXlsxRefusesMoreRowsThanExcelHolds(): void
    {
        $x = new XlsxWriter();
        $x->header(['H']);
        $reflection = new \ReflectionProperty($x, 'row');
        $reflection->setAccessible(true);
        $reflection->setValue($x, XlsxWriter::MAX_ROWS);

        try {
            $x->row(['one too many']);
            $this->fail('expected a RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('1048576', $e->getMessage());
        }
    }

    public function testXlsxWithNothingInItIsStillAValidFile(): void
    {
        $x = new XlsxWriter();
        [$zip, $sheet] = $this->open($x->finish());

        $this->assertNotSame('', $sheet);
        $doc = new DOMDocument();
        $this->assertTrue(@$doc->loadXML($sheet));
    }

    public function testXlsxMemoryDoesNotGrowWithTheNumberOfRows(): void
    {
        $x = new XlsxWriter();
        $x->header(['A', 'B', 'C']);
        for ($i = 0; $i < 2000; ++$i) {
            $x->row(['warm up', $i, 'x']);
        }
        $before = memory_get_usage();
        for ($i = 0; $i < 60000; ++$i) {
            $x->row(['Customer '.$i, $i, str_repeat('n', 40)]);
        }
        $growth = memory_get_usage() - $before;
        $this->temp[] = $x->finish();

        $this->assertLessThan(1024 * 1024, $growth, '60,000 more rows cost under 1 MB');
    }

    // ---- Cells -------------------------------------------------------

    public function testCellValues(): void
    {
        $this->assertNull(Cell::value(['type' => 'text'], ''));
        $this->assertNull(Cell::value(['type' => 'text'], null));
        $this->assertNull(Cell::value(['type' => 'password'], 'hash'), 'passwords never leave');

        $this->assertSame(19.5, Cell::value(['type' => 'number'], '19.5'));
        $this->assertSame(-5, Cell::value(['type' => 'number'], '-5'));
        $this->assertSame('007', Cell::value(['type' => 'number'], '007'), 'leading zeros are kept');
        $this->assertSame('1234567890123456789', Cell::value(['type' => 'number'], '1234567890123456789'), 'long whole numbers stay text');
        $this->assertSame('abc', Cell::value(['type' => 'number'], 'abc'));

        $this->assertSame('Shipped', Cell::value(['type' => 'select'], 'shipped', ['shipped' => 'Shipped']));
        $this->assertSame('gone', Cell::value(['type' => 'radio'], 'gone', []), 'an unknown option shows its value');
        $this->assertSame('New, On sale', Cell::value(['type' => 'multiselect'], '["new","sale"]', ['new' => 'New', 'sale' => 'On sale']));
        $this->assertSame('a.jpg', Cell::value(['type' => 'image'], 'crud/orders/ab/a.jpg'));
        $this->assertSame('2026-10-01', Cell::value(['type' => 'date'], '2026-10-01'));
    }

    // ---- Exporter ----------------------------------------------------

    public function testLimitsHaveSaneDefaultsAndAreCappedByEnvironment(): void
    {
        $this->assertSame(1000000, Exporter::limit('csv'));
        $this->assertSame(250000, Exporter::limit('xlsx'));
        $this->assertSame(2000, Exporter::limit('pdf'));

        putenv('EXPORT_MAX_ROWS_PDF=500');
        putenv('EXPORT_MAX_ROWS_XLSX=99999999');
        putenv('EXPORT_MAX_ROWS_CSV=abc');
        try {
            $this->assertSame(500, Exporter::limit('pdf'));
            $this->assertSame(XlsxWriter::MAX_ROWS - 1, Exporter::limit('xlsx'), 'never above what Excel can hold');
            $this->assertSame(1000000, Exporter::limit('csv'), 'garbage falls back to the default');
        } finally {
            putenv('EXPORT_MAX_ROWS_PDF');
            putenv('EXPORT_MAX_ROWS_XLSX');
            putenv('EXPORT_MAX_ROWS_CSV');
        }
    }

    public function testFilenameIsSafe(): void
    {
        $this->assertSame(1, preg_match('/^orders-\d{8}-\d{6}\.csv$/', Exporter::filename('orders', 'csv')));
        $this->assertSame(1, preg_match('/^a-b-\d{8}-\d{6}\.pdf$/', Exporter::filename("a\"\r\nb", 'pdf')));
    }

    public function testCsvThroughTheExporter(): void
    {
        $stream = fopen('php://memory', 'w+b');
        $n = Exporter::csv($stream, ['ID', 'Name'], function ($push) {
            $push([[1, 'Ann'], [2, 'Bob']]);
            $push([[3, 'Cy']]);
        });
        rewind($stream);

        $this->assertSame(3, $n);
        $this->assertSame("ID,Name\r\n1,Ann\r\n2,Bob\r\n3,Cy\r\n", substr((string) stream_get_contents($stream), 3));
    }

    public function testPdfThroughTheExporterWhenDompdfIsInstalled(): void
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            $this->markTestSkipped('dompdf is not installed');
        }

        [$bytes, $rows] = Exporter::pdf('Orders', 'all records', ['ID', 'Name'], function ($push) {
            $push([[1, '<script>alert(1)</script> é日本'], [2, 'Bob']]);
        });

        $this->assertSame(2, $rows);
        $this->assertSame('%PDF-', substr($bytes, 0, 5));
    }
}
