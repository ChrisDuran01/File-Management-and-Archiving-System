<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Console\Command;

/**
 * The last resort when nobody can reset someone's two-factor in the app -
 * the Adviser and the President both lost their phones and their recovery
 * codes. Needs the server itself, so it can't be used from outside. The
 * account sets two-factor up again at its next sign-in.
 */
class ResetTwoFactor extends Command
{
    protected $signature = 'two-factor:reset {email : The account\'s email address}';

    protected $description = 'Turn off an account\'s two-factor sign-in so it is set up again at the next sign-in';

    public function handle(TwoFactor $twoFactor): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No account has that email address.');

            return self::FAILURE;
        }

        $twoFactor->reset($user);

        ActivityLog::create([
            'user_name'  => 'System',
            'activity'   => 'Reset two-factor (server command) for: ' . $user->name,
            'ip_address' => null,
        ]);

        $this->info("Two-factor reset for {$user->name}. They set it up again at their next sign-in.");

        return self::SUCCESS;
    }
}
