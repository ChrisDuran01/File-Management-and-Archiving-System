<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Keeps OCR and a restore from running at the same time. OCR has its own
 * queue worker (the "ocr" queue), so without this a restore could replace
 * the database and the files under a file that is being read, and the OCR
 * job would then write its text onto rows that changed under it. With one
 * worker for everything this could not happen.
 *
 * Kept in the file cache, not the database one: a restore empties the cache
 * tables halfway through (BackupController::keepAcrossRestore()).
 */
class OcrRestoreLock
{
    /** Longest an OCR job is expected to run (a 200-page scan batch). */
    private const OCR_SECONDS = 1800;

    /** A restore re-uploads every file, so it can take hours. */
    private const RESTORE_SECONDS = 6 * 3600;

    /** How often a waiting restore says it is still waiting. */
    private const NOTICE_EVERY_SECONDS = 60;

    /** How long a restore waits for the file being read to finish. */
    public static int $restoreWaitSeconds = 1800;

    /** The lock an OCR job holds while it runs. */
    public static function forOcrJob(): Lock
    {
        return self::lock(self::OCR_SECONDS);
    }

    /** Whether a restore is running or waiting to start - OCR jobs hold back. */
    public static function restoreWaiting(): bool
    {
        return self::store()->has(self::restoreKey());
    }

    /**
     * Runs $restore once no OCR job is running, and holds OCR back until it
     * is over. $waiting is called now and then while it waits. Returns false,
     * without running $restore, when a file was still being read after
     * $restoreWaitSeconds.
     */
    public static function whileRestoring(callable $restore, callable $waiting): bool
    {
        $store = self::store();
        $lock = self::lock(self::RESTORE_SECONDS);

        // First, so no new OCR job starts while this waits for the running one.
        $store->put(self::restoreKey(), true, self::RESTORE_SECONDS);

        try {
            $started = now()->getTimestamp();
            $lastNotice = null;

            while (! $lock->get()) {
                $now = now()->getTimestamp();

                if ($now - $started >= self::$restoreWaitSeconds) {
                    return false;
                }

                if ($lastNotice === null || $now - $lastNotice >= self::NOTICE_EVERY_SECONDS) {
                    $waiting();
                    $lastNotice = $now;
                }

                Sleep::sleep(1);
            }

            try {
                $restore();
            } finally {
                $lock->release();
            }

            return true;
        } finally {
            $store->forget(self::restoreKey());
        }
    }

    private static function store(): Repository
    {
        return Cache::store('file');
    }

    private static function lock(int $seconds): Lock
    {
        /** @var LockProvider $files */
        $files = self::store()->getStore();

        return $files->lock(self::key(), $seconds);
    }

    /** Per database, so the test suite never waits on this PC's OCR worker. */
    private static function key(): string
    {
        return 'ocr-restore:' . DB::connection()->getDatabaseName();
    }

    private static function restoreKey(): string
    {
        return self::key() . ':restore';
    }
}
