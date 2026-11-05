<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-factor sign-in with an authenticator app (Google Authenticator,
 * Microsoft Authenticator, an iPhone's built-in codes...): the app and this
 * server share a secret key, and both turn it into the same 6-digit code
 * every 30 seconds. Nothing is sent anywhere; the server's clock has to be
 * right.
 *
 * The secret and the recovery codes are stored encrypted (User casts). A
 * recovery code is kept only as a hash, so it is shown once, when made.
 */
class TwoFactor
{
    private const RECOVERY_CODES = 8;

    public function __construct(private Google2FA $google2fa = new Google2FA())
    {
    }

    /**
     * The secret the setup page shows, made the first time it is opened and
     * kept until a code confirms it - so the QR code doesn't change while
     * the officer is still finding their app.
     */
    public function pendingSecret(User $user): string
    {
        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => $this->google2fa->generateSecretKey(32)])->save();
        }

        return $user->two_factor_secret;
    }

    /** The QR code the app scans, as inline SVG (made here; no internet needed). */
    public function qrCodeSvg(User $user): string
    {
        $url = $this->google2fa->getQRCodeUrl(config('two_factor.issuer'), $user->email, $this->pendingSecret($user));
        $svg = (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd())))->writeString($url);

        // Drop the XML prolog so it can sit inside the page.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }

    /** The secret in groups of four, for typing into an app by hand. */
    public function readableSecret(User $user): string
    {
        return trim(chunk_split($this->pendingSecret($user), 4, ' '));
    }

    /**
     * Whether $code is the app's current code (one 30-second step either way
     * for a clock that is a little off). A code is good once: the step it
     * was used for is remembered, and only later steps count after it.
     */
    public function verify(User $user, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);

        if (! $user->two_factor_secret || strlen($code) !== 6) {
            return false;
        }

        $step = $this->google2fa->verifyKeyNewer($user->two_factor_secret, $code, (int) $user->two_factor_last_step);

        if ($step === false) {
            return false;
        }

        $user->forceFill(['two_factor_last_step' => $step])->save();

        return true;
    }

    /**
     * Turns two-factor on after the setup page's code checked out, and
     * returns the first recovery codes to show.
     */
    public function confirm(User $user): array
    {
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $user->forgetTrustedDevices();

        return $this->newRecoveryCodes($user);
    }

    /** Eight fresh one-time codes (replacing any old ones), returned once in plain text. */
    public function newRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODES))
            ->map(fn () => self::randomChars(5) . '-' . self::randomChars(5))
            ->all();

        $user->forceFill(['two_factor_recovery_codes' => array_map(self::hash(...), $codes)])->save();

        return $codes;
    }

    /** Uses up one recovery code; false if it isn't one of the account's unused codes. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $hash = self::hash($code);
        $codes = $user->two_factor_recovery_codes ?? [];

        if (! in_array($hash, $codes, true)) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$hash]))])->save();

        return true;
    }

    public function recoveryCodesLeft(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    /**
     * Takes two-factor off an account so it is set up again at the next
     * sign-in (lost phone, new phone). Trusted devices are forgotten, and
     * with $signOut every device signed in as them is signed out too.
     */
    public function reset(User $user, bool $signOut = true): void
    {
        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_last_step'      => null,
        ])->save();

        $user->forgetTrustedDevices();

        if ($signOut) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }

    /** Letters and digits that can't be mistaken for each other when copied by hand (no O/0, I/1/L). */
    private static function randomChars(int $length): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        return implode('', array_map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)], range(1, $length)));
    }

    /** Case, spaces and dashes don't matter when a recovery code is typed. */
    private static function hash(string $code): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code)));
    }
}
