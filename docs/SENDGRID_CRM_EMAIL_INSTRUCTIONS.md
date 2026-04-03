# SendGrid Integration Instructions for Migration Manager CRM

This document covers how SendGrid should be configured and used across the CRM — architecture, `.env` setup, every backend send path, what gets removed, code changes required, and troubleshooting.

> **Stack:** Laravel 12 · PostgreSQL · XAMPP/Windows  
> **Related docs:** `README.md` · `docs/MATTER_REMINDERS_IMPLEMENTATION_REVIEW.md`

---

## 1) High-Level Architecture

### What we are moving to

**SendGrid only.** Every email sent by the CRM — compose, invoices, receipts, EOI/ROI confirmations, matter reminders, appointment reminders, visa expiry reminders, and electronic signatures — goes through one named Laravel mailer: `sendgrid`.

| Mailer name | Purpose |
|-------------|---------|
| `sendgrid` | All CRM email sends without exception |

### What is being fully removed

| Removed | Reason |
|---------|--------|
| SMTP sending (Zoho and any other SMTP provider) | Replaced entirely by SendGrid |
| `custom_smtp` mailer | Was used by `CustomMailService` for dynamic per-account SMTP — no longer needed |
| `CustomMailService` dynamic SMTP transport | The whole dynamic-SMTP-switching pattern is gone |
| `EmailConfigService::applyConfig()` runtime SMTP swapping | No longer needed — SendGrid API key is the same for all senders |
| Per-account SMTP credentials in `emails` table | `smtp_host`, `smtp_port`, `smtp_encryption`, `password` columns become unused |
| ZeptoMail (API and SMTP) | Replaced by SendGrid for signature emails |
| `ZeptoMailService` | Removed after signature migration |

### New simplified flow

1. Admin or staff picks a From address in the CRM compose/send UI
2. From address and display name are resolved from the `emails` table (or `.env` fallback) — **transport credentials are not read from this table any more**
3. Every send calls `Mail::mailer('sendgrid')->...` — one transport, one API key
4. Email metadata is logged in `email_logs` table; attachments archived to S3 if configured

---

## 2) Configuration

### 2.1 Named mailer — `config/mail.php`

Replace the current `smtp` and `custom_smtp` mailer usage with a single `sendgrid` entry:

```php
'default' => env('MAIL_MAILER', 'sendgrid'),

'mailers' => [

    'sendgrid' => [
        'transport'  => 'smtp',
        'host'       => 'smtp.sendgrid.net',
        'port'       => 587,
        'username'   => 'apikey',
        'password'   => env('SENDGRID_API_KEY'),
        'encryption' => 'tls',
        'timeout'    => null,
    ],

    'log' => [
        'transport' => 'log',
        'channel'   => env('MAIL_LOG_CHANNEL'),
    ],

    'array' => [
        'transport' => 'array',
    ],

],
```

> The `smtp` and `custom_smtp` mailer entries can be removed entirely. They are no longer used.

### 2.2 SendGrid service config — `config/services.php`

Add the SendGrid block. Remove the `zeptomail` block.

```php
// ADD
'sendgrid' => [
    'api_key'    => env('SENDGRID_API_KEY'),
    'base_url'   => env('SENDGRID_BASE_URL', 'https://api.sendgrid.com'),
    'from_email' => env('SENDGRID_FROM_EMAIL', ''),
],

// REMOVE the zeptomail block entirely:
// 'zeptomail' => [ ... ],
// 'eoi' => [ ... ],   // if it only existed to support Zepto-based EOI sending
```

### 2.3 Required `.env` values

```env
# SendGrid — only key needed for transport
SENDGRID_API_KEY=SG.your_key_here

# Default From address (fallback when emails table has no match)
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Migration Manager"

# Mailer
MAIL_MAILER=sendgrid
```

### 2.4 `.env` values to remove

These are no longer needed once SMTP is removed:

```env
# REMOVE — SMTP provider settings no longer used
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=

# REMOVE — ZeptoMail no longer used
ZEPTOMAIL_API_KEY=
ZEPTOMAIL_FROM_EMAIL=
ZEPTOMAIL_FROM_NAME=
ZEPTOMAIL_API_URL=
ZEPTO_EMAIL=
ZEPTO_SMTP_HOST=
ZEPTO_SMTP_PORT=
ZEPTO_SMTP_USERNAME=
ZEPTO_SMTP_PASSWORD=
ZEPTO_SMTP_ENCRYPTION=
ZEPTO_FROM_NAME=
EOI_FROM_EMAIL=        # if it only drove ZeptoMail-based EOI sends
```

### 2.5 Optional `.env` values

```env
SENDGRID_BASE_URL=https://api.eu.sendgrid.com   # EU accounts only
SENDGRID_FROM_EMAIL=default@yourdomain.com       # Default From override
```

### 2.6 Sender verification requirement

**Every From address used in the CRM must be verified in SendGrid** (Settings → Sender Authentication → Single Senders, or Domain Authentication for your whole domain).

If an unverified address is used as From, SendGrid will reject the send with a 403 error. This applies to:

- Every active row in the `emails` table (`status = 1`)
- Any hardcoded From addresses in mailable `build()` methods (see section 4.3)
- The `MAIL_FROM_ADDRESS` env value
- The signature From address

After configuring, clear caches:

```bash
php artisan config:clear
php artisan cache:clear
```

---

## 3) Sender Configuration — `emails` Table

The `emails` table **still stores sender accounts** but its role is now simpler — it only needs to provide the **From address and display name**. It no longer stores SMTP credentials because SendGrid handles transport for all senders via a single API key.

| Column | New role |
|--------|----------|
| `email` | From address — still used |
| `display_name` | Sender display name — still used |
| `email_signature` | HTML signature — still used |
| `status` | Active flag — still used |
| `user_id` | Staff access control — still used |
| `password` | **No longer used** — was SMTP password, not needed for SendGrid |
| `smtp_host` | **No longer used** — transport is fixed to SendGrid |
| `smtp_port` | **No longer used** |
| `smtp_encryption` | **No longer used** |

> No migration is needed to drop these unused columns immediately — they can be left in place and ignored, or cleaned up in a later housekeeping migration.

**`EmailConfigService` simplification:**

The methods `forAccount()`, `forAccountById()`, `buildConfig()`, and `applyConfig()` currently build and apply per-account SMTP config. Since SMTP is gone:

- `applyConfig()` — **remove all call sites**; the method itself can be removed or left as a no-op
- `buildConfig()` — simplify to return only `from_address`, `from_name`, and `email_signature`
- `getZeptoAccount()` / `getZeptoApiConfig()` — **remove**
- `forAccount()` / `forAccountById()` / `getDefaultAccount()` — keep, but only need to return From address + display name + signature

---

## 3.5) Pre-Apply Replacement Map (SMTP/Zepto -> SendGrid)

Use this section as a gate before any code changes.  
For every flow below, create/verify the sender in SendGrid first, then apply the code migration.

| Area / Flow | Current dependency to replace | File(s) to change | Sender email to create/verify in SendGrid |
|---|---|---|---|
| Base template/compose sends | Bare `Mail::to()` + legacy SMTP behavior | `app/Http/Controllers/Controller.php` | All active sender addresses from `emails.email` |
| Invoice + receipts (client sends) | SMTP-style/bare mail sends | `app/Http/Controllers/CRM/ClientAccountsController.php`, `app/Mail/InvoiceEmailManager.php`, `app/Mail/MultipleattachmentEmailManager.php` | `invoice@bansalimmigration.com.au` |
| EOI/ROI confirmation | `EmailConfigService::applyConfig()` (SMTP runtime swap) | `app/Http/Controllers/CRM/ClientEoiRoiController.php`, `app/Http/Controllers/CRM/EoiRoiSheetController.php` | `admin@bansalimmigration.com.au` (or your chosen EOI sender) |
| Signature send/reminder | ZeptoMail API (`ZeptoMailService`) | `app/Services/SignatureService.php`, `app/Http/Controllers/CRM/SignatureDashboardController.php`, `app/Http/Controllers/CRM/DocumentController.php` | `signature@bansalimmigration.com.au` |
| Client portal activation/deactivation | Bare `Mail::send()` | `app/Http/Controllers/CRM/ClientPortalController.php` | `MAIL_FROM_ADDRESS` value |
| Appointment confirmation/cancellation | Bare `Mail::to()` | `app/Services/BansalAppointmentSync/NotificationService.php` | `MAIL_FROM_ADDRESS` value (or dedicated appointments sender) |
| Visa expiry + cron email jobs | Bare `Mail` sends | `app/Console/Commands/VisaExpireReminderEmail.php`, `app/Console/Commands/CronJob.php` | `MAIL_FROM_ADDRESS` value (or dedicated reminders sender) |
| Hubdoc invoice forwarding | Bare `Mail::to()` (transport default dependent) | `app/Http/Controllers/CRM/ClientAccountsController.php`, `app/Jobs/SendHubdocInvoiceJob.php` | `MAIL_FROM_ADDRESS` value (Hubdoc recipient stays `HUBDOC_EMAIL`) |

### Required SendGrid sender identities (minimum set)

Create/verify these first:

1. `signature@bansalimmigration.com.au` (signature flows)
2. `invoice@bansalimmigration.com.au` (invoice/receipt flows)
3. `admin@bansalimmigration.com.au` (EOI/ROI confirmations)
4. `MAIL_FROM_ADDRESS` current value (global fallback sender)

Also verify any additional active sender rows in `emails.email` because compose/template sends may use them.

### Quick SQL to list sender addresses in use

```sql
-- Active sender accounts used by CRM compose/template flows
SELECT id, email, display_name, status
FROM emails
WHERE status = 1
ORDER BY email;
```

### Pre-apply checklist

- [ ] SendGrid API key has **Mail Send** permission
- [ ] Domain authentication (SPF/DKIM) is completed in SendGrid
- [ ] All required sender addresses above are verified in SendGrid
- [ ] `SENDGRID_FROM_EMAIL` is set (optional but recommended fallback)
- [ ] `MAIL_FROM_ADDRESS` is one of the verified SendGrid sender identities

---

## 4) Backend Email Send Paths

### 4.1 Base controller helper methods — `app/Http/Controllers/Controller.php`

These protected methods are inherited by most CRM controllers. Update every `Mail::to()->...` call to use the named mailer explicitly:

| Method | Current | After |
|--------|---------|-------|
| `send_email_template()` | `Mail::to()->send()` | `Mail::mailer('sendgrid')->to()->send()` |
| `send_compose_template()` | `Mail::to()->send()` | `Mail::mailer('sendgrid')->to()->send()` |
| `send_attachment_email_template()` | `Mail::to()->queue()` | `Mail::mailer('sendgrid')->to()->queue()` |
| `send_multipleattachment_email_template()` | `Mail::to()->queue()` | `Mail::mailer('sendgrid')->to()->queue()` |
| `send_multiple_attach_compose()` | `Mail::to()->queue()` | `Mail::mailer('sendgrid')->to()->queue()` |

### 4.2 `CustomMailService` — remove entirely

**File:** `app/Services/CustomMailService.php`

`CustomMailService` exists purely to create dynamic per-account SMTP transports at runtime (different Zoho credentials per sender). Since all sending now goes through the single SendGrid mailer, this service is no longer needed.

**Action:** Remove `CustomMailService.php`. Replace any call to `CustomMailService::sendWithCustomSmtp()` or `CustomMailService::sendEmailTemplate()` with a direct `Mail::mailer('sendgrid')->to()->send()` call using `CommonMail` or the appropriate mailable.

Call sites to find and replace:

```bash
# Find all usages
rg "CustomMailService" app/
```

### 4.3 Mailables — hardcoded From addresses

All hardcoded From addresses in mailables must be **verified SendGrid senders**. Verify each one in SendGrid → Sender Authentication.

| Mailable | File | From address source |
|----------|------|---------------------|
| `CommonMail` | `app/Mail/CommonMail.php` | Passed in via `$this->sender` |
| `InvoiceEmailManager` | `app/Mail/InvoiceEmailManager.php` | `$this->array['from']` — currently `invoice@bansalimmigration.com.au` |
| `MultipleattachmentEmailManager` | `app/Mail/MultipleattachmentEmailManager.php` | `$this->array['from']` |
| `EoiConfirmationMail` | `app/Mail/EoiConfirmationMail.php` | Resolved via `EmailConfigService` |
| `AppointmentMail` | `app/Mail/AppointmentMail.php` | Check for hardcoded From |
| `AppointmentCancellation` | `app/Mail/AppointmentCancellation.php` | Check for hardcoded From |

### 4.4 Invoice and receipt email flows — `ClientAccountsController`

**File:** `app/Http/Controllers/CRM/ClientAccountsController.php`

Three queued email flows for financial documents tied to client matters:

| Flow | Mailable | Update needed |
|------|----------|---------------|
| Send Invoice to client | `InvoiceEmailManager` | `Mail::mailer('sendgrid')->to()->queue()` |
| Send Client Fund Receipt | `InvoiceEmailManager` | `Mail::mailer('sendgrid')->to()->queue()` |
| Send Office Receipt | `InvoiceEmailManager` | `Mail::mailer('sendgrid')->to()->queue()` |

The `$invoiceArray['from']` is hardcoded to `invoice@bansalimmigration.com.au` — verify this is a SendGrid verified sender.

### 4.5 EOI/ROI confirmation emails

**Files:**
- `app/Http/Controllers/CRM/ClientEoiRoiController.php`
- `app/Http/Controllers/CRM/EoiRoiSheetController.php`

**Current code (uses `applyConfig()` — must be replaced):**

```php
$eoiFromConfig = $this->emailConfigService->getEoiFromAccount();
if ($eoiFromConfig) {
    $this->emailConfigService->applyConfig($eoiFromConfig);  // REMOVE THIS
}
Mail::to($client->email)->send(new EoiConfirmationMail(...));
```

**Updated code:**

```php
$fromAccount = $this->emailConfigService->getEoiFromAccount();
$fromAddress = $fromAccount['from_address'] ?? config('mail.from.address');
$fromName    = $fromAccount['from_name']    ?? config('mail.from.name');

Mail::mailer('sendgrid')
    ->to($client->email)
    ->send(new EoiConfirmationMail(..., $fromAddress, $fromName));
```

Remove all `applyConfig()` call sites across both controllers.

### 4.6 Electronic signature emails — ZeptoMail → SendGrid migration

**File:** `app/Services/SignatureService.php`

Signature emails (initial send + reminders) currently use **ZeptoMail API** via `ZeptoMailService`. **ZeptoMail is being replaced by SendGrid.**

**Current code (to be removed):**

```php
$zeptoApiConfig = $this->emailConfigService->getZeptoApiConfig();
$this->zeptoMailService->sendFromTemplate(
    $template,
    $templateData,
    ['address' => $signer->email, 'name' => $signer->name],
    $subject,
    $zeptoApiConfig['from_address'],
    $zeptoApiConfig['from_name'],
    $attachments
);
```

**Replacement:**

```php
$fromAccount = $this->emailConfigService->getDefaultAccount();
Mail::mailer('sendgrid')
    ->to(['address' => $signer->email, 'name' => $signer->name])
    ->send(new \App\Mail\SignatureMail(
        $templateData,
        $subject,
        $fromAccount['from_address'],
        $fromAccount['from_name']
    ));
```

**Migration steps:**

1. Create `app/Mail/SignatureMail.php` mailable using the existing `emails.signature.send` and `emails.signature.send_agreement` blade views
2. Update `SignatureService::sendSigningEmail()` — replace ZeptoMail call with `Mail::mailer('sendgrid')`
3. Update `SignatureService::remind()` — replace ZeptoMail call with `Mail::mailer('sendgrid')`
4. Remove `ZeptoMailService` constructor injection from `SignatureService`
5. Update `SignatureDashboardController` to use `Mail::mailer('sendgrid')` (it also calls `EmailConfigService` for signature sends)
6. Delete `app/Services/ZeptoMailService.php`
7. Remove `getZeptoApiConfig()` and `getZeptoAccount()` from `EmailConfigService`
8. Remove `zeptomail` block from `config/services.php`
9. Remove all `ZEPTOMAIL_*` and `ZEPTO_*` vars from `.env`
10. Verify the signature From address is a verified SendGrid sender

### 4.7 Hubdoc invoice emails

**Files:**
- `app/Http/Controllers/CRM/ClientAccountsController.php` — `sendToHubdoc()` method
- `app/Jobs/SendHubdocInvoiceJob.php` — `handle()` method

Hubdoc is an accounting integration. The CRM sends a PDF copy of each invoice to a Hubdoc email address (`HUBDOC_EMAIL` in `.env`) for automatic data capture. This flow uses the `HubdocInvoiceMail` mailable and has two send call sites — a direct send in the controller and a queued job version.

**Current code (both locations):**

```php
Mail::to(env('HUBDOC_EMAIL', 'bansalcrm11@gmail.com'))->send(new HubdocInvoiceMail($invoiceData));
```

**Updated code (both locations) — add the named mailer:**

```php
Mail::mailer('sendgrid')->to(env('HUBDOC_EMAIL', 'bansalcrm11@gmail.com'))->send(new HubdocInvoiceMail($invoiceData));
```

**No other changes needed:**

- `HubdocInvoiceMail::build()` does not set a From address — it inherits `MAIL_FROM_ADDRESS` from `.env`. As long as that address is a verified SendGrid sender, the email will deliver to Hubdoc exactly as before.
- The `HUBDOC_EMAIL` env var, the PDF attachment logic, the `hubdoc_sent` / `hubdoc_sent_at` database flags, and the `checkHubdocStatus()` method are all completely unaffected.

With these two line changes, Hubdoc invoice sending stays fully operational after the SendGrid migration.

### 4.9 Appointment reminder emails

**File:** `app/Services/BansalAppointmentSync/NotificationService.php`

```php
// Current
Mail::to($appointment->client_email)->send(new AppointmentMail(...));

// Updated
Mail::mailer('sendgrid')->to($appointment->client_email)->send(new AppointmentMail(...));
```

Same pattern for `AppointmentCancellation`.

### 4.10 Client portal activation emails

**File:** `app/Http/Controllers/CRM/ClientPortalController.php`

```php
// Current
Mail::send('emails.client_portal_active_email', [...], function($message) { ... });

// Updated
Mail::mailer('sendgrid')->send('emails.client_portal_active_email', [...], function($message) { ... });
```

### 4.11 Scheduled commands

**`app/Console/Commands/VisaExpireReminderEmail.php`**

- Artisan signature: `VisaExpireReminderEmail:daily`
- Queries clients whose visa is expiring (linked to client matters)
- Update: `Mail::mailer('sendgrid')->to()->send(new CommonMail(...))`

**`app/Console/Commands/CronJob.php`**

- Handles scheduled invoice email delivery
- Update: `Mail::mailer('sendgrid')->to()->queue(new InvoiceEmailManager(...))`

---

## 5) Admin UI — Email Account Management

**Location:** Admin Console → Features → Emails  
**Controller:** `app/Http/Controllers/AdminConsole/EmailController.php`  
**Views:** `resources/views/AdminConsole/features/emails/create.blade.php` and `edit.blade.php`

Since SMTP is removed, the `smtp_host`, `smtp_port`, `smtp_encryption`, and `password` fields in the admin UI are **no longer needed**. Do not add them.

The admin UI should only manage:

- **Email address** (the From address, must be a verified SendGrid sender)
- **Display name**
- **Email signature** (HTML)
- **Status** (active/inactive)
- **User sharing** (which staff can use this sender)

If the UI currently shows a `password` field, it can be hidden or removed since it no longer drives email sending.

---

## 6) Test Plan (CRM-specific)

Run these after configuring SendGrid:

1. **Basic compose email** — from CRM client detail, send a test email
2. **Invoice email with PDF attachment** — send invoice to a client (queued)
3. **Client fund receipt email** — send receipt (queued)
4. **Office receipt email** — send office receipt (queued)
5. **EOI confirmation email** — trigger EOI confirmation via matter
6. **Appointment confirmation email** — create or update a booking appointment
7. **Visa expiry reminder** — run `php artisan VisaExpireReminderEmail:daily` manually
8. **Client portal activation** — toggle portal access for a client
9. **Hubdoc invoice send** — send an invoice to Hubdoc and confirm the PDF arrives at `HUBDOC_EMAIL`
10. **Document signature send** — send a document for e-signature from the signature dashboard
11. **Signature reminder** — send a reminder to a pending signer
12. Confirm email activity is logged in `email_logs` table
13. Confirm errors appear in `storage/logs/laravel.log`

Queue worker must be running for queued mailables:

```bash
php artisan queue:work
```

---

## 7) Full Code Changes Checklist

| File | Action |
|------|--------|
| `config/mail.php` | Add `sendgrid` named mailer; set as default; remove `smtp` and `custom_smtp` entries |
| `config/services.php` | Add `sendgrid` block; remove `zeptomail` block |
| `.env` | Add `SENDGRID_API_KEY`; remove `MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION` and all `ZEPTO*`/`ZEPTOMAIL*` vars |
| `app/Services/CustomMailService.php` | **Delete** — replaced by direct `Mail::mailer('sendgrid')` calls |
| `app/Services/ZeptoMailService.php` | **Delete** — replaced by SendGrid |
| `app/Services/EmailConfigService.php` | Remove `applyConfig()`, `getZeptoAccount()`, `getZeptoApiConfig()`; simplify `buildConfig()` to return only From address/name/signature |
| `app/Http/Controllers/Controller.php` | Add `Mail::mailer('sendgrid')` to all five send helper methods |
| `app/Http/Controllers/CRM/ClientAccountsController.php` | Add `Mail::mailer('sendgrid')` to all invoice/receipt queue calls |
| `app/Http/Controllers/CRM/ClientPortalController.php` | Add `Mail::mailer('sendgrid')` to portal activation send |
| `app/Http/Controllers/CRM/ClientEoiRoiController.php` | Remove `applyConfig()` call; add `Mail::mailer('sendgrid')` |
| `app/Http/Controllers/CRM/EoiRoiSheetController.php` | Remove `applyConfig()` call; add `Mail::mailer('sendgrid')` |
| `app/Http/Controllers/CRM/SignatureDashboardController.php` | Replace ZeptoMail/`applyConfig()` usage with `Mail::mailer('sendgrid')` |
| `app/Services/SignatureService.php` | Replace `ZeptoMailService` calls with `Mail::mailer('sendgrid')`; remove constructor injection |
| `app/Services/BansalAppointmentSync/NotificationService.php` | Add `Mail::mailer('sendgrid')` to appointment sends |
| `app/Console/Commands/VisaExpireReminderEmail.php` | Add `Mail::mailer('sendgrid')` |
| `app/Console/Commands/CronJob.php` | Add `Mail::mailer('sendgrid')` |
| `app/Http/Controllers/CRM/ClientAccountsController.php` (`sendToHubdoc`) | Add `Mail::mailer('sendgrid')` — keeps Hubdoc unaffected |
| `app/Jobs/SendHubdocInvoiceJob.php` | Add `Mail::mailer('sendgrid')` — keeps Hubdoc unaffected |
| `app/Mail/SignatureMail.php` | **Create** — new mailable for signature send/reminder using existing blade views |
| `resources/views/AdminConsole/features/emails/create.blade.php` | Remove/hide `password` field; do not add SMTP fields |
| `resources/views/AdminConsole/features/emails/edit.blade.php` | Remove/hide `password` field; do not add SMTP fields |

---

## 8) Cleanup After Migration — What Can Be Removed

Once SendGrid is working and all flows are confirmed, the following can be safely removed. Nothing here affects email logging, IMAP inbox sync, or any other CRM feature.

---

### 8.1 Database columns to drop — `emails` table

These four columns were only ever used for per-account SMTP configuration. Since SendGrid handles transport via a single API key in `.env`, they are no longer read or written.

| Table | Column | Why it was there | Safe to drop |
|-------|--------|-----------------|--------------|
| `emails` | `smtp_host` | Per-account SMTP server (default: `smtp.zoho.com`) | Yes |
| `emails` | `smtp_port` | Per-account SMTP port (default: `587`) | Yes |
| `emails` | `smtp_encryption` | Per-account TLS/SSL setting | Yes |
| `emails` | `password` | Per-account SMTP password | Yes |

Create a migration to drop them:

```php
Schema::table('emails', function (Blueprint $table) {
    $table->dropColumn(['smtp_host', 'smtp_port', 'smtp_encryption', 'password']);
});
```

> **No entire tables need to be dropped** as part of this migration.  
> All email logging tables (`email_logs`, `email_log_attachments`, `email_labels`, `email_label_email_log`) stay as-is.

---

### 8.2 `.env` variables to remove

These are confirmed present in the current `.env` file and are no longer needed after the migration.

**SMTP sending vars (replace, not just remove):**

```env
# Change MAIL_MAILER (don't remove, just update):
MAIL_MAILER=smtp          →  MAIL_MAILER=sendgrid

# Remove these entirely:
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=bansalcrm11@gmail.com
MAIL_PASSWORD="xeyy ezdy czig ovat"
MAIL_ENCRYPTION=tls

# Keep these (update From address to a verified SendGrid sender if needed):
MAIL_FROM_ADDRESS=bansalcrm11@gmail.com
MAIL_FROM_NAME="Bansal Migration"
```

**ZeptoMail vars (remove entirely):**

```env
ZEPTO_EMAIL=signature@bansalimmigration.com.au
ZEPTO_SMTP_HOST=smtp.zeptomail.com
ZEPTO_SMTP_PORT=587
ZEPTO_SMTP_ENCRYPTION=tls
ZEPTO_SMTP_USERNAME=emailapikey
ZEPTO_SMTP_PASSWORD=wSsVR61x/hDz...
ZEPTO_FROM_NAME="Bansal Migration"
ZEPTOMAIL_API_KEY=wSsVR61y+UWj...
ZEPTOMAIL_FROM_EMAIL=signature@bansalimmigration.com.au
ZEPTOMAIL_FROM_NAME="Bansal Migration"
```

**Zoho OAuth vars (remove entirely — Zoho is no longer the mail provider):**

```env
ZOHO_CLIENT_ID=1000.G1NF6ZW2AM3OLQU8FSVYUR0EFFVNYW
ZOHO_CLIENT_SECRET=5c1394a4282ca96512a...
ZOHO_REDIRECT_URI="http://localhost:8000/auth/zoho/callback"
```

**SendGrid vars (keep — these are the new ones):**

```env
SENDGRID_API_KEY=SG.Va3C5si0ST...   ← keep
SENDGRID_BASE_URL=https://api.sendgrid.com   ← keep
SENDGRID_FROM_EMAIL=                ← fill in with verified sender
```

---

### 8.3 `config/services.php` blocks to remove

```php
// REMOVE — ZeptoMail no longer used
'zeptomail' => [
    'api_key'    => env('ZEPTOMAIL_API_KEY'),
    'from_email' => env('ZEPTOMAIL_FROM_EMAIL', '...'),
    'from_name'  => env('ZEPTOMAIL_FROM_NAME', '...'),
    'api_url'    => env('ZEPTOMAIL_API_URL', '...'),
],

// REMOVE — EOI From was only used to drive ZeptoMail-based EOI sends
'eoi' => [
    'from_email' => env('EOI_FROM_EMAIL', '...'),
],
```

---

### 8.4 `config/mail.php` mailer entries to remove

```php
// REMOVE — no longer used
'smtp' => [ ... ],
'custom_smtp' => [ ... ],
'failover' => [ ... ],   // only useful if smtp is the backup; remove if desired
```

Keep only: `sendgrid`, `log`, `array`.

---

### 8.5 PHP service files to delete

| File | Reason |
|------|--------|
| `app/Services/ZeptoMailService.php` | Entire ZeptoMail API integration — replaced by SendGrid |
| `app/Services/CustomMailService.php` | Dynamic per-account SMTP transport — replaced by single SendGrid mailer |
| `app/Services/EmailService.php` | Has hardcoded `smtp.zoho.com` SMTP switching — same pattern as `applyConfig()`, now redundant |

> Before deleting `EmailService.php`, run a search for any remaining call sites:
> ```bash
> rg "EmailService" app/
> ```
> If it is only injected or called in places already being updated, it is safe to remove.

---

### 8.6 `EmailConfigService` methods to remove

Keep the file but remove these methods — they are no longer called after the migration:

| Method | Remove because |
|--------|---------------|
| `applyConfig()` | Swapped per-account SMTP at runtime — no longer needed |
| `buildConfig()` | Built SMTP config arrays — no longer needed in its current form |
| `getZeptoAccount()` | ZeptoMail SMTP config — ZeptoMail removed |
| `getZeptoApiConfig()` | ZeptoMail REST API config — ZeptoMail removed |

Keep these methods (they are still used to resolve From address and display name):

| Method | Keep because |
|--------|-------------|
| `forAccount(string $email)` | Still resolves sender name from `emails` table |
| `forAccountById(int $id)` | Still resolves sender by ID |
| `getDefaultAccount()` | Still provides fallback From address |
| `getEnvAccount()` | Still provides `.env`-based From address |
| `getEoiFromAccount()` | Still resolves EOI From address |

---

### 8.7 What NOT to remove

| Item | Keep because |
|------|-------------|
| `config/imap.php` | IMAP is for **incoming** email sync (`SyncEmails` command) — completely separate from outbound sending |
| `app/Console/Commands/SyncEmails.php` | Incoming email sync — unaffected by SendGrid |
| `email_logs` table | All sent email history is stored here — untouched |
| `email_log_attachments` table | Attachment records — untouched |
| `email_labels` table | Email labels/tags — untouched |
| `email_label_email_log` pivot table | Label-to-email relationships — untouched |
| `emails.email` column | Still the From address for each sender account |
| `emails.display_name` column | Still the sender display name |
| `emails.email_signature` column | Still the HTML signature appended to emails |
| `emails.status` column | Still the active/inactive flag |
| `emails.user_id` column | Still controls which staff can use each sender |
| `HUBDOC_EMAIL` env var | Still needed for Hubdoc invoice routing |
| `MAIL_FROM_ADDRESS` env var | Still the default From fallback |
| `MAIL_FROM_NAME` env var | Still the default sender name |

---

## 9) Deliverability and Compliance Checklist

- Verify domain authentication (SPF + DKIM) in SendGrid Dashboard → Settings → Sender Authentication
- Verify every individual From address if not using domain-level authentication
- Warm up sending reputation for new domain/IP before sending bulk reminders
- Use a monitored reply-to mailbox for client responses
- Consider unsubscribe handling for matter reminder and visa expiry emails
- Track bounces/blocks/spam reports via SendGrid event webhooks (optional, no new DB table needed for basic use)

---

## 10) Rollback Plan

If critical issues occur after switching to SendGrid:

1. Re-add the `smtp` mailer to `config/mail.php` with Zoho settings
2. Temporarily set `MAIL_MAILER=smtp` and restore `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` in `.env`
3. Run `php artisan config:clear`
4. Restart the queue worker
5. Test one email path before restoring full traffic

**For signature emails during ZeptoMail → SendGrid transition:**  
Do not delete `ZeptoMailService.php` or remove `ZEPTOMAIL_*` env vars until signature sends via SendGrid are confirmed working. If signature sends fail, temporarily revert `SignatureService` to ZeptoMail while debugging the signature sender verification in SendGrid.

---

## 11) Troubleshooting

| Symptom | Likely cause | Fix |
|---------|-------------|-----|
| 403 Forbidden on send | From address not verified in SendGrid | Add address to SendGrid → Sender Authentication |
| 535 Auth failed | `SENDGRID_API_KEY` not set or wrong | Check `.env` has `SENDGRID_API_KEY=SG.xxx` |
| API key permission error | Key missing Mail Send permission | In SendGrid → API Keys, ensure the key has **Mail Send** access |
| Emails still routing through Zoho | Old `MAIL_HOST`/`MAIL_MAILER` still in `.env` | Remove SMTP vars; run `php artisan config:clear` |
| `applyConfig()` errors after removal | Call site not yet removed | Search codebase for `applyConfig(` and remove all usages |
| `CustomMailService` errors | Not yet deleted | Remove file and all usages |
| EOI email not sending | `getEoiFromAccount()` returns null, no fallback | Ensure an active `emails` record exists for the EOI From address |
| Queued invoice/receipt emails not sending | Queue worker not running | Run `php artisan queue:work` |
| Signature emails failing after migration | `SignatureService` still references `ZeptoMailService` | Complete migration steps in section 4.6 |
| Signature From address rejected | Signature From not verified in SendGrid | Verify `signature@yourdomain.com` in SendGrid → Sender Authentication |
| Visa expiry reminders not sending | Scheduler not running or command not updated | Run `php artisan schedule:run`; ensure command uses `Mail::mailer('sendgrid')` |
| S3 archival silently skipped | S3 not configured | Set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` in `.env` |
| Config not picking up changes | Cached config | Run `php artisan config:clear && php artisan cache:clear` |

---

## 12) Summary

This migration replaces **all** existing email transports (Zoho SMTP, custom per-account SMTP, ZeptoMail) with a single SendGrid mailer.

The result is a significantly simpler architecture:

- One API key in `.env` drives all email sending
- No more runtime SMTP credential swapping (`applyConfig()` removed)
- No more per-account SMTP config in the `emails` table
- `CustomMailService` and `ZeptoMailService` deleted
- `emails` table used only to resolve From address, display name, and email signature

Key work required:

1. Add `sendgrid` named mailer to `config/mail.php` and set as default
2. Add `SENDGRID_API_KEY` to `.env`; remove all SMTP and ZeptoMail env vars
3. Add `Mail::mailer('sendgrid')` to every send call across controllers, services, and commands
4. Remove all `applyConfig()` call sites
5. Delete `CustomMailService` and replace call sites with direct mailer calls
6. Migrate `SignatureService` from ZeptoMail to SendGrid; delete `ZeptoMailService`
7. Verify all From addresses as SendGrid verified senders
