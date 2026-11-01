<?php

namespace App\Support\Documents;

use App\Support\SchoolYear;
use Carbon\Carbon;

/**
 * The SG's resolution, laid out after SG Executive Resolution No. 04-06-26
 * (2526-034): number and series, the title and author between rules, the
 * WHEREAS clauses, WHEREFORE (who moved it), the RESOLVED clauses, ADOPTED,
 * the vote, then the signatures from "Certified true and correct" up to
 * "Approved".
 */
class ResolutionDocument extends TemplateDocument
{
    public const SIGNATORY_GROUPS = [
        'certified'    => 'Certified true and correct',
        'concurred'    => 'Concurred',
        'noted'        => 'Noted',
        'recommending' => 'Recommending Approval',
        'approved'     => 'Approved',
    ];

    public static function rules(): array
    {
        return [
            'fields.kind'       => 'nullable|string|max:120',
            'fields.number'     => 'nullable|string|max:60',
            'fields.title'      => 'required|string|max:500',
            'fields.author'     => 'nullable|string|max:255',
            'fields.whereas'    => 'nullable|array|max:40',
            'fields.whereas.*'  => 'nullable|string|max:5000',
            'fields.moved_by'   => 'nullable|string|max:255',
            'fields.seconded'   => 'nullable|string|max:255',
            'fields.resolved'   => 'nullable|array|max:20',
            'fields.resolved.*' => 'nullable|string|max:5000',
            'fields.adopted_on' => 'nullable|date_format:Y-m-d',
            'fields.venue'      => 'nullable|string|max:255',
            'fields.vote'       => 'nullable|string|max:120',
            'fields.signed'     => 'nullable|boolean',
        ] + static::signatoryRules();
    }

    public static function normalize(array $input): array
    {
        return [
            'kind'        => static::text($input['kind'] ?? '', 120) ?: 'SG Executive Resolution',
            'number'      => static::text($input['number'] ?? '', 60),
            'title'       => static::text($input['title'] ?? '', 500),
            'author'      => static::text($input['author'] ?? ''),
            // A clause pasted with its lead word keeps it only once.
            'whereas'     => array_map(
                fn ($clause) => preg_replace('/^WHEREAS\b[,:;]?\s*/i', '', $clause),
                static::lines($input['whereas'] ?? [])
            ),
            'moved_by'    => static::text($input['moved_by'] ?? ''),
            'seconded'    => static::text($input['seconded'] ?? ''),
            'resolved'    => array_map(
                fn ($clause) => preg_replace('/^(NOW,?\s+THEREFORE,?\s+BE\s+IT\s+)?RESOLVED(\s+(FURTHER|FINALLY))?\b[,:;]?\s*/i', '', $clause),
                static::lines($input['resolved'] ?? [])
            ),
            'adopted_on'  => static::date($input['adopted_on'] ?? null),
            'venue'       => static::text($input['venue'] ?? ''),
            'vote'        => static::text($input['vote'] ?? '', 120),
            'signed'      => filter_var($input['signed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'signatories' => static::signatories($input['signatories'] ?? []),
        ];
    }

    public static function defaults(?array $last): array
    {
        $kept = fn (string $key, string $fallback) => static::text($last[$key] ?? '') ?: $fallback;
        $blank = fn (string ...$positions) => array_map(fn ($position) => ['name' => '', 'position' => $position], $positions);

        return [
            'kind'        => $kept('kind', 'SG Executive Resolution'),
            'number'      => '',
            'title'       => '',
            'author'      => $kept('author', 'The Office of the Student Government'),
            'whereas'     => ['', '', ''],
            'moved_by'    => '',
            'seconded'    => $kept('seconded', 'unanimously seconded by the officers'),
            'resolved'    => ['', ''],
            'adopted_on'  => Carbon::today()->format('Y-m-d'),
            'venue'       => $kept('venue', 'the SG Conference Hall, Quirino State University – Diffun Campus, Province of Quirino'),
            'vote'        => $kept('vote', 'Approved unanimously'),
            'signed'      => false,
            // The positions on the SG's April 2026 resolution.
            'signatories' => static::lastSignatories($last, [
                'certified'    => $blank('SG Executive PIO', 'SG Auditor', 'SG Executive Treasurer', 'SG Secretary', 'Vice President, Student Government'),
                'concurred'    => $blank('President, Student Government'),
                'noted'        => $blank('Adviser, Student Government', 'Director for Student Affairs and Services'),
                'recommending' => $blank('Vice President for Administration & Finance'),
                'approved'     => $blank('University President'),
            ]),
        ];
    }

    public static function copyOf(array $fields): array
    {
        return array_merge(parent::copyOf($fields), [
            'number'     => '',
            'adopted_on' => Carbon::today()->format('Y-m-d'),
        ]);
    }

    public static function filename(array $fields): string
    {
        return static::safeFilename($fields['number'] !== ''
            ? $fields['kind'].' No. '.$fields['number']
            : 'Resolution - '.$fields['title']);
    }

    public static function title(array $fields): string
    {
        return $fields['title'];
    }

    public static function view(): string
    {
        return 'Admin.templates.documents.resolution';
    }

    public static function form(): string
    {
        return 'Admin.templates.forms.resolution';
    }

    /** "2026" from the adoption date (this year without one). */
    public static function series(?string $adoptedOn): string
    {
        return (string) ($adoptedOn ? Carbon::parse($adoptedOn)->year : Carbon::now()->year);
    }

    /** "2526" for school year 2025-2026, as the SG writes it in the number. */
    public static function schoolYearCode(Carbon $date): string
    {
        $year = SchoolYear::of($date);

        return substr($year, 2, 2).substr($year, 7, 2);
    }

    /**
     * Highest running number used in each school year, from resolution
     * numbers like "04-06-26 (2526-034)" => ['2526' => 34].
     *
     * @param  iterable<string>  $numbers
     * @return array<string, int>
     */
    public static function sequences(iterable $numbers): array
    {
        $highest = [];
        foreach ($numbers as $number) {
            if (is_string($number) && preg_match('/\((\d{4})-(\d{1,4})\)/', $number, $m)) {
                $highest[$m[1]] = max($highest[$m[1]] ?? 0, (int) $m[2]);
            }
        }

        return $highest;
    }

    /**
     * The next number: the adoption date as MM-DD-YY, then the school year
     * and the next running number in it - "04-06-26 (2526-034)" is the 34th
     * resolution of 2025-2026, adopted April 6, 2026.
     */
    public static function suggestNumber(?string $adoptedOn, array $sequences): string
    {
        $date = $adoptedOn ? Carbon::parse($adoptedOn) : Carbon::today();
        $code = static::schoolYearCode($date);

        return $date->format('m-d-y').' ('.$code.'-'.str_pad((string) (($sequences[$code] ?? 0) + 1), 3, '0', STR_PAD_LEFT).')';
    }

    /** The lead word printed before RESOLVED clause $i of $count. */
    public static function resolvedLead(int $i, int $count): string
    {
        if ($i === 0) {
            return 'RESOLVED';
        }

        return ($i === $count - 1 && $count >= 3) ? 'RESOLVED FINALLY' : 'RESOLVED FURTHER';
    }

    /** A filled-in resolution for the letterhead preview - never saved. */
    public static function sample(): array
    {
        $fields = static::defaults(null);

        return array_merge($fields, [
            'number'   => '00-00-00 (0000-000)',
            'title'    => 'A sample resolution showing how this letterhead looks',
            'whereas'  => [
                'this is placeholder text standing in for a real resolution, shown only so you can see the letterhead around an actual document;',
                'the text of a resolution can run to a second or third page, and the letterhead appears on every page;',
            ],
            'moved_by' => 'Juan Dela Cruz, SG Senator',
            'resolved' => ['as it is hereby resolved, that this preview is not saved anywhere.'],
        ]);
    }
}
