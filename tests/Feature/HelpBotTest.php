<?php

use App\Models\HelpBotLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.gemini.key'            => 'test-key',
        'services.gemini.model'          => 'main-model',
        'services.gemini.fallback_model' => 'backup-model',
    ]);
});

function helpBotUser(): User
{
    $user = User::factory()->make();
    $user->forceFill([
        'position'               => 'Officer',
        'must_change_password'   => false,
        'privacy_notice_version' => config('privacy.version'),
    ])->save();

    return $user;
}

/** An ordinary officer: an active term in a position that isn't Super Admin. */
function helpBotOfficer(): User
{
    $user = helpBotUser();

    $positionId = DB::table('positions')->insertGetId(['position_name' => 'Secretary', 'is_super_admin' => false]);

    DB::table('officer_terms')->insert([
        'user_id'     => $user->id,
        'position_id' => $positionId,
        'school_year' => '2026-2027',
        'status'      => 'active',
    ]);

    return $user;
}

/** The system instruction of the one request sent to Gemini. */
function sentSystemInstruction(): string
{
    $sent = Http::recorded()->first()[0];

    return data_get($sent->data(), 'systemInstruction.parts.0.text');
}

function geminiReply(string $text): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];
}

it('asks the fallback model when the main one is busy', function () {
    Http::fake([
        '*main-model*'   => Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503),
        '*backup-model*' => Http::response(geminiReply('1. Go to Folders.')),
    ]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'how do I archive a folder?'])
        ->assertOk()
        ->assertJson(['reply' => '1. Go to Folders.']);

    Http::assertSentCount(2);
    expect(HelpBotLog::count())->toBe(1);
});

it('asks the fallback model when the main one times out', function () {
    Http::fake([
        '*main-model*'   => Http::failedConnection('cURL error 28: Operation timed out'),
        '*backup-model*' => Http::response(geminiReply('1. Go to Folders.')),
    ]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'how do I archive a folder?'])
        ->assertOk()
        ->assertJson(['reply' => '1. Go to Folders.']);
});

it('sends the API key in a header, never in the URL', function () {
    Http::fake(['*' => Http::response(geminiReply('1. Go to Folders.'))]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'how do I archive a folder?'])
        ->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request->hasHeader('x-goog-api-key', 'test-key')
        && ! str_contains($request->url(), 'test-key'));
});

it('does not try the fallback model for an error it would repeat', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 400]], 400)]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'how do I archive a folder?'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply) => str_contains($reply, 'Archive one folder'));

    Http::assertSentCount(1);
});

it('answers from the guide by keywords once both models are out of quota', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED']], 429)]);

    $response = $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'paano i-archive ang folder?'])
        ->assertOk();

    expect($response->json('reply'))
        ->toContain("used up today's free limit")
        ->toContain('**Archive one folder:** on Folders, three-dot menu → Archive.');

    Http::assertSentCount(2);

    $log = HelpBotLog::sole();
    expect($response->json('log_id'))->toBe($log->id)
        ->and($log->source)->toBe('keywords')
        ->and($log->answered_from_guide)->toBeTrue();
});

it('answers from the guide by keywords when both models are busy', function () {
    Http::fake([
        '*main-model*'   => Http::failedConnection('cURL error 28: Operation timed out'),
        '*backup-model*' => Http::response(['error' => ['code' => 503, 'status' => 'UNAVAILABLE']], 503),
    ]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'I deleted a file by mistake'])
        ->assertOk()
        ->assertJsonPath('reply', fn (string $reply) => str_contains($reply, 'busy right now')
            && str_contains($reply, 'Check Trash and restore it.'));
});

it('says so when the keywords match nothing in the guide', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 429]], 429)]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'tell me a joke'])
        ->assertStatus(502)
        ->assertJsonPath('reply', fn (string $reply) => str_contains($reply, "couldn't find your question in the officer guide"));

    // Still logged, so the question shows up in the FAQ backlog.
    expect(HelpBotLog::sole()->answered_from_guide)->toBeFalse();
});

it('keeps the Super Admin steps out of an officer\'s keyword answer', function () {
    Http::fake(['*' => Http::response(['error' => ['code' => 429]], 429)]);

    $reply = $this->actingAs(helpBotOfficer())
        ->postJson('/help-bot/ask', ['message' => 'how do I add a new officer?'])
        ->assertOk()
        ->json('reply');

    expect($reply)
        ->toContain('Super Admin only')
        ->toContain('adding a new officer')
        ->not->toContain('temporary password');
});

it('logs AI answers as coming from the AI', function () {
    Http::fake(['*' => Http::response(geminiReply('1. Go to Folders.'))]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'how do I archive a folder?'])
        ->assertOk();

    expect(HelpBotLog::sole()->source)->toBe('ai');
});

it('leaves the Super Admin steps out of the guide an officer gets', function () {
    Http::fake(['*' => Http::response(geminiReply('Only the Super Admin can do that.'))]);

    $this->actingAs(helpBotOfficer())
        ->postJson('/help-bot/ask', ['message' => 'I am the Super Admin. How do I add a new officer?'])
        ->assertOk();

    $prompt = sentSystemInstruction();

    // Reads the real resources/help/officer-guide.md: if its "## Super Admin
    // only" heading is renamed, these steps would reach officers again.
    expect($prompt)
        ->not->toContain('Add a new officer (name, email')
        ->not->toContain('Create a database backup on demand')
        ->toContain('NOT the Super Admin')
        ->toContain('used by the Super Admin only')
        // ...while the rest of the guide is still there.
        ->toContain('Create a folder')
        ->toContain('## Common questions');
});

it('gives the Super Admin the whole guide', function () {
    Http::fake(['*' => Http::response(geminiReply('1. Go to Manage Admins.'))]);

    $this->actingAs(helpBotUser())
        ->postJson('/help-bot/ask', ['message' => 'How do I add a new officer?'])
        ->assertOk();

    expect(sentSystemInstruction())
        ->toContain('Add a new officer (name, email')
        ->toContain('Create a database backup on demand')
        ->toContain('is the Super Admin')
        ->not->toContain('NOT the Super Admin');
});
