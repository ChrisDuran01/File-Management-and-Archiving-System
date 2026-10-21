<?php

use App\Models\Announcement;
use App\Models\AnnouncementImage;
use App\Models\EngagementEvent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ShareCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();          // posting queues the student push
    Notification::fake(); // and rings the other officers' bells
});

function seoAnnouncement(array $attributes = []): Announcement
{
    return Announcement::create($attributes + [
        'title' => 'Intramurals 2026 schedule',
        'body'  => '<p>Games start <strong>Monday</strong>.</p><p>Bring your ID.</p>',
    ]);
}

function seoUrl(Announcement $announcement): string
{
    return route('student.announcement', [$announcement, $announcement->slug()]);
}

/** The page's schema.org JSON-LD, decoded. */
function seoSchema(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $match);

    return json_decode($match[1] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
}

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function seoSuperAdmin(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

test('an announcement has its own page with search and link-preview tags', function () {
    $announcement = seoAnnouncement();
    $url = seoUrl($announcement);

    expect($url)->toEndWith('/stuDashboard/announcements/'.$announcement->id.'/intramurals-2026-schedule');

    $html = $this->get($url)->assertOk()
        ->assertSee('<title>Intramurals 2026 schedule | QSU Student Government</title>', false)
        ->assertSee('<meta name="description" content="Games start Monday. Bring your ID.">', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:title" content="Intramurals 2026 schedule">', false)
        ->assertDontSee('noindex')
        ->assertHeaderMissing('X-Robots-Tag')
        ->getContent();

    $article = seoSchema($html)['@graph'][0];
    expect($article['@type'])->toBe('NewsArticle')
        ->and($article['headline'])->toBe('Intramurals 2026 schedule')
        ->and($article['datePublished'])->toBe($announcement->created_at->toIso8601String())
        ->and($article['mainEntityOfPage'])->toBe($url)
        // No photo: the branded card is the picture.
        ->and($article['image'][0])->toContain('/share-card.jpg?v=');
});

test('an announcement with photos uses its first photo as the preview picture', function () {
    Storage::fake('public');
    $announcement = seoAnnouncement();
    foreach (['first', 'second'] as $i => $name) {
        $photo = UploadedFile::fake()->image("{$name}.jpg", 800 + $i, 600);
        Storage::disk('public')->putFileAs('announcements/images', $photo, "{$name}.jpg");
        AnnouncementImage::create(['announcement_id' => $announcement->id, 'path' => "announcements/images/{$name}.jpg", 'sort_order' => $i]);
    }
    $first = route('announcements.image', AnnouncementImage::orderBy('sort_order')->first()->id);

    $html = $this->get(seoUrl($announcement))->assertOk()
        ->assertSee('<meta property="og:image" content="'.$first.'">', false)
        ->assertSee('<meta property="og:image:width" content="800">', false)
        ->getContent();

    expect(seoSchema($html)['@graph'][0]['image'])->toHaveCount(2);
});

test('a missing or outdated end of the address redirects to the current one', function () {
    $announcement = seoAnnouncement();

    $this->get('/stuDashboard/announcements/'.$announcement->id)->assertStatus(301)->assertRedirect(seoUrl($announcement));
    $this->get('/stuDashboard/announcements/'.$announcement->id.'/old-title')->assertStatus(301)->assertRedirect(seoUrl($announcement));
    $this->get('/stuDashboard/announcements/999999')->assertNotFound();
});

test('"Hide from Google" asks search engines not to list it, but it still previews when shared', function () {
    $hidden = seoAnnouncement(['title' => 'Election results', 'hide_from_search' => true]);
    $listed = seoAnnouncement(['title' => 'Clean-up drive']);

    $this->get(seoUrl($hidden))->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertSee('<meta property="og:title" content="Election results">', false);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
    $sitemap = simplexml_load_string($response->getContent());
    $addresses = [];
    foreach ($sitemap->url as $entry) {
        $addresses[] = (string) $entry->loc;
    }

    expect($addresses)->toBe([route('student.dashboard'), seoUrl($listed)]);
});

test('robots.txt is served by the app and points search engines at the sitemap', function () {
    // A file in public/ would be served instead, without the Sitemap line.
    expect(file_exists(public_path('robots.txt')))->toBeFalse();

    $this->get('/robots.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Sitemap: '.route('sitemap'), false);
});

test('the student page has preview tags, organization schema and a share link for each announcement', function () {
    $announcement = seoAnnouncement();
    $card = route('share.card', ['v' => app(ShareCard::class)->version(SiteSetting::current())]);

    $html = $this->get(route('student.dashboard'))->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('student.dashboard').'">', false)
        ->assertSee('<meta property="og:image" content="'.$card.'">', false)
        ->assertSee('href="'.seoUrl($announcement).'"', false)
        ->getContent();

    expect(seoSchema($html)['@graph'][0])->toMatchArray(['@type' => 'Organization', 'name' => 'QSU Student Government']);
});

test('an announcement title cannot break out of the schema script', function () {
    $announcement = seoAnnouncement(['title' => '</script><script>alert(1)</script>']);

    $html = $this->get(seoUrl($announcement))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)')
        ->and(seoSchema($html)['@graph'][0]['headline'])->toBe('</script><script>alert(1)</script>');
});

test('officers can hide an announcement from Google and show it again', function () {
    $admin = seoSuperAdmin();

    $this->actingAs($admin)->get(route('announcements.index'))->assertOk()->assertSee('Hide from Google');

    $this->actingAs($admin)->post(route('announcements.store'), [
        'title' => 'Dean\'s listers', 'body' => '<p>Congratulations!</p>', 'hide_from_search' => '1',
    ])->assertSessionHas('success');
    $announcement = Announcement::firstWhere('title', 'Dean\'s listers');
    expect($announcement->hide_from_search)->toBeTrue();

    $this->actingAs($admin)->get(route('announcements.index'))->assertSee('Hidden from Google');

    // Unticked sends nothing at all.
    $this->actingAs($admin)->put(route('announcements.update', $announcement), [
        'title' => 'Dean\'s listers', 'body' => '<p>Congratulations!</p>',
    ])->assertSessionHas('success');
    expect($announcement->fresh()->hide_from_search)->toBeFalse();
});

test('search engine and preview crawlers are not counted in Reports', function () {
    $announcement = seoAnnouncement();
    $body = json_encode(['events' => [['type' => 'announcement_open', 'id' => $announcement->id]]]);
    $send = fn (string $agent) => $this->call('POST', route('engagement.track'), [], [], [], [
        'HTTP_USER_AGENT' => $agent, 'CONTENT_TYPE' => 'text/plain',
    ], $body);

    $send('Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    $send('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');
    expect(EngagementEvent::count())->toBe(0);

    // A phone whose name merely contains "bot" still counts.
    $send('Mozilla/5.0 (Linux; Android 10; CUBOT X30) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36');
    expect(EngagementEvent::count())->toBe(1);
});

test('the branded share card is a 1200x630 picture', function () {
    // Its own storage folder, so the test never replaces the live card.
    $storage = sys_get_temp_dir().'/sg-share-card-test-'.uniqid();
    $this->app->useStoragePath($storage);

    $response = $this->get(route('share.card', ['v' => 'anything']))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $size = getimagesize($response->baseResponse->getFile()->getPathname());

    expect([$size[0], $size[1]])->toBe([1200, 630]);

    \Illuminate\Support\Facades\File::deleteDirectory($storage);
});
