<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A link that lets someone without an account open and download one file,
 * for a few days (DURATIONS), until an officer turns it off. Made from the
 * website's Share dialog or the phone app's Share sheet (ShareLinkController).
 *
 * A link only works while the officer who made it could still make it now
 * (opensFile()): their term ending, the file going to Trash, or its folder
 * becoming restricted all stop it, the same way they stop that officer.
 */
class FileShareLink extends Model
{
    /** How long a new link may work, in days. */
    public const DURATIONS = [1, 7, 30];
    public const DEFAULT_DURATION = 7;

    protected $fillable = ['file_id', 'created_by', 'token_hash', 'token', 'expires_at'];

    protected $hidden = ['token_hash', 'token'];

    protected $casts = [
        'token'          => 'encrypted',
        'expires_at'     => 'datetime',
        'revoked_at'     => 'datetime',
        'last_opened_at' => 'datetime',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function createFor(File $file, User $user, int $days): self
    {
        $token = Str::random(40);

        return self::create([
            'file_id'    => $file->id,
            'created_by' => $user->id,
            'token_hash' => self::hash($token),
            'token'      => $token,
            'expires_at' => now()->addDays($days),
        ]);
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', self::hash($token))->first();
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function url(): string
    {
        return route('share.show', $this->token);
    }

    /** Neither expired nor turned off - says nothing yet about the file itself. */
    public function isLive(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }

    /** Links still worth listing in the Share dialog. */
    public function scopeLive($query)
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * The file this link opens right now, or null when it must not open
     * anything: expired, turned off, the file in Trash (File's soft delete
     * hides it) or its folder gone, or the officer who made it no longer
     * allowed to share it.
     */
    public function opensFile(): ?File
    {
        if (! $this->isLive()) {
            return null;
        }

        $file    = File::with('folder')->find($this->file_id);
        $creator = $this->creator;

        if (! $file || ($file->folder_id && ! $file->folder) || ! $creator || ! $creator->isActiveOfficer()) {
            return null;
        }

        return $file->isShareableBy($creator) ? $file : null;
    }
}
