<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use App\Support\OpensslConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends one browser push to every student who tapped "Notify me" on the
 * public page. Runs on the queue (needs `queue:work`). Each kind of alert
 * (a new announcement, a new public document) is a small subclass that only
 * says what the notification shows.
 */
abstract class SendStudentPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * What the notification shows: title, body, url (opened when tapped) and
     * tag (one notification per tag on a device). Null sends nothing.
     */
    abstract protected function payload(): ?array;

    /** Names this push in the log, e.g. "Announcement push #12". */
    abstract protected function label(): string;

    public function handle(): void
    {
        $vapid = config('webpush.vapid');

        if (! $vapid['public_key'] || ! $vapid['private_key']) {
            Log::warning('Web push skipped: VAPID keys are not configured (run php artisan webpush:vapid).');

            return;
        }

        $payload = $this->payload();
        if ($payload === null) {
            return;
        }

        $subscriptions = PushSubscription::all();
        if ($subscriptions->isEmpty()) {
            return;
        }

        // web-push makes a throwaway EC key per send. On a CLI without
        // OPENSSL_CONF (XAMPP/Windows) that fails with a cryptic error, so
        // say what to fix instead and let the queue retry later.
        if (! OpensslConfig::ecKeysWork()) {
            Log::error('Web push cannot run: PHP OpenSSL cannot create EC keys. Set the OPENSSL_CONF environment variable (e.g. C:\\xampp\\php\\extras\\ssl\\openssl.cnf) for the queue worker process and restart it.');
            $this->fail(new \RuntimeException('OpenSSL EC key generation unavailable (OPENSSL_CONF not set).'));

            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject'    => $vapid['subject'],
            'publicKey'  => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ]]);

        $payload = json_encode($payload);
        $byEndpoint = $subscriptions->keyBy('endpoint');

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys'     => ['p256dh' => $sub->p256dh_key, 'auth' => $sub->auth_token],
                ]),
                $payload
            );
        }

        $sent = 0;
        $removed = 0;

        foreach ($webPush->flush() as $report) {
            $sub = $byEndpoint[$report->getEndpoint()] ?? null;

            if ($report->isSuccess()) {
                $sent++;
                $sub?->forceFill(['last_pushed_at' => now()])->save();
            } elseif ($report->isSubscriptionExpired()) {
                // 404/410: the student revoked permission or cleared the browser. Stop trying.
                $sub?->delete();
                $removed++;
            } else {
                Log::notice('Web push failed: ' . $report->getReason());
            }
        }

        Log::info("{$this->label()}: {$sent} sent, {$removed} expired subscription(s) removed.");
    }
}
