<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use App\Support\Aws\SnsMessageVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Receiver for SES bounce + complaint notifications delivered via SNS.
 *
 * Wiring (done once in the AWS console): SES identity → Notifications → an SNS
 * topic for Bounce + Complaint → an HTTPS subscription to this endpoint.
 *
 * SNS posts JSON with Content-Type text/plain, so we read the raw body rather
 * than $request->json(). Every message is signature-verified before we act on
 * it (see SnsMessageVerifier) — the route is public but forgery-proof.
 *
 * Responses are always 200 for an authentic-but-uninteresting message so SNS
 * doesn't retry-storm; only a bad signature / unparseable body is a 4xx.
 */
class SesNotificationController extends Controller
{
    public function __construct(private readonly SnsMessageVerifier $verifier) {}

    public function handle(Request $request): JsonResponse
    {
        $message = json_decode($request->getContent(), true);
        if (! is_array($message)) {
            return response()->json(['error' => 'invalid_body'], 400);
        }

        if (! $this->verifier->verify($message)) {
            Log::warning('SES/SNS: signature verification failed', [
                'type'     => $message['Type'] ?? null,
                'topicArn' => $message['TopicArn'] ?? null,
            ]);

            return response()->json(['error' => 'invalid_signature'], 403);
        }

        // Optional defence-in-depth: pin to a known topic once configured.
        $expectedTopic = config('services.ses.sns_topic_arn');
        if ($expectedTopic && ($message['TopicArn'] ?? null) !== $expectedTopic) {
            Log::warning('SES/SNS: unexpected TopicArn', ['topicArn' => $message['TopicArn'] ?? null]);

            return response()->json(['error' => 'unexpected_topic'], 403);
        }

        return match ($message['Type'] ?? '') {
            'SubscriptionConfirmation' => $this->confirmSubscription($message),
            'Notification'             => $this->handleNotification($message),
            'UnsubscribeConfirmation'  => response()->json(['status' => 'ok']),
            default                    => response()->json(['status' => 'ignored']),
        };
    }

    /** Auto-confirm the SNS subscription (the message is already verified). */
    private function confirmSubscription(array $message): JsonResponse
    {
        $url = $message['SubscribeURL'] ?? null;
        if (is_string($url)) {
            try {
                Http::timeout(5)->get($url);
                Log::info('SES/SNS: subscription confirmed', ['topicArn' => $message['TopicArn'] ?? null]);
            } catch (\Throwable $e) {
                Log::warning('SES/SNS: subscription confirm failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json(['status' => 'confirmed']);
    }

    private function handleNotification(array $message): JsonResponse
    {
        $event = json_decode((string) ($message['Message'] ?? ''), true);
        if (! is_array($event)) {
            return response()->json(['status' => 'ignored']);
        }

        $type = $event['notificationType'] ?? $event['eventType'] ?? null;

        return match ($type) {
            'Bounce'    => $this->handleBounce($event),
            'Complaint' => $this->handleComplaint($event),
            default     => response()->json(['status' => 'ignored']),   // Delivery, etc.
        };
    }

    /**
     * Only PERMANENT bounces are suppressed. A Transient bounce (mailbox full,
     * greylisted) is temporary — SES retries, and suppressing would wrongly cut
     * off a live address.
     */
    private function handleBounce(array $event): JsonResponse
    {
        $bounce = $event['bounce'] ?? [];
        if (($bounce['bounceType'] ?? null) !== 'Permanent') {
            return response()->json(['status' => 'transient_ignored']);
        }

        $count = 0;
        foreach ($bounce['bouncedRecipients'] ?? [] as $r) {
            $email = $r['emailAddress'] ?? null;
            if (is_string($email) && $email !== '') {
                EmailSuppression::suppress(
                    $email,
                    EmailSuppression::REASON_BOUNCE,
                    $bounce['bounceSubType'] ?? 'Permanent',
                    $r['diagnosticCode'] ?? null,
                );
                $count++;
            }
        }

        Log::info('SES: suppressed permanent bounce(s)', ['count' => $count]);

        return response()->json(['status' => 'suppressed', 'count' => $count]);
    }

    private function handleComplaint(array $event): JsonResponse
    {
        $complaint = $event['complaint'] ?? [];

        $count = 0;
        foreach ($complaint['complainedRecipients'] ?? [] as $r) {
            $email = $r['emailAddress'] ?? null;
            if (is_string($email) && $email !== '') {
                EmailSuppression::suppress(
                    $email,
                    EmailSuppression::REASON_COMPLAINT,
                    $complaint['complaintFeedbackType'] ?? null,
                );
                $count++;
            }
        }

        Log::info('SES: suppressed complaint(s)', ['count' => $count]);

        return response()->json(['status' => 'suppressed', 'count' => $count]);
    }
}
