<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * A browser an officer ticked "Trust this device for 30 days" on: signing
 * in there skips the authenticator code until it expires. The browser keeps
 * a random token in a cookie (one per account); only its hash is stored, so
 * the row alone can't be used, and deleting it is enough to forget the
 * device (User::forgetTrustedDevices()).
 */
class TrustedDevice extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'label', 'expires_at', 'last_used_at'];

    protected $casts = [
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function cookieName(User $user): string
    {
        return 'qsu_trusted_' . $user->id;
    }

    /** Whether this browser was trusted by $user and still is. */
    public static function recognizes(Request $request, User $user): bool
    {
        $token = $request->cookie(self::cookieName($user));

        if (! is_string($token) || $token === '') {
            return false;
        }

        $device = self::where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();

        $device?->forceFill(['last_used_at' => now()])->save();

        return $device !== null;
    }

    /** Trusts this browser for $user; the returned cookie has to go out with the response. */
    public static function trust(Request $request, User $user): Cookie
    {
        $token = Str::random(64);
        $days = (int) config('two_factor.trust_days');

        self::create([
            'user_id'    => $user->id,
            'token_hash' => hash('sha256', $token),
            'label'      => Str::limit((string) $request->userAgent(), 250, ''),
            'expires_at' => now()->addDays($days),
        ]);

        return cookie(self::cookieName($user), $token, $days * 24 * 60, '/', null, config('session.secure'), true, false, 'lax');
    }
}
