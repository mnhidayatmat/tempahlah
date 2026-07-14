import 'dotenv/config';
import path from 'node:path';

function required(name) {
  const v = process.env[name];
  if (!v) throw new Error(`Missing required env var: ${name}`);
  return v;
}

export const config = {
  port: Number(process.env.PORT ?? 3001),
  host: process.env.HOST ?? '127.0.0.1',

  authToken: required('SIDECAR_AUTH_TOKEN'),
  webhookSecret: required('WEBHOOK_SECRET'),
  webhookUrl: required('LARAVEL_WEBHOOK_URL'),

  sessionDir: path.resolve(process.env.SESSION_DIR ?? '/var/lib/tempahlah-wa-sessions'),

  sendMinGapMs: Number(process.env.SEND_MIN_GAP_MS ?? 8000),

  // Drop inbound messages older than this at the source. WhatsApp delivers
  // every message that queued while a linked device was offline the moment it
  // reconnects — those must NOT be forwarded, or the AI agent replies to
  // stale/long-past messages "out of the blue" on connect. Keep this aligned
  // with Laravel's AGENT_MAX_INBOUND_AGE_MINUTES (default 5 min = 300 s).
  maxInboundAgeSeconds: Number(process.env.MAX_INBOUND_AGE_SECONDS ?? 300),

  logLevel: process.env.LOG_LEVEL ?? 'info',
};
