<?php

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
});

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function a11ySuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** A folder holding one file, and a folder in Trash, so the per-item buttons render. */
function a11yRecords(User $user): array
{
    Storage::fake('local');
    Storage::disk('local')->put('uploads/minutes.pdf', '%PDF-1.4 test');

    $folder = Folder::create(['name' => 'Minutes', 'created_by' => $user->id]);

    $file = new File();
    $file->forceFill([
        'folder_id'    => $folder->id,
        'filename'     => 'minutes.pdf',
        'filepath'     => 'uploads/minutes.pdf',
        'local_path'   => 'uploads/minutes.pdf',
        'size'         => 2048,
        'storage_type' => 'local',
    ])->save();

    Folder::create(['name' => 'Old drafts', 'created_by' => $user->id])->delete();

    $proposal = \App\Models\ActivityProposal::create([
        'title'      => 'Leadership Training',
        'objectives' => ['Train officers'],
        'schedule'   => [['time' => '8:00 AM', 'activity' => 'Registration', 'personnel' => 'SG Secretary']],
        'signatories' => ['prepared' => [['name' => 'A. Officer', 'position' => 'SG Secretary']]],
        'created_by' => $user->id,
    ]);
    $proposal->items()->create(['category' => 'Meals', 'particular' => 'Lunch', 'quantity' => 60, 'unit' => 'pax', 'unit_price' => 60]);

    return [$folder, $file, $proposal];
}

function a11yDom(string $html): DOMXPath
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    return new DOMXPath($dom);
}

/** Text a screen reader would take from an element's content. */
function a11yText(DOMNode $node): string
{
    if ($node instanceof DOMText) {
        return $node->nodeValue;
    }
    if ($node instanceof DOMElement) {
        $tag = strtolower($node->tagName);
        if ($node->getAttribute('aria-hidden') === 'true' || in_array($tag, ['script', 'style', 'template'], true)) {
            return '';
        }
        if (trim($node->getAttribute('aria-label')) !== '') {
            return $node->getAttribute('aria-label');
        }
        if ($tag === 'img') {
            return $node->getAttribute('alt');
        }
    }

    $text = '';
    foreach ($node->childNodes as $child) {
        $text .= ' ' . a11yText($child);
    }

    return $text;
}

/**
 * The name a button or link is announced with. A lone "×" doesn't count:
 * it is read out as "times".
 */
function a11yName(DOMElement $el, DOMXPath $xp): string
{
    if ($ids = trim($el->getAttribute('aria-labelledby'))) {
        $text = '';
        foreach (preg_split('/\s+/', $ids) as $id) {
            $target = $xp->query("//*[@id='{$id}']")->item(0);
            $text .= $target ? ' ' . a11yText($target) : '';
        }
        return trim($text);
    }

    $text = trim(preg_replace('/\s+/u', ' ', a11yText($el)));
    if ($text !== '' && $text !== '×') {
        return $text;
    }

    return trim($el->getAttribute('title'));
}

/**
 * Out of reach for everyone: aria-hidden, a <template>, or a <dialog> that
 * isn't open (its buttons are filled in by script when it opens, like the
 * confirm box's OK button).
 */
function a11yHidden(DOMElement $el): bool
{
    for ($node = $el; $node instanceof DOMElement; $node = $node->parentNode) {
        $tag = strtolower($node->tagName);
        if ($node->getAttribute('aria-hidden') === 'true' || $tag === 'template'
            || ($tag === 'dialog' && ! $node->hasAttribute('open'))) {
            return true;
        }
    }

    return false;
}

/** Everything on the page that fails the three checks, as readable lines. */
function a11yProblems(string $html): array
{
    $xp = a11yDom($html);
    $problems = [];
    $describe = fn (DOMElement $el) => '<' . $el->tagName
        . ($el->getAttribute('id') ? ' id="' . $el->getAttribute('id') . '"' : '')
        . ($el->getAttribute('class') ? ' class="' . $el->getAttribute('class') . '"' : '')
        . ($el->getAttribute('name') ? ' name="' . $el->getAttribute('name') . '"' : '') . '>';

    foreach ($xp->query('//img[not(@alt)]') as $img) {
        $problems[] = 'image without alt text: ' . $describe($img);
    }

    foreach ($xp->query("//button | //a[@href] | //*[@role='button']") as $el) {
        if (! a11yHidden($el) && a11yName($el, $xp) === '') {
            $problems[] = 'control with no name: ' . $describe($el);
        }
    }

    $fields = "//input[not(@type='hidden') and not(@type='submit') and not(@type='button') and not(@type='image') and not(@hidden)]"
        . ' | //select | //textarea';
    foreach ($xp->query($fields) as $field) {
        if (a11yHidden($field)) {
            continue;
        }
        $id = $field->getAttribute('id');
        $named = trim($field->getAttribute('aria-label')) !== ''
            || trim($field->getAttribute('aria-labelledby')) !== ''
            || ($id !== '' && $xp->query("//label[@for='{$id}']")->length > 0)
            || $xp->query('ancestor::label', $field)->length > 0;
        if (! $named) {
            $problems[] = 'field with no label: ' . $describe($field);
        }
    }

    return $problems;
}

test('the checks catch each kind of problem they look for', function () {
    $html = '<html lang="en"><body>'
        . '<img src="logo.png">'                                              // no alt
        . '<button type="button"><i class="bi bi-three-dots"></i></button>'  // icon only
        . '<button type="button">&times;</button>'                          // read as "times"
        . '<label>Folder name</label><input type="text" name="name">'       // label not tied to it
        . '<button type="button" aria-label="More actions"><i></i></button>' // fine
        . '<label>Title <input name="title"></label>'                       // fine: wrapped
        . '<dialog><button type="button"></button></dialog>'                // fine: closed
        . '</body></html>';

    expect(a11yProblems($html))->toBe([
        'image without alt text: <img>',
        'control with no name: <button>',
        'control with no name: <button>',
        'field with no label: <input name="name">',
    ]);
});

test('officer pages give every image alt text, every control a name and every field a label', function (string $url) {
    $user = a11ySuperAdmin();
    [$folder, $file, $proposal] = a11yRecords($user);
    $url = str_replace(['{folder}', '{file}', '{proposal}'], [$folder->id, $file->id, $proposal->id], $url);

    $html = $this->actingAs($user)->get($url)->assertOk()->getContent();

    expect(a11yProblems($html))->toBe([]);
})->with([
    'Admin dashboard' => '/adminDashboard',
    'SuperAdmin dashboard' => '/superAdminDashboard',
    'Folders'         => '/folders',
    'Folder'          => '/folders/{folder}',
    'File preview'    => '/files/{file}/preview',
    'Documents'       => '/documents',
    'Scan Inbox'      => '/documents/scans',
    'Archives'        => '/archives',
    'Trash'           => '/trash',
    'Templates'       => '/templates',
    'Letterheads'     => '/letterheads',
    'Proposals'       => '/proposals',
    'New proposal'    => '/proposals/create',
    'Proposal'        => '/proposals/{proposal}',
    'Edit proposal'   => '/proposals/{proposal}/edit',
    'Announcements'   => '/announcements',
    'Site Content'    => '/site-settings',
    'Profile'         => '/profile',
    'SG Officers'     => '/manageAdmins',
    'Add Officer'     => '/addAdmin',
    'Activity Logs'   => '/activityLogs',
    'Backups'         => '/backup',
    'Search'          => '/search?query=minutes',
]);

test('public pages give every image alt text, every control a name and every field a label', function (string $url) {
    $html = $this->get($url)->assertOk()->getContent();

    expect(a11yProblems($html))->toBe([]);
})->with([
    'Student page'    => '/stuDashboard',
    'Sign in'         => '/login',
    'Forgot password' => '/forgot-password',
    'Privacy notice'  => '/privacy',
]);

test('both officer layouts can be used from the keyboard', function (bool $superAdmin) {
    $user = a11ySuperAdmin();
    if (! $superAdmin) {
        $positionId = \Illuminate\Support\Facades\DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
        \Illuminate\Support\Facades\DB::table('officer_terms')->insert([
            'user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active',
        ]);
    }

    $xp = a11yDom($this->actingAs($user)->get('/folders')->assertOk()->getContent());

    // "Skip to main content" first, pointing at the page's <main>.
    $skip = $xp->query('//body//a')->item(0);
    expect($skip->getAttribute('href'))->toBe('#main-content')
        ->and($xp->query("//main[@id='main-content']")->length)->toBe(1);

    // The menu toggle and the account menu are real buttons that say their state.
    $toggle = $xp->query("//button[@id='header-toggle']")->item(0);
    $account = $xp->query("//button[@id='profileToggle']")->item(0);
    expect($toggle?->getAttribute('aria-expanded'))->toBe('false')
        ->and($toggle?->getAttribute('aria-label'))->toBe('Expand menu')
        ->and($account?->getAttribute('aria-expanded'))->toBe('false');

    // Menu text stays readable to screen readers while the menu is collapsed:
    // no rule hides .nav_name with visibility (opacity only).
    $css = implode("\n", array_map(fn ($s) => $s->textContent, iterator_to_array($xp->query('//style'))));
    expect(preg_match('/\.nav_name[^{]*\{[^}]*visibility:\s*hidden/', $css))->toBe(0);
})->with(['officer layout' => false, 'SuperAdmin layout' => true]);
