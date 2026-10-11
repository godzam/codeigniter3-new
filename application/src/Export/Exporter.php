<?php

namespace App\Export;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/**
 * Turns rows into a CSV, Excel or PDF file. The rows come from a "feed": a
 * callable that is handed a $push callable and calls it with chunks of rows
 * (each row an array of cells), so the whole table is never in memory at once
 * (PDF is the exception, see below).
 *
 *   Exporter::csv($stream, $labels, function ($push) { $push([[1, 'a'], [2, 'b']]); });
 *
 * Limits (rows per export) come from the environment so an operator can match
 * them to the server; the defaults are what a small server handles in a
 * request without a gateway timeout:
 *
 *   EXPORT_MAX_ROWS_CSV   default 1000000  (streamed, constant memory)
 *   EXPORT_MAX_ROWS_XLSX  default 250000   (streamed to a temp file, constant memory; Excel itself stops at 1,048,575)
 *   EXPORT_MAX_ROWS_PDF   default 2000     (the page layout is built in memory)
 */
final class Exporter
{
    public const FORMATS = ['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'];

    public const TYPES = [
        'csv' => 'text/csv; charset=UTF-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    private const DEFAULT_LIMITS = ['csv' => 1000000, 'xlsx' => 250000, 'pdf' => 2000];
    private const HARD_LIMITS = ['csv' => 20000000, 'xlsx' => XlsxWriter::MAX_ROWS - 1, 'pdf' => 20000];

    /**
     * @return array<string, string> format => label of the formats this server can produce
     */
    public static function available()
    {
        $formats = ['csv' => self::FORMATS['csv']];
        if (class_exists(\ZipArchive::class)) {
            $formats['xlsx'] = self::FORMATS['xlsx'];
        }
        if (class_exists(Dompdf::class)) {
            $formats['pdf'] = self::FORMATS['pdf'];
        }

        return $formats;
    }

    /**
     * Most rows one export of this format may contain.
     */
    public static function limit($format)
    {
        $format = (string) $format;
        $env = getenv('EXPORT_MAX_ROWS_'.strtoupper($format));
        $value = is_string($env) && ctype_digit($env) && (int) $env > 0 ? (int) $env : (self::DEFAULT_LIMITS[$format] ?? 1000);

        return min($value, self::HARD_LIMITS[$format] ?? 1000);
    }

    /**
     * @param resource                $stream
     * @param array<int, string>      $labels
     * @param callable(callable): void $feed
     *
     * @return int rows written
     */
    public static function csv($stream, array $labels, callable $feed)
    {
        $csv = new CsvWriter($stream);
        $csv->row($labels);
        $feed(static function (array $rows) use ($csv) {
            foreach ($rows as $row) {
                $csv->row($row);
            }
        });

        return $csv->rows() - 1;
    }

    /**
     * @param array<int, string>       $labels
     * @param array<int, int|float>    $widths
     * @param callable(callable): void $feed
     *
     * @return array{0: string, 1: int} path of a temporary file to delete after sending, rows written
     */
    public static function xlsx($title, array $labels, array $widths, callable $feed)
    {
        $xlsx = new XlsxWriter($title);
        $xlsx->header($labels, $widths);
        $feed(static function (array $rows) use ($xlsx) {
            foreach ($rows as $row) {
                $xlsx->row($row);
            }
        });
        $path = $xlsx->finish();

        return [$path, $xlsx->rows()];
    }

    /**
     * A landscape A4 table. Unlike the other two this builds the whole page in
     * memory (that is how PDF layout works), which is why its row limit is small.
     *
     * @param array<int, string>       $labels
     * @param callable(callable): void $feed
     *
     * @return array{0: string, 1: int} the PDF bytes, rows written
     */
    public static function pdf($title, $subtitle, array $labels, callable $feed)
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('PDF export needs the dompdf/dompdf package (run composer install).');
        }

        $e = static function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'@page{margin:28px 24px}body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#212529}'
            .'h1{font-size:14px;margin:0 0 2px}p{margin:0 0 10px;color:#6c757d}'
            .'table{width:100%;border-collapse:collapse;table-layout:auto}'
            .'th{background:#e9ecef;text-align:left}th,td{border:1px solid #ced4da;padding:3px 4px;vertical-align:top;word-wrap:break-word}'
            .'thead{display:table-header-group}tr{page-break-inside:avoid}'
            .'</style></head><body><h1>'.$e($title).'</h1><p>'.$e($subtitle).'</p><table><thead><tr>';
        foreach ($labels as $label) {
            $html .= '<th>'.$e($label).'</th>';
        }
        $html .= '</tr></thead><tbody>';

        $rows = 0;
        $feed(static function (array $chunk) use (&$html, &$rows, $e) {
            foreach ($chunk as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>'.$e(is_bool($cell) ? (int) $cell : $cell).'</td>';
                }
                $html .= '</tr>';
                ++$rows;
            }
        });
        $html .= '</tbody></table></body></html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('isFontSubsettingEnabled', true);
        $options->set('chroot', sys_get_temp_dir());
        $options->set('tempDir', sys_get_temp_dir());

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        return [(string) $pdf->output(), $rows];
    }

    /**
     * A safe download name: letters, digits, dashes, with the date.
     */
    public static function filename($slug, $format)
    {
        return preg_replace('/[^a-z0-9_-]+/i', '-', (string) $slug).'-'.date('Ymd-His').'.'.$format;
    }
}
