<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Folder;
use App\Models\SchoolYearArchive;
use App\Support\SchoolYear;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Archives a whole school year at a time: the records of one SG
 * administration stay together. End Term stores a school year's folders
 * away (folders.is_archived), where they are read-only but still searchable;
 * this packs every one of them into the archive, one ZIP per folder
 * (FolderArchiver), so a damaged file can never cost a whole year.
 *
 * Automatically config('archive.retention_years') after the school year
 * ends (archives:school-years, nightly), or sooner by hand by the Adviser
 * or President. A restored school year (ArchiveController::restoreSchoolYear)
 * is held out of the automatic archive until someone archives it again.
 */
class SchoolYearArchiver
{
    public function __construct(private FolderArchiver $folders)
    {
    }

    public static function retentionYears(): int
    {
        return max(0, (int) config('archive.retention_years', 3));
    }

    /** The day a school year is archived automatically: N years after its last day. */
    public static function dueOn(string $schoolYear): Carbon
    {
        return SchoolYear::range($schoolYear)[1]->copy()->addSecond()->addYears(self::retentionYears());
    }

    public static function hasEnded(string $schoolYear): bool
    {
        return SchoolYear::range($schoolYear)[1]->isPast();
    }

    /**
     * Finished school years with stored-away files not yet in the archive,
     * oldest first. still_active counts that year's folders still on the
     * Folders page: End Term hasn't been run for it, so it can't be archived.
     *
     * @return Collection<int, array{school_year: string, folders: int, files: int, still_active: int, due_on: Carbon, held: bool}>
     */
    public function waiting(): Collection
    {
        $held = SchoolYearArchive::where('status', 'restored')->pluck('school_year')->all();

        return Folder::where('is_archived', true)
            ->whereNotNull('school_year')
            ->whereHas('file')
            ->withCount('file')
            ->get()
            ->groupBy('school_year')
            ->filter(fn ($folders, $schoolYear) => SchoolYear::isValid($schoolYear) && self::hasEnded($schoolYear))
            ->map(fn ($folders, $schoolYear) => [
                'school_year'  => $schoolYear,
                'folders'      => $folders->count(),
                'files'        => (int) $folders->sum('file_count'),
                'still_active' => Folder::where('is_archived', false)->where('school_year', $schoolYear)->count(),
                'due_on'       => self::dueOn($schoolYear),
                'held'         => in_array($schoolYear, $held, true),
            ])
            ->sortKeys()
            ->values();
    }

    /**
     * School years whose automatic archive date has come: finished, End
     * Term run, and not held back by a restore.
     *
     * @return Collection<int, array{school_year: string, folders: int, files: int, still_active: int, due_on: Carbon, held: bool}>
     */
    public function due(): Collection
    {
        return $this->waiting()->filter(fn (array $year) => ! $year['held']
            && $year['still_active'] === 0
            && $year['due_on']->isPast())->values();
    }

    /**
     * Why a school year can't be archived right now, or null when it can.
     */
    public function refusal(string $schoolYear): ?string
    {
        if (! SchoolYear::isValid($schoolYear)) {
            return 'That is not a school year.';
        }
        if (! self::hasEnded($schoolYear)) {
            return "SY {$schoolYear} has not ended yet.";
        }

        $year = $this->waiting()->firstWhere('school_year', $schoolYear);
        if (! $year) {
            return "Nothing from SY {$schoolYear} is waiting to be archived.";
        }
        if ($year['still_active'] > 0) {
            return "Some of SY {$schoolYear}'s folders are still on the Folders page. Run End Term for that school year first.";
        }

        return null;
    }

    /**
     * Packs every stored-away folder of the school year that has files.
     * Empty folders have nothing to keep and stay where they are.
     *
     * @param  'automatic'|'by_hand'  $how
     * @return array{folders: int, files: int, empty: int, failed: string[]}
     */
    public function archive(string $schoolYear, string $archivedBy, string $how): array
    {
        $result = ['folders' => 0, 'files' => 0, 'empty' => 0, 'failed' => []];
        $remarks = $how === 'automatic'
            ? "Part of SY {$schoolYear}, archived automatically " . self::retentionYears() . ' year(s) after it ended.'
            : "Part of SY {$schoolYear}, archived by {$archivedBy}.";

        foreach (Folder::where('is_archived', true)->where('school_year', $schoolYear)->withCount('file')->get() as $folder) {
            if ($folder->file_count === 0) {
                $result['empty']++;
                continue;
            }

            $done = $this->folders->archive($folder, $archivedBy, $how === 'automatic' ? 'school_year_auto' : 'school_year', $remarks);

            if ($done['success']) {
                $result['folders']++;
                $result['files'] += $done['archivedFilesCount'];
            } else {
                $result['failed'][] = $folder->name . ': ' . $done['message'];
            }
        }

        if ($result['folders'] > 0) {
            SchoolYearArchive::updateOrCreate(['school_year' => $schoolYear], [
                'status'       => 'archived',
                'archived_how' => $how,
                'archived_by'  => $archivedBy,
                'archived_at'  => now(),
                'restored_by'  => null,
                'restored_at'  => null,
            ]);

            ActivityLog::create([
                'user_name'  => $archivedBy,
                'activity'   => "Archived school year {$schoolYear}: {$result['folders']} folder(s), {$result['files']} file(s)"
                    . ($how === 'automatic' ? ' (automatic, ' . self::retentionYears() . ' years after it ended)' : '')
                    . ($result['failed'] ? '. Not archived: ' . implode('; ', $result['failed']) : ''),
                'ip_address' => $how === 'automatic' ? null : request()?->ip(),
            ]);
        }

        return $result;
    }
}
