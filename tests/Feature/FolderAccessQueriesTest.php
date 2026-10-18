<?php

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/** An ordinary officer: an active term in a position that isn't Adviser/President. */
function accessOfficer(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);
    DB::table('officer_terms')->insert(['user_id' => $user->id, 'position_id' => $positionId, 'school_year' => '2026-2027', 'status' => 'active']);

    return $user;
}

function restrictedFolders(int $count): array
{
    return collect(range(1, $count))
        ->map(fn ($n) => Folder::create(['name' => "Restricted {$n}", 'is_restricted' => true])->id)
        ->all();
}

function foldersPageQueries($test, User $user): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $test->actingAs($user)->get(route('folders.index'))->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

test('the folders page asks about access once, however many folders are restricted', function () {
    $officer = accessOfficer();

    $ids = restrictedFolders(5);
    DB::table('folder_access')->insert(['folder_id' => $ids[0], 'user_id' => $officer->id]);
    foldersPageQueries($this, $officer); // warm-up: the first request also reads things later ones remember
    $withFive = foldersPageQueries($this, $officer);

    restrictedFolders(15);
    $withTwenty = foldersPageQueries($this, $officer);

    expect($withTwenty)->toBe($withFive);
});

test('access is still decided correctly: given, created, or neither', function () {
    $officer = accessOfficer();
    $other = accessOfficer();

    [$given, $notGiven] = restrictedFolders(2);
    $own = Folder::create(['name' => 'Mine', 'is_restricted' => true, 'created_by' => $officer->id]);
    $open = Folder::create(['name' => 'Open', 'is_restricted' => false]);
    DB::table('folder_access')->insert(['folder_id' => $given, 'user_id' => $officer->id]);

    expect(Folder::find($given)->isAccessibleBy($officer))->toBeTrue()
        ->and(Folder::find($notGiven)->isAccessibleBy($officer))->toBeFalse()
        ->and($own->isAccessibleBy($officer))->toBeTrue()
        ->and($open->isAccessibleBy($officer))->toBeTrue()
        ->and(Folder::find($given)->isAccessibleBy($other))->toBeFalse()
        ->and(Folder::isSuperAdmin($officer))->toBeFalse();
});

test('a change of access or role shows on the very next request', function () {
    $officer = accessOfficer();
    [$folderId] = restrictedFolders(1);

    $this->actingAs($officer)->get(route('folders.show', $folderId))->assertRedirect(); // restricted
    $this->actingAs($officer)->get('/backup')->assertForbidden();                     // not a SuperAdmin

    DB::table('folder_access')->insert(['folder_id' => $folderId, 'user_id' => $officer->id]);
    $presidentId = DB::table('positions')->insertGetId(['position_name' => 'President', 'is_super_admin' => true]);
    DB::table('officer_terms')->where('user_id', $officer->id)->update(['position_id' => $presidentId]);

    // Same User object, next request: answered fresh, not from the first request.
    $this->actingAs($officer)->get(route('folders.show', $folderId))->assertOk();
    $this->actingAs($officer)->get('/backup')->assertOk();
});

test('queued jobs start without answers left over from the previous job', function () {
    expect(Event::hasListeners(JobProcessing::class))->toBeTrue();

    $officer = accessOfficer();
    expect(Folder::isSuperAdmin($officer))->toBeFalse();

    DB::table('officer_terms')->where('user_id', $officer->id)->delete(); // no term at all = SuperAdmin
    expect(Folder::isSuperAdmin($officer))->toBeFalse(); // still the remembered answer...

    Folder::forgetAccessAnswers(); // ...until a request ends or a job starts
    expect(Folder::isSuperAdmin($officer))->toBeTrue();
});
