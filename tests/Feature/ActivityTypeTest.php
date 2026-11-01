<?php

use App\Models\ActivityLog;
use App\Support\ActivityType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * How every message the code logs starts, as [text, file]: the leading text
 * of the 'activity' value in each ActivityLog::create([...]), of the message
 * given to a controller's own log($request, ...) helper, and of an $activity
 * variable. Both branches count when the value starts with "$condition ? :".
 */
function loggedMessageStarts(): array
{
    $found = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $tokens = array_values(array_filter(
            token_get_all(file_get_contents($file->getPathname())),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
        $text = fn (int $i) => is_array($tokens[$i] ?? null) ? $tokens[$i][1] : ($tokens[$i] ?? null);

        foreach (array_keys($tokens) as $i) {
            $at = match (true) {
                $text($i) === "'activity'" && $text($i + 1) === '=>' && insideActivityLogCreate($tokens, $i) => $i + 2,
                $text($i) === '$this' && $text($i + 1) === '->' && $text($i + 2) === 'log' && $text($i + 3) === '('
                    && $text($i + 4) === '$request' && $text($i + 5) === ',' => $i + 6,
                $text($i) === '$activity' && $text($i + 1) === '=' => $i + 2,
                default => null,
            };

            foreach ($at === null ? [] : leadingTexts($tokens, $at) as $start) {
                $found[] = [$start, $file->getFilename()];
            }
        }
    }

    return $found;
}

/** Whether the array key at $i sits directly in ActivityLog::create([ ... ]). */
function insideActivityLogCreate(array $tokens, int $i): bool
{
    $text = fn (int $n) => is_array($tokens[$n] ?? null) ? $tokens[$n][1] : ($tokens[$n] ?? null);

    for ($depth = 0, $j = $i - 1; $j >= 0; $j--) {
        $t = $text($j);
        if ($t === ']' || $t === ')') {
            $depth++;
        } elseif ($t === '[' || $t === '(') {
            if ($depth-- > 0) {
                continue;
            }

            return $t === '[' && $text($j - 1) === '(' && $text($j - 2) === 'create'
                && $text($j - 3) === '::' && $text($j - 4) === 'ActivityLog';
        }
    }

    return false;
}

/** The text an expression's value starts with (both branches of a leading "? :"). */
function leadingTexts(array $tokens, int $at): array
{
    $expr = [];
    for ($depth = 0, $n = $at; isset($tokens[$n]); $n++) {
        $t = is_array($tokens[$n]) ? $tokens[$n][1] : $tokens[$n];
        if ($t === '(' || $t === '[') {
            $depth++;
        } elseif (($t === ')' || $t === ']') && --$depth < 0) {
            break;
        } elseif ($depth === 0 && ($t === ',' || $t === ';')) {
            break;
        }
        $expr[] = $tokens[$n];
    }

    $string = function (int $k) use ($expr): ?string {
        $t = $expr[$k] ?? null;
        if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
            return stripslashes(substr($t[1], 1, -1));
        }
        if ($t === '"') {
            $next = $expr[$k + 1] ?? null;

            return is_array($next) && $next[0] === T_ENCAPSED_AND_WHITESPACE ? $next[1] : '';
        }

        return null;
    };

    $first = 0;
    while (($expr[$first] ?? null) === '(') {
        $first++;
    }
    if (($start = $string($first)) !== null) {
        return [$start];
    }

    $question = array_search('?', $expr, true);
    if ($question === false) {
        return [];
    }
    $colon = array_search(':', array_slice($expr, $question, null, true), true);

    return array_values(array_filter([$string($question + 1), $colon === false ? null : $string($colon + 1)], fn ($s) => $s !== null));
}

/** A message start that has a type - or that is cut short, like "Restricted" . ' folder: '. */
function hasAType(string $start): bool
{
    if (trim($start) === '' || ActivityType::of($start) !== ActivityType::OTHER) {
        return true;
    }

    foreach (ActivityType::TYPES as $type) {
        foreach ($type['starts'] as $known) {
            if (str_starts_with(mb_strtolower($known), mb_strtolower($start))) {
                return true;
            }
        }
    }

    return false;
}

test('every message the code logs has a type', function () {
    $found = loggedMessageStarts();

    // The scan still finds the messages (about 90 on 2026-10-30), and the
    // check still turns away a message nobody sorted.
    expect(count($found))->toBeGreaterThan(75)
        ->and(hasAType('Exported the logs'))->toBeFalse()
        ->and(hasAType('Restricted'))->toBeTrue();

    $untyped = collect($found)
        ->reject(fn (array $message) => hasAType($message[0]))
        ->map(fn (array $message) => $message[1] . ': "' . $message[0] . '"')
        ->values()
        ->all();

    expect($untyped)->toBe([], 'Add how these messages start to App\Support\ActivityType');
});

test('the filter in the database and the type of one entry agree', function () {
    $samples = [
        'Logged in', 'Logged out (inactivity)', 'Failed login attempt', 'Login blocked - too many failed attempts',
        'Uploaded 1 file(s): announcement.pdf', 'Made a share link for file: a.pdf (works for 7 days)',
        'Restricted folder: Minutes', 'Moved folder to Trash: Minutes', 'Restored from Trash: folder Minutes',
        'Archived folder: Minutes', 'Restored folder: Minutes', 'Downloaded archive: Minutes',
        'Moved activity proposal "Fun Run" to Trash', 'Filed scan batch #3 into "Minutes" as 2 document(s): x',
        'Posted announcement: Foundation Day', 'Added display-only officer Ana (Auditor) to site content',
        'Ended 2025-2026 officer term (moved to former, folders/files stored)', 'Added officer: Ana',
        'Something nobody sorted',
    ];
    foreach ($samples as $text) {
        ActivityLog::create(['user_name' => 'Tester', 'activity' => $text, 'ip_address' => '10.0.0.1']);
    }

    foreach (array_keys(ActivityType::options()) as $key) {
        $inDatabase = ActivityType::scope(ActivityLog::query(), $key)->pluck('activity')->sort()->values()->all();
        $inPhp = collect($samples)->filter(fn ($text) => ActivityType::of($text) === $key)->sort()->values()->all();

        expect($inDatabase)->toBe($inPhp, "type {$key}");
    }

    expect(ActivityType::of('Uploaded 1 file(s): announcement.pdf'))->toBe('files')
        ->and(ActivityType::of('Archived folder: Minutes'))->toBe('backups')
        ->and(ActivityType::of('Restricted folder: Minutes'))->toBe('folders')
        ->and(ActivityType::of('Something nobody sorted'))->toBe(ActivityType::OTHER);
});

test('no start has a LIKE wildcard in it', function () {
    $starts = collect(ActivityType::TYPES)->flatMap(fn (array $type) => $type['starts']);

    expect($starts->filter(fn (string $start) => strpbrk($start, '%_\\') !== false)->all())->toBe([]);
});
