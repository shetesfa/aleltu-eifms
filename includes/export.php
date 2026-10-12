<?php
/**
 * Aleltu Farm EIFMS - CSV export helper.
 *
 * No PhpSpreadsheet, no library — plain PHP fputcsv() streamed directly
 * to the browser. Every spreadsheet program (Excel, LibreOffice, Google
 * Sheets) opens a .csv file natively, so this covers "export to Excel"
 * without needing Composer or any dependency.
 *
 * Usage: call this LAST in a page — it sends headers and exits, so run
 * your query first, then call export_csv($rows, 'filename.csv').
 */
function export_csv(array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders Amharic/accented text correctly, not mojibake

    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0])); // header row from the first row's keys
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
    }

    fclose($out);
    exit;
}
