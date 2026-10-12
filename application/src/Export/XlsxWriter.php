<?php

namespace App\Export;

use RuntimeException;
use ZipArchive;

/**
 * A small .xlsx writer that streams: rows are appended to a temporary XML
 * file as they come, and the file is zipped at the end, so memory use stays
 * flat whether the sheet has ten rows or a few hundred thousand. (Libraries
 * that build the whole workbook in memory need hundreds of MB for that.)
 *
 *   $x = new XlsxWriter('Orders');
 *   $x->header(['Number', 'Customer', 'Total'], [12, 30, 12]);
 *   $x->row(['A-1', 'Ann', 19.99]);          // numbers stay numbers, strings stay text
 *   $path = $x->finish();                    // temp file; delete it after sending
 *
 * Strings are stored as inline strings, so nothing a user typed can ever run as
 * a formula. Excel allows 1,048,576 rows and 32,767 characters per cell; both
 * limits are enforced.
 */
final class XlsxWriter
{
    public const MAX_ROWS = 1048576;
    private const MAX_CELL = 32767;

    /** @var string */
    private $title;

    /** @var string|null */
    private $sheetFile;

    /** @var resource|null */
    private $sheet;

    /** @var int */
    private $row = 0;

    /** @var int */
    private $columns = 0;

    /** @var array<int, float|int> */
    private $widths = [];

    /** @var bool */
    private $hasHeader = false;

    /** @var string */
    private $tmpDir;

    /**
     * @param string $title sheet name; Excel allows 31 characters and no : \ / ? * [ ]
     */
    public function __construct($title = 'Sheet1', $tmpDir = null)
    {
        $title = trim(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $title));
        $this->title = mb_substr($title === '' ? 'Sheet1' : $title, 0, 31);
        $this->tmpDir = $tmpDir ?? sys_get_temp_dir();
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Must be the first call. Bold, frozen and filterable.
     *
     * @param array<int, string>    $labels
     * @param array<int, int|float> $widths characters per column; empty = a default
     */
    public function header(array $labels, array $widths = [])
    {
        $this->open(count($labels), $widths);
        $this->hasHeader = true;
        $this->write($labels, 1);
    }

    /**
     * @param array<int, scalar|null> $cells int and float become numbers; everything else text
     */
    public function row(array $cells)
    {
        if ($this->sheet === null) {
            $this->open(count($cells), []);
        }
        if ($this->row >= self::MAX_ROWS) {
            throw new RuntimeException('Excel sheets hold at most '.self::MAX_ROWS.' rows.');
        }
        $this->write($cells, 0);
    }

    public function rows()
    {
        return max(0, $this->row - ($this->hasHeader ? 1 : 0));
    }

    /**
     * Closes the sheet and builds the file.
     *
     * @return string path of a temporary .xlsx the caller must delete
     */
    public function finish()
    {
        if ($this->sheet === null) {
            $this->open(1, []);
        }

        $last = self::columnName(max(1, $this->columns)).max(1, $this->row);
        fwrite($this->sheet, '</sheetData>');
        if ($this->hasHeader && $this->row > 1) {
            fwrite($this->sheet, '<autoFilter ref="A1:'.$last.'"/>');
        }
        fwrite($this->sheet, '</worksheet>');
        fclose($this->sheet);
        $this->sheet = null;

        $out = tempnam($this->tmpDir, 'xlsx');
        $zip = new ZipArchive();
        if ($out === false || $zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The workbook could not be created.');
        }
        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFile((string) $this->sheetFile, 'xl/worksheets/sheet1.xml');
        $zip->close();

        $this->cleanup();

        return $out;
    }

    public static function columnName($index)
    {
        $name = '';
        for ($n = (int) $index; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26).$name;
        }

        return $name;
    }

    private function open($columns, array $widths)
    {
        $this->columns = max(1, min((int) $columns, 16384));
        $this->widths = array_values($widths);
        $this->sheetFile = tempnam($this->tmpDir, 'xlsxsheet');
        $this->sheet = $this->sheetFile === false ? false : fopen($this->sheetFile, 'wb');
        if ($this->sheet === false) {
            throw new RuntimeException('The workbook could not be created.');
        }

        $cols = '';
        for ($i = 0; $i < $this->columns; ++$i) {
            $w = isset($this->widths[$i]) ? max(4, min(80, (float) $this->widths[$i])) : 16;
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$w.'" customWidth="1"/>';
        }

        fwrite($this->sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0">'
            .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
            .'</sheetView></sheetViews>'
            .'<cols>'.$cols.'</cols><sheetData>');
    }

    /**
     * @param array<int, scalar|null> $cells
     */
    private function write(array $cells, $style)
    {
        ++$this->row;
        $xml = '<row r="'.$this->row.'">';
        $i = 0;
        foreach ($cells as $value) {
            if (++$i > $this->columns) {
                break;
            }
            $ref = self::columnName($i).$this->row;
            $s = $style === 1 ? ' s="1"' : '';

            if ($value === null || $value === '') {
                continue;
            }
            if (is_int($value) || (is_float($value) && is_finite($value))) {
                $xml .= '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>';
            } else {
                $xml .= '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::text((string) (is_bool($value) ? (int) $value : $value)).'</t></is></c>';
            }
        }
        fwrite($this->sheet, $xml.'</row>');
    }

    private static function text($value)
    {
        // Characters XML 1.0 cannot carry would make Excel call the file corrupt.
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x{FFFE}\x{FFFF}]/u', '', $value) ?? '';
        if (mb_strlen($value) > self::MAX_CELL) {
            $value = mb_substr($value, 0, self::MAX_CELL);
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function cleanup()
    {
        if (is_resource($this->sheet)) {
            fclose($this->sheet);
        }
        $this->sheet = null;
        if ($this->sheetFile !== null && is_file($this->sheetFile)) {
            @unlink($this->sheetFile);
        }
        $this->sheetFile = null;
    }

    private function workbook()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.htmlspecialchars($this->title, ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function contentTypes()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private static function rootRels()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbookRels()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private static function styles()
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE9ECEF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
