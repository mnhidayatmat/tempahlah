import express from 'express';
import { config } from './config.js';
import { logger } from './logger.js';
import { requireBearer } from './auth.js';
import { sessions } from './session-manager.js';

const app = express();
app.use(express.json({ limit: '2mb' }));

// All endpoints require the shared bearer token. Loopback-only host binding
// is the first line of defence; the bearer is the second.
app.use(requireBearer);

app.get('/health', (_req, res) => {
  res.json({ ok: true, ts: new Date().toISOString() });
});

/**
 * POST /sessions/:tenantId/start
 * Boot (or rehydrate) the tenant's Baileys session. Returns current status.
 * If a QR is needed, the QR data URL arrives shortly via the
 * `session.qr` webhook — clients should poll GET /status for it.
 */
app.post('/sessions/:tenantId/start', async (req, res) => {
  try {
    const entry = await sessions.start(req.params.tenantId);
    res.json({ ok: true, status: entry.status });
  } catch (err) {
    logger.error({ err: err.message, tenantId: req.params.tenantId }, 'start failed');
    res.status(500).json({ ok: false, error: err.message });
  }
});

/**
 * GET /sessions/:tenantId/status
 * Cheap polling endpoint. Returns the in-memory state — does not touch WA.
 */
app.get('/sessions/:tenantId/status', (req, res) => {
  res.json(sessions.status(req.params.tenantId));
});

/**
 * POST /sessions/:tenantId/send
 * Body: { to: "+60123456789", body: "Hi", media?: { url, kind, filename } }
 */
app.post('/sessions/:tenantId/send', async (req, res) => {
  const { to, body, media } = req.body ?? {};
  if (!to || !body) {
    return res.status(422).json({ ok: false, error: 'to + body required' });
  }
  try {
    const result = await sessions.send(req.params.tenantId, to, body, media);
    res.json({ ok: true, ...result });
  } catch (err) {
    const code = err.code ?? 'SEND_FAILED';
    const httpCode = code === 'RATE_LIMITED' ? 429 :
                     code === 'NOT_CONNECTED' ? 409 : 500;
    res.status(httpCode).json({
      ok: false,
      code,
      error: err.message,
      retryAfterMs: err.retryAfterMs,
    });
  }
});

/**
 * POST /sessions/:tenantId/logout
 * Clean logout + wipe local auth state. Tenant has to re-scan to come back.
 */
app.post('/sessions/:tenantId/logout', async (req, res) => {
  try {
    await sessions.logout(req.params.tenantId);
    res.json({ ok: true });
  } catch (err) {
    res.status(500).json({ ok: false, error: err.message });
  }
});

app.use((err, _req, res, _next) => {
  logger.error({ err: err.message, stack: err.stack }, 'unhandled');
  res.status(500).json({ ok: false, error: 'internal' });
});

app.listen(config.port, config.host, () => {
  logger.info({ port: config.port, host: config.host }, 'wa-sidecar listening');
});

process.on('SIGTERM', () => {
  logger.info('SIGTERM — exiting');
  process.exit(0);
});
