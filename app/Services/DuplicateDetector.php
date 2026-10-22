<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Finds files that look like a copy of an older file, so the same minutes or
 * resolution doesn't end up in the records twice under different names.
 *
 *  - Exact copy: the same bytes (SHA-256 of the file). Known the moment a
 *    file is uploaded, before its text has been read.
 *  - Near copy: its extracted text is at least 85% alike by TF-IDF cosine
 *    similarity (TextVectors) - the same document saved as Word and as PDF,
 *    a re-scan, a renamed "- Copy". Known once OCR has finished.
 *
 * Of two alike files, the one named like a copy ("... - Copy", "... (1)") is
 * the copy; otherwise the newer one is (files.duplicate_of_id points at the
 * original). When several files match, one with the same name apart from
 * its extension or punctuation ("No-005.docx" / "No-005.pdf") is preferred
 * over one that is merely alike. An officer's "not a copy, keep both"
 * answer (duplicate_dismissed_at) is never overridden. Nothing is moved or
 * deleted.
 *
 * These columns are written without touching updated_at, which the folder
 * list shows as "Modified".
 */
class DuplicateDetector
{
    public const SIMILARITY = 0.85;

    /** @var array<int, array{0: File, 1: int}>|null files the last findOriginal() found to be copies of the file it checked */
    private ?array $copiesOf = null;

    public function __construct(private readonly TextVectors $vectors)
    {
    }

    /** Stores the word counts of the file's extracted text (null when there is too little). */
    public function indexText(File $file): void
    {
        $this->write($file, ['text_terms' => $this->vectors->termCounts($file->ocr_text)]);
    }

    /** Stores the SHA-256 of the file's bytes, read from the local copy or the cloud. */
    public function indexHash(File $file): ?string
    {
        $hash = $this->contentHash($file);
        if ($hash) {
            $this->write($file, ['content_hash' => $hash]);
        }

        return $hash;
    }

    /**
     * Decides whether $file is a copy of an older file and records the
     * answer. Returns the original, or null.
     *
     * @param  array<int, array<string, int>>|null  $corpus  id => word counts of every file, when
     *                                                         checking many files in a row
     */
    public function check(File $file, ?array $corpus = null): ?File
    {
        if ($file->duplicate_dismissed_at) {
            return null;
        }

        [$original, $similarity] = $this->findOriginal($file, $corpus) ?? [null, null];

        $this->write($file, ['duplicate_of_id' => $original?->id, 'duplicate_similarity' => $similarity]);

        // The original uploaded after its "- Copy": the older file is the
        // copy, and it isn't checked again on its own.
        foreach ($this->copiesOf ?? [] as [$copy, $sim]) {
            if (! $copy->duplicate_of_id && ! $copy->duplicate_dismissed_at) {
                $this->write($copy, ['duplicate_of_id' => $file->id, 'duplicate_similarity' => $sim]);
            }
        }
        $this->copiesOf = null;

        return $original;
    }

    /** An officer's "not a copy, keep both": the file is never flagged again. */
    public function dismiss(File $file): void
    {
        $this->write($file, ['duplicate_dismissed_at' => now()]);
    }

    /**
     * The older file $file looks like a copy of, with how alike they are (0-100).
     *
     * @return array{0: File, 1: int}|null
     */
    public function findOriginal(File $file, ?array $corpus = null): ?array
    {
        $this->copiesOf = [];

        // Exact copies: of every file with these bytes, the most original one.
        if ($file->content_hash) {
            $same = File::where('content_hash', $file->content_hash)->whereKeyNot($file->id)->get();

            if ($same->isNotEmpty()) {
                $root = $same->reduce(fn (?File $best, File $f) => $best && $this->isCopyOf($f, $best) ? $best : $f);

                if ($this->isCopyOf($file, $root)) {
                    return [$root, 100];
                }

                $this->copiesOf = $same->map(fn (File $f) => [$f, 100])->all();

                return null;
            }
        }

        $terms = $file->text_terms;
        if (! $terms) {
            return null;
        }

        $corpus ??= $this->corpus();
        $corpus[$file->id] = $terms;

        $df = $this->vectors->documentFrequencies($corpus);
        $n = count($corpus);
        $mine = $this->vectors->weigh($terms, $df, $n);

        $alike = [];
        foreach ($corpus as $id => $counts) {
            if ($id === $file->id) {
                continue;
            }
            $sim = $this->vectors->dot($mine, $this->vectors->weigh($counts, $df, $n));
            if ($sim >= self::SIMILARITY) {
                $alike[$id] = (int) min(100, round($sim * 100));
            }
        }

        if (! $alike) {
            return null;
        }

        // Which side of each pair is the copy.
        $originals = [];
        foreach (File::whereKey(array_keys($alike))->get() as $other) {
            if ($this->isCopyOf($file, $other)) {
                $originals[] = [$other, $alike[$other->id]];
            } else {
                $this->copiesOf[] = [$other, $alike[$other->id]];
            }
        }

        // Same name first ("No-005.docx" is the Word copy of "No-005.pdf",
        // not of "No-006.docx"), then the most alike.
        $stem = $this->stem($file->filename);
        usort($originals, fn ($x, $y) => [$this->stem($y[0]->filename) === $stem, $y[1]] <=> [$this->stem($x[0]->filename) === $stem, $x[1]]);

        return $originals[0] ?? null;
    }

    /**
     * Of two alike files, whether $a is the copy and $b the original: a name
     * like a copy decides; otherwise the newer file (a minute apart or more),
     * otherwise the one saved second.
     */
    public function isCopyOf(File $a, File $b): bool
    {
        $aCopy = $this->namedLikeCopy($a->filename);
        if ($aCopy !== $this->namedLikeCopy($b->filename)) {
            return $aCopy;
        }

        if ($a->created_at && $b->created_at && abs($a->created_at->diffInSeconds($b->created_at)) > 60) {
            return $a->created_at->gt($b->created_at);
        }

        return $a->id > $b->id;
    }

    /** "minutes - Copy.docx", "minutes (1).docx", "minutes copy.docx" */
    private function namedLikeCopy(string $filename): bool
    {
        return (bool) preg_match('/(\s*-\s*copy(\s*\(\d+\))?|[\s_]copy|\s\(\d+\))$/i', pathinfo($filename, PATHINFO_FILENAME));
    }

    /** The name without extension, copy marks or punctuation: "3-MOTM-E.M.-07-16-26" and "3-MOTM-EM-07-16-26" match. */
    private function stem(string $filename): string
    {
        $name = preg_replace('/(\s*-\s*copy(\s*\(\d+\))?|[\s_]copy|\s\(\d+\))$/i', '', pathinfo($filename, PATHINFO_FILENAME));

        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($name));
    }

    /**
     * Word counts of every file not in Trash.
     *
     * @return array<int, array<string, int>>
     */
    public function corpus(): array
    {
        return File::whereNotNull('text_terms')
            ->get(['id', 'text_terms'])
            ->mapWithKeys(fn (File $f) => [$f->id => $f->text_terms])
            ->filter()
            ->all();
    }

    public function contentHash(File $file): ?string
    {
        try {
            if ($file->local_path && Storage::disk('local')->exists($file->local_path)) {
                return hash_file('sha256', Storage::disk('local')->path($file->local_path));
            }

            if ($file->filepath && Storage::disk('cloud')->exists($file->filepath)) {
                $stream = Storage::disk('cloud')->readStream($file->filepath);
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                fclose($stream);

                return hash_final($ctx);
            }
        } catch (Throwable) {
            // Unreadable right now; the file is simply not checked by hash.
        }

        return null;
    }

    private function write(File $file, array $values): void
    {
        $row = $values;
        if (array_key_exists('text_terms', $row) && $row['text_terms'] !== null) {
            $row['text_terms'] = json_encode($row['text_terms'], JSON_UNESCAPED_UNICODE);
        }

        DB::table('files')->where('id', $file->id)->update($row);

        $file->forceFill($values)->syncOriginalAttributes(array_keys($values));
    }
}
