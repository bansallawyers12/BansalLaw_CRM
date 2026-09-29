# BansalLaw CRM — Outlook Add-in Production & Local Guide

## 1. Overview
The **BansalLaw CRM Outlook Add-in** ("Save to BansalLaw CRM") allows lawyers and staff to directly link emails and attachments to client matters in BansalLaw CRM without downloading `.msg` or `.eml` files to disk or dealing with drag-and-drop browser restrictions.

### Key Capabilities:
- **Works in All Outlook Clients:** New Outlook for Windows, Classic Outlook, Outlook for Mac, and Outlook on the Web (`outlook.office.com`).
- **Automatic Matter Matching:** Detects client reference numbers (`GURM2600071`), matter codes (`CRM_1`, `FAM_1`), client email addresses, and subject lines.
- **Manual Matter Search:** Fast autocomplete matter search if auto-match is not certain.
- **Folder Selection for Attachments:**
  - Files attachments into specific matter document categories (from `visa_document_types`, e.g. *General*, *Authority to Act*, *Accounts*, etc.).
  - Supports personal document categories or email-only storage.
  - Supports per-attachment folder overrides when emails contain multiple files.
- **Direct S3 Document Storage:** Documents are uploaded directly to the CRM's S3 storage and linked to both the Matter Documents tab and the Emails tab.
- **Persistent Staff Attribution:** Remembers the staff user's selection across email sessions.

---

## 2. Architecture & Components

| Component | Path | Responsibility |
|---|---|---|
| **Controller** | `app/Http/Controllers/CRM/OutlookAddinController.php` | Serves taskpane, generates dynamic manifest XML, performs matching, queries folders, ingests email & attachments. |
| **Taskpane View** | `resources/views/outlook_addin/taskpane.blade.php` | Office.js UI rendered in the Outlook sidepanel with Bansal branding, attachment controls, folder dropdowns, and status feedback. |
| **Manifest Generator** | `app/Console/Commands/GenerateOutlookAddinManifest.php` | Artisan command (`php artisan outlook:manifest`) to build production or local XML. |
| **Manifest Route** | `GET /outlook-addin/manifest.xml` | Dynamically serves manifest XML tailored to the requesting host. |
| **CSRF Exemption** | `app/Http/Middleware/VerifyCsrfToken.php` | Exempts `outlook-addin/*` to support Office.js iframe webviews without partitioned cookie issues. |

---

## 3. Configuration & Environment Variables

Add to `.env` (optional override):
```env
# Optional: Set explicit base URL for the Outlook Add-in (defaults to https://legal.bansalcrm.com)
OUTLOOK_ADDIN_BASE_URL=https://legal.bansalcrm.com
```

If `OUTLOOK_ADDIN_BASE_URL` is omitted, the add-in automatically uses `https://legal.bansalcrm.com` for production or the active tunnel host for local dev.

---

## 4. How to Generate Manifest for Production

Run the Artisan command with the live production URL:
```bash
php artisan outlook:manifest --url=https://legal.bansalcrm.com
```
This updates `public/outlook-addin/manifest.xml` and `manifest.production.xml` with production HTTPS URLs (`https://legal.bansalcrm.com`).

To test what the XML looks like without modifying the file:
```bash
php artisan outlook:manifest --url=https://legal.bansalcrm.com --stdout
```

---

## 5. Production Deployment (Microsoft 365 Admin Center)

For firm-wide deployment across all staff members without individual manual sideloading:

1. Log in to the **[Microsoft 365 Admin Center](https://admin.microsoft.com)** with Global Administrator or Exchange Administrator credentials.
2. Navigate to **Settings** &rarr; **Integrated apps**.
3. Click **Upload custom apps**.
4. Choose **Office Add-in** as the app type.
5. Choose one of the deployment methods:
   - **Provide link to manifest file:**
     Enter: `https://legal.bansalcrm.com/outlook-addin/manifest.xml`
   - **Upload manifest file (.xml) from device:**
     Upload `public/outlook-addin/manifest.production.xml` (or `public/outlook-addin/manifest.xml`).
6. Specify target users:
   - **Entire organization** (recommended for all firm staff), or
   - Specific groups (e.g. Legal Team, Migration Agents).
7. Review permissions (`ReadWriteItem`) and click **Deploy**.
8. Within a few hours, the **Save to CRM** button will appear on all staff Outlook applications automatically.

---

## 6. Local Development & Tunnel Setup

When developing locally:
1. Start your local server:
   ```bash
   php artisan serve --port=8000
   ```
2. Start an HTTPS tunnel (Microsoft Office requires HTTPS):
   ```bash
   npx localtunnel --port 8000
   ```
   *(Note the generated URL, e.g. `https://tall-zoos-pay.loca.lt`)*
3. Generate the manifest for the active tunnel:
   ```bash
   php artisan outlook:manifest --url=https://tall-zoos-pay.loca.lt
   ```
4. Sideload into Outlook:
   - In Outlook, click **Apps** &rarr; **Add apps** &rarr; **Manage add-ins**.
   - Under **My add-ins** &rarr; **Custom add-ins**, choose **Add from File...** and select `public/outlook-addin/manifest.xml`.

---

## 7. Security & Frame Headers
The taskpane endpoint sends the following CSP header to permit embedding in Microsoft 365 and Office clients:
```http
Content-Security-Policy: frame-ancestors 'self' https://*.office.com https://*.office365.com https://*.outlook.com https://*.microsoft.com http://localhost:* https://localhost:*;
```
And removes `X-Frame-Options` so modern browser iframes in Outlook Web are not blocked.

---

## 8. Logging & 10-Day Automatic Retention Policy

All Outlook Add-in actions (successes, errors, exceptions, and frontend telemetry) are automatically logged date-wise into dedicated log files under `storage/logs/outlook-addin/`:

| Log File | Contents |
|---|---|
| `outlook-addin-YYYY-MM-DD.log` | Consolidated chronological log of all events (`[SUCCESS]`, `[ERROR]`, `[INFO]`) with full payload details. |
| `outlook-addin-success-YYYY-MM-DD.log` | Dedicated success log for fast inspection of saved emails, document IDs, and attachment mappings. |
| `outlook-addin-errors-YYYY-MM-DD.log` | Dedicated error log for fast troubleshooting of validation failures, auth issues, or upload errors. |

### Automatic 10-Day Retention Window
- Only files from the **current date and the last 10 days** are kept on the server.
- Files older than 10 days are **automatically deleted** on every write operation and during the daily scheduled cleanup command:
  ```bash
  php artisan logs:prune-email-ops
  ```
- No manual log cleanup is needed.
