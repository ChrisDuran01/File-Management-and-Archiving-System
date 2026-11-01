<?php

use App\Models\Letterhead;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // School year 2026-2027 (it starts in August).
    $this->travelTo('2026-10-22 10:00');
    Storage::fake('public');
});

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function letterheadOfficer(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** A one-page PDF of the given size in points, like Word's "Save as PDF" of a letterhead. */
function letterheadPdf(float $width = 612, float $height = 936): UploadedFile
{
    $pdf = Pdf::loadHTML('<div style="background:#3a8f3a;height:90pt;color:#fff;">OFFICE OF THE STUDENT GOVERNMENT</div>')
        ->setPaper([0, 0, $width, $height])
        ->output();

    return UploadedFile::fake()->createWithContent('letterhead.pdf', $pdf);
}

function pdftoppmMissing(): bool
{
    return ! is_file((string) config('services.ocr.pdftoppm_path'));
}

it('turns a letterhead PDF into a sharp page image', function () {
    if (pdftoppmMissing()) {
        $this->markTestSkipped('Poppler (pdftoppm) is not installed here.');
    }

    $this->actingAs(letterheadOfficer())
        ->post(route('letterheads.store'), ['name' => '2026-2027', 'school_year' => '2026-2027', 'border_image' => letterheadPdf()])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $letterhead = Letterhead::sole();
    expect($letterhead->border_image_path)->toEndWith('.jpg');

    [$width, $height] = getimagesize(Storage::disk('public')->path($letterhead->border_image_path));
    expect($width)->toBe(2550)        // 8.5 in at 300 dpi
        ->and($height)->toBe(3900)    // 13 in
        ->and($letterhead->printWarnings())->toBe([]);
});

it('warns when the page will print blurry or trimmed', function () {
    $this->actingAs(letterheadOfficer())->post(route('letterheads.store'), [
        'name' => 'Screenshot',
        'border_image' => UploadedFile::fake()->image('letterhead.png', 722, 1090),
    ])->assertSessionHas('success', fn ($message) => str_contains($message, 'Check the note on its card'));

    $warnings = Letterhead::sole()->printWarnings();
    expect($warnings)->toHaveCount(1)->and($warnings[0])->toContain('Blurry when printed: the page image is 722 px wide');

    $this->actingAs(letterheadOfficer())->post(route('letterheads.store'), [
        'name' => 'Letter size',
        'border_image' => UploadedFile::fake()->image('letterhead.png', 2550, 3300),
    ]);

    expect(Letterhead::where('name', 'Letter size')->sole()->printWarnings()[0])->toContain('Not Long size');

    $this->actingAs(letterheadOfficer())->get(route('letterheads.index'))
        ->assertOk()
        ->assertSee('Blurry when printed')
        ->assertSee('Not Long size');
});

it('refuses a PDF it cannot read', function () {
    if (pdftoppmMissing()) {
        $this->markTestSkipped('Poppler (pdftoppm) is not installed here.');
    }

    $this->actingAs(letterheadOfficer())->post(route('letterheads.store'), [
        'name' => 'Broken',
        'border_image' => UploadedFile::fake()->createWithContent('letterhead.pdf', "%PDF-1.4\nnot really a pdf"),
    ])->assertSessionHasErrors('border_image');

    expect(Letterhead::count())->toBe(0);
});

it('keeps one default, and new documents and proposal PDFs start with it', function () {
    $old = Letterhead::create(['name' => '2025-2026', 'school_year' => '2025-2026']);
    $new = Letterhead::create(['name' => '2026-2027', 'school_year' => '2026-2027']);
    $old->makeDefault();

    $this->actingAs(letterheadOfficer())->post(route('letterheads.default', $new))->assertRedirect();

    expect($old->fresh()->is_default)->toBeFalse()
        ->and($new->fresh()->is_default)->toBeTrue()
        ->and(Letterhead::defaultOne()->id)->toBe($new->id);

    $this->actingAs(letterheadOfficer())->get(route('templates.index'))
        ->assertSee('<option value="'.$new->id.'" selected>2026-2027 (default)</option>', false);
});

it('asks for this year\'s letterhead when the default is from a past school year', function () {
    Letterhead::create(['name' => '2025-2026', 'school_year' => '2025-2026'])->makeDefault();

    $this->actingAs(letterheadOfficer())->get(route('letterheads.index'))
        ->assertSee('is for SY 2025-2026; this is SY 2026-2027', false);
});

it('copies a letterhead for the next school year with its own images', function () {
    $image = UploadedFile::fake()->image('page.png', 2550, 3900)->store('letterheads', 'public');
    $source = Letterhead::create(['name' => '2026-2027', 'school_year' => '2026-2027', 'border_image_path' => $image, 'office_name' => 'OFFICE OF THE STUDENT GOVERNMENT']);
    $source->makeDefault();

    $this->actingAs(letterheadOfficer())->post(route('letterheads.copy', $source))
        ->assertRedirect(route('letterheads.index'))
        ->assertSessionHas('open_letterhead');

    $copy = Letterhead::whereKeyNot($source->id)->sole();
    expect($copy->name)->toBe('2027-2028')
        ->and($copy->school_year)->toBe('2027-2028')
        ->and($copy->is_default)->toBeFalse()
        ->and($copy->office_name)->toBe('OFFICE OF THE STUDENT GOVERNMENT')
        ->and($copy->border_image_path)->not->toBe($image);

    Storage::disk('public')->assertExists([$image, $copy->border_image_path]);

    // Deleting the copy leaves the original's image alone.
    $this->actingAs(letterheadOfficer())->delete(route('letterheads.destroy', $copy));
    Storage::disk('public')->assertExists($image);
});
