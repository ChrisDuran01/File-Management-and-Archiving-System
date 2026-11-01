<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The kinds of action in Activity Logs, for its "Type" filter.
 *
 * A log entry has no type column, only its text ("Uploaded 2 file(s): ..."),
 * so each type is a list of words an entry's text starts with. An entry
 * belongs to the first type, in the order below, that one of its starts
 * matches, and to "Other" when none does. Starts, not "contains": a file
 * named "announcement.pdf" must not make its upload a Student page entry.
 *
 * The same lists filter in SQL (scope) and label a single entry in PHP (of),
 * so the two never disagree. A new kind of log message needs its start here,
 * or it only shows under "All types" - ActivityTypeTest checks every message
 * the code writes. Starts must not contain the LIKE wildcards % or _.
 */
class ActivityType
{
    public const OTHER = 'other';

    public const TYPES = [
        'sign-ins' => ['label' => 'Sign-ins', 'starts' => [
            'Logged in', 'Logged out', 'Changed password', 'Reset password',
            'Requested password reset', 'Acknowledged privacy notice', 'Updated profile',
        ]],
        'security' => ['label' => 'Security', 'starts' => [
            'Failed login', 'Login blocked', 'Login attempt on disabled',
        ]],
        'files' => ['label' => 'Files', 'starts' => [
            'Uploaded', 'Cancelled upload', 'Renamed file', 'Downloaded file', 'Deleted file', 'Moved file to Trash',
            'Changed access for file', 'Marked ', 'Made ', 'Removed the final mark',
            'Turned off a share link', 'Sent a copy of a file', 'Kept file offline', 'Saved ',
            'Duplicate check', 'Updated duplicate check',
            'Restored from Trash', 'Permanently deleted from Trash', 'Emptied the Trash', 'Trash auto-purge',
        ]],
        'folders' => ['label' => 'Folders', 'starts' => [
            'Added folder', 'Renamed folder', 'Restricted folder', 'Unrestricted folder',
            'Moved folder to Trash', 'Deleted folder',
        ]],
        'documents' => ['label' => 'Documents', 'starts' => [
            'Generated document', 'Edited document', 'Updated document', 'Moved document to Trash',
            'Created activity proposal', 'Updated activity proposal', 'Updated status of activity proposal',
            'Copied activity proposal', 'Moved activity proposal', 'Deleted activity proposal',
            'Updated price check settings',
            'Added letterhead', 'Updated letterhead', 'Set the default letterhead', 'Copied letterhead', 'Deleted letterhead',
            'Ingested scan batch', 'Filed scan batch', 'Discarded scan batch', 'Re-processing scan batch', 'Scan batch', 'Pruned ',
        ]],
        'student-page' => ['label' => 'Student page', 'starts' => [
            'Posted announcement', 'Updated announcement', 'Deleted announcement', 'Updated site content',
            'Added display-only officer', 'Removed display-only officer', 'Shrunk ',
        ]],
        'officers' => ['label' => 'Officers', 'starts' => [
            'Added officer', 'Updated officer', 'Archived officer', 'Reactivated officer', 'Deleted officer',
            'Ended ', 'Started new school year',
        ]],
        'backups' => ['label' => 'Backups & archives', 'starts' => [
            'Created backup', 'Downloaded backup', 'Restored system', 'Turned automatic backup', 'Changed backup',
            'Archived school year', 'Restored school year',
            'Archived folder', 'Auto-archived', 'Restored folder', 'Downloaded archive', 'Deleted archive',
            'Copied files from Supabase', 'Log retention purge',
        ]],
    ];

    /** @return array<string, string> key => label, in menu order, "Other" last */
    public static function options(): array
    {
        return array_map(fn (array $type) => $type['label'], self::TYPES) + [self::OTHER => 'Other'];
    }

    public static function isValid(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::options());
    }

    /** The type key of one log entry's text. */
    public static function of(string $activity): string
    {
        $text = mb_strtolower($activity);

        foreach (self::TYPES as $key => $type) {
            foreach ($type['starts'] as $start) {
                if (str_starts_with($text, mb_strtolower($start))) {
                    return $key;
                }
            }
        }

        return self::OTHER;
    }

    /** Narrows a query on activity_logs to one type - the SQL twin of of(). */
    public static function scope(Builder $query, string $key): Builder
    {
        $earlier = [];

        foreach (self::TYPES as $typeKey => $type) {
            if ($typeKey === $key) {
                return $query
                    ->where(fn (Builder $match) => self::startsWithAny($match, $type['starts']))
                    ->when($earlier, fn (Builder $q) => $q->whereNot(fn (Builder $match) => self::startsWithAny($match, $earlier)));
            }
            $earlier = array_merge($earlier, $type['starts']);
        }

        return $query->whereNot(fn (Builder $match) => self::startsWithAny($match, $earlier));
    }

    private static function startsWithAny(Builder $query, array $starts): void
    {
        foreach ($starts as $start) {
            $query->orWhere('activity', 'like', $start . '%');
        }
    }
}
