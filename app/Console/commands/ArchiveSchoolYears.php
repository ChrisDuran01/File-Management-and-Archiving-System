<?php

namespace App\Console\Commands;

use App\Services\SchoolYearArchiver;
use Illuminate\Console\Command;

/**
 * The retention rule: a school year is archived as a whole
 * config('archive.retention_years') after it ends. Runs nightly
 * (routes/console.php). Replaced folders:archive-stale, which archived
 * single folders that nobody had opened for 3 years.
 */
class ArchiveSchoolYears extends Command
{
    protected $signature = 'archives:school-years {--dry-run : List what would be archived without archiving it}';

    protected $description = 'Archive every school year that ended long enough ago (config archive.retention_years)';

    public function handle(SchoolYearArchiver $archiver): int
    {
        $years = SchoolYearArchiver::retentionYears();
        $due = $archiver->due();

        if ($due->isEmpty()) {
            $this->info("No school year is due: none ended {$years}+ year(s) ago with records still waiting.");

            foreach ($archiver->waiting() as $year) {
                $this->line(" - SY {$year['school_year']}: {$year['folders']} folder(s), {$year['files']} file(s), "
                    . ($year['held'] ? 'restored, waits for someone to archive it again'
                        : ($year['still_active'] ? 'End Term not run yet'
                        : 'due ' . $year['due_on']->format('M j, Y'))));
            }

            return self::SUCCESS;
        }

        foreach ($due as $year) {
            $this->line(($this->option('dry-run') ? '[DRY RUN] ' : '') . "SY {$year['school_year']}: {$year['folders']} folder(s), {$year['files']} file(s), due {$year['due_on']->format('M j, Y')}");

            if ($this->option('dry-run')) {
                continue;
            }

            $result = $archiver->archive($year['school_year'], 'System (Retention Policy)', 'automatic');
            $this->info("   -> archived {$result['folders']} folder(s), {$result['files']} file(s)");

            foreach ($result['failed'] as $failure) {
                $this->warn("   -> not archived: {$failure}");
            }
        }

        return self::SUCCESS;
    }
}
