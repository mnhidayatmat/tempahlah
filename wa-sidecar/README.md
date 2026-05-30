# Tempahlah WhatsApp sidecar

Stand-alone Node service that wraps [Baileys](https://github.com/WhiskeySockets/Baileys) so the Laravel app can:

- onboard each tenant by QR scan
- send transactional WhatsApp messages on the tenant's behalf
- receive inbound messages (for opt-out keywords like `STOP` / `BERHENTI`)

**Not** to be exposed publicly. Binds to `127.0.0.1:3001`. Laravel reaches it over the loopback interface.

## Quick start (local)

```bash
cd wa-sidecar
cp .env.example .env
# fill SIDECAR_AUTH_TOKEN, WEBHOOK_SECRET, LARAVEL_WEBHOOK_URL
npm install
npm run dev
```

## API

All requests require `Authorization: Bearer ${SIDECAR_AUTH_TOKEN}`.

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/health` | Liveness probe |
| `POST` | `/sessions/:tenantId/start` | Boot or rehydrate a tenant session |
| `GET` | `/sessions/:tenantId/status` | Poll for QR + connection state |
| `POST` | `/sessions/:tenantId/send` | Send a message (text or text + media) |
| `POST` | `/sessions/:tenantId/logout` | Logout + wipe local auth state |

## Webhooks → Laravel

Sidecar POSTs JSON to `${LARAVEL_WEBHOOK_URL}` for these events:

- `session.qr` — new QR code generated (data URL inside payload)
- `session.connected` — scan succeeded
- `session.banned` — WhatsApp closed the connection with logged-out / forbidden
- `session.disconnected` — clean logout via the sidecar
- `session.error` — transient failure during reconnect
- `message.inbound` — guest sent something to the tenant

Each request carries `X-WA-Signature: hex(hmacsha256(WEBHOOK_SECRET, body))`.

## Session storage

Each tenant's Baileys auth state lives at `${SESSION_DIR}/{tenantId}/`. Persistent across restarts. To force a re-scan, delete the directory.

## Production deploy

Runs under systemd as `tempahlah-wa-sidecar.service`. See `deploy/tempahlah-wa-sidecar.service` in the repo root.
