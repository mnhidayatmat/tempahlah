import makeWASocket, {
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
} from '@whiskeysockets/baileys';
import { Boom } from '@hapi/boom';

// After this many consecutive QRs without a scan, stop. WhatsApp's
// anti-abuse system will start refusing new pair requests if we loop
// indefinitely.
const MAX_UNSCANNED_QRS = 5;
import path from 'node:path';
import fs from 'node:fs/promises';
import QRCode from 'qrcode';
import { config } from './config.js';
import { logger } from './logger.js';
import { postWebhook } from './webhook.js';

/**
 * Holds one Baileys socket per tenant.
 *
 * Persistence: Baileys's useMultiFileAuthState() writes creds + signal keys
 * to disk under SESSION_DIR/{tenant_id}/. Survives restarts. If/when we add
 * multi-droplet support, swap for an S3-backed auth state.
 *
 * Rate-limiting: each session tracks `lastSentAt`. Send calls reject with
 * RATE_LIMITED if the gap is too small. Random jitter keeps the cadence
 * organic-looking.
 */
class SessionManager {
  /** @type {Map<string, SessionEntry>} */
  #sessions = new Map();

  async start(tenantId) {
    if (this.#sessions.has(tenantId)) {
      return this.#sessions.get(tenantId);
    }
    const entry = await this.#bootSession(tenantId);
    this.#sessions.set(tenantId, entry);
    return entry;
  }

  status(tenantId) {
    const entry = this.#sessions.get(tenantId);
    if (!entry) return { status: 'disconnected' };
    return {
      status: entry.status,
      phone: entry.phone,
      pushName: entry.pushName,
      qrDataUrl: entry.qrDataUrl,
      qrGeneratedAt: entry.qrGeneratedAt,
      lastError: entry.lastError,
    };
  }

  async send(tenantId, recipientPhone, body, media) {
    const entry = this.#sessions.get(tenantId);
    if (!entry || entry.status !== 'connected') {
      // Sidecar refusing send while Laravel may still think we're connected
      // (the connection.update 'close' webhook may have failed, or Baileys
      // dropped silently after a transient error). Fire a disconnected
      // webhook so Laravel re-syncs and the dashboard shows "Reconnect".
      try {
        await postWebhook('session.disconnected', {
          tenantId,
          reason: entry ? `session ${entry.status}` : 'no session in sidecar memory',
        });
      } catch { /* webhook delivery failure shouldn't mask the original error */ }
      const err = new Error('Session not connected');
      err.code = 'NOT_CONNECTED';
      throw err;
    }

    const now = Date.now();
    const gap = now - entry.lastSentAt;
    const jitter = Math.floor((Math.random() * 0.6 - 0.3) * config.sendMinGapMs);
    const minGap = config.sendMinGapMs + jitter;
    if (gap < minGap) {
      const err = new Error(`Rate limited: ${minGap - gap}ms remaining`);
      err.code = 'RATE_LIMITED';
      err.retryAfterMs = minGap - gap;
      throw err;
    }

    const jid = toJid(recipientPhone);

    let result;
    if (media?.url) {
      const buffer = await downloadMedia(media.url);
      if (media.kind === 'pdf' || media.kind === 'doc') {
        result = await entry.sock.sendMessage(jid, {
          document: buffer,
          mimetype: media.kind === 'pdf' ? 'application/pdf' : 'application/octet-stream',
          fileName: media.filename ?? 'document.pdf',
          caption: body,
        });
      } else if (media.kind === 'image') {
        result = await entry.sock.sendMessage(jid, {
          image: buffer,
          caption: body,
        });
      } else {
        result = await entry.sock.sendMessage(jid, { text: body });
      }
    } else {
      result = await entry.sock.sendMessage(jid, { text: body });
    }

    entry.lastSentAt = Date.now();
    return {
      wamid: result?.key?.id,
      jid,
    };
  }

  async logout(tenantId) {
    const entry = this.#sessions.get(tenantId);
    if (entry?.sock) {
      try { await entry.sock.logout(); } catch { /* already gone */ }
    }
    this.#sessions.delete(tenantId);
    await fs.rm(this.#dirFor(tenantId), { recursive: true, force: true });
    await postWebhook('session.disconnected', { tenantId, reason: 'logout' });
  }

  #dirFor(tenantId) {
    return path.join(config.sessionDir, String(tenantId));
  }

  async #bootSession(tenantId) {
    const dir = this.#dirFor(tenantId);
    await fs.mkdir(dir, { recursive: true });
    const { state, saveCreds } = await useMultiFileAuthState(dir);
    const { version } = await fetchLatestBaileysVersion();

    /** @type {SessionEntry} */
    const entry = {
      tenantId,
      status: 'connecting',
      sock: null,
      phone: null,
      pushName: null,
      qrDataUrl: null,
      qrGeneratedAt: null,
      lastError: null,
      lastSentAt: 0,
      qrAttempts: 0,
      giveUp: false,
    };

    const childLogger = logger.child({ tenantId });

    const sock = makeWASocket({
      version,
      logger: childLogger,
      printQRInTerminal: false,
      auth: {
        creds: state.creds,
        keys: makeCacheableSignalKeyStore(state.keys, childLogger),
      },
      // Canonical Baileys browser identifier — WA recognises this as a
      // standard linked-device fingerprint and is less likely to flag the
      // pair request. Replaces our custom ['Tempahlah','Chrome',...] tuple.
      browser: Browsers.macOS('Desktop'),
      markOnlineOnConnect: true,
      generateHighQualityLinkPreview: false,
      syncFullHistory: false,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
      const { connection, lastDisconnect, qr } = update;

      if (qr) {
        entry.qrAttempts += 1;
        // Give up after MAX_UNSCANNED_QRS to avoid tripping WA's anti-abuse
        // (the "Can't link new devices right now" warning users see when we
        // try to pair too many times without a successful scan).
        if (entry.qrAttempts > MAX_UNSCANNED_QRS) {
          childLogger.warn({ qrAttempts: entry.qrAttempts }, 'giving up after unscanned QRs');
          entry.giveUp = true;
          entry.status = 'expired';
          entry.qrDataUrl = null;
          entry.lastError = 'No scan after ' + MAX_UNSCANNED_QRS + ' QR refreshes. Click Reconnect to try again.';
          try { sock.end(undefined); } catch { /* noop */ }
          this.#sessions.delete(tenantId);
          await postWebhook('session.error', {
            tenantId,
            error: entry.lastError,
            expired: true,
          });
          return;
        }
        entry.qrDataUrl = await QRCode.toDataURL(qr, { margin: 1, width: 320 });
        entry.qrGeneratedAt = new Date().toISOString();
        entry.status = 'qr_pending';
        await postWebhook('session.qr', {
          tenantId,
          qrDataUrl: entry.qrDataUrl,
          generatedAt: entry.qrGeneratedAt,
        });
      }

      if (connection === 'open') {
        entry.status = 'connected';
        entry.qrDataUrl = null;
        entry.phone = sock.user?.id?.split(':')[0]?.replace(/^/, '+');
        entry.pushName = sock.user?.name ?? null;
        entry.lastError = null;
        await postWebhook('session.connected', {
          tenantId,
          phone: entry.phone,
          pushName: entry.pushName,
        });
      }

      if (connection === 'close') {
        // If we explicitly gave up above (too many unscanned QRs), do nothing.
        if (entry.giveUp) return;

        const code = new Boom(lastDisconnect?.error)?.output?.statusCode;
        const reason = DisconnectReason[code] ?? `code:${code}`;
        childLogger.warn({ code, reason }, 'connection closed');

        // 401/403 → user logged out from phone OR WhatsApp banned the number.
        // Wipe local state so a re-scan starts fresh.
        if (code === DisconnectReason.loggedOut ||
            code === DisconnectReason.forbidden) {
          this.#sessions.delete(tenantId);
          await fs.rm(dir, { recursive: true, force: true });
          await postWebhook('session.banned', { tenantId, reason });
          return;
        }

        // 408 (timeoutError) usually means QR expired without a scan.
        // Baileys raises this every ~2.5 min. Don't auto-reconnect on this
        // class of error — that's what trips WA's anti-abuse.
        if (code === DisconnectReason.timedOut || code === 408) {
          this.#sessions.delete(tenantId);
          await postWebhook('session.error', {
            tenantId,
            error: 'QR timed out — click Reconnect to retry',
          });
          return;
        }

        // Transient — Baileys auto-reconnects, but mark state so dashboard
        // shows the spinner. Crucially, fire session.disconnected NOW so
        // Laravel doesn't keep dispatching sends into a half-dead socket.
        // If the reboot succeeds the normal session.connected webhook will
        // re-mark Laravel as connected.
        entry.status = 'connecting';
        entry.lastError = reason;
        await postWebhook('session.disconnected', { tenantId, reason });
        try {
          // IMPORTANT: REPLACE the entry, don't merge into the old one.
          // #bootSession creates a fresh entry whose event handlers are
          // closure-bound to update THAT entry. Object.assign(entry, next)
          // would copy data once but leave #sessions pointing at the old
          // entry — so when the new sock fires 'open' it mutates the
          // orphaned `next` (firing the webhook to Laravel) but send()
          // and /status keep reading the stale old entry forever stuck
          // at 'connecting'.
          const next = await this.#bootSession(tenantId);
          this.#sessions.set(tenantId, next);
        } catch (err) {
          entry.status = 'error';
          entry.lastError = err.message;
          await postWebhook('session.error', { tenantId, error: err.message });
        }
      }
    });

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
      if (type !== 'notify') return;
      for (const m of messages) {
        if (m.key.fromMe) continue;
        const text =
          m.message?.conversation ??
          m.message?.extendedTextMessage?.text ??
          '';
        if (!text) continue;
        const fromJid = m.key.remoteJid ?? '';
        const phone = fromJid.split('@')[0];
        await postWebhook('message.inbound', {
          tenantId,
          fromPhone: `+${phone}`,
          body: text,
          receivedAt: new Date().toISOString(),
        });
      }
    });

    entry.sock = sock;
    return entry;
  }
}

function toJid(phone) {
  // Strip everything that isn't a digit, then append the WA suffix.
  const digits = String(phone).replace(/[^0-9]/g, '');
  if (!digits) throw new Error('Invalid phone');
  return `${digits}@s.whatsapp.net`;
}

async function downloadMedia(url) {
  const { default: axios } = await import('axios');
  const res = await axios.get(url, {
    responseType: 'arraybuffer',
    timeout: 20000,
    maxContentLength: 25 * 1024 * 1024, // 25 MB cap
  });
  return Buffer.from(res.data);
}

/** @typedef {object} SessionEntry
 *  @property {string} tenantId
 *  @property {'connecting'|'qr_pending'|'connected'|'error'} status
 *  @property {any} sock
 *  @property {string|null} phone
 *  @property {string|null} pushName
 *  @property {string|null} qrDataUrl
 *  @property {string|null} qrGeneratedAt
 *  @property {string|null} lastError
 *  @property {number} lastSentAt
 */

export const sessions = new SessionManager();
