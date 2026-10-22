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
 *  - Document map: classical multidimensional scaling of the files'
 *    cosine similarities, so documents that read alike sit together.
 *  - Possible misfiling: a file is compared to the "centroid" (summed
 *    TF-IDF vector) of its own folder's OTHER files and of every other
 *    folder. If it reads much more like another folder, it is suggested
 *    for a move. This is a nearest-centroid text classifier.
 *  - Folder topics: the words with the highest summed weight per folder.
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

    private const ANOMALY_BASELINE_DAYS = 90;

    private const ANOMALY_RECENT_DAYS = 7;

    private const ANOMALY_Z = 3.0;

    private const ANOMALY_MIN_COUNT = 5;

    public function __construct(private readonly TextVectors $vectors)
    {
    }

    /**
     * @return array{corpus: int, misfiled: array, folderTopics: array, map: array}
     */
    public function build(User $viewer): array
    {
        $folders = Folder::all()->keyBy('id');
        $accessible = $folders->filter(fn (Folder $f) => $f->isAccessibleBy($viewer))->keys()->all();

        $files = File::whereIn('folder_id', $accessible)
            ->where(fn ($q) => $q->whereNotNull('text_terms')->orWhere(fn ($q) => $q->whereNotNull('ocr_text')->where('ocr_text', '!=', '')))
            ->latest()->limit(self::MAX_FILES)
            ->get(['id', 'filename', 'folder_id', 'text_terms', 'ocr_text', 'created_at']);

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
        $empty = ['corpus' => $n, 'misfiled' => [], 'folderTopics' => [], 'map' => []];
        if ($n < 2) {
            return $empty;
        }

        $df = $this->vectors->documentFrequencies($tf);
        $vec = array_map(fn ($counts) => $this->vectors->weigh($counts, $df, $n), $tf);
        $byId = $files->keyBy('id');

        $ids = array_keys($vec);
        $gram = [];
        for ($i = 0, $c = count($ids); $i < $c; $i++) {
            $gram[$i][$i] = 1.0;
            for ($j = $i + 1; $j < $c; $j++) {
                $gram[$i][$j] = $gram[$j][$i] = $this->vectors->dot($vec[$ids[$i]], $vec[$ids[$j]]);
            }
        }

        $misfiled = $this->findMisfiled($vec, $byId, $folders);

        return [
            'corpus'       => $n,
            'misfiled'     => $misfiled,
            'folderTopics' => $this->folderTopics($vec, $byId, $folders),
            'map'          => $this->documentMap($ids, $gram, $byId, $folders, array_column(array_column($misfiled, 'file'), 'id')),
        ];
    }

    /**
     * 2-D "document map" by classical multidimensional scaling (equivalent
     * to PCA on the TF-IDF vectors): double-centre the similarity matrix,
     * take its top two eigenvectors by power iteration, and use them as
     * x/y. Documents with similar text land close together, so folders show
     * up as clusters, duplicates sit on top of each other, and a misfiled
     * document sits inside another folder's cluster.
     */
    private function documentMap(array $ids, array $gram, $byId, $folders, array $misfiledIds): array
    {
        $n = count($ids);
        if ($n < 3) {
            return [];
        }

        // B = J G J  (double centring)
        $rowMean = array_map(fn ($row) => array_sum($row) / $n, $gram);
        $grand = array_sum($rowMean) / $n;
        $B = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $B[$i][$j] = $gram[$i][$j] - $rowMean[$i] - $rowMean[$j] + $grand;
            }
        }

        [$l1, $v1] = $this->topEigen($B, $n);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $B[$i][$j] -= $l1 * $v1[$i] * $v1[$j]; // deflate
            }
        }
        [$l2, $v2] = $this->topEigen($B, $n);

        $s1 = sqrt(max(0, $l1));
        $s2 = sqrt(max(0, $l2));

        // Largest folders get their own colour/legend entry; the rest share "Other".
        $counts = [];
        foreach ($ids as $id) {
            $fid = $byId[$id]->folder_id;
            $counts[$fid] = ($counts[$fid] ?? 0) + 1;
        }
        arsort($counts);
        $named = array_slice(array_keys($counts), 0, 2);

        $points = [];
        foreach ($ids as $i => $id) {
            $f = $byId[$id];
            $points[] = [
                'x'      => round($v1[$i] * $s1, 4),
                'y'      => round($v2[$i] * $s2, 4),
                'file'   => $f->filename,
                'folder' => in_array($f->folder_id, $named, true) ? ($folders[$f->folder_id]->name ?? 'Unknown') : 'Other folders',
                'moved'  => in_array($id, $misfiledIds, true),
            ];
        }

        return $points;
    }

    /** Dominant eigenpair of a symmetric matrix by power iteration. */
    private function topEigen(array $M, int $n): array
    {
        $v = [];
        for ($i = 0; $i < $n; $i++) {
            $v[$i] = sin($i + 1) + 1.5; // fixed, non-degenerate start
        }
        $v = $this->unit($v);

        $lambda = 0.0;
        for ($iter = 0; $iter < 200; $iter++) {
            $w = array_fill(0, $n, 0.0);
            for ($i = 0; $i < $n; $i++) {
                $s = 0.0;
                $row = $M[$i];
                for ($j = 0; $j < $n; $j++) {
                    $s += $row[$j] * $v[$j];
                }
                $w[$i] = $s;
            }
            $newLambda = 0.0;
            foreach ($v as $i => $x) {
                $newLambda += $x * $w[$i];
            }
            $w = $this->unit($w);
            $converged = abs($newLambda - $lambda) < 1e-9;
            $v = $w;
            $lambda = $newLambda;
            if ($converged) {
                break;
            }
        }

        return [$lambda, $v];
    }

    private function unit(array $v): array
    {
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));

        return $norm > 0 ? array_map(fn ($x) => $x / $norm, $v) : $v;
    }

    private function findMisfiled(array $vec, $byId, $folders): array
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

        $out = [];
        foreach ($vec as $fileId => $v) {
            $file = $byId[$fileId];
            $own = $file->folder_id;

            // Need a real reference set: at least 2 OTHER files in its folder.
            if (($members[$own] ?? 0) < 3) {
                continue;
            }

            // Own folder centroid WITHOUT this file, so it isn't compared to itself.
            $ownCentroid = $sum[$own];
            foreach ($v as $term => $w) {
                $ownCentroid[$term] = ($ownCentroid[$term] ?? 0) - $w;
            }
            $ownSim = $this->vectors->dot($v, $this->vectors->normalize($ownCentroid));

            $bestFolder = null;
            $bestSim = 0.0;
            foreach ($sum as $folderId => $centroid) {
                if ($folderId === $own || ($members[$folderId] ?? 0) < 2) {
                    continue;
                }
                $sim = $this->vectors->dot($v, $this->vectors->normalize($centroid));
                if ($sim > $bestSim) {
                    $bestSim = $sim;
                    $bestFolder = $folderId;
                }
            }

            if ($bestFolder !== null
                && $bestSim >= self::MISFILED_MIN_SIMILARITY
                && $bestSim - $ownSim >= self::MISFILED_MARGIN) {
                $out[] = [
                    'file'        => $this->fileRow($file, $folders),
                    'suggested'   => ['id' => $bestFolder, 'name' => $folders[$bestFolder]->name ?? 'Unknown'],
                    'own_match'   => (int) round(max(0, $ownSim) * 100),
                    'other_match' => (int) round($bestSim * 100),
                    'gap'         => $bestSim - $ownSim,
                ];
            }
        }

        usort($out, fn ($x, $y) => $y['gap'] <=> $x['gap']);

        return array_slice($out, 0, 8);
    }

    /** Top distinguishing terms per folder (highest summed TF-IDF weight). */
    private function folderTopics(array $vec, $byId, $folders): array
    {
        $sum = [];
        $members = [];
        foreach ($vec as $fileId => $v) {
            $folderId = $byId[$fileId]->folder_id;
            $members[$folderId] = ($members[$folderId] ?? 0) + 1;
            foreach ($v as $term => $w) {
                $sum[$folderId][$term] = ($sum[$folderId][$term] ?? 0) + $w;
            }
        }

        $topics = [];
        foreach ($sum as $folderId => $terms) {
            arsort($terms);
            $topics[] = [
                'folder' => $folders[$folderId]->name ?? 'Unknown',
                'files'  => $members[$folderId],
                'terms'  => array_slice(array_keys($terms), 0, 5),
            ];
        }

        usort($topics, fn ($a, $b) => $b['files'] <=> $a['files']);

        return array_slice($topics, 0, 6);
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
