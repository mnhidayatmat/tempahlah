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

    // Force every outbound business message to be non-ephemeral. If the host
    // has set an account-level "disappearing messages" default (e.g. 90 days),
    // WhatsApp would otherwise expire our booking confirmations, payment
    // receipts and agent replies — leaving guests AND the host with no audit
    // trail. ephemeralExpiration:0 overrides the chat default per-message.
    const sendOpts = { ephemeralExpiration: 0 };

    let result;
    if (media?.url) {
      const buffer = await downloadMedia(media.url);
      if (media.kind === 'pdf' || media.kind === 'doc') {
        result = await entry.sock.sendMessage(jid, {
          document: buffer,
          mimetype: media.kind === 'pdf' ? 'application/pdf' : 'application/octet-stream',
          fileName: media.filename ?? 'document.pdf',
          caption: body,
        }, sendOpts);
      } else if (media.kind === 'image') {
        result = await entry.sock.sendMessage(jid, {
          image: buffer,
          caption: body,
        }, sendOpts);
      } else {
        result = await entry.sock.sendMessage(jid, { text: body }, sendOpts);
      }
    } else {
      result = await entry.sock.sendMessage(jid, { text: body }, sendOpts);
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

        // Force-off the account-level "Default message timer" (the 24h /
        // 7d / 90d disappearing-messages default WhatsApp applies to every
        // NEW chat). Without this, even though we pass ephemeralExpiration:0
        // per outbound, an inbound message arriving first can land in a
        // chat with the host's account default already applied — so the
        // host's own copy of confirmations, invoices and receipts vanishes
        // after the timer. The booking-platform audit trail must NEVER
        // expire on the host's device.
        //
        // Baileys exposes this as updateDefaultDisappearingMode(seconds);
        // 0 = off. We pass it on every connect (idempotent) so a host who
        // re-enables the default in their phone settings between sessions
        // gets it flipped back off the moment our session reconnects.
        try {
          if (typeof sock.updateDefaultDisappearingMode === 'function') {
            await sock.updateDefaultDisappearingMode(0);
            childLogger.info('account default disappearing-messages forced OFF');
          } else {
            childLogger.warn(
              'sock.updateDefaultDisappearingMode is not available on this Baileys version — skipping account-level force-off (per-message ephemeralExpiration:0 still applies)',
            );
          }
        } catch (err) {
          // Don't fail the connection — log and continue. Per-message
          // ephemeralExpiration:0 still protects our outbound messages.
          childLogger.warn(
            { err: err?.message },
            'failed to force-off account default disappearing-messages',
          );
        }

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

        // HARD GUARD: only ever act on a genuine 1:1 customer DM. WhatsApp
        // delivers status/story updates, group chatter, channel posts and
        // broadcast-list messages through this SAME `messages.upsert`
        // 'notify' event. A text STATUS/STORY in particular arrives with
        // remoteJid = 'status@broadcast' and the poster's phone JID in
        // key.senderPn — which resolvePhoneNumberJid() below would happily
        // return, so the sidecar forwarded it as a normal inbound and the AI
        // agent replied to the person's story out of nowhere. Drop anything
        // that isn't a direct chat right here, before it can reach Laravel.
        // Real DMs use @s.whatsapp.net or (LID-routed) @lid; everything else
        // (status@broadcast, @g.us groups, @newsletter channels, @broadcast
        // lists) is NOT a customer messaging us.
        if (!isDirectChat(m.key.remoteJid)) {
          childLogger.info(
            { remoteJid: m.key.remoteJid },
            'skipping non-DM inbound (status/story, group, channel or broadcast) — the agent only replies to direct customer chats',
          );
          continue;
        }

        const text =
          m.message?.conversation ??
          m.message?.extendedTextMessage?.text ??
          '';
        if (!text) continue;

        // Freshness gate — the single most important guard against the AI
        // agent replying to a customer "out of the blue" on connect.
        //
        // WhatsApp holds every message that arrived while this linked device
        // was offline and flushes them ALL as `type: 'notify'` the instant we
        // reconnect (they are new to *this* socket, so the notify/append filter
        // above does not catch them). Their real send time, however, is in the
        // past. Drop anything older than the cutoff right here so it never
        // reaches Laravel — protecting the agent reply, the out-of-hours
        // auto-reply, and every other inbound path at once.
        //
        // m.messageTimestamp is Unix SECONDS (a protobuf Long or a number).
        // Fail OPEN when it's missing/zero: forward it, so a genuinely new
        // message with an odd payload is never silently swallowed.
        const sentAtSec = Number(
          m.messageTimestamp?.toNumber?.() ?? m.messageTimestamp ?? 0,
        );
        if (sentAtSec > 0) {
          const ageSec = Math.floor(Date.now() / 1000) - sentAtSec;
          if (ageSec > config.maxInboundAgeSeconds) {
            childLogger.info(
              { ageSec, cutoffSec: config.maxInboundAgeSeconds, from: m.key.remoteJid },
              'dropping stale inbound (WhatsApp flushed an offline/old message on connect) — not forwarding',
            );
            continue;
          }
        }

        // WhatsApp's Multi-Device protocol routes some senders through
        // anonymized "LID" JIDs (e.g. 204062519738487@lid) instead of the
        // expected 60xxxxxxxxx@s.whatsapp.net. We can't reply to a LID —
        // outbound to that JID looks "sent" but never reaches the user.
        // Resolve to the underlying phone-number JID using whatever Baileys
        // surfaces, in priority order.
        const pnJid = resolvePhoneNumberJid(m.key, sock);

        if (!pnJid) {
          childLogger.warn(
            { keyDump: m.key, remoteJid: m.key.remoteJid },
            'inbound from unresolvable JID (likely @lid with no phone mapping yet) — skipping to avoid replying into the void',
          );
          continue;
        }

        const phone = pnJid.split('@')[0];
        await postWebhook('message.inbound', {
          tenantId,
          fromPhone: `+${phone}`,
          body: text,
          // WhatsApp's real send time (epoch seconds), so Laravel gates on when
          // the customer actually sent it — not when we happened to receive it.
          // 0 when unknown (Laravel then treats it as fresh, fail-open).
          sentAtUnix: sentAtSec,
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

/**
 * True only for a genuine 1:1 direct-message chat — the ONLY context the AI
 * agent is allowed to auto-reply into. WhatsApp routes several non-customer
 * message types through the same messages.upsert 'notify' event:
 *   - status@broadcast   → someone's status / story update
 *   - <id>@g.us          → group chat
 *   - <id>@newsletter    → channel post
 *   - <id>@broadcast     → broadcast-list message
 * A real DM ends in @s.whatsapp.net, or @lid when WhatsApp's multi-device
 * protocol routes the sender through an anonymized Linked-Identity JID.
 * Anything else must be dropped before it can trigger a reply.
 */
function isDirectChat(jid) {
  if (typeof jid !== 'string' || jid === '') return false;
  if (jid === 'status@broadcast') return false;
  return jid.endsWith('@s.whatsapp.net') || jid.endsWith('@lid');
}

/**
 * Resolve an inbound message's true phone-number JID. WhatsApp's MD
 * protocol increasingly routes messages through "LID" (Linked-Identity)
 * JIDs like `204062519738487@lid` — sending a reply to that JID looks
 * "sent" but never lands in the user's chat.
 *
 * Strategies in priority order:
 *   1. m.key.remoteJid                — already a phone JID? use it.
 *   2. m.key.remoteJidAlt             — Baileys exposes the paired PN JID
 *                                       when it knows the LID↔PN mapping.
 *   3. m.key.senderPn                 — sender's phone-number JID (newer Baileys).
 *   4. sock.signalRepository.lidMapping.getPNForLID(jid)
 *                                     — explicit lookup against the live store.
 *
 * Returns the resolved `xxx@s.whatsapp.net` JID, or null if none of the
 * strategies yield a phone-number JID (caller should skip the message).
 */
function resolvePhoneNumberJid(key, sock) {
  const isPN = (j) => typeof j === 'string' && j.endsWith('@s.whatsapp.net');

  if (isPN(key.remoteJid)) return key.remoteJid;
  if (isPN(key.remoteJidAlt)) return key.remoteJidAlt;
  if (isPN(key.senderPn)) return key.senderPn;

  // Last-ditch: ask Baileys's LID mapping store to resolve the @lid JID.
  // The API surface varies across Baileys versions — try the common
  // shapes, swallow errors so a missing store doesn't kill the inbound
  // handler.
  const lidJid = key.remoteJid;
  if (typeof lidJid === 'string' && lidJid.endsWith('@lid')) {
    try {
      const store = sock?.signalRepository?.lidMapping;
      const fn = store?.getPNForLID ?? store?.getPnForLid;
      if (typeof fn === 'function') {
        const resolved = fn.call(store, lidJid);
        // Some impls are async; if so we get a Promise — best-effort sync only
        // here. The .then branch is just for visibility in logs if it ever
        // returns one.
        if (resolved && typeof resolved === 'string' && isPN(resolved)) {
          return resolved;
        }
      }
    } catch { /* fall through to null */ }
  }

  return null;
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
