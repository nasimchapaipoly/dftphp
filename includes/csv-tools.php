<?php
/**
 * Class Routine Management System (NasimSoft)
 * Shared CSV Import / Export Helpers
 * Copyright (c) 2026 NasimSoft.
 *
 * Every "Bulk Upload / Update" feature (Subjects, Teachers, Rooms, Labs) uses
 * these functions so parsing, encoding, and error-reporting behave identically
 * everywhere.
 */

declare(strict_types=1);

if (!function_exists('csvDownload')) {
    /**
     * Stream a CSV file to the browser and stop execution.
     * @param string $filename  e.g. "subjects-export.csv"
     * @param array  $headers   column headers, e.g. ['subject_code','name',...]
     * @param array  $rows      each row is a plain array matching $headers order
     */
    function csvDownload(string $filename, array $headers, array $rows): void {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM so Excel renders correctly
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}

if (!function_exists('csvParseUpload')) {
    /**
     * Parse an uploaded CSV file into an array of associative rows, keyed by
     * the lower-cased, trimmed header names from the first line.
     *
     * @throws Exception if the file is missing, unreadable, or empty.
     * @return array<int, array<string, string>>
     */
    function csvParseUpload(array $file): array {
        if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new Exception('Please choose a CSV file to upload.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            throw new Exception('Only .csv files are supported.');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Could not read the uploaded file.');
        }

        // Strip a UTF-8 BOM if present so the first header isn't mangled
        $bom = fread($handle, 3);
        if ($bom !== chr(0xEF) . chr(0xBB) . chr(0xBF)) {
            rewind($handle);
        }

        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            throw new Exception('The CSV file appears to be empty.');
        }
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn($v) => trim((string)$v) !== '')) === 0) {
                continue; // skip fully blank lines
            }
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = trim((string)($line[$i] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        if (empty($rows)) {
            throw new Exception('No data rows found below the header line.');
        }

        return $rows;
    }
}

if (!function_exists('csvImportSummaryFlash')) {
    /**
     * Build a friendly flash message summarising a bulk import result.
     */
    function csvImportSummaryFlash(int $created, int $updated, array $errors): void {
        $parts = [];
        if ($created > 0) $parts[] = "{$created} created";
        if ($updated > 0) $parts[] = "{$updated} updated";
        $summary = !empty($parts) ? implode(', ', $parts) : 'No records changed';

        if (empty($errors)) {
            setFlash('success', "Import complete: {$summary}.");
        } else {
            $errorPreview = implode(' | ', array_slice($errors, 0, 5));
            $more = count($errors) > 5 ? ' (+' . (count($errors) - 5) . ' more)' : '';
            setFlash('warning', "Import finished with issues: {$summary}. Errors: {$errorPreview}{$more}");
        }
    }
}
