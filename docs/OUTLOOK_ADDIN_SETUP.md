# BansalLaw CRM — Outlook Add-in Production & Local Guide

## 1. Overview
The **BansalLaw CRM Outlook Add-in** ("Save to BansalLaw CRM") lets staff link emails and attachments to client matters without downloading `.msg` / `.eml` files.

### Live production URL
**https://legal.bansalcrm.com/**

| Endpoint | URL |
|---|---|
| Taskpane | https://legal.bansalcrm.com/outlook-addin/taskpane |
| Dynamic manifest | https://legal.bansalcrm.com/outlook-addin/manifest.xml |
| Download manifest | https://legal.bansalcrm.com/outlook-addin/manifest/download |
| Static deploy file | `public/outlook-addin/manifest.production.xml` |

Local development uses a separate add-in id and display name (**Save to BansalLaw CRM (Local)**) so production and local can both be installed in Outlook at once.

---

## 2. Architecture & Components

| Component | Path | Responsibility |
|---|---|---|
| **Controller** | `app/Http/Controllers/CRM/OutlookAddinController.php` | Taskpane, dynamic manifest, match, folders, save |
| **Taskpane** | `resources/views/outlook_addin/taskpane.blade.php` | Office.js UI |
| **Manifest command** | `php artisan outlook:manifest` | Builds production and/or local XML |
| **CSRF exemption** | `app/Http/Middleware/VerifyCsrfToken.php` | `outlook-addin/*` |
| **Config** | `config/services.php` → `outlook_addin` | Production + local tunnel URLs |

---

## 3. Environment variables

```env
# Live CRM (always used when APP_ENV=production)
OUTLOOK_ADDIN_PRODUCTION_URL=https://legal.bansalcrm.com

# Local HTTPS tunnel only — omit / leave empty on production servers
OUTLOOK_ADDIN_BASE_URL=https://YOUR-TUNNEL.trycloudflare.com

OUTLOOK_ADDIN_LOG_RETENTION_DAYS=10
```

| Environment | Which URL the add-in uses |
|---|---|
| `APP_ENV=production` | Always `OUTLOOK_ADDIN_PRODUCTION_URL` |
| `APP_ENV=local` | `OUTLOOK_ADDIN_BASE_URL` if set, else production URL |
| Request host is a tunnel | Dynamic `/outlook-addin/manifest.xml` adapts to that host |

---

## 4. Production (live)

### Generate manifests
```bash
php artisan outlook:manifest --production
```
Writes:
- `public/outlook-addin/manifest.production.xml`
- `public/outlook-addin/manifest.xml` (production copy)

### Deploy checklist (server)
1. Deploy code that includes Outlook add-in routes (`/outlook-addin/*`).
2. On the server `.env`:
   ```env
   APP_ENV=production
   APP_URL=https://legal.bansalcrm.com
   OUTLOOK_ADDIN_PRODUCTION_URL=https://legal.bansalcrm.com
   # Do NOT set a tunnel URL here
   ```
3. Confirm:
   - https://legal.bansalcrm.com/outlook-addin/taskpane
   - https://legal.bansalcrm.com/outlook-addin/manifest.xml
   - https://legal.bansalcrm.com/img/logo_new.png
4. Microsoft 365 Admin Center → **Settings** → **Integrated apps** → **Upload custom apps** → Office Add-in:
   - Link: `https://legal.bansalcrm.com/outlook-addin/manifest.xml`  
   - Or upload `manifest.production.xml`
5. Assign to the firm (or groups) and deploy.

---

## 5. Local development (same machine, alongside production)

Outlook requires **HTTPS**, so local HTTP (`127.0.0.1:8001`) must sit behind a tunnel.

1. Start CRM on this checkout:
   ```bash
   php artisan serve --port=8001
   ```
2. Start Cloudflare tunnel (keep running):
   ```bash
   npx cloudflared tunnel --url http://127.0.0.1:8001
   ```
3. Put the tunnel URL in `.env` and regenerate local manifests:
   ```env
   OUTLOOK_ADDIN_PRODUCTION_URL=https://legal.bansalcrm.com
   OUTLOOK_ADDIN_BASE_URL=https://YOUR-TUNNEL.trycloudflare.com
   ```
   ```bash
   php artisan config:clear
   php artisan outlook:manifest --all
   ```
4. Sideload **local** add-in in Outlook:
   - **Apps** → **My add-ins** → **Custom add-ins** → **Add from File…**
   - Choose `public/outlook-addin/manifest.local.xml` (not `manifest.xml` — that file stays production)
   - Ribbon label: **Save to CRM (Local)**
5. Production add-in (if already deployed) remains **Save to CRM** and still hits `legal.bansalcrm.com`.

### Generate both at once
```bash
php artisan outlook:manifest --all
```

### Useful flags
```bash
php artisan outlook:manifest --production
php artisan outlook:manifest --local --url=https://YOUR-TUNNEL.trycloudflare.com
php artisan outlook:manifest --url=https://legal.bansalcrm.com --stdout
```

**Notes**
- Quick tunnels get a **new URL every restart** — update `.env`, run `--all` or `--local`, re-sideload.
- Prefer Cloudflare over localtunnel (`loca.lt`).
- Point the tunnel at **8001** for this repo (not an older checkout on 8000).

---

## 6. Security & Frame Headers
Taskpane responses allow Office iframes:
```http
Content-Security-Policy: frame-ancestors 'self' https://*.office.com https://*.office365.com https://*.outlook.com https://*.microsoft.com http://localhost:* https://localhost:*;
```
`X-Frame-Options` is removed for the taskpane. CSRF is exempt for `outlook-addin/*`.

---

## 7. Logging & retention

Logs under `storage/logs/outlook-addin/`:

| File | Contents |
|---|---|
| `outlook-addin-YYYY-MM-DD.log` | All events |
| `outlook-addin-success-YYYY-MM-DD.log` | Successful saves |
| `outlook-addin-errors-YYYY-MM-DD.log` | Failures |

Retention defaults to **10 days** (`OUTLOOK_ADDIN_LOG_RETENTION_DAYS`), enforced on write and via:
```bash
php artisan logs:prune-email-ops
```
