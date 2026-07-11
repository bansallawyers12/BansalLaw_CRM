# CRM Email S3 Storage Implementation

This document describes S3 storage for CRM-sent emails (full HTML snapshot + attachments). It enables consistent archival with uploaded `.msg` emails and attachment download in the Email tab.

> **Related:** Outbound sending uses AWS SES + Zoho — see [AWS_SES_CRM_EMAIL_INSTRUCTIONS.md](AWS_SES_CRM_EMAIL_INSTRUCTIONS.md).

---

## Bansal Law CRM

### Files Created/Modified

| File | Change |
|------|--------|
| `app/Services/CrmSentEmailS3Service.php` | **Created** – Service to store email HTML + attachments on S3 |
| `app/Http/Controllers/CRM/CRMUtilityController.php` | Integrated `CrmSentEmailS3Service` after successful `sendEmail()` |
| `app/Http/Controllers/CRM/ClientsController.php` | Updated `filterSentEmails` and `filterLeadEmails` for S3 preview URLs |

### CrmSentEmailS3Service (Bansal Law CRM)

- **Models:** `EmailLog`, `EmailLogAttachment`, `Document`, `Admin`
- **S3 path:** `{client_ref}/crm_sent/sent/{timestamp}-{uniqid}-email.html`
- **Attachment path:** `{client_ref}/attachments/{timestamp}_{uniqid}_{filename}`
- **Document:** `doc_type = 'crm_sent'`, `mail_type = 'sent'`, `myfile` = full S3 URL, `myfile_key` = filename
- **Checks:** S3 config before upload; skips if not configured
- **Client ref:** Uses `Admin.client_id` when available, else `'client_' . $clientId` (works for leads)

### Filter Logic (filterSentEmails / filterLeadEmails / filterEmails)

- If `docInfo.myfile_key` exists → use `docInfo.myfile` (full S3 URL)
- Else → build URL: `{base_url}/{clientRef}/{doc_type}/{mail_type}/{myfile}`
- Fallback `clientRef`: `AdminInfo.client_id` or `'client_' . ($email->client_id ?? $client_id ?? 0)`
- `filterLeadEmails` uses `Admin::withoutGlobalScopes()` when resolving client for leads

### Send Flow (CRMUtilityController)

1. Create `EmailLog`, save
2. Send email via `EmailService::sendEmail()` → `MailRoutingService` (SES or Zoho)
3. Build `attachmentTuples` from `$attachments`
4. Call `CrmSentEmailS3Service::storeToS3($obj, $subject, $message, $attachmentTuples)`
5. S3 failure is caught/logged; send still succeeds

### Attachment Download

- `EmailLogAttachmentController` uses `s3_key` for download/preview
- CRM-sent attachments create `EmailLogAttachment` with `s3_key` set

---

## Historical: bansalcrm2 fork

The sections below document a parallel implementation in an older CRM codebase (`bansalcrm2`). They are kept for reference if porting fixes between repos.

### Files Created/Modified

| File | Change |
|------|--------|
| `app/Services/CrmSentEmailS3Service.php` | **Created** – Service for `MailReport` / `MailReportAttachment` |
| `app/Http/Controllers/Admin/AdminController.php` | Injected service; set `client_id`, `client_matter_id` on MailReport; call `storeToS3()` after send |
| `app/Http/Controllers/CRM/EmailQueryV2Controller.php` | Updated `filterSentEmails` for S3 preview fallback |

---

## Common Fixes / Troubleshooting

### Sent emails not appearing in Email tab

- Ensure `client_id` (and `client_matter_id` when matter-scoped) is set on the email log.
- For client Sent tab: `compose_client_matter_id` must be set when composing.
- Check `filterSentEmails` / `filterLeadEmails` query (client_id, type, mail_type, conversion_type).

### Preview URL 404 or blank

- Verify Document has `myfile_key` and `myfile` (full S3 URL) for `crm_sent` docs.
- If using legacy path: check `clientRef` resolves correctly (Admin lookup).
- Confirm S3 bucket, region, and CORS allow reads.

### Attachment download fails

- Check `EmailLogAttachment` has `s3_key` populated.
- Verify file exists at `Storage::disk('s3')->exists($s3_key)`.
- Ensure S3 config (key, secret, bucket, region) is correct.

### S3 upload fails silently

- S3 errors are logged; email send still succeeds.
- Check `config('filesystems.disks.s3.key')` and `config('filesystems.disks.s3.bucket')` — service skips if not set.
- Review logs for `CrmSentEmailS3Service` messages.

---

## Database Columns Referenced

**Bansal Law CRM:**

- `email_logs`: `client_id`, `client_matter_id`, `uploaded_doc_id`, `type`
- `email_log_attachments`: `s3_key`, `file_path`, `filename`, etc.
- `documents`: `doc_type`, `myfile`, `myfile_key`, `mail_type`, `client_id`, `client_matter_id`
