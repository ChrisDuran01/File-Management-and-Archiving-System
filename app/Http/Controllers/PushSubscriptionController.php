<?php

namespace App\Http\Controllers;

use App\Models\EngagementEvent;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public (no login) endpoints the student page calls when someone taps
 * "Notify me" / "Turn off notifications". Throttled in routes/web.php.
 */
class PushSubscriptionController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint'    => ['required', 'string', 'max:1000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth'   => ['required', 'string', 'max:255'],
        ]);

        if (! $this->isAllowedEndpoint($data['endpoint'])) {
            return response()->json(['ok' => false, 'message' => 'Unsupported push service.'], 422);
        }

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'endpoint'   => $data['endpoint'],
                'p256dh_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
            ]
        );

        // Count real opt-ins only - not a browser re-sending the same
        // subscription, nor one the page moved to its new worker ("moved").
        if ($subscription->wasRecentlyCreated && ! $request->boolean('moved')) {
            EngagementEvent::recordFor($request, 'notify_on');
        }

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1000'],
        ]);

        $removed = PushSubscription::where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))->delete();

        if ($removed && ! $request->boolean('moved')) {
            EngagementEvent::recordFor($request, 'notify_off');
        }

        return response()->json(['ok' => true]);
    }

    private function isAllowedEndpoint(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));

        foreach (config('webpush.allowed_endpoint_hosts', []) as $allowed) {
            $allowed = strtolower($allowed);
            // A leading dot means "this domain and any subdomain".
            if (str_starts_with($allowed, '.')) {
                if (str_ends_with($host, $allowed)) {
                    return true;
                }
            } elseif ($host === $allowed) {
                return true;
            }
        }

        return false;
    }
}
