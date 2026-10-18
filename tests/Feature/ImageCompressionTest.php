<?php

use App\Models\Announcement;
use App\Models\AnnouncementImage;
use App\Models\User;
use App\Services\ImageCompressor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Notification::fake();
    Storage::fake('public');
});

/** No officer_terms row at all counts as SuperAdmin - see Folder::isSuperAdmin(). */
function imageTestUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** A temp file holding $bytes, removed at the end of the test run. */
function imageTempFile(string $bytes, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'img') . '.' . $extension;
    file_put_contents($path, $bytes);
    register_shutdown_function(fn () => @unlink($path));

    return $path;
}

/** A JPEG like a phone camera's: an EXIF block with an orientation tag. */
function phoneJpeg(int $width, int $height, int $orientation): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, (int) ($width / 2), $height, imagecolorallocate($image, 30, 120, 60));
    ob_start();
    imagejpeg($image, null, 95);
    $jpeg = ob_get_clean();

    $tiff = "II\x2A\x00\x08\x00\x00\x00"           // little-endian TIFF, first IFD at 8
        . "\x01\x00"                                 // one entry
        . "\x12\x01\x03\x00\x01\x00\x00\x00" . pack('v', $orientation) . "\x00\x00" // Orientation
        . "\x00\x00\x00\x00";                        // no next IFD
    $app1 = "Exif\x00\x00" . $tiff;

    return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
}

function pngBytes(GdImage $image, int $level = -1): string
{
    imagesavealpha($image, true);
    ob_start();
    imagepng($image, null, $level);

    return ob_get_clean();
}

/** Random speckles blown up 4x: smooth, photo-like colour changes - unlike a flat test image. */
function photoLikePng(int $width, int $height): string
{
    mt_srand(7);
    $small = imagecreatetruecolor(intdiv($width, 4), intdiv($height, 4));
    for ($y = 0; $y < imagesy($small); $y++) {
        for ($x = 0; $x < imagesx($small); $x++) {
            imagesetpixel($small, $x, $y, mt_rand(0, 0xFFFFFF));
        }
    }
    $photo = imagecreatetruecolor($width, $height);
    imagecopyresampled($photo, $small, 0, 0, 0, 0, $width, $height, imagesx($small), imagesy($small));

    return pngBytes($photo);
}

/** Every pixel a random colour - like a photo's sensor grain, which PNG cannot squeeze. */
function grainyPng(int $width, int $height): string
{
    mt_srand(11);
    $image = imagecreatetruecolor($width, $height);
    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
        }
    }

    return pngBytes($image);
}

test('a phone photo is shrunk, turned upright, and loses its metadata (location included)', function () {
    $path = imageTempFile(phoneJpeg(3000, 2000, 6), 'jpg'); // 6 = rotate 90 degrees to show
    expect(exif_read_data($path)['Orientation'])->toBe(6);

    [$bytes, $extension] = app(ImageCompressor::class)->compress($path, ImageCompressor::ANNOUNCEMENT_IMAGE);

    $out = imagecreatefromstring($bytes);
    expect($extension)->toBe('jpg')
        ->and(imagesx($out))->toBe(1067)   // portrait now, longest edge 1600
        ->and(imagesy($out))->toBe(1600)
        ->and(str_contains($bytes, "Exif\x00\x00"))->toBeFalse()
        ->and(strlen($bytes))->toBeLessThan(filesize($path));
});

test('a logo with a transparent background stays a PNG and keeps the transparency', function () {
    $logo = imagecreatetruecolor(2000, 2000);
    imagealphablending($logo, false);
    imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
    imagefilledrectangle($logo, 500, 500, 1500, 1500, imagecolorallocatealpha($logo, 200, 160, 20, 0));

    [$bytes, $extension] = app(ImageCompressor::class)->compress(imageTempFile(pngBytes($logo), 'png'), ImageCompressor::LOGO);

    $out = imagecreatefromstring($bytes);
    expect($extension)->toBe('png')
        ->and(imagesx($out))->toBe(1024)
        ->and((imagecolorat($out, 5, 5) >> 24) & 0x7F)->toBe(127)    // corner still see-through
        ->and((imagecolorat($out, 512, 512) >> 24) & 0x7F)->toBe(0); // middle still solid
});

test('a photo saved as PNG becomes a much smaller JPEG', function () {
    $path = imageTempFile(photoLikePng(2000, 1500), 'png');

    [$bytes, $extension] = app(ImageCompressor::class)->compress($path, ImageCompressor::PHOTO);

    expect($extension)->toBe('jpg')
        ->and(imagesx(imagecreatefromstring($bytes)))->toBe(600)
        ->and(strlen($bytes))->toBeLessThan(filesize($path) / 4);
});

test('a flat-colour graphic (poster, screenshot) stays a sharper, smaller PNG', function () {
    $poster = imagecreatetruecolor(2000, 1500);
    imagefill($poster, 0, 0, imagecolorallocate($poster, 255, 255, 255));
    for ($x = 0; $x < 2000; $x += 4) {
        imagefilledrectangle($poster, $x, 300, $x + 1, 1200, imagecolorallocate($poster, 20, 90, 40));
    }

    [$bytes, $extension] = app(ImageCompressor::class)->compress(imageTempFile(pngBytes($poster), 'png'), ImageCompressor::ANNOUNCEMENT_IMAGE);

    expect($extension)->toBe('png')
        ->and(imagesx(imagecreatefromstring($bytes)))->toBe(1600);
});

test('GIFs, PDFs and images that would only grow are stored exactly as uploaded', function () {
    $compressor = app(ImageCompressor::class);

    // Already as small as a PNG gets, and too small to resize: nothing to gain.
    $tiny = imagecreatetruecolor(40, 40);
    expect($compressor->compress(imageTempFile(pngBytes($tiny, 9), 'png'), ImageCompressor::PHOTO))->toBeNull();

    $gif = UploadedFile::fake()->create('wave.gif', 20, 'image/gif');
    file_put_contents($gif->getRealPath(), base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    $gifPath = $compressor->store($gif, 'announcements', ImageCompressor::ANNOUNCEMENT_IMAGE);
    expect($gifPath)->toEndWith('.gif');

    $pdf = UploadedFile::fake()->create('minutes.pdf', 30, 'application/pdf');
    expect($compressor->store($pdf, 'announcements', ImageCompressor::ANNOUNCEMENT_ATTACHMENT))->toEndWith('.pdf');
});

test('a profile photo straight from a phone is accepted and saved at avatar size', function () {
    $user = imageTestUser();

    $this->actingAs($user)->put(route('profile.update'), [
        'name'          => $user->name,
        'email'         => $user->email,
        'profile_photo' => UploadedFile::fake()->image('me.png', 3000, 2250)->size(6000), // 6 MB - over the old 2 MB limit
    ])->assertSessionHasNoErrors();

    $photo = $user->fresh()->profile_photo;
    Storage::disk('public')->assertExists($photo);
    expect($photo)->toStartWith('profile-photos/')
        ->and(getimagesizefromstring(Storage::disk('public')->get($photo))[0])->toBe(600);
});

test('announcement photos are shrunk, and an image attachment downloads under its new type', function () {
    $this->actingAs(imageTestUser())->post(route('announcements.store'), [
        'title'      => 'General assembly',
        'body'       => '<p>See you there.</p>',
        'images'     => [UploadedFile::fake()->image('stage.jpg', 4000, 3000)],
        'attachment' => UploadedFile::fake()->createWithContent('poster.png', grainyPng(600, 800)),
    ])->assertSessionHasNoErrors();

    $announcement = Announcement::firstOrFail();
    $image = AnnouncementImage::firstOrFail();

    expect(getimagesizefromstring(Storage::disk('public')->get($image->path))[0])->toBe(1600)
        ->and($announcement->attachment_path)->toEndWith('.jpg')
        ->and($announcement->attachment_original_name)->toBe('poster.jpg')
        ->and(getimagesizefromstring(Storage::disk('public')->get($announcement->attachment_path))[1])->toBe(800); // under 2400: not resized
});
