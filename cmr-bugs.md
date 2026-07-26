# CRM Bugs Audit

**Date:** 2026-07-26  
**Scope:** Full CRM codebase audit (read-only; no fixes applied)  
**Branch:** master  
**Method:** Static code review of controllers, routes, services, and related JS/views, area by area

Severity key:

| Level | Meaning |
|-------|---------|
| **Critical** | Data loss, money integrity failure, or unauthenticated/remote abuse |
| **High** | IDOR, privilege bypass, CSRF mutating GETs, XSS, or major workflow breakage |
| **Medium** | Incorrect business logic, races, incomplete features, null crashes |
| **Low** | Info disclosure / edge cases with limited impact |
| **Suspected** | Likely bug; needs product or runtime confirmation |

---

## Area 1 — Clients

### 1.1 Critical — Client merge soft-deletes the survivor (`merge_into`), not the source
- **Files:** `app/Http/Controllers/CRM/ClientsController.php` (~3562–3564); `public/js/crm/clients/clients-listing-spa.js` (~345–362)
- **What goes wrong:** UI sends `merge_from` → `merge_into` and removes the *source* row from the list. Backend copies related rows from `merge_from` into `merge_into`, then soft-deletes **`merge_into`** (the intended survivor). Result: survivor is deleted; source remains.
- **Impact:** Destructive data loss / wrong record retained after merge. Combined with incomplete merge (#1.3) this is especially severe.
- **Evidence:** `DB::table('admins')->where('id', $request->merge_into)->update(['is_deleted' => now()]);` while frontend does `removeClientRow(clickedIds[0])` for `merge_from`.

### 1.2 High — Client merge has no authorization / ownership checks
- **Files:** `ClientsController::merge_records` (~3556–3750); `routes/clients.php` (~363)
- **What goes wrong:** Any authenticated staff can POST arbitrary `merge_from` / `merge_into` IDs. No `StaffClientVisibility` / role gate.
- **Impact:** Destructive IDOR — staff can merge (and soft-delete) clients they cannot normally access.

### 1.3 Medium — Merge omits core CRM data (matters, trust, invoices, personal details)
- **Files:** `ClientsController::merge_records` (~3556–3746)
- **What goes wrong:** Even if delete direction were fixed, merge copies notes/docs/emails/activities but **not** `client_matters`, `account_client_receipts`, personal-details tables, etc.
- **Impact:** Silent incomplete merge; finance and matters stay on the wrong/orphaned record.

### 1.4 Critical — Document download via `filelink` skips access control
- **Files:** `app/Http/Controllers/CRM/Clients/ClientDocumentsController.php` (~2934–3002)
- **What goes wrong:** With `document_id`, access is checked. Without it, any auth staff can pass an S3 URL/`filelink`, resolve the key, and get a temporary download for any object on the disk if the path is known/guessable.
- **Impact:** Cross-client document disclosure for any logged-in staff.

### 1.5 High — Notes CRUD is IDOR (no CRM access checks)
- **Files:** `app/Http/Controllers/CRM/Clients/ClientNotesController.php` (~49–511)
- **What goes wrong:** Create/update/list/delete/pin/get note by `client_id` / `note_id` with no `canAccessClientOrLead`.
- **Also:** invalid `noteid` → `Note::find` null → `$obj->replicate()` null dereference (~57–59).
- **Impact:** Staff can read/alter notes on clients they cannot open; possible 500 on bad IDs.

### 1.6 High — Matter tasks API has no CRM access checks
- **Files:** `app/Http/Controllers/CRM/Clients/ClientMatterTaskController.php` (~46–157)
- **What goes wrong:** `index`/`store`/`update`/`destroy` only check `client_id` matches the task row. No visibility gate.
- **Impact:** Any staff can list/create/toggle/delete another client’s tasks.

### 1.7 High — Most document mutations only enforce access for restricted PA roles
- **Files:** `ClientDocumentsController.php` (~84–114, ~129–131, ~1714–1716, ~1809–1810)
- **What goes wrong:** `denyJsonUnlessStaffClientAccess` / `blockEchoUnlessStaffClientAccess` return early (allow) unless `isRestrictedPersonAssisting`. Non-PA staff can upload/delete/move checklists for unallocated clients while list/detail are restricted.
- **Impact:** Inconsistent ACL — mutations weaker than reads for non-restricted roles.

### 1.8 High — Email HTML preview has no client access check
- **Files:** `ClientsController.php` (~8386–8427); `routes/clients.php` (~389)
- **What goes wrong:** `GET /email-logs/{id}/preview-html` loads any `EmailLog` by id from S3 and returns parsed HTML. No `canAccessClientOrLead` on `emailLog->client_id`.
- **Impact:** Cross-client email content disclosure for any logged-in staff.

### 1.9 High — Email delete lacks client-record authorization
- **Files:** `ClientsController.php` (~4333–4434)
- **What goes wrong:** Requires delete-email privilege, but never checks the user may access the email’s client. Optional `client_id`/`client_matter_id` match only if the caller supplies them.
- **Impact:** Privileged staff can delete emails (and attachment rows) for any client by id.

### 1.10 High — Activity log delete/pin and cost-agreement delete lack access checks
- **Files:**
  - `ClientsController::deleteactivitylog` (~3795–3811)
  - `ClientsController::pinactivitylog` (~3814–3833)
  - `ClientsController::deletecostagreement` (~5525–5566) — mutating **GET**
- **Impact:** Delete/pin any activity by id; delete any cost agreement by id across clients.

### 1.11 High — `convertLeadOnly` skips access control and incomplete status transition
- **Files:** `ClientsController.php` (~6387–6432); `routes/clients.php` (~66)
- **What goes wrong:**
  1. No `canAccessClientOrLead` — any staff can convert any lead with an active matter.
  2. Sets `type = 'client'` but **does not** set `lead_status = 'converted'` (unlike `Lead::convertToClient()`).
- **Impact:** Unauthorized conversion; broken converted-client UX / analytics that rely on `lead_status`.

### 1.12 High — Lead↔client type change via GET (CSRF + demotion)
- **Files:** `ClientsController.php` (~6278–6371); `routes/clients.php` (~65)
- **What goes wrong:** `GET /clients/changetype/{id}/{type}` mutates state. CSRF if a logged-in staff browser hits a crafted URL. `slug == 'lead'` demotes a client to lead and clears `user_id`. Conversion path also skips `lead_status = 'converted'`.
- **Impact:** Unauthorized type flip / demotion via link or CSRF.

### 1.13 High — Trust receipt matter fix is a mutating GET with no access check
- **Files:** `ClientAccountsController.php` (~5457–5524); `routes/clients.php` (~256–257)
- **What goes wrong:** `GET /clients/fix-client-fund-receipt-matter/{id}` updates `client_matter_id` on a trust ledger row then redirects to regenerate PDF. No `ensureCrmRecordAccess`.
- **Impact:** Any auth staff (or CSRF via image/link) can reattribute trust receipts.

### 1.14 High — Inbox email reassign: no access checks + null-deref risk
- **Files:** `ClientsController.php` (~3841–3915)
- **What goes wrong:** Reassigns documents/emails to any `reassign_client_id` without visibility checks. Missing source/dest admin → null deref; missing email log → null deref; undefined `$saved_mail_report_info` if doc save fails.
- **Impact:** Wrong-client email attachment; 500 errors.

### 1.15 High — `notpickedcall` lacks access control (SMS + flag)
- **Files:** `ClientsController.php` (~3754–3792)
- **What goes wrong:** Updates `not_picked_call` and can send SMS to any admin id’s phone. No `canAccessClientOrLead`.
- **Impact:** Abuse / privacy / SMS cost.

### 1.16 Medium — `saveRelationship` writes against staff Auth id, not CRM client
- **Files:** `ClientPersonalDetailsController.php` (~646–662); `routes/clients.php` (~294)
- **What goes wrong:** `$clientId = Auth::id()` (staff), then inserts `ClientRelationship` with that id. Relationships never attach to the CRM client.
- **Impact:** Broken partner/relationship data for that legacy route.

### 1.17 Medium/High — Debug endpoints leak client PII
- **Files:**
  - `ClientPersonalDetailsController::searchPartnerTest` (~718–748); route GET `clients/search-partner-test`
  - `ClientPersonalDetailsController::testBidirectionalRemoval` (~6020–6043); route GET `clients/test-bidirectional`
- **What goes wrong:** Unrestricted (auth only) endpoints return names, emails, phones, `related_files` for sample/search clients. No access filtering.
- **Impact:** PII disclosure to any logged-in staff.

### 1.18 Medium — Void invoice can null-deref / proceed inconsistently
- **Files:** `ClientAccountsController.php` (~4390–4427)
- **What goes wrong:** Access checked only when a matching receipt row exists. Bulk update runs regardless. Loop then uses `$invoice_info->client_id` / `$client_info->client_id` with no null guards.
- **Impact:** 500 mid-void if a selected id is missing/wrong type after update.

### 1.19 Medium — `printPreview` assumes receipt exists
- **Files:** `ClientAccountsController.php` (~5231–5247)
- **What goes wrong:** If no receipt, `$record_get[0]` throws. Access check skipped when empty, then crash.
- **Impact:** Unhandled 500 for bad IDs.

### 1.20 Low/Medium — `EnsuresCrmRecordAccess` silently allows non-client/lead IDs
- **Files:** `app/Http/Controllers/Concerns/EnsuresCrmRecordAccess.php` (~20–34)
- **What goes wrong:** If `adminId` is not `type in (client,lead)`, gate returns without abort. Callers that trust this for “any id” can skip ACL when a non-CRM id is supplied.
- **Impact:** ACL bypass when misused by callers (also relevant to trust posts — see Area 6).

---

## Area 2 — Leads

### 2.1 Critical — GET bulk-converts up to 500 leads (including archived)
- **Files:** `app/Http/Controllers/CRM/Leads/LeadConversionController.php` (~33–56); `routes/web.php` (~217)
- **What goes wrong:** `GET /leads/convert` (super-admin only) runs `Lead::withArchived()->paginate(500)` and converts **every** row on that page — not a selected set. Mutating GET → CSRF if a super-admin session loads a malicious page/link.
- **Impact:** Mass incorrect status + activity spam; accidental conversion of archived leads.
- **Evidence:**
  ```php
  $enqdatas = Lead::withArchived()->paginate(500);
  foreach ($enqdatas as $lead) { $lead->convertToClient(); }
  ```

### 2.2 High — Bulk convert ignores matter requirement / silent failures
- **Files:** `LeadConversionController::bulkConvertToClient` (~154–188); `Lead::convertToClient` (~149–171)
- **What goes wrong:** Single convert enforces active assigned matter; bulk / #2.1 do not. Failures swallowed (`catch` empty / continue).
- **Impact:** Leads become clients without matters; ops/analytics diverge from single-convert rules.

### 2.3 Medium — Conversion matter numbers race (duplicate refs)
- **Files:** `LeadConversionController::convertSingleLead` (~118–127); `ClientsController::changetype` (~6322–6324)
- **What goes wrong:** Count-then-insert for `client_unique_matter_no` without locking/unique constraint handling.
- **Impact:** Concurrent converts for same client+matter type can produce duplicate matter numbers.

### 2.4 Medium — Single convert can set arbitrary `user_id`
- **Files:** `LeadConversionController::convertSingleLead` (~97–101)
- **What goes wrong:** After convert, `$requestData['user_id']` is applied with no staff-existence / privilege check.
- **Impact:** Assignee can be set to invalid or unauthorized staff ids.

### 2.5 Medium — `getConversionStats` “converted this month” is wrong
- **Files:** `LeadConversionController::getConversionStats` (~202–208)
- **What goes wrong:** Counts all clients with `updated_at` in current month, not actual conversions (`lead_status = converted` / activity type).
- **Impact:** Misleading analytics for admins.

### 2.6 Low — Assignable staff list unrestricted when `lead_id` omitted
- **Files:** `LeadAssignmentController::getAssignableStaff` (~36–53)
- **What goes wrong:** Without `lead_id`, returns all active staff (id, name, email).
- **Impact:** Minor directory disclosure.

### 2.7 Low — Analytics date parse can 500
- **Files:** `LeadAnalyticsController.php` (~41–42, ~90–91, ~116–117)
- **What goes wrong:** `Carbon::parse($request->get('start_date'))` with no validation → invalid input throws.
- **Impact:** Unhandled exception on bad query params.

### 2.8 Suspected — Auto-convert on matter assignee update bypasses UI confirm
- **Files:** `app/Services/LeadMatterAssignedConversion.php`; callers in `ClientPersonalDetailsController` (~946), `ClientsController` (~5891/5988)
- **What goes wrong:** Saving matter assignees auto-calls `convertToClient()` when an active assigned matter exists.
- **Impact:** Staff can convert a lead by editing assignees without going through convert UX. Confirm product intent before treating as defect.

### 2.9 Low — Lead analytics filter TODO unfinished
- **Files:** `resources/views/crm/leads/analytics/dashboard.blade.php` (~492)
- **What goes wrong:** Comment `// TODO: Add filter implementation` — filter UI incomplete.
- **Impact:** Incomplete analytics UX (functional gap).

---

## Area 3 — Matters / Matter Hub / Workflow

### 3.1 Critical — Permanent matter delete has no permission or closed-status check
- **Files:** `app/Http/Controllers/CRM/ClientMatterHubController.php` (~1070–1116); `routes/matter_workflow.php` (~20)
- **What goes wrong:** `deleteClientMatter` only checks that the matter is older than one year. It does **not** require `matter_status = 0`, and has **no** `canCloseDiscontinueMatter` / admin / module check.
- **Impact:** Any authenticated staff can permanently delete an **active** matter older than 1 year.

### 3.2 High — Legacy discontinue/reopen bypass permission model
- **Files:** `ClientMatterHubController.php` (~1410–1447); `routes/crm_matter_hub.php` (~13–14)
- **What goes wrong:** Newer `discontinueClientMatter` / `reopenClientMatter` enforce `canCloseDiscontinueMatter()` / module `45`. Legacy `discontinueMatter` and `revertMatter` do not.
- **Impact:** Any staff can close or reopen any matter via legacy routes.

### 3.3 High — Completing/discontinuing a matter hard-deletes all matter email history
- **Files:** `ClientMatterHubController.php` (~88–102, ~740–742, ~1422–1423, ~2530–2552)
- **What goes wrong:** `completestage`, `discontinueClientMatter`, and `discontinueMatter` call `deleteEmailConversationsForMatter()`, which permanently deletes `email_logs`, attachments DB rows, and label pivots. Reopen does not restore them.
- **Impact:** Closing a matter destroys CRM email history; S3 may remain orphaned; sync may re-import oddly (see #5.5).

### 3.4 High — Legacy stage APIs query non-existent `w_id` column
- **Files:** `ClientMatterHubController.php` (~109–186, ~1196–1204, ~1441–1442); `app/Models/WorkflowStage.php` (fillable: `workflow_id` only)
- **What goes wrong:** Legacy `updatestage` / `updatebackstage` / `getMatterLogs` / `revertMatter` filter with `where('w_id', ...)`. Schema has `workflow_id`, not `w_id`. Stage advancement also orders by `id` instead of `sort_order` in places.
- **Impact:** SQL errors or incorrect stage behavior when workflow id is present.

### 3.5 High — Legacy stage/complete routes are GET and mutate state
- **Files:** `routes/matter_workflow.php` (~12–14)
- **What goes wrong:** `/updatestage`, `/completestage`, `/updatebackstage` are `Route::get` but mutate matter status/stage (and can delete emails).
- **Impact:** CSRF via img/link and accidental prefetch.

### 3.6 High — Stored XSS in matter logs / notes UI
- **Files:** `ClientMatterHubController.php` (~1236–1252 `getMatterLogs`, ~1265–1277 `addNote`, ~1304–1307 `getMatterNotes`)
- **What goes wrong:** Note/activity `subject` and `description` are stored from request and echoed raw into HTML (`<?php echo $applicationlist->description; ?>`). No `e()` / `htmlspecialchars`.
- **Impact:** Staff (or compromised account) can inject script into any matter’s activity UI.

### 3.7 Medium — Previous-stage progress calculated across all workflows
- **Files:** `ClientMatterHubController.php` (~493–497)
- **What goes wrong:** After moving previous stage, progress uses `WorkflowStage::count()` and unscoped order queries, while next-stage path scopes by `workflow_id`.
- **Impact:** Wrong progress % / first-stage flags when multiple workflows exist.

### 3.8 Medium — Matter Hub lacks `StaffClientVisibility` on mutating/read endpoints
- **Files:** `ClientMatterHubController.php` (stage, deadline, ownership, checklist, document approve/delete, etc.)
- **What goes wrong:** Restricted Person Assisting staff can act on any matter ID if they know/guess it, despite allocation enforcement elsewhere.
- **Impact:** Allocation bypass for restricted roles.

### 3.9 Medium — Failed client-portal email reports success
- **Files:** `ClientMatterHubController.php` (~1355–1370)
- **What goes wrong:** When `$sent` is false, response still sets `'status' => true` with message “Please try again”.
- **Impact:** UI shows success when email failed.

### 3.10 Suspected / Medium — Stage advance race (lost updates)
- **Files:** `updateClientMatterNextStage` (~198–323)
- **What goes wrong:** Read stage → compute next → save with no lock/transaction.
- **Impact:** Concurrent advances can skip stages or double-apply side effects.

### 3.11 High — Checklist document delete can delete any documents row
- **Files:** `ClientMatterHubController.php` `deleteChecklistDocument` (~2283–2310)
- **What goes wrong:** Loads/deletes by `documents.id` with no `cp_list_id` / type constraint and no client-access check.
- **Impact:** Staff can delete arbitrary documents (including signature docs).

### 3.12 Medium — Checklist status update always reports success
- **Files:** `ClientMatterHubController.php` `updateChecklistDocumentStatus` (~2343–2348)
- **What goes wrong:** Query Builder `update()` returns an int, never `false`. Condition `$updated !== false` always succeeds, even when 0 rows match.
- **Impact:** False success for non-existent IDs.

---

## Area 4 — Documents & E-Signatures

### 4.1 Critical — Signature dashboard & related routes registered outside `auth:admin`
- **Files:** `routes/documents.php` (~41–80 unauthenticated block; second copy inside auth ~211–303); `web.php` loads documents routes
- **What goes wrong:** First registrations for `/signatures/*`, `/clients/{id}/matters`, doc-to-pdf, and `/debug-pdf-page/{id}/{page}` have **no** auth middleware. Laravel matches the first route.
- **Impact:** Unauthenticated callers can hit signature admin actions and client-matters API.

### 4.2 Critical — Public PDF page render with no signing token
- **Files:** `routes/documents.php` (~182–183); `PublicDocumentController::getPage` (~859–1059); also unauthenticated `/debug-pdf-page/{id}/{page}` (~85–161)
- **What goes wrong:** Anyone who knows a document ID can render PDF pages. Pages may also be cached under `storage/app/public/pdf_pages/` (world-readable if `storage:link` exists).
- **Impact:** Public document content disclosure.

### 4.3 Critical — Public signed-document download with no token
- **Files:** `routes/documents.php` (~189–190); `PublicDocumentController::downloadSigned` (~1068–1128)
- **What goes wrong:** `/documents/{id}/download-signed` serves/redirects the signed PDF with only the numeric ID — no token, no auth. Same pattern for download-and-thankyou path.
- **Impact:** Anyone with a document ID can download signed PDFs.

### 4.4 Critical — Agreement signing accepts/overwrites arbitrary token
- **Files:** `PublicDocumentController::sign` (~61–67); `DocumentController::sendSigningLink` (~1436–1443)
- **What goes wrong:** For `doc_type == 'agreement'`, a pending signer’s token is **replaced** with whatever token appears in the URL (if format-valid). Sending also accepts client-supplied `pdf_sign_token`.
- **Impact:** Attacker with document ID can mint a link and take over signing.

### 4.5 Critical — Unauthenticated public reminder send
- **Files:** `routes/documents.php` (~199–200); `PublicDocumentController::sendReminder` (~1253–1288)
- **What goes wrong:** Public POST can trigger signing reminders for any document/signer with no auth/token proof.
- **Impact:** Spam / harassment / info leak via email.

### 4.6 High — Stored XSS via EML HTML preview
- **Files:** `ClientDocumentsController.php` `extractHtmlFromEml` / `decodeEmlPart` (~2476–2519); used in `preview_document` (~2378–2389)
- **What goes wrong:** HTML parts of `.eml` files are returned as `text/html` without sanitization.
- **Impact:** Malicious email content stored as a document executes in staff browsers.

### 4.7 High — Legal form update mass-assignment / IDOR
- **Files:** `LegalFormsController.php` `update` (~195–232); `ClientLegalForm` fillable includes `client_id`, `pdf_path`, trust account fields, `is_uploaded`, etc.
- **What goes wrong:** `$data = $request->all()` then `$legalForm->update($data)` allows rewriting client association, paths, trust BSB/account, form type, etc. No `StaffClientVisibility` on show/destroy/download either.
- **Impact:** Ownership hijack / trust details rewrite / path manipulation.

### 4.8 High — Signature bulk archive has no authorization
- **Files:** `SignatureDashboardController.php` `bulkArchive` (~908–920)
- **What goes wrong:** Archives any document IDs without policy/`authorize` checks (unlike bulk void). Combined with unauthenticated signature routes (#4.1), especially severe.
- **Impact:** Mass archive of signature documents.

### 4.9 Medium — Doc delete null-deref when admin row missing
- **Files:** `ClientDocumentsController.php` `deletedocs` (~1717–1723)
- **What goes wrong:** After loading document, `$admin = ...->first()` may be null; code uses `$admin->client_id` for S3 delete.
- **Impact:** Fatal error / failed cleanup.

### 4.10 Medium — Doc-to-PDF debug endpoints unauthenticated
- **Files:** `routes/documents.php` (~47–51); `DocToPdfController.php` `debugConfig` (~51–59)
- **What goes wrong:** `/doc-to-pdf/debug` exposes converter URL/timeout/app URL; convert/test endpoints have no auth.
- **Impact:** Config leak / resource abuse of converter.

---

## Area 5 — Email integration

### 5.1 High — Email label apply skips client-access check (remove has it)
- **Files:** `EmailLabelController.php` `apply` (~124–149) vs `remove` (~177–180)
- **What goes wrong:** Applying a label to any `email_logs` row has no `ensureCrmRecordAccessForOptionalClientId`. Restricted staff can mutate labels on emails for clients they cannot access.
- **Impact:** Label IDOR; inconsistent with remove path.

### 5.2 High — Assignment service can reassign already-assigned mail to another client
- **Files:** `UnassignedEmailAssignmentService.php` (~24–73); called from `SyncedEmailController::assignToClient`
- **What goes wrong:** Only short-circuits if already assigned to the **same** client+matter. Otherwise relocates S3 objects and rewrites `client_id` / matter — even if mail was already on another client. No `StaffClientVisibility` on target client.
- **Impact:** Data loss / wrong-client attachment of privileged email.

### 5.3 Medium — Orphan email attachments skip access gate
- **Files:** `EmailLogAttachmentController.php` `download` / `preview` (~55–60, ~178–183)
- **What goes wrong:** Access check runs only `if ($emailLog)`. If relation missing, attachment downloads with no client check.
- **Impact:** Orphan attachment disclosure.

### 5.4 Medium — Staff signature lookup by `from_email` leaks other users’ signatures
- **Files:** `ComposeSendersController.php` `staffSignature` (~79–91)
- **What goes wrong:** Fallback queries any active staff signature matching `from_email`, exposing another user’s HTML signature to the requester.
- **Impact:** Signature content disclosure across staff.

### 5.5 Suspected / Medium — Closing matter + email wipe interacts badly with synced inbox
- **Files:** Area 3 #3.3 + `IncomingEmailSyncService` / assignment services
- **What goes wrong:** After discontinue deletes `email_logs`, IMAP sync may re-import as “new” unassigned/auto-assigned mail (depending on message-id dedupe), or leave S3 orphans with no CRM index.
- **Impact:** Duplicate/orphan mail after matter close.

### 5.6 Low — Compose senders list exposes all active Zoho/SES From addresses
- **Files:** `ComposeSendersController.php` (~97–134)
- **What goes wrong:** Returns all active mailbox From addresses (not scoped to caller).
- **Impact:** Info disclosure in multi-office setups (may be intentional).

---

## Area 6 — Trust Accounting & Financial

### 6.1 Critical — Fee-transfer “residual deposit” creates phantom trust money
- **Files:** `app/Http/Controllers/CRM/ClientAccountsController.php` (~718–857)
- **What goes wrong:** When a Fee Transfer exceeds the invoice remaining balance, the code caps the withdrawal, then inserts a synthetic `Deposit` for the unused remainder. Unlike office-receipt overpayments (real money in), fee transfers *remove* trust money. Crediting a residual deposit restores the ledger as if the excess never left, while the invoice still shows paid — trust balance is inflated vs bank.
- **Impact:** Trust ledger / bank reconciliation mismatch; inflated funds held.

### 6.2 Critical — Void invoice fallback can void unrelated fee transfers
- **Files:** `ClientAccountsController.php` (~4520–4548)
- **What goes wrong:** If no fee transfer matches `invoice_no`, Method 2 matches by `withdraw_amount = $invoiceAmount` and allows `invoice_no` NULL/empty. That can void another matter’s/client’s fee transfer of the same dollar amount.
- **Impact:** Incorrect trust money returned; cross-matter/client corruption.

### 6.3 High — Matter-scoped funds check inconsistent (cross-matter withdrawal)
- **Files:** `ClientAccountsController.php` (~495–548 vs ~941–944); `TrustLedgerBalanceService.php` (~27–48)
- **What goes wrong:** Invoice fee-transfer path: if `client_matter_id` is empty, balance includes **all** matters. No-invoice path / `currentFundsHeld(null)` uses `whereNull('client_matter_id')` only.
- **Impact:** Staff can withdraw against the wrong pool or understate/overstate available funds depending on path.

### 6.4 High — Concurrent trust posts race (TOCTOU overdraw)
- **Files:** `ClientAccountsController::saveaccountreport` (~538–568, ~939–993)
- **What goes wrong:** Funds-held check is read-then-insert with no `lockForUpdate` / enclosing transaction.
- **Impact:** Two simultaneous withdrawals can both pass the check and overdraw trust.

### 6.5 High — Invoice void has no privilege gate (trust-affecting)
- **Files:** `ClientAccountsController.php` (~4390–4405)
- **What goes wrong:** `delete_receipt` requires effective super-admin; `void_invoice` only uses `ensureCrmRecordAccess`. Any staff with client access can void invoices and reverse fee transfers.
- **Impact:** Unauthorized trust-affecting voids.

### 6.6 High — `ensureCrmRecordAccess` allows missing / non-client IDs (trust posts)
- **Files:** `EnsuresCrmRecordAccess.php` (~20–34); used by `saveaccountreport` (~406)
- **What goes wrong:** If `admins.id` is not type `client|lead` (or missing), the trait **returns without aborting**. Callers can post ledger rows against arbitrary/nonexistent `client_id`.
- **Impact:** Orphan / invalid trust ledger rows.

### 6.7 Medium — Spoofable actor on trust posts / Rule 42 authority
- **Files:** `ClientAccountsController.php` (~662, ~697, ~969, ~1031, ~5124)
- **What goes wrong:** `user_id` / Rule 42 `authorised_by_staff_id` taken from request `loggedin_staffid` / `loggedin_userid` instead of only `Auth::id()`.
- **Impact:** Broken audit attribution / spoofed authority.

### 6.8 Medium — Receipt ID generation race
- **Files:** `ClientAccountsController.php` (~491–492)
- **What goes wrong:** `receipt_id = max + 1` without locking; concurrent posts can collide or reuse IDs.
- **Impact:** Duplicate receipt IDs.

### 6.9 Medium — Trust sequence first-row race
- **Files:** `TrustReceiptSequenceService.php` (~40–63)
- **What goes wrong:** When no sequence row exists, two concurrent transactions can both insert `last_sequence = 1` (duplicate `TR-YYYY-000001`) or fail uniquely depending on DB constraints.
- **Impact:** Duplicate or failed trust receipt numbers.

### 6.10 Medium — `getInvoiceAmount` IDOR
- **Files:** `ClientAccountsController.php` (~6109–6131)
- **What goes wrong:** Returns invoice `balance_amount` by `invoice_no` with no CRM access check.
- **Impact:** Cross-client invoice balance disclosure.

### 6.11 Suspected / Medium — Disbursement/Refund may overdraw (only logged)
- **Files:** `ClientAccountsController.php` (~997–1007); `TrustLedgerBalanceService.php` (~51–69)
- **What goes wrong:** Overdrawn Disbursement/Refund is logged (`overdrawn_transaction_posted`) but not blocked (unlike Fee Transfer).
- **Impact:** If practice policy forbids overdraw, this is a money bug; if Rule 40 “allow + report” is intentional, severity drops.

---

## Area 7 — Dashboard, Assignee, Office Visits, Broadcasts, Booking

### 7.1 High — Any staff can delete any assignee action by ID
- **Files:** `AssigneeController.php` (~590–708); `routes/web.php` (~327–342)
- **What goes wrong:** `destroy`, `destroy_by_me`, `destroy_to_me`, `destroy_activity`, `destroy_complete_activity` load by ID and soft-delete with no ownership / assignee / client-access check.
- **Impact:** Cross-user task deletion.

### 7.2 High — Stored XSS in assignee action list
- **Files:** `AssigneeController.php` (~455–500); `Utf8Helper.php` (~14–26)
- **What goes wrong:** `note_description` is returned via DataTables `rawColumns` after `safeSanitize`, which only fixes encoding — **does not strip HTML**.
- **Impact:** Script in action descriptions executes for viewers.

### 7.3 High — Dashboard can update any office-visit status
- **Files:** `DashboardController.php` (~250–277)
- **What goes wrong:** `updateCheckinStatus` updates any `CheckinLog` by `checkin_id` with no assignee/client access check and no status validation.
- **Impact:** Unauthorized visit status changes.

### 7.4 High — Office visit mutations null-deref / skip auth when missing
- **Files:** `OfficeVisitController.php` (~457–605)
- **What goes wrong:** `update_visit_purpose`, `change_assignee`, `attend_session`, `complete_session` call `ensureCrmRecordAccess` only if row exists, then dereference `$obj` without null guard → 500 or silent skip of auth then crash.
- **Impact:** Crashes; inconsistent auth on missing rows.

### 7.5 Medium — Broadcasts: any authenticated staff can blast “all”
- **Files:** `BroadcastNotificationAjaxController.php` (~26–50); `BroadcastNotificationService.php` (~21–51)
- **What goes wrong:** No role gate on `store`; `scope=all` resolves all recipients.
- **Impact:** Internal spam/phishing vector.

### 7.6 Medium — Audit login log readable by any staff
- **Files:** `AuditLogController.php` (~31–37)
- **What goes wrong:** Unlike Staff Login Analytics (roles 1/12/super), audit log index has only `auth:admin`.
- **Impact:** Login history disclosure beyond intended roles.

### 7.7 Critical — Public wallet payment marks appointment paid without Stripe verify
- **Files:** `routes/api.php` (~38–39); `PublicBookingController.php` (~2770–2835; also ~2509–2586 authenticated wallet twin)
- **What goes wrong:** `record-payment-without-login-wallet` / wallet path `updateOrCreate` payment as `succeeded` and sets `is_paid` / `status=paid` using client-supplied `payment_intent_id` with **no** `StripePaymentService::recordPaymentByIntent`.
- **Impact:** Anyone can mark any appointment paid without paying.

---

## Area 8 — Admin Console & Auth / Access

### 8.1 High — Phone OTP IDOR (any contact)
- **Files:** `PhoneVerificationController.php` (~23–37); `PhoneVerificationService.php` (~26–28)
- **What goes wrong:** Auth’d staff can send/verify OTP for any `client_contacts.id` with no `ensureCrmRecordAccess` on parent client.
- **Impact:** SMS spam and contact verification hijack.

### 8.2 High — Email verification status IDOR
- **Files:** `EmailVerificationController.php` (~95–111)
- **What goes wrong:** `getStatus($emailId)` returns verification state for any email id under `auth:admin` without client access check.
- **Impact:** Cross-client verification status disclosure.

### 8.3 Medium — Hardcoded privileged staff IDs in config
- **Files:** `config/crm_access.php` (~17–41, ~99–101 via `CrmAccessService::isExemptRole`)
- **What goes wrong:** Default `exempt_staff_ids` embeds production staff IDs when env is blank.
- **Impact:** Wrong deploy/env → unintended allocation bypass / front-desk privileges.

### 8.4 Medium — Admin Console feature controllers rely only on coarse middleware
- **Files:** `routes/adminconsole.php` (~36); many `AdminConsole\*Controller` (Matter, Branches, SMS send, etc.)
- **What goes wrong:** Middleware `adminconsole` gates by role list; many feature controllers lack per-action `checkAuthorizationAction` (unlike Staff/Roles).
- **Impact:** Privilege concentration if role 12/17 is broad (may be intentional).

### 8.5 Low — SuperAdmin elevation itself looks sound
- **Files:** `SuperAdminElevationController.php` (~14–36); `CrmAccessService.php` (~43–76)
- **Note:** Toggle requires `grant_super_admin_access` and non-role-1; effective privileges need session flag. **No bug found** — recorded for completeness.

### 8.6 Low — Incoming SMS webhook handling unfinished
- **Files:** `app/Http/Controllers/AdminConsole/Sms/SmsWebhookController.php` (~57, ~108)
- **What goes wrong:** TODO comments — incoming message handling not implemented.
- **Impact:** Incomplete two-way SMS (functional gap).

---

## Area 9 — SMS, API, Infrastructure

### 9.1 Critical — Unauthenticated Stripe PaymentIntent creation
- **Files:** `routes/api.php` (~41–114)
- **What goes wrong:** Public POST creates PaymentIntents with caller-chosen `amount`/`currency`/`metadata` using the practice Stripe secret.
- **Impact:** Fraud charges, PI spam, cost/abuse against Stripe account.

### 9.2 Critical — Service-account token endpoint ignores credentials
- **Files:** `routes/api.php` (~116); `ServiceAccountController.php` (~18–49)
- **What goes wrong:** Accepts `admin_email`/`admin_password` but never validates them; returns a “token” and logs email. Comment says “For local development”.
- **Impact:** Dangerous if reachable in production — fake auth success.

### 9.3 High — SMS webhooks unauthenticated / unsigned
- **Files:** `routes/sms.php` (~19–27); `SmsWebhookController.php` (~21–98)
- **What goes wrong:** Twilio/Cellcast status endpoints update `SmsLog` by message id with no signature/auth.
- **Impact:** Attacker can forge delivery status.

### 9.4 High — Unauthenticated lead API discloses existing PII
- **Files:** `routes/api.php` (~27); `LeadBookingApiController.php` (~45–52, ~140–158)
- **What goes wrong:** `POST /api/leads` with an existing email returns `lead_id`, name, phone, client reference — email enumeration + PII leak.
- **Impact:** Public PII disclosure / enumeration.

### 9.5 Medium — Manual SMS send: any Admin Console user → any phone
- **Files:** `SmsSendController.php` (~36–63)
- **What goes wrong:** No client-access check; `phone` + optional `client_id` arbitrary.
- **Impact:** Cost/abuse SMS.

### 9.6 Suspected / Medium — Compose email SSRF via document URL
- **Files:** `CRMUtilityController.php` (~1547–1561)
- **What goes wrong:** `file_get_contents($fileUrl)` on `Document.myfile` when URL-shaped. If `myfile` can be attacker-controlled, server-side fetch of internal URLs.
- **Impact:** SSRF if document URLs are writable by untrusted parties.

---

## Cross-cutting summary

| Severity | Approx. count | Dominant themes |
|----------|---------------|-----------------|
| Critical | ~12 | Merge survivor deleted; open signature/admin routes; unsigned PDF download; wallet “paid” without Stripe; open PaymentIntent API; fee-transfer phantom deposit; GET convert-all-leads; `filelink` S3 download |
| High | ~35+ | Widespread IDOR (notes/tasks/docs/emails/activities); CSRF mutating GETs; XSS; matter email wipe; permission bypasses |
| Medium | ~25+ | Incomplete merge; races; null derefs; wrong stats; debug leaks |
| Low / Suspected | ~10 | Directory disclosure; unfinished TODOs; product-intent edge cases |

---

## Suggested fix priority (documentation only — not applied)

1. Auth-wrap / remove public duplicate routes in `routes/documents.php`; token-gate `getPage` / `downloadSigned` / public reminder; fix agreement token overwrite.
2. Invert merge delete target (`merge_from`); add access checks; move/relink matters & trust or block merge until complete.
3. Remove or heavily guard `GET /leads/convert`; never convert “all”; require POST + explicit IDs.
4. Drop or bind `filelink` download to a `documents` row + access check.
5. Fix wallet payment path to verify Stripe PaymentIntent (same as non-wallet path); lock down `/payments/create-payment-intent` and service-account token endpoint.
6. Fix fee-transfer residual deposit logic; remove/amount-only void fallback; add locking for trust posts.
7. Add `canAccessClientOrLead` / `ensureCrmRecordAccess` consistently to notes, tasks, document mutations (all roles), email preview/delete, activity/cost delete, convert paths, reassign email, assignee deletes, office-visit updates.
8. Change mutating GETs (`changetype`, receipt matter fix, lead convert, stage updates) to POST + CSRF.
9. Stop hard-deleting matter emails on discontinue (soft-delete or archive); fix legacy `w_id` → `workflow_id`.
10. Remove or gate debug routes (`search-partner-test`, `test-bidirectional`, `doc-to-pdf/debug`, `debug-pdf-page`).

---

## Notes

- This audit is **static** (code review). Runtime/QA confirmation is still recommended for Suspected items and money paths.
- Vendor / TinyMCE TODOs in `public/js/tinymce/**` and bundler TODOs in `public/js/app.js` were excluded as third-party noise.
- No code fixes were applied in this pass — findings only.
