<?php

namespace App\Jobs\Middleware;

use App\Support\OcrRestoreLock;

/**
 * For the OCR jobs: while a restore runs, or waits for the file being read
 * to finish, the job goes back on its queue for a minute later instead of
 * running (OcrRestoreLock). As a fresh copy rather than release(), so the
 * wait doesn't use up the job's tries - ExtractFileOcrText has only one.
 */
class HoldDuringRestore
{
    public const RETRY_SECONDS = 60;

    public function handle(object $command, callable $next): void
    {
        // The sync queue (the test suite) runs a dispatch on the spot:
        // putting the job back would run it again right away, forever.
        if (! $command->job || $command->job->getConnectionName() === 'sync') {
            $next($command);

            return;
        }

        $lock = OcrRestoreLock::forOcrJob();

        if (OcrRestoreLock::restoreWaiting() || ! $lock->get()) {
            $copy = clone $command;
            $copy->job = null;
            dispatch($copy->delay(self::RETRY_SECONDS));

            return;
        }

        try {
            $next($command);
        } finally {
            $lock->release();
        }
    }
}
