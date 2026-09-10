<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ocr' => [
        'tesseract_path' => env('OCR_TESSERACT_PATH', 'tesseract'),
        'pdftotext_path' => env('OCR_PDFTOTEXT_PATH', 'pdftotext'),
        'pdftoppm_path'  => env('OCR_PDFTOPPM_PATH', 'pdftoppm'),
        // Extra Poppler tools (same bin dir as pdftoppm on the standard
        // Windows build) used by the scan-inbox split pipeline, plus ZBar's
        // zbarimg for optional barcode separator-sheet detection.
        'pdfinfo_path'     => env('OCR_PDFINFO_PATH', 'pdfinfo'),
        'pdfseparate_path' => env('OCR_PDFSEPARATE_PATH', 'pdfseparate'),
        'pdfunite_path'    => env('OCR_PDFUNITE_PATH', 'pdfunite'),
        'zbarimg_path'     => env('OCR_ZBARIMG_PATH', 'zbarimg'),
    ],

    'backup' => [
        'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        // AES-256 password backups are encrypted with (DPA/RA 10173 - the ZIP
        // holds a full DB dump plus every file). No key = no backup: losing
        // this key means every existing backup is unreadable, so it needs to
        // be escrowed somewhere outside .env (e.g. a password manager/safe),
        // not just left on this server.
        'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    ],

    // Google Gemini - powers the in-app "Ask for help" assistant that walks
    // new officers through the system. The key is server-side only; the
    // browser never sees it (requests go through HelpBotController).
    'gemini' => [
        'key'   => env('GEMINI_API_KEY'),
        // Google retires dated model ids fairly often; the "-latest" alias
        // tracks the current Flash model. Pin a dated id here if you need
        // frozen behaviour (e.g. gemini-3.6-flash).
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
    ],

];
