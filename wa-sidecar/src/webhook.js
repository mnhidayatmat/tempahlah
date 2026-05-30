import axios from 'axios';
import crypto from 'node:crypto';
import { config } from './config.js';
import { logger } from './logger.js';

/**
 * POST signed JSON to Laravel.
 *
 * Signature: hex(hmac-sha256(WEBHOOK_SECRET, raw body))
 * Header:    X-WA-Signature
 *
 * Laravel verifies in App\Http\Middleware\VerifyWhatsappWebhook.
 */
export async function postWebhook(eventType, payload) {
  const body = JSON.stringify({
    event: eventType,
    payload,
    sent_at: new Date().toISOString(),
  });

  const signature = crypto
    .createHmac('sha256', config.webhookSecret)
    .update(body)
    .digest('hex');

  try {
    await axios.post(config.webhookUrl, body, {
      headers: {
        'Content-Type': 'application/json',
        'X-WA-Signature': signature,
        'X-WA-Event': eventType,
      },
      timeout: 5000,
      // Laravel may briefly 502 during deploys; do not retry forever.
      validateStatus: (s) => s >= 200 && s < 500,
    });
  } catch (err) {
    logger.warn({ err: err.message, eventType }, 'webhook delivery failed');
  }
}
