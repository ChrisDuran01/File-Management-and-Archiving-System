<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\Archive;
use App\Models\File;
use App\Models\Folder;
use App\Models\OfficerTerm;
use App\Models\User;
use App\Services\FolderArchiver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Storage::fake('local');
    Storage::fake('cloud');

    // The live MySQL users table has no position column (see TermHandoverTest).
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('position'));
});

const SECURE_PASSWORD = 'New-Pass2027!';

/** No officer_terms row counts as SuperAdmin - see Folder::isSuperAdmin(). */
function secUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer (Secretary, 2026-2027). */
function secOfficer(): User
{
    $user = secUser();
    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]),
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

/** A restricted folder of $owner with one PDF, archived for real. */
function secRestrictedArchive(User $owner): Archive
{
    $folder = Folder::create(['name' => 'Disciplinary cases', 'school_year' => '2023-2024', 'created_by' => $owner->id, 'is_restricted' => true]);

    $localPath = 'uploads/' . $folder->id . '/case.pdf';
    Storage::disk('local')->put($localPath, '%PDF-1.4 case notes');
    (new File())->forceFill([
        'folder_id' => $folder->id, 'filename' => 'case.pdf', 'filepath' => 'case.pdf',
        'local_path' => $localPath, 'size' => 20, 'storage_type' => 'local',
    ])->save();

    expect(app(FolderArchiver::class)->archive($folder->fresh(), 'Tester')['success'])->toBeTrue();

    return Archive::latest('id')->first();
}

const SEC_PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36';

test('an archived restricted folder cannot be browsed or read by an officer it was not shared with', function () {
    $owner = secOfficer();
    $archive = secRestrictedArchive($owner);
    $file = ['id' => $archive->id, 'filename' => 'case.pdf'];

    $outsider = secOfficer();
    $this->actingAs($outsider)->get(route('archives.show', $archive->id))->assertForbidden();
    $this->actingAs($outsider)->get(route('archives.previewFile', $file))->assertForbidden();
    $this->actingAs($outsider)->get(route('archives.streamFile', $file))->assertForbidden();
    $this->withHeader('User-Agent', SEC_PHONE)->actingAs($outsider)->get(route('app.archive', $archive->id))->assertForbidden();
    $this->withHeader('User-Agent', SEC_PHONE)->actingAs($outsider)->get(route('app.archive.file', $file))->assertForbidden();

    // Its creator and the Adviser/President still can.
    foreach ([$owner, secUser()] as $allowed) {
        $this->actingAs($allowed)->get(route('archives.show', $archive->id))->assertOk()->assertSee('case.pdf');
    }
    $this->actingAs($owner)->get(route('archives.streamFile', $file))
        ->assertOk()
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
});

test('outside local dev only the APP_URL host is answered, so reset links cannot point elsewhere', function () {
    expect(app(Illuminate\Contracts\Http\Kernel::class)->hasMiddleware(TrustHosts::class))->toBeTrue()
        ->and((new TrustHosts(app()))->hosts())->toContain('^(.+\.)?'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST)).'$');
});

test('changing the email or password on Profile needs the current password', function () {
    $user = secOfficer();

    $this->actingAs($user)->from('/profile')
        ->put(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com'])
        ->assertSessionHasErrors(['current_password' => 'Enter your current password to change your email or password.']);
    expect($user->fresh()->email)->not->toBe('new@example.com');

    $this->actingAs($user)
        ->put(route('profile.update'), ['name' => $user->name, 'email' => 'new@example.com', 'current_password' => 'password'])
        ->assertSessionHasNoErrors();
    expect($user->fresh()->email)->toBe('new@example.com');

    // Same email, no password: no current password needed.
    $this->actingAs($user)
        ->put(route('profile.update'), ['name' => 'Renamed', 'email' => 'new@example.com'])
        ->assertSessionHasNoErrors();
});

test('a new Profile password must be strong, and signs out the other devices', function () {
    $user = secOfficer();
    DB::table('sessions')->insert(['id' => 'phone-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($user)
        ->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'current_password' => 'password',
            'password' => 'secret1', 'password_confirmation' => 'secret1'])
        ->assertSessionHasErrors('password');

    $this->actingAs($user)
        ->put(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'current_password' => 'password',
            'password' => SECURE_PASSWORD, 'password_confirmation' => SECURE_PASSWORD])
        ->assertSessionHasNoErrors();

    expect(Hash::check(SECURE_PASSWORD, $user->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'phone-session')->exists())->toBeFalse();
});

test('every response gets the security headers, without overriding one set on purpose', function () {
    $this->get(route('student.dashboard'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security');

    $this->get('/no-such-page')->assertNotFound()->assertHeader('X-Frame-Options', 'SAMEORIGIN');

    $response = (new SecurityHeaders())->handle(Request::create('/s/x'), fn () => response('ok')->header('Referrer-Policy', 'no-referrer'));
    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
});

test('Add Officer never leaves an account without its term, and asks for a strong password', function () {
    $admin = secUser();
    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Treasurer', 'is_super_admin' => false]);
    $officer = fn (string $password) => ['name' => 'Ana Reyes', 'email' => 'ana@example.com', 'password' => $password,
        'position_id' => $positionId, 'school_year' => '2026-2027'];

    $this->actingAs($admin)->post(route('officers.store'), $officer('secret1'))->assertSessionHasErrors('password');

    // The term can't be saved: the account must not stay behind, since an
    // account with no term counts as SuperAdmin.
    OfficerTerm::creating(fn () => throw new RuntimeException('database went away'));
    $this->actingAs($admin)->post(route('officers.store'), $officer(SECURE_PASSWORD))->assertServerError();

    expect(User::where('email', 'ana@example.com')->exists())->toBeFalse();
});

test('folders have no create or edit page, and a contact message has a length limit', function () {
    $this->actingAs(secUser())->get('/folders/create')->assertNotFound();

    $this->from(route('student.dashboard'))->post(route('send.message'), [
        'name' => 'Ana', 'email' => 'ana@example.com', 'student_id' => '21-0001',
        'subject' => 'Hello', 'message' => str_repeat('a', 5001),
    ])->assertSessionHasErrors('message');
});
