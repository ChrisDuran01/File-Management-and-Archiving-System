<?php

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function titlesSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** The tab title as the browser shows it: <title> with its whitespace collapsed. */
function tabTitle(string $html): string
{
    preg_match('~<title>(.*?)</title>~s', $html, $match);

    return html_entity_decode(preg_replace('/\s+/', ' ', trim($match[1] ?? '')), ENT_QUOTES);
}

/** Real <h1> elements - not the text "<h1>" inside a style or script. */
function h1Count(string $html): int
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    return $dom->getElementsByTagName('h1')->length;
}

test('each officer page has its own tab title and one main heading', function (string $url, string $title) {
    $html = $this->actingAs(titlesSuperAdmin())->get($url)->assertOk()->getContent();

    expect(tabTitle($html))->toBe($title)
        ->and(h1Count($html))->toBe(1);
})->with([
    // The SuperAdmin dashboard uses MySQL's DATE_FORMAT, so it can't run on
    // the tests' SQLite - checked on the real database instead.
    'Admin dashboard'      => ['/adminDashboard', 'Dashboard · QSU-FMAS'],
    'Folders'              => ['/folders', 'Folders · QSU-FMAS'],
    'Documents'            => ['/documents', 'Documents · QSU-FMAS'],
    'Scan Inbox'           => ['/documents/scans', 'Scan Inbox · QSU-FMAS'],
    'Archives'             => ['/archives', 'Archives · QSU-FMAS'],
    'Trash'                => ['/trash', 'Trash · QSU-FMAS'],
    'Templates'            => ['/templates', 'Document Templates · QSU-FMAS'],
    'Letterheads'          => ['/letterheads', 'Letterheads · QSU-FMAS'],
    'Announcements'        => ['/announcements', 'Announcements · QSU-FMAS'],
    'Site Content'         => ['/site-settings', 'Site Content · QSU-FMAS'],
    'Profile'              => ['/profile', 'My Profile · QSU-FMAS'],
    'SG Officers'          => ['/manageAdmins', 'SG Officers · QSU-FMAS'],
    'Add Officer'          => ['/addAdmin', 'Add Officer · QSU-FMAS'],
    'Activity Logs'        => ['/activityLogs', 'Activity Logs · QSU-FMAS'],
    'Backups'              => ['/backup', 'Backup Management · QSU-FMAS'],
    'Search'               => ['/search?query=budget', 'Search: "budget" · QSU-FMAS'],
]);

test('a folder page is titled with the folder name, escaped', function () {
    $user = titlesSuperAdmin();
    $folder = Folder::create(['name' => 'Minutes <2026> & Notes', 'created_by' => $user->id]);

    $html = $this->actingAs($user)->get(route('folders.show', $folder->id))->assertOk()->getContent();

    expect(tabTitle($html))->toBe('Minutes <2026> & Notes · QSU-FMAS')
        ->and($html)->toContain('Minutes &lt;2026&gt; &amp; Notes · QSU-FMAS')
        ->and(h1Count($html))->toBe(2); // the heading, and its copy in the loading skeleton
});
