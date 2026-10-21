<?php

use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Favicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Its own storage folder, so the tests never replace the live icon.
    $this->storage = sys_get_temp_dir().'/sg-favicon-test-'.uniqid();
    $this->app->useStoragePath($this->storage);
});

afterEach(function () {
    File::deleteDirectory($this->storage);
});

/** @return array<int, string> each image in a .ico file, keyed by its width */
function icoImages(string $ico): array
{
    $header = unpack('vreserved/vtype/vcount', $ico);
    expect($header['reserved'])->toBe(0)->and($header['type'])->toBe(1);

    $images = [];
    for ($i = 0; $i < $header['count']; $i++) {
        $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vlength/Voffset', $ico, 6 + 16 * $i);
        $images[$entry['width']] = substr($ico, $entry['offset'], $entry['length']);
    }

    return $images;
}

test('the icon is a favicon.ico with 16, 32 and 48 pixel sizes', function () {
    $response = $this->get('/favicon.ico')->assertOk()->assertHeader('Content-Type', 'image/x-icon');
    $images = icoImages(file_get_contents($response->baseResponse->getFile()->getPathname()));

    expect(array_keys($images))->toBe(Favicon::SIZES);
    foreach ($images as $size => $png) {
        $info = getimagesizefromstring($png);
        expect([$info[0], $info[1], $info['mime']])->toBe([$size, $size, 'image/png']);
    }
});

test('the logo is cut round: clear corners, solid middle', function () {
    $response = $this->get('/favicon.ico')->assertOk();
    $icon = imagecreatefromstring(icoImages(file_get_contents($response->baseResponse->getFile()->getPathname()))[48]);

    $alpha = fn (int $x, int $y) => (imagecolorat($icon, $x, $y) >> 24) & 0x7F;

    expect($alpha(0, 0))->toBe(127)
        ->and($alpha(47, 47))->toBe(127)
        ->and($alpha(24, 24))->toBe(0);
});

test('every page links the icon with a version that changes with the logo', function () {
    Storage::fake('public');

    $before = app(Favicon::class)->version(SiteSetting::current());
    $this->get(route('login'))->assertOk()
        ->assertSee('<link rel="icon" href="'.route('favicon', ['v' => $before]).'" sizes="16x16 32x32 48x48">', false);

    $logo = UploadedFile::fake()->image('logo.png', 300, 200)->store('site', 'public');
    SiteSetting::current()->update(['logo_path' => $logo]);
    $after = app(Favicon::class)->version(SiteSetting::current());

    expect($after)->not->toBe($before);
    $this->get(route('student.dashboard'))->assertOk()
        ->assertSee('href="'.route('favicon', ['v' => $after]).'"', false);
});

test('an officer held on the change-password page still gets the icon', function () {
    $user = User::factory()->make();
    $user->forceFill(['position' => 'Officer', 'must_change_password' => true])->save();

    $this->actingAs($user)->get(route('password.change'))->assertOk();
    $this->actingAs($user)->get('/favicon.ico')->assertOk()->assertHeader('Content-Type', 'image/x-icon');
});
