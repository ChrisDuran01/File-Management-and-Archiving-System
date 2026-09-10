<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Watched inbox
    |--------------------------------------------------------------------------
    |
    | The folder the scanner software (Epson Scan 2 / NAPS2) is configured to
    | drop finished PDFs into. `documents:scan-inbox` moves new files out of
    | here into the sub-folders below as it processes them.
    |
    */

    'inbox_path'        => env('SCAN_INBOX_PATH', storage_path('app/scan_inbox')),
    'processing_subdir' => 'processing',
    'processed_subdir'  => 'processed',
    'failed_subdir'     => 'failed',

    /*
    |--------------------------------------------------------------------------
    | Ingestion limits
    |--------------------------------------------------------------------------
    */

    // Cap files handled per scheduled run so a big backlog can't turn one
    // run into an hours-long one (mirrors SyncLocalFilesToCloud::MAX_PER_RUN).
    'max_per_run' => (int) env('SCAN_MAX_PER_RUN', 10),

    // A file is only picked up once its size has stopped changing and it is
    // this many seconds old - so a scan still being written isn't ingested
    // half-complete.
    'stable_seconds' => (int) env('SCAN_STABLE_SECONDS', 15),

    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],

    // Refuse absurdly large batches outright rather than let one job run for
    // an unbounded time; the operator can raise this and re-process.
    'max_pages' => (int) env('SCAN_MAX_PAGES', 200),

    /*
    |--------------------------------------------------------------------------
    | Rendering / processing
    |--------------------------------------------------------------------------
    */

    'thumbnail_dpi'   => (int) env('SCAN_THUMB_DPI', 120),
    'ocr_dpi'         => (int) env('SCAN_OCR_DPI', 200),
    'process_timeout' => (int) env('SCAN_PROCESS_TIMEOUT', 240),

    /*
    |--------------------------------------------------------------------------
    | Document separation (one batch PDF -> several documents)
    |--------------------------------------------------------------------------
    |
    | Barcode is tried first when enabled and zbarimg resolves; a page is a
    | separator only if a decoded barcode value exactly equals barcode_token.
    | Blank-page detection is the fallback and only runs if no barcode
    | separators were found. The operator always corrects the proposed
    | boundaries on the review screen, so these are hints, not decisions.
    |
    */

    'separation' => [
        'barcode_enabled'     => (bool) env('SCAN_BARCODE_SEPARATION', true),
        'barcode_token'       => env('SCAN_BARCODE_TOKEN', 'DOCSPLIT'),
        'blank_page_enabled'  => (bool) env('SCAN_BLANKPAGE_SEPARATION', true),
        // Fraction of dark pixels below which a page counts as visually blank.
        'blank_ink_threshold' => (float) env('SCAN_BLANK_INK_THRESHOLD', 0.004),
    ],

    /*
    |--------------------------------------------------------------------------
    | Housekeeping
    |--------------------------------------------------------------------------
    */

    // documents:prune-scan-batches drops the raw stored file + row for
    // batches that were filed or failed longer ago than this.
    'batch_retention_days' => (int) env('SCAN_BATCH_RETENTION_DAYS', 30),

];
