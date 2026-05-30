# Installing the WhatsApp sidecar on production

One-time setup. After this, the Hostiqo webhook will sync code on every push; only the systemd unit + `wa-sidecar/.env` need manual care.

## Prerequisites

```bash
# 1. Node.js 20+ (we use 22 LTS). Skip if already installed.
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs

node --version    # → v22.x
npm --version
```

## Install

```bash
# 2. Install deps in the sidecar dir.
cd /var/www/tempahlah_com/wa-sidecar
sudo -u www-data npm ci --omit=dev

# 3. Configure the sidecar.
sudo -u www-data cp .env.example .env
sudo -u www-data nano .env
#   Set SIDECAR_AUTH_TOKEN  = openssl rand -hex 32  (output)
#   Set WEBHOOK_SECRET      = openssl rand -hex 32  (output, different value)
#   LARAVEL_WEBHOOK_URL     = http://127.0.0.1/api/wa/webhook
#                            (or https://tempahlah.com/api/wa/webhook if loopback won't work)
sudo chmod 640 .env
sudo chown www-data:www-data .env

# 4. Mirror the secrets into Laravel's .env so they agree.
cd /var/www/tempahlah_com
sudo -u www-data nano .env
#   WHATSAPP_DRIVER=baileys
#   WHATSAPP_SIDECAR_URL=http://127.0.0.1:3001
#   WHATSAPP_SIDECAR_TOKEN=<SAME as SIDECAR_AUTH_TOKEN>
#   WHATSAPP_WEBHOOK_SECRET=<SAME as WEBHOOK_SECRET>

sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache

# 5. Install + start the systemd unit.
sudo cp deploy/tempahlah-wa-sidecar.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now tempahlah-wa-sidecar
sudo systemctl status tempahlah-wa-sidecar --no-pager

# 6. Smoke test.
curl -s http://127.0.0.1:3001/health \
  -H "Authorization: Bearer $(grep SIDECAR_AUTH_TOKEN wa-sidecar/.env | cut -d= -f2)"
# → {"ok":true,"ts":"..."}
```

## On every deploy (handled by webhook)

The Hostiqo webhook already runs `composer install && php artisan migrate --force` on every push. For the sidecar, add the equivalent in the panel:

```bash
cd wa-sidecar && npm ci --omit=dev && sudo systemctl restart tempahlah-wa-sidecar
```

(or just SSH in and run those three commands after pushes that touch `wa-sidecar/`).

## Logs

```bash
sudo journalctl -u tempahlah-wa-sidecar -n 200 -f
```

## Reset a tenant's session manually

```bash
sudo rm -rf /var/lib/tempahlah-wa-sessions/{TENANT_ID}
sudo systemctl restart tempahlah-wa-sidecar
```

The tenant will see "Disconnected" in the dashboard and can re-scan.
