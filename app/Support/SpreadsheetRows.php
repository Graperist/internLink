<?php

namespace App\Support;

use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Reads the rows of an uploaded .csv or .xlsx file, keyed by the heading
 * row (headings in snake_case: "ID Number" -> id_number). Uses only PHP's
 * own zip and XML support, so no spreadsheet package is needed.
 */
class SpreadsheetRows
{
    /** @return array<int, array<string, string>> data rows, in file order */
    public static function read(string $path, string $extension): array
    {
        $grid = strtolower($extension) === 'xlsx' ? self::xlsxGrid($path) : self::csvGrid($path);

        // The first non-empty row is the heading row.
        while ($grid && self::isBlank($grid[0])) {
            array_shift($grid);
        }
        if (! $grid) {
            return [];
        }
        $headings = array_map(fn ($h) => Str::snake(Str::lower(trim(preg_replace('/^\x{FEFF}/u', '', (string) $h)))), array_shift($grid));
        $headings = array_map(fn ($h) => preg_replace('/_+/', '_', str_replace([' ', '-'], '_', $h)), $headings);

        $rows = [];
        foreach ($grid as $cells) {
            if (self::isBlank($cells)) {
                $rows[] = null;   // keep the numbering of the rows below it
                continue;
            }
            $row = [];
            foreach ($headings as $i => $heading) {
                if ($heading !== '') {
                    $row[$heading] = trim((string) ($cells[$i] ?? ''));
                }
            }
            $rows[] = $row;
        }

        // Drop blank rows at the end of the sheet.
        while ($rows && end($rows) === null) {
            array_pop($rows);
        }

        return $rows;
    }

    private static function isBlank(array $cells): bool
    {
        return trim(implode('', array_map('strval', $cells))) === '';
    }

    private static function csvGrid(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('The file could not be read.');
        }
        $first = fgets($handle) ?: '';
        // Excel in some regions saves CSV with semicolons.
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($handle);

        $grid = [];
        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $grid[] = array_map(fn ($c) => mb_check_encoding((string) $c, 'UTF-8') ? (string) $c : mb_convert_encoding((string) $c, 'UTF-8', 'Windows-1252'), $cells);
        }
        fclose($handle);

        return $grid;
    }

    private static function xlsxGrid(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The .xlsx file could not be opened. Save it again from Excel, or as .csv.');
        }

        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $doc = simplexml_load_string($xml);
            foreach ($doc->si as $si) {
                $text = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            }
        }

        $sheetPath = self::firstSheetPath($zip);
        $xml = $zip->getFromName($sheetPath);
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('The .xlsx file has no worksheet.');
        }

        $doc = simplexml_load_string($xml);
        $grid = [];
        foreach ($doc->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $col = self::columnIndex((string) $c['r']) ?? count($cells);
                $type = (string) $c['t'];
                $value = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    default => (string) $c->v,
                };
                $cells[$col] = $value;
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $cells += array_fill(0, $max + 1, '');
                ksort($cells);
            }
            $rowNumber = (int) $row['r'] ?: count($grid) + 1;
            $grid[$rowNumber - 1] = array_values($cells);
        }
        if (! $grid) {
            return [];
        }
        // Fill rows Excel skipped (empty rows) so row numbers stay right.
        $grid += array_fill(0, max(array_keys($grid)) + 1, []);
        ksort($grid);

        return array_values($grid);
    }

    private static function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook !== false && $rels !== false) {
            $wb = simplexml_load_string($workbook);
            $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $sheet = $wb->xpath('//m:sheets/m:sheet')[0] ?? null;
            $rid = $sheet ? (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] : null;
            foreach (simplexml_load_string($rels)->Relationship as $rel) {
                if ((string) $rel['Id'] === $rid) {
                    $target = ltrim((string) $rel['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function columnIndex(string $ref): ?int
    {
        if (! preg_match('/^([A-Z]+)/', $ref, $m)) {
            return null;
        }
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
