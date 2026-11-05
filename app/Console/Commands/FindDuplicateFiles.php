<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\File;
use App\Services\DuplicateDetector;
use Illuminate\Console\Command;

/**
 * Runs the duplicate check over files that were saved before it existed:
 * fills in each file's fingerprint (content_hash) and word counts
 * (text_terms), then marks every file that looks like a copy of another
 * one. New uploads are checked on their own (FileController::store and
 * ExtractFileOcrText), so this is a one-off. It only sets flags - nothing is
 * moved or deleted - it is safe to run again, and an officer's "not a copy"
 * answer is kept.
 */
class FindDuplicateFiles extends Command
{
    protected $signature = 'files:find-duplicates';

    protected $description = 'Fingerprint existing files and mark the ones that look like a copy of an older file';

    public function handle(DuplicateDetector $detector): int
    {
        $hashed = $indexed = $unreadable = 0;

        // ---- 1. fingerprints and word counts ----
        File::orderBy('id')->chunkById(200, function ($files) use ($detector, &$hashed, &$indexed, &$unreadable) {
            foreach ($files as $file) {
                if (! $file->content_hash) {
                    $detector->indexHash($file) ? $hashed++ : $unreadable++;
                }
                if ($file->text_terms === null && filled($file->ocr_text)) {
                    $detector->indexText($file);
                    if ($file->text_terms !== null) {
                        $indexed++;
                    }
                }
            }
        });

        $this->info("Fingerprinted {$hashed} file(s), read the words of {$indexed}."
            . ($unreadable ? " {$unreadable} could not be read from storage." : ''));

        // ---- 2. compare, every file against all the others ----
        $corpus = $detector->corpus();
        $found = [];

        File::orderBy('id')->chunkById(200, function ($files) use ($detector, $corpus, &$found) {
            foreach ($files as $file) {
                if ($original = $detector->check($file, $corpus)) {
                    $found[] = [$file->filename, $original->filename, $file->duplicate_similarity];
                }
            }
        });

        if ($found) {
            $this->table(['Possible copy', 'Copy of', 'Alike %'], $found);
        }
        $this->info('Marked ' . count($found) . ' file(s) as possible copies.');

        if ($found) {
            ActivityLog::create([
                'user_name'  => 'System',
                'activity'   => 'Duplicate check marked ' . count($found) . ' file(s) as possible copies',
                'ip_address' => null,
            ]);
        }

        return self::SUCCESS;
    }
}
