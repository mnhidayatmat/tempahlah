<?php

namespace App\Support\Aws;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies the authenticity of an Amazon SNS message without the AWS SDK's
 * MessageValidator (that class isn't shipped in the pruned aws-sdk-php build
 * present here). Implements the documented SNS signature scheme:
 *
 *   1. The SigningCertURL MUST be an https AWS SNS host — this is the guard
 *      against an attacker pointing us at a certificate they control.
 *   2. Rebuild the exact "string to sign" from a fixed set of fields in the
 *      order AWS specifies, per message Type.
 *   3. openssl_verify() that string against the base64 Signature using the
 *      public key from the fetched certificate (SHA1 for SignatureVersion 1,
 *      SHA256 for version 2).
 *
 * An attacker who knows the webhook URL therefore still cannot forge a bounce
 * or a subscription confirmation: they'd need AWS's private key.
 */
class SnsMessageVerifier
{
    /** Only certificates served from a genuine AWS SNS endpoint are trusted. */
    private const CERT_HOST_PATTERN = '/^sns\.[a-z0-9-]+\.amazonaws\.com(\.cn)?$/';

    public function verify(array $message): bool
    {
        $signature = $message['Signature'] ?? null;
        $certUrl = $message['SigningCertURL'] ?? null;
        $version = (string) ($message['SignatureVersion'] ?? '1');

        if (! is_string($signature) || ! is_string($certUrl)) {
            return false;
        }

        if (! $this->certUrlIsTrusted($certUrl)) {
            Log::warning('SNS: rejected untrusted SigningCertURL', ['url' => $certUrl]);

            return false;
        }

        $algo = match ($version) {
            '1' => OPENSSL_ALGO_SHA1,
            '2' => OPENSSL_ALGO_SHA256,
            default => null,
        };
        if ($algo === null) {
            return false;
        }

        $stringToSign = $this->stringToSign($message);
        if ($stringToSign === null) {
            return false;
        }

        $pem = $this->fetchCertificatePem($certUrl);
        if ($pem === null) {
            return false;
        }

        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            return false;
        }

        $result = openssl_verify($stringToSign, base64_decode($signature), $publicKey, $algo);

        return $result === 1;
    }

    private function certUrlIsTrusted(string $url): bool
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https') {
            return false;
        }

        return isset($parts['host']) && preg_match(self::CERT_HOST_PATTERN, $parts['host']) === 1;
    }

    /**
     * The canonical string is the signed fields, each rendered as
     * "Key\nValue\n", in the AWS-mandated order for the message Type.
     * Returns null for an unrecognised Type (nothing to verify).
     */
    private function stringToSign(array $m): ?string
    {
        $type = $m['Type'] ?? '';

        $fields = match ($type) {
            'Notification' => array_values(array_filter(
                ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
                // Subject is optional and only signed when present.
                fn ($f) => $f !== 'Subject' || array_key_exists('Subject', $m),
            )),
            'SubscriptionConfirmation', 'UnsubscribeConfirmation' => [
                'Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type',
            ],
            default => null,
        };

        if ($fields === null) {
            return null;
        }

        $out = '';
        foreach ($fields as $field) {
            if (! array_key_exists($field, $m) || ! is_scalar($m[$field])) {
                return null;
            }
            $out .= $field."\n".$m[$field]."\n";
        }

        return $out;
    }

    /**
     * Fetch (and cache) the PEM certificate. Cached by URL — AWS rotates these
     * rarely and the URL changes when they do, so caching on the URL is safe.
     * Overridable in tests to avoid a real network fetch.
     */
    protected function fetchCertificatePem(string $url): ?string
    {
        return Cache::remember('sns_cert:'.sha1($url), now()->addDay(), function () use ($url) {
            try {
                $res = Http::timeout(5)->get($url);
            } catch (\Throwable $e) {
                Log::warning('SNS: certificate fetch failed', ['url' => $url, 'error' => $e->getMessage()]);

                return null;
            }

            return $res->successful() ? $res->body() : null;
        });
    }
}
