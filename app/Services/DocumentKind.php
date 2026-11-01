<?php

namespace App\Services;

/**
 * What kind of document a file is, read from its title - the first lines
 * of its text - and which kinds a folder is for, read from its name.
 *
 * Word counts (TextVectors) tell what a document is ABOUT; the SG's folders
 * are sorted by what KIND of document it is. A resolution about student
 * councils uses the words of the Constitution and By-Laws, but its title
 * still says "A RESOLUTION". So the title decides when there is one.
 */
class DocumentKind
{
    /** The title is within the first lines, after the letterhead. */
    private const TITLE_LINES = 10;

    /**
     * kind => label, title pattern (matched at the start of a line, at most
     * three words in front, e.g. "SG Executive Resolution No."), and the
     * folder-name pattern of the folder it belongs in.
     */
    private const KINDS = [
        'resolution' => [
            'label'  => 'Resolution',
            'title'  => '/^(?:[\p{L}.\-]+\s+){0,3}RESOLUTION\s+(?:NO\b|NUMBER\b)|^A\s+RESOLUTION\b/iu',
            'folder' => '/\bRESOLUTIONS?\b|\bRESO\b/i',
        ],
        'minutes' => [
            'label'  => 'Minutes',
            'title'  => '/^(?:MINUTES\s+OF|KATITIKAN\s+NG)\b/iu',
            'folder' => '/\bMINUTES\b|\bMOTM\b|\bMOM\b|\bKATITIKAN\b/i',
        ],
        'report' => [
            'label'  => 'Report',
            'title'  => '/^(?:[\p{L}\-]+\s+){0,3}REPORTS?\b/iu',
            'folder' => '/\bREPORTS?\b/i',
        ],
        'cbl' => [
            'label'  => 'Constitution and By-Laws',
            'title'  => '/^(?:[\p{L}\p{N}\-]+\s+){0,3}CONSTITUTION\s+AND\s+BY[\s\-]?LAWS\b/iu',
            'folder' => '/\bCBL\b|CONSTITUTION|BY[\s\-]?LAWS/i',
        ],
        'action_plan' => [
            'label'  => 'Plan of Action',
            'title'  => '/^(?:[\p{L}\-]+\s+){0,2}(?:PLAN\s+OF\s+ACTION|ACTION\s+PLAN)\b/iu',
            'folder' => '/PLAN\s+OF\s+ACTION|ACTION\s+PLAN/i',
        ],
        'calendar' => [
            'label'  => 'Calendar of Activities',
            'title'  => '/^CALENDAR\s+OF\s+ACTIVITIES\b/iu',
            'folder' => '/\bCALENDAR\b/i',
        ],
        'purchase' => [
            'label'  => 'Purchase request or order',
            'title'  => '/^PURCHASE\s+(?:REQUEST|ORDER)\b/iu',
            'folder' => '/\bPURCHASE\b|\bPR\s*(?:&|AND|\/)\s*PO\b/i',
        ],
        'sap' => [
            'label'  => 'Student Assistance Program',
            'title'  => '/^STUDENT\s+ASSISTANCE\s+PROGRAM\b/iu',
            'folder' => '/STUDENT\s+ASSISTANCE|\bSAP\b/i',
        ],
        'memo' => [
            'label'  => 'Memorandum',
            'title'  => '/^MEMORANDUM\b|^MEMO\s+(?:NO\b|ORDER\b)/iu',
            'folder' => '/\bMEMO(?:S|RANDUMS?|RANDA)?\b/i',
        ],
    ];

    /**
     * The kind named by the first title line found, or null. The first one
     * wins: a report whose body lists "A Resolution ..." is still a report.
     */
    public function fromText(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $lines = preg_split('/\R/u', mb_substr($text, 0, 3000)) ?: [];
        $seen = 0;
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line === '') {
                continue;
            }
            foreach (self::KINDS as $kind => $k) {
                if (preg_match($k['title'], $line)) {
                    return $kind;
                }
            }
            if (++$seen >= self::TITLE_LINES) {
                break;
            }
        }

        return null;
    }

    /**
     * The kinds a folder is for, by its name ("(MOTM) Minutes of the
     * Meetings" => minutes). Empty for a folder named after something else
     * (an event, a school year), which may hold any kind.
     *
     * @return string[]
     */
    public function forFolderName(string $name): array
    {
        return array_keys(array_filter(self::KINDS, fn ($k) => preg_match($k['folder'], $name)));
    }

    public function label(string $kind): string
    {
        return self::KINDS[$kind]['label'] ?? ucfirst($kind);
    }
}
