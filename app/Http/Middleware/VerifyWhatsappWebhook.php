<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies sidecar callbacks using a shared HMAC-SHA256 secret.
 *
 * The sidecar signs `JSON.stringify(body)` with WHATSAPP_WEBHOOK_SECRET and
 * sends the hex digest in `X-WA-Signature`. We re-hash the raw request body
 * and compare in constant time.
 */
class VerifyWhatsappWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('whatsapp.baileys.webhook_secret');
        if (! $secret) {
            abort(503, 'webhook secret not configured');
        }

        $signature = $request->header('X-WA-Signature');
        if (! $signature) {
            abort(401, 'missing signature');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'bad signature');
        }

        return $next($request);
    }
}
