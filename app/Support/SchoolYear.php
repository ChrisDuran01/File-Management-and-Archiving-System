<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\File;
use App\Models\Folder;
use App\Models\OfficerTerm;
use Carbon\Carbon;

/**
 * School years ("2026-2027") as date ranges. A school year starts on the
 * 1st of config('school.year_start_month') and ends the day before the next
 * one starts, so every date belongs to exactly one school year.
 */
class SchoolYear
{
    public static function startMonth(): int
    {
        $month = (int) config('school.year_start_month', 8);

        return $month >= 1 && $month <= 12 ? $month : 8;
    }

    /** The school year a date falls in, e.g. "2026-2027". */
    public static function of(Carbon $date): string
    {
        $startYear = $date->month >= self::startMonth() ? $date->year : $date->year - 1;

        return $startYear.'-'.($startYear + 1);
    }

    public static function current(): string
    {
        return self::of(Carbon::now());
    }

    public static function isValid(?string $label): bool
    {
        return is_string($label)
            && preg_match('/^(\d{4})-(\d{4})$/', $label, $m) === 1
            && (int) $m[2] === (int) $m[1] + 1;
    }

    /** @return array{0: Carbon, 1: Carbon} first moment to last moment of the school year */
    public static function range(string $label): array
    {
        $start = Carbon::create((int) substr($label, 0, 4), self::startMonth(), 1)->startOfDay();

        return [$start, $start->copy()->addYear()->subDay()->endOfDay()];
    }

    /** "2026-2027" -> "2025-2026" */
    public static function previous(string $label): string
    {
        $startYear = (int) substr($label, 0, 4) - 1;

        return $startYear.'-'.($startYear + 1);
    }

    /**
     * School years worth offering in a filter, newest first: every one from
     * the oldest record in the system up to now, plus any the records name
     * (an officer term or folder labelled with a school year).
     *
     * @return string[]
     */
    public static function options(): array
    {
        $now = Carbon::now();
        $oldest = collect([File::min('created_at'), ActivityLog::min('created_at')])->filter()->min();
        $cursor = $oldest ? Carbon::parse($oldest) : $now->copy();

        $years = [];
        while ($cursor->lte($now) && count($years) < 20) {
            $years[] = self::of($cursor);
            $cursor->addYear();
        }
        $years[] = self::current();

        return collect($years)
            ->merge(OfficerTerm::whereNotNull('school_year')->distinct()->pluck('school_year'))
            ->merge(Folder::whereNotNull('school_year')->distinct()->pluck('school_year'))
            ->filter(fn ($label) => self::isValid($label) && self::range($label)[0]->lte($now))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
