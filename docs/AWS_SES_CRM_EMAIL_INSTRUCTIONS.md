# AWS SES Email Integration — Bansal Law CRM

This document describes how outbound email works in the CRM after migration from SendGrid to **AWS SES** (system mail) plus **Zoho SMTP** (staff personal mail).

> **Related:** Sent-email S3 archival is documented in [`CRM_EMAIL_S3_IMPLEMENTATION.md`](CRM_EMAIL_S3_IMPLEMENTATION.md).

---

## Architecture

| Mailer | Transport | Used for |
|--------|-----------|----------|
| `ses` | AWS SES API | System / no-reply: invoices, receipts, appointments, signatures, hubdoc, verification, cron reminders |
| `zoho` | Zoho SMTP (per-account credentials) | Staff compose from personal `@bansallawyers.com.au` addresses |

Routing is handled by `App\Services\MailRoutingService`:

- **System mailer:** `CRM_SYSTEM_MAILER=ses` (see `config/mail_routing.php`)
- **Personal mailer:** `CRM_PERSONAL_MAILER=zoho`
- Patterns like `noreply@`, `no-reply@`, etc. always use SES
- `emails.mail_provider = ses` (or legacy `sendgrid`) → SES
- `emails.mail_provider = zoho` → Zoho SMTP with per-account password/host

All sends should go through `MailRoutingService` (or `Controller::crmMailRouting()` helpers), not bare `Mail::to()`.

---

## AWS setup (production)

### 1. SES domain (bansallawyers.com.au)

- Region: **ap-southeast-2** (Sydney)
- Domain verified in **Production** mode
- DKIM: 3 CNAME records in Cloudflare (DNS only)
- SPF (root): `v=spf1 include:zoho.com include:amazonses.com ~all`
- Custom MAIL FROM: `mail.bansallawyers.com.au`
  - MX → `feedback-smtp.ap-southeast-2.amazonses.com`
  - TXT → `v=spf1 include:amazonses.com ~all`

### 2. IAM

Use existing IAM user (e.g. `bansallawyers-prod`) with:

```json
{
  "Effect": "Allow",
  "Action": ["ses:SendEmail", "ses:SendRawEmail"],
  "Resource": "*"
}
```

Same `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` can be used for S3 if configured.

---

## `.env` configuration

```env
CRM_SYSTEM_MAILER=ses
CRM_PERSONAL_MAILER=zoho

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@bansallawyers.com.au
MAIL_FROM_NAME="Bansal Lawyers"

AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-southeast-2
```

**Remove** (no longer used):

```env
SENDGRID_API_KEY=
SENDGRID_BASE_URL=
SENDGRID_FROM_EMAIL=
```

After changes:

```bash
php artisan config:clear
php artisan config:cache
php artisan queue:restart
```

---

## Admin Console — email accounts

**Admin Console → Emails**

| Provider | Purpose |
|----------|---------|
| **AWS SES** | System senders (`noreply@`, `admin@`, etc.) — must be on verified domain |
| **Zoho SMTP** | Staff personal senders — requires Zoho app password + SMTP fields |

Run migration to convert legacy rows:

```bash
php artisan migrate
```

(`2026_07_11_000000_migrate_sendgrid_mail_provider_to_ses.php` updates `mail_provider=sendgrid` → `ses`.)

---

## Key code paths

| Flow | Entry point |
|------|-------------|
| Compose / templates | `Controller` send helpers → `MailRoutingService` |
| Queued CRM mail | `SendCrmEmailJob` |
| Invoices / receipts | `ClientAccountsController` → `queueTo()` |
| Hubdoc | `SendHubdocInvoiceJob`, `ClientAccountsController::sendToHubdoc` |
| Appointments | `BansalAppointmentSync\NotificationService` |
| Signatures | `SignatureService`, `DocumentController`, `SignatureDashboardController` |
| Email verification | `EmailVerificationService` |
| Compose From dropdown | `GET /crm/compose-senders` (`ComposeSendersController`) |

Legacy route `/crm/sendgrid-senders` still works (same handler).

---

## Testing

```bash
php artisan tinker
```

```php
Mail::mailer('ses')->raw('SES test', function ($m) {
    $m->to('your-email@gmail.com')
      ->subject('CRM SES test')
      ->from('noreply@bansallawyers.com.au', 'Bansal Lawyers');
});
```

Check **AWS SES → Sending statistics** and email headers (DKIM=pass, SPF=pass).

---

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `AccessDenied` on `ses:SendEmail` | Attach SES send policy to IAM user; confirm region |
| `Email address is not verified` | Use `@bansallawyers.com.au` From address; confirm domain verified in SES |
| Staff mail fails, system mail works | Check Zoho app password on `emails` row; `mail_provider=zoho` |
| System mail fails, staff mail works | Check `AWS_*` vars; IAM policy; `CRM_SYSTEM_MAILER=ses` |
| Compose From list empty | Add accounts in Admin Console; ensure `MAIL_FROM_ADDRESS` set |
| Config not updating | `php artisan config:clear` + restart queue workers |

---

## Config files

| File | Role |
|------|------|
| `config/mail.php` | `ses` and `zoho` mailers |
| `config/mail_routing.php` | System vs personal mailer names |
| `config/services.php` | AWS credentials for SES SDK |
