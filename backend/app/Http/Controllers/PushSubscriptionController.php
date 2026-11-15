<?php

namespace App\Http\Controllers;

use App\Models\UserPushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        $publicKey = config('services.webpush.public_key');
        abort_if(
            ! is_string($publicKey)
            || ! preg_match('/^[A-Za-z0-9_-]{80,90}$/D', $publicKey)
            || ! is_string(config('services.webpush.private_key'))
            || config('services.webpush.private_key') === ''
            || ! is_string(config('services.webpush.subject'))
            || config('services.webpush.subject') === '',
            503,
            'As notificações push ainda não estão configuradas no servidor.',
        );

        return response()->json(['data' => ['public_key' => $publicKey]]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{80,90}$/D'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,64}$/D'],
            'preferences.riskAlerts' => ['required', 'boolean'],
            'preferences.tripUpdates' => ['required', 'boolean'],
        ]);
        $this->validatePushEndpoint($validated['endpoint']);

        $subscription = UserPushSubscription::query()
            ->where('endpoint_hash', hash('sha256', $validated['endpoint']))
            ->first();

        abort_if(
            $subscription && $subscription->user_id !== $request->user()->id,
            409,
            'Esta subscrição de notificações pertence a outra conta.',
        );

        $subscription ??= new UserPushSubscription;
        $subscription->fill([
            'user_id' => $request->user()->id,
            'endpoint' => $validated['endpoint'],
            'endpoint_hash' => hash('sha256', $validated['endpoint']),
            'p256dh' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'preferences' => $validated['preferences'],
        ])->save();

        return response()->json(['data' => ['id' => $subscription->id]], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'url', 'max:2048']]);
        $this->validatePushEndpoint($validated['endpoint']);
        $request->user()->pushSubscriptions()->where('endpoint', $validated['endpoint'])->delete();

        return response()->json(['message' => 'Subscrição de notificações removida.']);
    }

    private function validatePushEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowedHosts = [
            'fcm.googleapis.com',
            'fcmregistrations.googleapis.com',
            'push.services.mozilla.com',
            'updates.push.services.mozilla.com',
            'notify.windows.com',
            'web.push.apple.com',
        ];
        $allowed = in_array($host, $allowedHosts, true)
            || str_ends_with($host, '.notify.windows.com')
            || str_ends_with($host, '.push.apple.com');

        if (($parts['scheme'] ?? null) !== 'https' || ! $allowed || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([
                'endpoint' => 'O endpoint de push não pertence a um serviço de notificações suportado.',
            ]);
        }
    }
}
