<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function send(User $user, string $category, string $title, string $body): void
    {
        $subscriptions = $user->pushSubscriptions()
            ->get()
            ->filter(fn ($subscription): bool => ($subscription->preferences[$category] ?? false) === true);
        if ($subscriptions->isEmpty()) {
            return;
        }

        $publicKey = config('services.webpush.public_key');
        $privateKey = config('services.webpush.private_key');
        $subject = config('services.webpush.subject');
        if (! is_string($publicKey) || $publicKey === '' || ! is_string($privateKey) || $privateKey === ''
            || ! is_string($subject) || $subject === '') {
            Log::error('Push subscriptions exist but VAPID is not configured.', ['user_id' => $user->id]);
            return;
        }

        $push = new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ], [
            'TTL' => 300,
            'urgency' => 'normal',
        ]);
        $push->setReuseVAPIDHeaders(true);

        $subscriptionsByEndpoint = [];
        foreach ($subscriptions as $storedSubscription) {
            $subscriptionsByEndpoint[$storedSubscription->endpoint] = $storedSubscription;
            $push->queueNotification(
                Subscription::create([
                    'endpoint' => $storedSubscription->endpoint,
                    'keys' => [
                        'p256dh' => $storedSubscription->p256dh,
                        'auth' => $storedSubscription->auth_token,
                    ],
                ]),
                json_encode([
                    'title' => $title,
                    'body' => $body,
                ], JSON_THROW_ON_ERROR),
            );
        }

        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            $subscription = $subscriptionsByEndpoint[$report->getEndpoint()] ?? null;
            Log::warning('Web Push delivery failed.', [
                'subscription_id' => $subscription?->id,
                'reason' => $report->getReason(),
            ]);
            if ($report->isSubscriptionExpired()) {
                $subscription?->delete();
            }
        }
    }
}
