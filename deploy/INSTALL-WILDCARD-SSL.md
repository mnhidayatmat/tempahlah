# Wildcard SSL for `*.tempahlah.com`

Lets ANY tenant subdomain (`acme.tempahlah.com`, `wafahomestay.tempahlah.com`,
new tenants tomorrow, etc.) serve over HTTPS without per-tenant cert work.

Issued by **Let's Encrypt** via **DNS-01 challenge** against the **Name.com
API**. The acme.sh client handles the dance: it adds the `_acme-challenge`
TXT record via API, waits for propagation, completes the challenge, then
removes the TXT record. Auto-renews every ~60 days via cron.

## One-time install (already done on production)

```bash
# 1. Install acme.sh — Bash ACME client, no deps beyond curl/openssl
curl -s https://get.acme.sh | sh -s email=admin@tempahlah.com
ACMESH="$HOME/.acme.sh/acme.sh"

# 2. Issue the wildcard cert via Name.com DNS API
#    The Name.com API token must have DNS edit permission for tempahlah.com.
#    Get one at: https://www.name.com/account/settings/api → Generate Token
export Namecom_Username="<your-namecom-username>"
export Namecom_Token="<your-namecom-api-token>"

$ACMESH --issue --dns dns_namecom \
    -d tempahlah.com \
    -d '*.tempahlah.com' \
    --server letsencrypt

# 3. Install the cert + key to a stable path nginx watches.
#    The --reloadcmd is invoked on every successful renewal.
mkdir -p /etc/letsencrypt-wildcard
$ACMESH --install-cert -d tempahlah.com \
    --key-file       /etc/letsencrypt-wildcard/tempahlah.com.key \
    --fullchain-file /etc/letsencrypt-wildcard/tempahlah.com.fullchain.pem \
    --reloadcmd      "systemctl reload nginx"

# 4. Point nginx at the new cert (already done in deploy/nginx-tempahlah.com.conf)
#    Then validate + reload:
sudo nginx -t
sudo systemctl reload nginx
```

The credentials get stored once at `~/.acme.sh/account.conf` (root-only
readable) so future renewals don't need them re-entered.

## Auto-renewal

`acme.sh` installs this crontab entry on first install:

```cron
44 17 * * * "/root/.acme.sh"/acme.sh --cron --home "/root/.acme.sh" > /dev/null
```

Every day at 17:44 UTC it checks all managed certs. If one is within the
ARI-suggested renewal window (currently ~30 days before expiry for Let's
Encrypt) it renews. On success it runs the `reloadcmd` (`systemctl reload
nginx`) so the new cert goes live without manual steps.

Force-test the renewal flow:

```bash
sudo /root/.acme.sh/acme.sh --renew -d tempahlah.com --force --ecc
```

## Sanity checks

```bash
# Cert SAN list
echo | openssl s_client -connect tempahlah.com:443 -servername tempahlah.com \
    2>/dev/null | openssl x509 -noout -text | grep -A 1 "Subject Alternative Name"
# → DNS:*.tempahlah.com, DNS:tempahlah.com

# Any subdomain should pass full SSL verify
curl -s -o /dev/null -w "HTTP %{http_code}  ssl=%{ssl_verify_result}\n" \
    https://wafahomestay.tempahlah.com
# → HTTP 200  ssl=0
```

## Why this approach (vs the old HTTP-01 per-subdomain certbot setup)

Before: every new tenant required a `certbot --expand` run to add their
subdomain as a SAN to the apex cert. Worked but didn't scale — Let's
Encrypt rate-limits certs per registered domain (50/week), and slug changes
needed a re-issue every time.

Now: **one cert, infinitely many tenant subdomains**. Sign up a new tenant
at 3am → their subdomain serves over HTTPS the moment DNS propagates → zero
ops work. The old certbot-managed cert at `/etc/letsencrypt/live/tempahlah.com/`
still renews harmlessly but is no longer used by nginx. Delete with
`certbot delete --cert-name tempahlah.com` when you're ready to clean up.

## Rotating the Name.com API token

If you regenerate the token (or revoke the current one):

```bash
# Replace the stored Namecom_Token in acme.sh's account.conf
sudo nano /root/.acme.sh/account.conf
# Look for: SAVED_Namecom_Token='...'  → replace with new value

# Test the renewal still works
sudo /root/.acme.sh/acme.sh --renew -d tempahlah.com --force --ecc
```

Token only needs DNS edit permission for `tempahlah.com` — nothing more.
