<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Carbon\Carbon;

/**
 * Text mining over the archive's own documents, for the Reports page. Every
 * technique starts from the same TF-IDF vectors (TextVectors), built from the
 * word counts stored with each file (files.text_terms):
 *
 *  - Possible misfiling: first by title (DocumentKind) - a file titled
 *    "A RESOLUTION" in a folder named for reports. Otherwise a file is
 *    compared to the "centroid" (summed TF-IDF vector) of its own folder's
 *    OTHER files and of every other folder. If it reads much more like
 *    another folder, it is suggested for a move. This is a nearest-centroid
 *    text classifier.
 *
 * Copies of the same document are found by DuplicateDetector when each file
 * is saved, not here. Everything is computed on demand; nothing is stored.
 */
class ArchiveInsights
{
    /** Cap the corpus so the pairwise comparison stays cheap. */
    private const MAX_FILES = 400;

    private const MISFILED_MIN_SIMILARITY = 0.30;

    private const MISFILED_MARGIN = 0.15;

    /** With only one other file in its own folder, the other folder must match this well. */
    private const MISFILED_MIN_SIMILARITY_SMALL_FOLDER = 0.45;

    private const ANOMALY_BASELINE_DAYS = 90;

    private const ANOMALY_RECENT_DAYS = 7;

    private const ANOMALY_Z = 3.0;

    private const ANOMALY_MIN_COUNT = 5;

    public function __construct(
        private readonly TextVectors $vectors,
        private readonly DocumentKind $kinds,
    ) {
    }

    /**
     * @return array{corpus: int, misfiled: array}
     */
    public function build(User $viewer): array
    {
        $folders = Folder::all()->keyBy('id');
        $openFolders = $folders->filter(fn (Folder $f) => $f->isAccessibleBy($viewer));
        $accessible = $openFolders->keys()->all();

        $files = File::whereIn('folder_id', $accessible)
            ->where(fn ($q) => $q->whereNotNull('text_terms')->orWhere(fn ($q) => $q->whereNotNull('ocr_text')->where('ocr_text', '!=', '')))
            ->latest()->limit(self::MAX_FILES)
            ->get(['id', 'filename', 'folder_id', 'text_terms', 'ocr_text', 'content_hash', 'duplicate_of_id', 'folder_hint_dismissed_at', 'created_at']);

        // Word counts per file (stored; worked out here for a file saved
        // before they were). Files with too little text are left out.
        $tf = [];
        foreach ($files as $file) {
            $counts = $file->text_terms ?? $this->vectors->termCounts($file->ocr_text);
            if ($counts) {
                $tf[$file->id] = $counts;
            }
        }

        $n = count($tf);
        $empty = ['corpus' => $n, 'misfiled' => []];
        if ($n < 2) {
            return $empty;
        }

        $df = $this->vectors->documentFrequencies($tf);
        $vec = array_map(fn ($counts) => $this->vectors->weigh($counts, $df, $n), $tf);
        $byId = $files->keyBy('id');

        return [
            'corpus'   => $n,
            'misfiled' => $this->findMisfiled($vec, $byId, $folders, $openFolders),
        ];
    }

    /**
     * Files that may be in the wrong folder.
     *
     *  - By title: the file is titled as one kind ("A RESOLUTION") and its
     *    folder's name says it is for another ("Reports"). A folder named
     *    after something else (an event) may hold any kind, so it is never
     *    judged by title.
     *  - By words, when there is no title to go by: the file reads much more
     *    like another folder's files than its own folder's.
     *
     * A file is never compared with its own copies: next to its twin, any
     * folder would look like the right one.
     */
    private function findMisfiled(array $vec, $byId, $folders, $openFolders): array
    {
        // Sum vector per folder.
        $sum = [];
        $members = [];
        foreach ($vec as $fileId => $v) {
            $folderId = $byId[$fileId]->folder_id;
            $members[$folderId] = ($members[$folderId] ?? 0) + 1;
            foreach ($v as $term => $w) {
                $sum[$folderId][$term] = ($sum[$folderId][$term] ?? 0) + $w;
            }
        }
        $centroids = array_map(fn ($s) => $this->vectors->normalize($s), $sum);

        $copies = $this->copiesOf($byId, array_keys($vec));
        $folderKinds = $openFolders->map(fn (Folder $f) => $this->kinds->forFolderName($f->name))->all();

        $out = [];
        foreach ($vec as $fileId => $v) {
            $file = $byId[$fileId];
            // An officer said it belongs where it is ("Keep it here", FolderHint).
            if ($file->folder_hint_dismissed_at) {
                continue;
            }
            $own = $file->folder_id;
            $kind = $this->kinds->fromText($file->ocr_text);
            $ownKinds = $folderKinds[$own] ?? [];

            if ($kind && $ownKinds) {
                $target = in_array($kind, $ownKinds, true) ? null : $this->folderForKind($kind, $own, $openFolders, $folderKinds, $members);
                if ($target) {
                    $out[] = [
                        'file'        => $this->fileRow($file, $folders),
                        'reason'      => 'title',
                        'kind'        => $this->kinds->label($kind),
                        'suggested'   => ['id' => $target->id, 'name' => $target->name],
                        'own_match'   => null,
                        'other_match' => null,
                        'gap'         => 0.0,
                    ];
                }
                continue;
            }

            // Each folder WITHOUT this file and its copies.
            $count = $members;
            $adjusted = [];
            foreach (array_merge([$fileId], $copies[$fileId]) as $id) {
                $folderId = $byId[$id]->folder_id;
                $count[$folderId]--;
                $adjusted[$folderId] ??= $sum[$folderId];
                foreach ($vec[$id] as $term => $w) {
                    $adjusted[$folderId][$term] -= $w;
                }
            }
            $centroid = fn ($folderId) => isset($adjusted[$folderId]) ? $this->vectors->normalize($adjusted[$folderId]) : $centroids[$folderId];

            // Need something to compare its own folder by. With one other
            // file there, the other folder must match much better.
            if ($count[$own] < 1) {
                continue;
            }
            $minSim = $count[$own] < 2 ? self::MISFILED_MIN_SIMILARITY_SMALL_FOLDER : self::MISFILED_MIN_SIMILARITY;
            $ownSim = $this->vectors->dot($v, $centroid($own));

            $bestFolder = null;
            $bestSim = 0.0;
            foreach (array_keys($sum) as $folderId) {
                if ($folderId === $own || $count[$folderId] < 2) {
                    continue;
                }
                // A titled file can't belong in a folder named for another kind.
                if ($kind && ($folderKinds[$folderId] ?? []) && ! in_array($kind, $folderKinds[$folderId], true)) {
                    continue;
                }
                $sim = $this->vectors->dot($v, $centroid($folderId));
                if ($sim > $bestSim) {
                    $bestSim = $sim;
                    $bestFolder = $folderId;
                }
            }

            if ($bestFolder !== null
                && $bestSim >= $minSim
                && $bestSim - $ownSim >= self::MISFILED_MARGIN) {
                $out[] = [
                    'file'        => $this->fileRow($file, $folders),
                    'reason'      => 'words',
                    'kind'        => null,
                    'suggested'   => ['id' => $bestFolder, 'name' => $folders[$bestFolder]->name ?? 'Unknown'],
                    'own_match'   => (int) round(max(0, $ownSim) * 100),
                    'other_match' => (int) round($bestSim * 100),
                    'gap'         => $bestSim - $ownSim,
                ];
            }
        }

        // A title is surer than any word match, so those come first.
        usort($out, fn ($x, $y) => [$y['reason'] === 'title', $y['gap']] <=> [$x['reason'] === 'title', $x['gap']]);

        return array_slice($out, 0, 8);
    }

    /**
     * The other files that are copies of each file: the same bytes, or
     * marked by DuplicateDetector as a copy of the same original.
     *
     * @return array<int, int[]>
     */
    private function copiesOf($byId, array $ids): array
    {
        $byHash = [];
        $byOriginal = [];
        foreach ($ids as $id) {
            $file = $byId[$id];
            if ($file->content_hash) {
                $byHash[$file->content_hash][] = $id;
            }
            $byOriginal[$file->duplicate_of_id ?? $id][] = $id;
        }

        $copies = [];
        foreach ($ids as $id) {
            $file = $byId[$id];
            $copies[$id] = array_values(array_diff(array_unique(array_merge(
                $file->content_hash ? $byHash[$file->content_hash] : [],
                $byOriginal[$file->duplicate_of_id ?? $id],
            )), [$id]));
        }

        return $copies;
    }

    /**
     * The folder a file of this kind belongs in, among those the viewer may
     * open: the same school year as the file's folder first, then one that
     * isn't a past year's (read-only), then the one with the most files.
     */
    private function folderForKind(string $kind, int $ownId, $openFolders, array $folderKinds, array $members): ?Folder
    {
        $year = $openFolders[$ownId]->school_year ?? null;

        return $openFolders
            ->filter(fn (Folder $f) => in_array($kind, $folderKinds[$f->id] ?? [], true))
            ->sort(fn (Folder $a, Folder $b) => [$b->school_year === $year, ! $b->is_archived, $members[$b->id] ?? 0, $b->id]
                <=> [$a->school_year === $year, ! $a->is_archived, $members[$a->id] ?? 0, $a->id])
            ->first();
    }

    private function fileRow(File $file, $folders): array
    {
        return [
            'id'     => $file->id,
            'name'   => $file->filename,
            'folder' => $folders[$file->folder_id]->name ?? 'Unknown',
            'folder_id' => $file->folder_id,
            'date'   => $file->created_at?->format('M j, Y'),
        ];
    }

    // ------------------------------------------------------------------
    // UNUSUAL ACTIVITY (per-officer z-score on sensitive actions)
    // Not shown yet: it reads the activity log, which is SuperAdmin-only,
    // so it may only be added inside the Reports page's SuperAdmin panels.
    // ------------------------------------------------------------------

    /**
     * Each officer's daily count of sensitive actions (downloads, deletes)
     * compared with their OWN 90-day baseline, so a heavy user isn't flagged
     * for normal work but a quiet account suddenly downloading a lot is.
     */
    public function unusualActivity(): array
    {
        $now = Carbon::now();
        $recentStart = $now->copy()->subDays(self::ANOMALY_RECENT_DAYS - 1)->startOfDay();
        $baselineStart = $recentStart->copy()->subDays(self::ANOMALY_BASELINE_DAYS);

        $sensitive = '/^(Downloaded|Moved .* to Trash|Permanently deleted|Emptied the Trash|Deleted )/';

        $logs = ActivityLog::where('created_at', '>=', $baselineStart)
            ->get(['user_name', 'activity', 'created_at'])
            ->filter(fn ($l) => preg_match($sensitive, $l->activity));

        // user => date => count
        $daily = [];
        foreach ($logs as $log) {
            $daily[$log->user_name][$log->created_at->format('Y-m-d')] = ($daily[$log->user_name][$log->created_at->format('Y-m-d')] ?? 0) + 1;
        }

        $baselineDates = [];
        for ($d = $baselineStart->copy(); $d->lt($recentStart); $d->addDay()) {
            $baselineDates[] = $d->format('Y-m-d');
        }

        $anomalies = [];
        foreach ($daily as $user => $perDay) {
            // Zero-filled baseline: quiet days count as 0, they are part of the pattern.
            $series = array_map(fn ($date) => $perDay[$date] ?? 0, $baselineDates);
            $mean = array_sum($series) / max(1, count($series));
            $var = array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $series)) / max(1, count($series));
            $std = max(1.0, sqrt($var)); // floor so a near-constant baseline can't make z explode

            for ($d = $recentStart->copy(); $d->lte($now); $d->addDay()) {
                $key = $d->format('Y-m-d');
                $count = $perDay[$key] ?? 0;
                $z = ($count - $mean) / $std;

                if ($count >= self::ANOMALY_MIN_COUNT && $z >= self::ANOMALY_Z) {
                    $anomalies[] = [
                        'user'     => $user,
                        'date'     => $d->format('M j'),
                        'count'    => $count,
                        'baseline' => round($mean, 1),
                        'z'        => round($z, 1),
                    ];
                }
            }
        }

        usort($anomalies, fn ($a, $b) => $b['z'] <=> $a['z']);

        return [
            'anomalies'    => array_slice($anomalies, 0, 8),
            'anomalyUsers' => count($daily),
        ];
    }
}
