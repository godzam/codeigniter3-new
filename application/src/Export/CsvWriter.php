<?php

namespace App\Export;

/**
 * Writes CSV to a stream one row at a time, so memory use does not depend on
 * how many rows there are.
 *
 * The file starts with a UTF-8 byte order mark so Excel opens accents and
 * non-latin text correctly. Cells that a spreadsheet would run as a formula
 * (starting with = + - @ tab or CR) get a single quote in front, which is
 * the OWASP recommendation against CSV injection.
 */
final class CsvWriter
{
    /** @var resource */
    private $stream;

    /** @var int */
    private $rows = 0;

    /**
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
        fwrite($this->stream, "\xEF\xBB\xBF");
    }

    /**
     * @param array<int, scalar|null> $cells
     */
    public function row(array $cells)
    {
        fputcsv($this->stream, array_map([self::class, 'cell'], $cells), ',', '"', '\\', "\r\n");
        ++$this->rows;
    }

    public function rows()
    {
        return $this->rows;
    }

    /**
     * @param scalar|null $value
     *
     * @return string
     */
    public static function cell($value)
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $text = (string) $value;
        if (!is_numeric($text) && strpbrk($text[0], "=+-@\t\r") !== false) {
            return "'".$text;
        }

        return $text;
    }
}
