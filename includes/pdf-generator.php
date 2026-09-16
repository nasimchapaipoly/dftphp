<?php
/**
 * Class Routine Management System (NasimSoft)
 * Real PDF Generation via DOMPDF
 * Copyright (c) 2026 NasimSoft.
 *
 * DOMPDF is a Composer dependency (see composer.json) — run `composer install`
 * once on your server to enable real PDF output. Until then, every function
 * here reports itself as unavailable and callers fall back to the existing
 * browser Print-to-PDF view, so nothing breaks in the meantime.
 */

declare(strict_types=1);

if (!defined('DOMPDF_AUTOLOAD')) {
    define('DOMPDF_AUTOLOAD', __DIR__ . '/../vendor/autoload.php');
}

if (file_exists(DOMPDF_AUTOLOAD)) {
    require_once DOMPDF_AUTOLOAD;
}

if (!function_exists('isPdfEngineReady')) {
    function isPdfEngineReady(): bool {
        return file_exists(DOMPDF_AUTOLOAD) && class_exists('Dompdf\\Dompdf');
    }
}

if (!function_exists('resolveAssetPathForPdf')) {
    /**
     * Convert a webroot-relative asset path (e.g. "assets/uploads/branding/logo.png",
     * as stored in website_settings) into an absolute filesystem path DOMPDF
     * can read directly — no network fetch needed, no "isRemoteEnabled" risk.
     */
    function resolveAssetPathForPdf(string $relativePath): string {
        $relativePath = ltrim($relativePath, '/');
        $absolute = realpath(__DIR__ . '/../' . $relativePath);
        return $absolute !== false ? $absolute : '';
    }
}

if (!function_exists('renderHtmlToPdfBytes')) {
    /**
     * Render an HTML fragment (the <div class="routine-doc">...</div> from
     * routine-print-template.php) into real PDF bytes.
     *
     * @return string|null  Raw PDF bytes, or null if DOMPDF isn't installed.
     */
    function renderHtmlToPdfBytes(string $bodyHtml, string $orientation = 'landscape'): ?string {
        if (!isPdfEngineReady()) {
            return null;
        }

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', realpath(__DIR__ . '/..'));

        $dompdf = new \Dompdf\Dompdf($options);

        $fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            @page { margin: 16px 22px; }
            body { font-family: "DejaVu Sans", sans-serif; margin: 0; }
            table { border-collapse: collapse; }
        </style></head><body>' . $bodyHtml . '</body></html>';

        $dompdf->loadHtml($fullHtml, 'UTF-8');
        $dompdf->setPaper('A4', $orientation === 'portrait' ? 'portrait' : 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }
}
