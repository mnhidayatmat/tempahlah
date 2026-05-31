# Tempahlah nginx config

Production nginx vhost for `tempahlah.com` (+ `www.tempahlah.com` 301 →
apex + wildcard tenant subdomains served via the same root). Committed
here as the source of truth so Hostiqo / server rebuilds can re-apply
it without reverse-engineering tweaks we made on the live box.

## File

`deploy/nginx-tempahlah.com.conf` — drop-in vhost for nginx 1.24+ on
Ubuntu 24.04.

## What's in it that you won't get from a default Laravel template

1. **Livewire 4 bypass** (block beginning `location ^~ /livewire-`).
   Without this, the generic static-asset regex catches anything ending
   in `.js`, tries to serve it from disk, finds nothing, and 404s —
   which silently breaks **every Livewire interaction** because the
   browser can't load `livewire.min.js` (it's a Laravel route, not a
   file on disk). The `^~` prefix match wins over regex matches, so
   `/livewire-<hash>/...` requests reach PHP-FPM instead of dying in
   the static-asset block.

   Symptom if missing: console shows
   `GET https://tempahlah.com/livewire-<hash>/livewire.min.js 404`
   and every `wire:click` button on the dashboard does nothing.

2. **www → apex 301** at request time (`if ($host != 'tempahlah.com')`).

3. **ACME challenge carve-out** (`location ^~ /.well-known/acme-challenge/`)
   so Let's Encrypt cert renewal works without disabling the firewall
   block on hidden files.

4. **PHP-FPM socket pinned to `php8.4-fpm-tempahlahcom.sock`** — Hostiqo
   creates a per-site PHP-FPM pool. If you switch PHP versions, update
   this socket path.

5. **Security headers** + **denied file extensions** (`.env`, `.log`,
   `.sql`, `.sqlite`, etc.) — defence-in-depth for the times Laravel's
   own routing protection slips.

## Install on a fresh server

```bash
# 1. Copy into nginx sites
sudo cp deploy/nginx-tempahlah.com.conf /etc/nginx/sites-available/tempahlah.com.conf
sudo ln -sf /etc/nginx/sites-available/tempahlah.com.conf \
            /etc/nginx/sites-enabled/tempahlah.com.conf

# 2. Validate
sudo nginx -t

# 3. Reload (no downtime — open connections are kept)
sudo systemctl reload nginx
```

## Updating it after the fact

If you edit the file on the server (e.g. via Hostiqo's panel), commit
the new version back here so the repo stays the canonical source:

```bash
# On the server
cat /etc/nginx/sites-enabled/tempahlah.com.conf

# On your laptop, paste into deploy/nginx-tempahlah.com.conf, commit, push.
```

## Notes

- This config covers the **apex domain** AND **every tenant subdomain**
  (`server_name tempahlah.com www.tempahlah.com *.tempahlah.com;`).
  Laravel resolves the tenant from the host header via the
  `ResolveTenantFromSubdomain` middleware.
- The `if ($host = 'www.tempahlah.com')` block is intentionally narrow
  — it ONLY 301-redirects `www.` to the apex. A more permissive
  `!= 'tempahlah.com'` would catch every subdomain and break tenant
  routing.
- SSL paths point at the **wildcard cert** issued via acme.sh + Name.com
  DNS API, NOT the certbot HTTP-01 cert. See
  [`INSTALL-WILDCARD-SSL.md`](INSTALL-WILDCARD-SSL.md) for the
  wildcard-cert install + auto-renewal setup. The old certbot cert
  at `/etc/letsencrypt/live/tempahlah.com/` is no longer used by
  nginx but auto-renews harmlessly.
- If `nginx -t` warns
  `conflicting server name "www.tempahlah.com" on 0.0.0.0:443` it
  means another vhost in `sites-enabled/` also declares `www.tempahlah.com`.
  nginx ignores the duplicate (no functional impact) — but it's worth
  finding and removing the offender.
