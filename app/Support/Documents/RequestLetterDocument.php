<?php

namespace App\Support\Documents;

use Carbon\Carbon;

/**
 * A request letter from the SG: date, the person it is for (with "Thru"
 * when it passes through someone first), subject, salutation, the body,
 * the closing and the sender, then whoever notes or approves it.
 */
class RequestLetterDocument extends TemplateDocument
{
    public const SIGNATORY_GROUPS = [
        'sender'       => 'Signed by',
        'noted'        => 'Noted',
        'recommending' => 'Recommending Approval',
        'approved'     => 'Approved',
    ];

    public static function rules(): array
    {
        return [
            'fields.date'              => 'nullable|date_format:Y-m-d',
            'fields.recipient_name'    => 'required|string|max:255',
            'fields.recipient_details' => 'nullable|string|max:1000',
            'fields.thru'              => 'nullable|array|max:4',
            'fields.thru.*.name'       => 'nullable|string|max:120',
            'fields.thru.*.position'   => 'nullable|string|max:300',
            'fields.subject'           => 'nullable|string|max:255',
            'fields.salutation'        => 'nullable|string|max:120',
            'fields.body'              => 'required|string|max:20000',
            'fields.closing'           => 'nullable|string|max:120',
            'fields.signed'            => 'nullable|boolean',
        ] + static::signatoryRules();
    }

    public static function normalize(array $input): array
    {
        return [
            'date'              => static::date($input['date'] ?? null),
            'recipient_name'    => static::text($input['recipient_name'] ?? ''),
            'recipient_details' => static::text($input['recipient_details'] ?? '', 1000),
            'thru'              => static::people($input['thru'] ?? []),
            'subject'           => static::text($input['subject'] ?? ''),
            'salutation'        => static::text($input['salutation'] ?? '', 120),
            'body'              => static::text($input['body'] ?? '', 20000),
            'closing'           => static::text($input['closing'] ?? '', 120),
            'signed'            => filter_var($input['signed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'signatories'       => static::signatories($input['signatories'] ?? []),
        ];
    }

    public static function defaults(?array $last): array
    {
        $kept = fn (string $key, string $fallback) => static::text($last[$key] ?? '') ?: $fallback;
        $blank = fn (string ...$positions) => array_map(fn ($position) => ['name' => '', 'position' => $position], $positions);

        return [
            'date'              => Carbon::today()->format('Y-m-d'),
            'recipient_name'    => '',
            'recipient_details' => '',
            'thru'              => [],
            'subject'           => '',
            'salutation'        => 'Dear Sir/Madam:',
            'body'              => '',
            'closing'           => $kept('closing', 'Respectfully yours,'),
            'signed'            => false,
            'signatories'       => static::lastSignatories($last, [
                'sender'       => $blank('President, Student Government'),
                'noted'        => $blank('Adviser, Student Government'),
                'recommending' => [],
                'approved'     => [],
            ]),
        ];
    }

    public static function copyOf(array $fields): array
    {
        return array_merge(parent::copyOf($fields), ['date' => Carbon::today()->format('Y-m-d')]);
    }

    public static function filename(array $fields): string
    {
        return static::safeFilename('Request letter - '.static::title($fields));
    }

    public static function title(array $fields): string
    {
        return $fields['subject'] !== '' ? $fields['subject'] : 'to '.$fields['recipient_name'];
    }

    public static function view(): string
    {
        return 'Admin.templates.documents.request-letter';
    }

    public static function form(): string
    {
        return 'Admin.templates.forms.request-letter';
    }

    /** @return string[] the body's paragraphs: each new line starts one */
    public static function paragraphs(string $body): array
    {
        return static::splitLines($body);
    }
}
