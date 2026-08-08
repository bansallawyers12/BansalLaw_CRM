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
- **Status:** Fixed
- **Files:** `app/Http/Controllers/CRM/Clients/ClientDocumentsController.php` (`download_document`)
- **What goes wrong:** With `document_id`, access is checked. Without it, any auth staff can pass an S3 URL/`filelink`, resolve the key, and get a temporary download for any object on the disk if the path is known/guessable.
- **Impact:** Cross-client document disclosure for any logged-in staff.
- **Now:** Enforces `StaffClientVisibility` check on `matchingDoc` and falls back to client resolution via path prefix. Denies access (403) when document/client access cannot be authorized.

### 1.5 High — Notes CRUD is IDOR (no CRM access checks)
- **Status:** Fixed
- **Files:** `app/Http/Controllers/CRM/Clients/ClientNotesController.php`, `routes/clients.php`
- **What goes wrong:** Create/update/list/delete/pin/get note by `client_id` / `note_id` with no `canAccessClientOrLead`.
- **Also:** invalid `noteid` → `Note::find` null → `$obj->replicate()` null dereference (~57–59).
- **Impact:** Staff can read/alter notes on clients they cannot open; possible 500 on bad IDs.
- **Now:** All notes endpoints (`createnote`, `updateNoteDatetime`, `getnotedetail`, `viewnotedetail`, `viewapplicationnote`, `getnotes`, `deletenote`, `pinnote`) enforce `EnsuresCrmRecordAccess` on `client_id`, return 404 on non-existent records, and output standard JSON responses.

### 1.6 High — Matter tasks API has no CRM access checks
- **Status:** Fixed
- **Files:** `app/Http/Controllers/CRM/Clients/ClientMatterTaskController.php`
- **What goes wrong:** `index`/`store`/`update`/`destroy` only check `client_id` matches the task row. No visibility gate.
- **Impact:** Any staff can list/create/toggle/delete another client’s tasks.
- **Now:** All matter task endpoints (`index`, `store`, `update`, `destroy`) enforce `ensureCrmRecordAccess` on `client_id` directly from resolved records/models without reliance on client-controlled parameter tampering.

### 1.7 High — Most document mutations only enforce access for restricted PA roles
- **Status:** Fixed
- **Files:** `ClientDocumentsController.php`
- **What goes wrong:** `denyJsonUnlessStaffClientAccess` / `blockEchoUnlessStaffClientAccess` return early (allow) unless `isRestrictedPersonAssisting`. Non-PA staff can upload/delete/move checklists for unallocated clients while list/detail are restricted.
- **Impact:** Inconsistent ACL — mutations weaker than reads for non-restricted roles.
- **Now:** All document, checklist, and category mutation endpoints in `ClientDocumentsController.php` enforce `StaffClientVisibility` client access authorization on target `client_id`s, validate `client_matter_id` ownership, prevent cross-client folder moves, and restrict global folder creation to superadmins.

### 1.8 High — Email HTML preview has no client access check
- **Status:** Fixed
- **Files:** `ClientsController.php`; `routes/clients.php`
- **What goes wrong:** `GET /email-logs/{id}/preview-html` loads any `EmailLog` by id from S3 and returns parsed HTML. No `canAccessClientOrLead` on `emailLog->client_id`.
- **Impact:** Cross-client email content disclosure for any logged-in staff.
- **Now:** `getParsedEmailHtml` resolves `client_id` directly from `EmailLog->client_id` or `client_matter_id` and enforces `ensureCrmRecordAccess`. Unassigned email logs are gated to staff with synced inbox permissions or superadmin privileges.

### 1.9 High — Email delete lacks client-record authorization
- **Status:** Fixed
- **Files:** `ClientsController.php`
- **What goes wrong:** Requires delete-email privilege, but never checks the user may access the email’s client. Optional `client_id`/`client_matter_id` match only if the caller supplies them.
- **Impact:** Privileged staff can delete emails (and attachment rows) for any client by id.
- **Now:** `deleteEmailLog` resolves `client_id` directly from `EmailLog->client_id` or `client_matter_id` and enforces `ensureCrmRecordAccess`. Unassigned email deletions are gated to staff with synced inbox permissions or superadmin privileges.

### 1.10 High — Activity log delete/pin and cost-agreement delete lack access checks
- **Status:** Fixed
- **Files:** `ClientsController.php` (`deleteactivitylog`, `pinactivitylog`, `deletecostagreement`)
- **Impact:** Delete/pin any activity by id; delete any cost agreement by id across clients.
- **Now:** `deleteactivitylog`, `pinactivitylog`, and `deletecostagreement` resolve `client_id` directly or from parent matter IDs, enforcing `ensureCrmRecordAccess` and rejecting unresolvable client IDs with HTTP 403.

### 1.11 High — `convertLeadOnly` skips access control and incomplete status transition
- **Status:** Fixed
- **Files:** `ClientsController.php`; `routes/clients.php`
- **What goes wrong:**
  1. No `canAccessClientOrLead` — any staff can convert any lead with an active matter.
  2. Incomplete status transition / missing client reference handling on convert.
- **Impact:** Unauthorized conversion; broken converted-client UX / analytics that rely on status/references.
- **Now:** `convertLeadOnly` enforces `ensureCrmRecordAccess`, sets active status (`status = 'active'`), generates missing client reference IDs via `ClientReferenceService`, and eliminates user_id parameter tampering.

### 1.12 High — Lead↔client type change via GET (CSRF + demotion)
- **Status:** Fixed
- **Files:** `ClientsController.php`; `routes/clients.php`
- **Now:** `changetype` route restricted to POST with CSRF verification. Method enforces `ensureCrmRecordAccess`, disallows client-to-lead demotion, updates active status (`status = 'active'`), generates missing client reference IDs, and eliminates `user_id` parameter tampering.

### 1.13 High — Trust receipt matter fix is a mutating GET with no access check
- **Status:** Fixed
- **Files:** `ClientAccountsController.php`; `routes/clients.php`
- **Now:** `fixClientFundReceiptMatterAndRegenerate` route restricted to POST with CSRF verification. Method checks `$request->isMethod('post')`, uses `$request->input(...)`, and enforces `ensureCrmRecordAccess` on receipt's `client_id`.

### 1.14 High — Inbox email reassign: no access checks + null-deref risk
- **Status:** Fixed
- **Files:** `ClientsController.php`
- **Now:** `reassiginboxemail` and `reassigsentemail` enforce `ensureCrmRecordAccess` on both source and destination client IDs, validate destination matter ownership, and permit email reassign when document rows are missing without null-dereference errors.

### 1.15 High — `notpickedcall` lacks access control (SMS + flag)
- **Status:** Fixed
- **Files:** `ClientsController.php`
- **Now:** `notpickedcall` validates numeric client `id` > 0, enforces `ensureCrmRecordAccess`, verifies target record existence, and handles SMS sending safely with JSON responses.

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

### 9.3 High — SMS webhooks: CSRF blocks providers + no signature verification
- **Files:** `routes/sms.php` (~19–28); `VerifyCsrfToken.php` (~21–34); `SmsWebhookController.php` (~21–98)
- **What goes wrong:**
  1. SMS routes use `web` middleware (CSRF). `webhooks/sms/*` is not in `$except` → Twilio/Cellcast POSTs get **419**; delivery status never updates.
  2. Handlers trust `MessageSid`/`message_id` + status from the body with no Twilio signature / Cellcast secret check. Once CSRF is excepted, anyone can forge delivery status.
- **Impact:** Broken delivery tracking today; spoofable status once CSRF is fixed without signatures.

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

### 9.7 Critical — `/delete_action` deletes arbitrary table rows for any logged-in staff
- **Files:** `routes/web.php` (~123); `CRMUtilityController.php` (~697–843); used by Admin Console JS (`matter.js`, `document-checklist.js`)
- **What goes wrong:** Under `auth:admin` only (no role/table allowlist in the default branch). Client supplies `table` + `id`; else-branch does `DB::table($table)->where('id', $id)->delete()`.
- **Impact:** Any authenticated staff can destroy matters, checklists, staff rows, etc.

### 9.8 Critical — `/move_action` arbitrary column zeroing
- **Files:** `routes/web.php` (~124); `CRMUtilityController.php` (~365–389)
- **What goes wrong:** No `viewerCanMutateAnyRecord` check. Sets `$requestData['col'] = 0` on any existing table/id.
- **Impact:** Mass data corruption across arbitrary tables.

### 9.9 High — `/update_action` arbitrary column toggle (super-admin path)
- **Files:** `CRMUtilityController.php` (~291–326); `public/js/adminconsole/staff.js` (~547–561)
- **What goes wrong:** For effective super-admins, updates `$requestData['col']` on any `$requestData['table']` with no column allowlist. Non–super-admin Admin Console roles get “not authorized” so staff status toggle fails for them.
- **Impact:** Super-admin can toggle arbitrary columns; roles 12/17 cannot toggle staff active/inactive via UI.

### 9.10 Medium — `PythonService::mergePdfs` uses invalid HTTP attach API
- **Files:** `PythonService.php` (~100–114)
- **What goes wrong:** Builds a multipart array then calls `Http::attach($multipart)`. Laravel’s `attach($name, $contents, $filename)` expects scalar args → merge requests fail/malform.
- **Impact:** PDF merge integration broken when called.

### 9.11 Medium — Device token reassignment across users
- **Files:** `StaffApiAuthController.php` (~215–226)
- **What goes wrong:** Existing `device_token` row is reassigned to the newly logging-in staff with no ownership check.
- **Impact:** Push notifications can be hijacked by presenting another device’s token.

### 9.12 High — Staff API login leaves orphan Sanctum tokens on refresh-token failure
- **Files:** `StaffApiAuthController.php` (~55–86)
- **What goes wrong:** `$staff->createToken(...)` runs before refresh-token insert. On DB failure the API returns 500 but the Sanctum personal access token remains valid.
- **Impact:** Partial login / orphaned valid API credentials.

### 9.13 High — Staff API login has no rate limiting / lockout
- **Files:** `StaffApiAuthController.php` (~26–53); `routes/api.php` (~18)
- **What goes wrong:** `POST /api/admin-login` has no throttle (unlike web login’s RateLimiter).
- **Impact:** Online password spraying against staff accounts.

---

## Area 10 — Authentication & Authorization (supplement)

### 10.1 Critical — Plaintext password stored in “Remember Me” cookie
- **Files:** `AdminLoginController.php` (~112–114); `resources/views/auth/admin-login.blade.php` (~26–40, 57)
- **What goes wrong:** On successful login with `remember` set, cookies named `email` and `password` store the submitted plaintext password; the login view reads them back into the password input.
- **Impact:** Credential secrecy defeated (XSS on login page, shared browsers, cookie theft).

### 10.2 Critical — reCAPTCHA runs after authentication; failed captcha leaves session logged in
- **Files:** `AdminLoginController.php` (~54–58, 88–109)
- **What goes wrong:** `attemptLogin()` succeeds and session is regenerated **before** `authenticated()` verifies reCAPTCHA. On captcha failure the method redirects with an error but **does not log the user out**.
- **Impact:** Captcha is not an effective login gate; valid password alone establishes a usable admin session.

### 10.3 High — Inactive staff can still log in via web CRM
- **Files:** `AdminLoginController.php` (~66–71); contrast `StaffApiAuthController.php` (~43–46) which requires `status = 1`
- **What goes wrong:** Web login only calls `Auth::guard('admin')->attempt(email/password)` with no active-status filter.
- **Impact:** Deactivated staff retain CRM access through the web UI.

### 10.4 Medium — Login response enumerates valid emails
- **Files:** `AdminLoginController.php` (~131–139)
- **What goes wrong:** Failed login returns `"Wrong password"` when the email exists in `staff`, vs generic failure otherwise.
- **Impact:** Confirms account existence.

### 10.5 Medium — Logout audit user id taken from request body, not session
- **Files:** `AdminLoginController.php` (~158–168); `resources/views/layouts/app.blade.php` (~63–65)
- **What goes wrong:** `$user = $request->id` logs whatever `id` the client posts instead of `Auth::guard('admin')->id()`.
- **Impact:** Audit logs can be forged / mis-attributed.

### 10.6 Medium — Quick access grant check-then-create race
- **Files:** `CrmAccessService.php` (~159–203, 344–355); `AccessGrantController.php` (~47–78)
- **What goes wrong:** `hasDuplicateActiveQuickGrant()` then `create()` is not transactional/locked.
- **Impact:** Concurrent requests can create multiple active quick grants for the same staff+record.

### 10.7 Critical — Module authorization query hits non-existent `usertype` column
- **Files:** `app/Http/Controllers/Controller.php` (~253–276); used by `StaffController.php`, `UserroleController.php`
- **What goes wrong:** `checkAuthorizationAction()` runs `UserRole::where('usertype', $role)`. Live `user_roles` columns are only `id, name, description, module_access` (no `usertype`). Staff `role` is FK to `user_roles.id`. For non–Super-Admin actors this throws SQL error instead of allowing/denying.
- **Impact:** Role 12/17 (or any non-bypassed role) hits 500 on Staff create/edit/store/update or User Roles actions. Even if column were fixed, helper treats `module_access` as controller name strings while CRM uses numeric module keys — wrong allow/deny.

### 10.8 High — Any successful staff save can assign Super Admin role
- **Files:** `StaffController.php` (~387–398); staff form partials (~124–128)
- **What goes wrong:** `fillStaffFromRequest()` sets `$obj->role` from the request with no restriction. Role dropdown includes Super Admin id `1`. `grant_super_admin_access` is gated, but permanent `role = 1` is not.
- **Impact:** Permanent privilege escalation via staff create/update.

### 10.9 High — Invited staff tab returns all staff
- **Files:** `StaffController.php` (~335–339)
- **What goes wrong:** `'invited' => Staff::query()` has no invite/status filter.
- **Impact:** Invited tab lists every staff row, not invitees.

### 10.10 Medium — Staff timezone endpoint lacks module authorization
- **Files:** `StaffController.php` (~294–312); `routes/adminconsole.php` (~168)
- **What goes wrong:** `savezone` only requires `auth:admin` (+ adminconsole middleware). Any Admin Console user can set any staff member’s `time_zone` by `user_id`.
- **Impact:** Cross-staff timezone mutation.

---

## Area 11 — Dashboard (supplement)

### 11.1 High — Matter stage update has no authorization / ownership check (IDOR)
- **Files:** `DashboardController.php` (~85–97); `DashboardService.php` (~437–448)
- **What goes wrong:** Any authenticated staff can set `workflow_stage_id` on **any** `client_matters` row by `item_id`.
- **Impact:** Cross-team matter stage tampering.

### 11.2 High — Action complete / deadline extend have no access checks (IDOR)
- **Files:** `DashboardController.php` (~138–190); `DashboardService.php` (~520–586)
- **What goes wrong:** `extendNoteDeadline` / `updateActionCompleted` update notes by id/group with no assignee/owner/client access check.
- **Impact:** Complete or extend another user’s actions.

### 11.3 High — Dashboard matter list bypasses allocation for most roles
- **Files:** `DashboardService.php` (~57–95, 236–251)
- **What goes wrong:** `applyRoleBasedFiltering` only restricts roles `12`, `13`, `16`. Other non–super-admin roles get **no** matter filter.
- **Impact:** Firm-wide matters shown despite allocation elsewhere.

### 11.4 Medium — Active/closed matter counters are global, not viewer-scoped
- **Files:** `DashboardService.php` (~257–286)
- **What goes wrong:** Counts cache firm-wide totals with no staff filter.
- **Impact:** Leaks org-wide volume to every dashboard user.

### 11.5 Medium — Visa expiry message endpoint lacks client access check
- **Files:** `DashboardController.php` (~215–223); `DashboardService.php` (~632–636)
- **What goes wrong:** `getVisaExpiryMessage($clientId)` reads visa data for any `client_id` without access check.
- **Impact:** Cross-client visa data disclosure.

---

## Area 12 — Clients / Leads (supplement)

### 12.1 High — Note delete/pin via GET (CSRF-friendly state change)
- **Files:** `routes/clients.php` (~135, 142); `ClientNotesController.php` (~447–510)
- **What goes wrong:** Destructive note actions are `GET` routes, bypassing CSRF.
- **Impact:** Crafted link while a staff session is open can delete/pin notes.

### 12.2 High — Global client search returns PII for inaccessible records
- **Files:** `ClientsController.php` (~2861–3230, 3242–3261); `StaffClientVisibility.php` (~170–197)
- **What goes wrong:** `getallclients` searches all clients/leads; `enrichGlobalSearchItem` only sets `locked` — it does **not** redact name/email/phones.
- **Impact:** Allocation bypassed for discovery/PII via header search.

### 12.3 High — Parents/siblings/others save via `saveSection` fatals on PHP 8
- **Files:** `ClientPersonalDetailsController.php` (~1916–1921 vs ~6053, 6198, 6333)
- **What goes wrong:** `saveSection` calls `saveParentsInfoSection($request, $client)` (2 args) but those methods only accept `Request $request`. On PHP 8.3 → `ArgumentCountError`.
- **Impact:** Those sections cannot save through the primary AJAX path.

### 12.4 High — Hardcoded AusPost AUTH-KEY in source
- **Files:** `ClientPersonalDetailsController.php` (~122–134)
- **What goes wrong:** Live API key string is embedded in code and sent on every `updateAddress` call.
- **Impact:** Credential leak / API abuse.

### 12.5 High — Legacy test-score update has no access check + null deref
- **Files:** `ClientsController.php` (~1967–1987); `routes/clients.php` (~47)
- **What goes wrong:** `editTestScores` deletes/recreates scores for any `client_id` without access check; also reads `$client->type` before null guard.
- **Impact:** Cross-client score mutation; 500 on bad id.

### 12.6 Medium — Contact match / uniqueness endpoints leak existence/PII
- **Files:** `LeadController.php` (~2016–2127); `routes/web.php` (~204)
- **What goes wrong:** `is_email_unique`, `is_contactno_unique`, `checkContactMatch` query all clients/leads with no allocation filter. Phone uniqueness uses `LIKE '%contact%'` (false positives).
- **Impact:** Account/contact enumeration; blocked valid creates on substring matches.

### 12.7 Low — `POST /clients/edit` (`clients.update`) cannot receive route `{id}`
- **Files:** `routes/clients.php` (~41–42); `ClientsController.php` (~1930–1960)
- **What goes wrong:** Named update route has no `{id}` parameter, so native form POST hits `edit($id = null)` and redirects unauthorized. JS save-section avoids this; full form submit remains broken.
- **Impact:** Non-AJAX edit form submit fails.

---

## Area 13 — Matters / Documents / Email / Assignee (supplement)

### 13.1 High — Empty `unique_group_id` can mass-complete actions
- **Files:** `AssigneeController.php` (~97–101)
- **What goes wrong:** Defaults `unique_group_id` to `''`, then `where('unique_group_id', '')` + `whereNotNull('unique_group_id')`. Empty string is not NULL, so **all** notes with `unique_group_id = ''` and an assignee get `status = 1`.
- **Impact:** Mass unintended action completion.

### 13.2 High — Complete/reopen any action by id (no assignee/assigner check)
- **Files:** `AssigneeController.php` (~93–105, ~164–170, ~746–775)
- **What goes wrong:** `updateActionCompleted` / `updateAction` can complete any note by id with no check that actor is assignee, assigner, or admin.
- **Impact:** Cross-user action completion (in addition to destroy IDOR in #7.1).

### 13.3 High — Signature associate accepts matter from another client
- **Files:** `SignatureDashboardController.php` (~843–858); `SignatureService.php` (~475–486)
- **What goes wrong:** Validates `matter_id` exists in `client_matters`, not that it belongs to `entity_id`.
- **Impact:** Document attached to client A with client B’s matter id.

### 13.4 High — Manual email upload does not verify matter belongs to client
- **Files:** `EmailUploadController.php` (~513–517, ~608–649)
- **What goes wrong:** Uses `upload_*_mail_client_matter_id` as-is after client access check only. Smart import correctly checks via `matterBelongsToClient`.
- **Impact:** Cross-client matter linkage / wrong filing.

### 13.5 Medium — Checklist stage resolved by name only (cross-workflow collision)
- **Files:** `ClientMatterHubController.php` (~1539–1540)
- **What goes wrong:** `workflow_stages` looked up by `name` alone. Duplicate stage names across workflows attach the wrong `wf_stage_id`.
- **Impact:** Wrong checklist stage binding when names collide (e.g. `"New"`).

### 13.6 Medium — Reopen request null-deref on missing matter type
- **Files:** `ClientMatterHubController.php` (~885, ~964)
- **What goes wrong:** `Matter::find($clientMatter->sel_matter_id)->title` — if `sel_matter_id` is null/orphan, property access throws.
- **Impact:** Reopen request/approve notifications fail.

### 13.7 Medium — Admin `submitSignatures` requires constructed S3 key (breaks URL-based docs)
- **Files:** `DocumentController.php` (~2042–2057)
- **What goes wrong:** Builds `$s3Key` from admin `client_id` + `doc_type` + `myfile_key` with no null/URL fallback (public path has fallbacks).
- **Impact:** Staff signing fails for docs stored only via full `myfile` URL / missing `myfile_key`.

### 13.8 Medium — `getClientMatters` / `suggestAssociation` leak client–matter graph
- **Files:** `SignatureDashboardController.php` (~737–830)
- **What goes wrong:** No `ensureCrmRecordAccess` on client id / email lookup; returns matters for any client id or email match.
- **Impact:** Cross-client matter graph disclosure.

### 13.9 Medium — SMS template `usage_count` incremented before send succeeds
- **Files:** `UnifiedSmsManager.php` (~245–252)
- **What goes wrong:** `increment('usage_count')` runs before `sendSms()`. Failed sends still inflate usage; delete guard then blocks deleting unused-but-failed templates.
- **Impact:** Inflated usage stats; templates stuck undeleteable.

### 13.10 Medium — Non-delivered SMS status clears `delivered_at`
- **Files:** `SmsWebhookController.php` (~36–39, ~87–90); `UnifiedSmsManager.php` (~400–404)
- **What goes wrong:** Updates set `'delivered_at' => in_array($status, ['delivered']) ? now() : null`. Later `sent`/`queued` callbacks wipe a previously set delivery timestamp.
- **Impact:** Lost delivery evidence after out-of-order callbacks.

### 13.11 Medium — Workflow stages can be created with `workflow_id = null`
- **Files:** `WorkflowController.php` (~275–279, ~354–368)
- **What goes wrong:** If `workflow_id` omitted and no workflow named exactly `General` exists, stages insert with `workflow_id = null`.
- **Impact:** Orphaned stages detached from all workflows.

### 13.12 Medium — Matter / first-email templates saved with no validation
- **Files:** `MatterEmailTemplateController.php` (~58–80, ~110–132)
- **What goes wrong:** `store`/`update` accept unsanitized fields with no `validate()`; `matter_id` not checked against `matters`.
- **Impact:** Empty/invalid templates persist; bad matter links.

---

## Area 14 — Trust / Financial / Booking (supplement)

### 14.1 Critical — Trust void excludes original and also posts a reversing entry (double impact)
- **Files:** `ClientAccountsController.php` (~5106–5168, helpers ~67–115); `TrustLedgerBalanceService.php` (~13–48); `TrustReportQueryService.php` (~19–31)
- **What goes wrong:** Void path sets `trust_voided_at` on the original (excluded from balances/reports) **and** inserts a swap deposit/withdraw reversal that still counts. Net change ≈ **−2×** original movement, not zero.
- **Impact:** Trial balance, statements, journals, overdrawn report, and bank-recon lists corrupted.

### 14.2 High — EFTPOS surcharge added into trust deposit amount
- **Files:** `ClientAccountsController.php` (~478–487, ~934–936)
- **What goes wrong:** For trust `Deposit` + EFTPOS, `deposit_amount = principal + surcharge`. Surcharge is typically firm income, not client money.
- **Impact:** Current Funds Held / trial balance overstated.

### 14.3 High — Allocating a deposit creates fee transfer for full deposit (no invoice cap)
- **Files:** `ClientAccountsController.php` (~2803–2864, ~2902–2950)
- **What goes wrong:** `updateClientFundLedger` withdraws the entire deposit as Fee Transfer without capping to invoice outstanding. Invoice can be overpaid; status forced to Paid with balance clamped to 0.
- **Impact:** Over-allocation of trust to invoices.

### 14.4 High — Hard-delete of office/journal receipts does not recalculate invoice status
- **Files:** `ClientAccountsController.php` (~5195–5228)
- **What goes wrong:** Non-trust `delete_receipt` hard-deletes the row. If it was an office receipt applied to an invoice, invoice status/partial/balance stay stale.
- **Impact:** Invoice can remain Paid with payments gone.

### 14.5 High — Void invoice stores wrong `withdraw_amount_before_void`
- **Files:** `ClientAccountsController.php` (~4454–4458)
- **What goes wrong:** Saves `withdraw_amount_before_void` from `balance_amount` (outstanding), not the invoice total `withdraw_amount`.
- **Impact:** Recovery/audit of voided invoice totals corrupted.

### 14.6 Medium — Invoice void does not reverse/unallocate office receipts
- **Files:** `ClientAccountsController.php` (~4401–4636)
- **What goes wrong:** Voids invoice and fee transfers only. Office receipts keep `invoice_no` pointing at a zeroed/voided invoice.
- **Impact:** Money appears applied with no live invoice.

### 14.7 Medium — Office-receipt payment totals ignore `trust_voided_at` on fee transfers
- **Files:** `ClientAccountsController.php` (~2039–2049, ~2436–2446)
- **What goes wrong:** Fee-transfer sums for invoice status filter `void_fee_transfer` but not `trust_voided_at`.
- **Impact:** Inconsistent paid/outstanding after trust voids.

### 14.8 Medium — Period lock uses strict `d/m/Y`; bad dates can bypass lock check
- **Files:** `TrustPeriodService.php` (~27–45)
- **What goes wrong:** `Carbon::createFromFormat('d/m/Y', $ddMmYyyy)` throws/mis-parses non-conforming `trans_date` values.
- **Impact:** Locked period may not be enforced for malformed dates (or whole post fails inconsistently).

### 14.9 Critical — Unauthenticated booking API accepts `is_paid` / `payment_status=completed`
- **Files:** `LeadBookingApiController.php` (~166–355); `routes/api.php` (~27–28)
- **What goes wrong:** Public `POST /api/booking-appointments` allows callers to set `is_paid`, `payment_status`, `paid_at`, amounts, and status. Forces paid + `paid_at` when those flags are set. No auth.
- **Impact:** Anyone can create “paid” appointments without payment.

### 14.10 High — Paid bookings created with `is_paid = true` before payment
- **Files:** `PublicBookingController.php` (~652–655, ~748–755, ~987–990, ~1071–1078)
- **What goes wrong:** For paid services (`service_id` 1/3), CRM sets `is_paid => true` while `payment_status` may still be `pending`. Also accepts request `payment_status === 'completed'` to set CRM `status = 'paid'` without a charge.
- **Impact:** Appointments marked paid before Stripe succeeds.

### 14.11 High — `recordPaymentByIntent` weak ownership when metadata empty
- **Files:** `StripePaymentService.php` (~409–444); used by public record-payment (~2659–2703)
- **What goes wrong:** Metadata `appointment_id` is only checked if present. A succeeded PI of matching cent amount (currency not checked) can be attached to any unpaid appointment via unauthenticated `record-payment-without-login`.
- **Impact:** Cross-appointment payment attachment / free booking abuse.

### 14.12 High — Logged-in booking ignores Bansal slot-unavailable and still creates locally
- **Files:** `PublicBookingController.php` (~682–718)
- **What goes wrong:** On Bansal create failure, authenticated `addAppointment` always falls back to a temporary `bansal_appointment_id` and creates the CRM row. Guest flow aborts on slot conflicts; login flow does not.
- **Impact:** Double-booked slots for authenticated users.

### 14.13 Medium — Duplicate-slot check is per-client only (race + multi-client)
- **Files:** `PublicBookingController.php` (~582–594)
- **What goes wrong:** Uniqueness is only same `client_id` + datetime; no DB lock across clients.
- **Impact:** Two clients can book the same slot concurrently.

### 14.14 Medium — Client can self-complete appointments
- **Files:** `PublicBookingController.php` (~1680–1734)
- **What goes wrong:** Authenticated client may set status to `completed` with no staff confirmation / time check.
- **Impact:** Premature completion without staff oversight.

### 14.15 Medium — Sync skip-on-existing never updates payment/status from website
- **Files:** `AppointmentSyncService.php` (~140–143)
- **What goes wrong:** Existing `bansal_appointment_id` always `skipped`. Website payment/status changes never refresh CRM via polling sync.
- **Impact:** Stale paid/cancelled state in CRM.

### 14.16 Medium — Front-desk submit does not enforce “today” on appointment
- **Files:** `FrontDeskCheckInController.php` (~187–194); contrast `CheckInAppointmentService.php` (~16–27)
- **What goes wrong:** Comment says validate appointment is today; code only checks `client_id` match.
- **Impact:** Past/future appointments can be linked / claimed at check-in.

### 14.17 Medium — Stored XSS in office-visit detail HTML
- **Files:** `OfficeVisitController.php` (~284, ~390)
- **What goes wrong:** `visit_purpose` and history `description` are echoed unescaped into HTML returned by `getcheckin`.
- **Impact:** Script execution when viewing visit detail modal.

---

## Cross-cutting summary

| Severity | Approx. count | Dominant themes |
|----------|---------------|-----------------|
| Critical | ~20+ | Merge survivor deleted; open signature routes; unsigned PDF download; wallet/booking pay without Stripe; open PaymentIntent + service-token APIs; `/delete_action`/`/move_action`; trust void double-count; fee-transfer phantom deposit; plaintext password cookie; reCAPTCHA bypass; `usertype` authz SQL break |
| High | ~55+ | Widespread IDOR; CSRF mutating GETs; XSS; matter email wipe; SMS CSRF+unsigned; global search PII; PHP 8 parents save crash; privilege escalation via role assign |
| Medium | ~40+ | Incomplete merge; races; null derefs; wrong stats; booking slot races; period lock; orphan stages |
| Low / Suspected | ~12 | Directory disclosure; unfinished TODOs; product-intent edge cases |

---

## Suggested fix priority (documentation only — not applied)

1. Stop storing passwords in cookies; verify reCAPTCHA **before** login / logout on failure; enforce `status = 1` on web login; fix `checkAuthorizationAction` (`usertype` → role id + real module ACL).
2. Lock down `/delete_action` / `/move_action` / `/update_action` (table/column allowlists + role gates).
3. Auth-wrap / remove public duplicate routes in `routes/documents.php`; token-gate `getPage` / `downloadSigned` / public reminder; fix agreement token overwrite.
4. Invert merge delete target (`merge_from`); add access checks; move/relink matters & trust or block merge until complete.
5. Fix wallet + public booking payment paths (verify Stripe; reject client-supplied `is_paid`); lock down PaymentIntent + service-account token endpoints.
6. Fix fee-transfer residual deposit; trust void double-count; EFTPOS surcharge in trust; amount-only void fallback; add locking for trust posts.
7. Add `canAccessClientOrLead` consistently to notes, tasks, document mutations, email preview/delete, dashboard mutates, assignee complete/destroy, convert paths; redact global search for locked rows.
8. Change mutating GETs to POST + CSRF; except SMS webhooks from CSRF **and** add provider signature verification.
9. Stop hard-deleting matter emails on discontinue; fix legacy `w_id` → `workflow_id`; fix `saveSection` parents/siblings signatures; empty-`unique_group_id` mass complete.
10. Remove or gate debug routes; remove hardcoded AusPost key; gate Super Admin role assignment.

---

## Notes

- This audit is **static** (code review). Runtime/QA confirmation is still recommended for Suspected items and money paths.
- Vendor / TinyMCE TODOs in `public/js/tinymce/**` and bundler TODOs in `public/js/app.js` were excluded as third-party noise.
- No code fixes were applied in this pass — findings only.
- **Supplement (Areas 10–14):** Added after deeper parallel audits completed; covers Auth, Dashboard, Admin Console ACL, `/delete_action`, trust void double-count, booking payment spoofing, and related gaps not fully listed in Areas 1–9.
