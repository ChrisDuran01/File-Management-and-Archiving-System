<?php

namespace App\Support\Documents;

use Carbon\Carbon;

/**
 * One kind of document the Document Templates page builds from a form
 * (a resolution, a request letter). Each kind says which fields it takes,
 * how to clean what was typed, what a new one starts with, and which views
 * draw its form and its pages. What was typed is kept with the PDF
 * (files.template_fields), so a document can be opened again and fixed.
 */
abstract class TemplateDocument
{
    /** Signature groups in the order they are printed: key => label. */
    public const SIGNATORY_GROUPS = [];

    /** Validation rules for the request's fields.* when generating. */
    abstract public static function rules(): array;

    /** What was typed, cleaned into the shape that is stored and drawn. */
    abstract public static function normalize(array $input): array;

    /** A new document's starting values; $last is the last one made of this kind. */
    abstract public static function defaults(?array $last): array;

    /** Name of the saved PDF, without ".pdf". */
    abstract public static function filename(array $fields): string;

    /** Short name for the activity log and the folder suggestion. */
    abstract public static function title(array $fields): string;

    /** Blade view drawing the document itself (PDF and live preview). */
    abstract public static function view(): string;

    /** Blade view drawing the fill-in form. */
    abstract public static function form(): string;

    /**
     * Starting values for "Copy": the old document with what belongs to a
     * single document (its date, its "(SGD)") reset.
     */
    public static function copyOf(array $fields): array
    {
        return array_merge(static::normalize($fields), ['signed' => false]);
    }

    protected static function text(mixed $value, int $max = 255): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $value = trim(preg_replace('/\R/u', "\n", (string) $value));

        return mb_substr($value, 0, $max);
    }

    /** @return string[] the non-empty entries of a list of text boxes */
    protected static function lines(mixed $values, int $max = 5000): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($value) => static::text($value, $max), $values),
            fn ($value) => $value !== ''
        ));
    }

    protected static function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, array{name: string, position: string}> rows with a name or a position */
    protected static function people(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $people = [];
        foreach ($rows as $row) {
            $person = [
                'name'     => static::text(is_array($row) ? ($row['name'] ?? '') : '', 120),
                'position' => static::text(is_array($row) ? ($row['position'] ?? '') : '', 300),
            ];
            if ($person['name'] !== '' || $person['position'] !== '') {
                $people[] = $person;
            }
        }

        return $people;
    }

    /** @return array<string, array> every signature group of this kind, cleaned */
    protected static function signatories(mixed $groups): array
    {
        $clean = [];
        foreach (array_keys(static::SIGNATORY_GROUPS) as $key) {
            $clean[$key] = static::people(is_array($groups) ? ($groups[$key] ?? []) : []);
        }

        return $clean;
    }

    /** The last document's signatories when it had any names, else $fallback. */
    protected static function lastSignatories(?array $last, array $fallback): array
    {
        $groups = static::signatories($last['signatories'] ?? []);
        $named = collect($groups)->flatten(1)->contains(fn ($person) => $person['name'] !== '');

        return $named ? $groups : $fallback;
    }

    /** Validation rules shared by every kind's signature groups. */
    protected static function signatoryRules(string $prefix = 'fields.signatories'): array
    {
        return [
            $prefix                => 'nullable|array',
            $prefix.'.*'           => 'nullable|array|max:12',
            $prefix.'.*.*.name'    => 'nullable|string|max:120',
            $prefix.'.*.*.position' => 'nullable|string|max:300',
        ];
    }

    /** The groups with at least one person, ready for the signatories view. */
    public static function signatoryGroups(array $fields): array
    {
        $groups = [];
        foreach (static::SIGNATORY_GROUPS as $key => $label) {
            if (! empty($fields['signatories'][$key])) {
                $groups[$key] = ['label' => $label, 'people' => $fields['signatories'][$key]];
            }
        }

        return $groups;
    }

    /**
     * Typed text made safe for the page, with **double asterisks** around
     * words making them bold - as the SG bolds a phrase inside a clause.
     */
    public static function inline(string $text): string
    {
        return preg_replace('/\*\*(.+?)\*\*/su', '<b>$1</b>', e($text));
    }

    /** "6<sup>th</sup> day of April 2026" (HTML), or '' without a date. */
    public static function dayPhrase(?string $date): string
    {
        if (! $date) {
            return '';
        }

        $day = Carbon::parse($date);
        $n = $day->day;
        $suffix = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n.'<sup>'.$suffix.'</sup> day of '.$day->format('F Y');
    }

    /** "October 22, 2026", or '' without a date. */
    public static function longDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('F j, Y') : '';
    }

    /** @return string[] the lines of a multi-line box, blank ones dropped */
    public static function splitLines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $text)), fn ($line) => $line !== ''));
    }

    /** Characters a file name can't have, and a length the Files list can show. */
    protected static function safeFilename(string $name): string
    {
        $name = trim(preg_replace('/[\\\\\/:*?"<>|]+/', '-', preg_replace('/\s+/u', ' ', $name)));

        return mb_substr($name, 0, 150) ?: 'Document';
    }
}
