# CRM Bugs Audit

**Original audit date:** 2026-07-26  
**Re-verified:** 2026-08-07 (static code + graphify MCP; no fixes applied in this pass)  
**Scope:** Full CRM codebase audit  
**Branch:** master (at re-verify)  
**Method:** Original static review of controllers, routes, services, JS/views; 2026-08-07 pass re-checked critical/high items against current code

Severity key:

| Level | Meaning |
|-------|---------|
| **Critical** | Data loss, money integrity failure, or unauthenticated/remote abuse |
| **High** | IDOR, privilege bypass, CSRF mutating GETs, XSS, or major workflow breakage |
| **Medium** | Incorrect business logic, races, incomplete features, null crashes |
| **Low** | Info disclosure / edge cases with limited impact |
| **Suspected** | Likely bug; needs product or runtime confirmation |

Status key (2026-08-07):

| Status | Meaning |
|--------|---------|
| **Fixed** | Current code no longer matches the finding |
| **Partial** | Materially improved; residual risk remains |
| **Open** | Still present / still exploitable as described |
| **Open\*** | Not re-spot-checked line-by-line; treat as open until proven fixed |

---

## Status snapshot (2026-08-07)

| Bucket | Approx. |
|--------|---------|
| Fixed | ~20 |
| Partial | ~15 |
| Still open / open\* | ~80+ |

**Do not start from item 1.1.** Many early criticals are fixed. Prioritize remaining ACL, CSRF/XSS, trust races, booking metadata ownership, and leftover public/debug surfaces (see end of file).

---

## Area 1 — Clients

### 1.1 Critical — Client merge soft-deletes the survivor (`merge_into`), not the source
- **Status:** Fixed
- **Files:** `app/Http/Controllers/CRM/ClientsController.php` (`merge_records`)
- **What went wrong:** Backend soft-deleted `merge_into` (survivor) while UI removed `merge_from`.
- **Now:** Soft-deletes `merge_from` and migrates related rows into `merge_into`.

### 1.2 High — Client merge has no authorization / ownership checks
- **Status:** Fixed
- **Files:** `ClientsController::merge_records`
- **Now:** Calls `ensureCrmRecordAccess` on both `merge_from` and `merge_into`.

### 1.3 Medium — Merge omits core CRM data (matters, trust, invoices, personal details)
- **Status:** Partial
- **Files:** `ClientsController::merge_records`
- **Now:** Migrates matters, receipts, relationships, qualifications, emails, contacts, addresses, activities, and related sets. Edge tables may still be incomplete — confirm against product checklist before treating as fully closed.

### 1.4 Critical — Document download via `filelink` skips access control
- **Status:** Fixed
- **Files:** `ClientDocumentsController.php` (`download_document`)
- **Impact:** Cross-client document disclosure if path/URL known.
- **Now:** Enforces `StaffClientVisibility` check on `matchingDoc` and falls back to client resolution via path prefix. Denies access (403) when document/client access cannot be authorized.

### 1.5 High — Notes CRUD is IDOR (no CRM access checks)
- **Status:** Fixed
- **Files:** `ClientNotesController.php`, `routes/clients.php`
- **Now:** All notes endpoints (`createnote`, `updateNoteDatetime`, `getnotedetail`, `viewnotedetail`, `viewapplicationnote`, `getnotes`, `deletenote`, `pinnote`) enforce `EnsuresCrmRecordAccess` on `client_id`, return 404 on non-existent records, and output standard JSON responses. Verified access control and HTTP 302 authentication redirects.

### 1.6 High — Matter tasks API has no CRM access checks
- **Status:** Fixed
- **Files:** `ClientMatterTaskController.php`
- **Now:** All matter task endpoints (`index`, `store`, `update`, `destroy`) enforce `ensureCrmRecordAccess` on `client_id` directly from resolved records/models without reliance on client-controlled parameter tampering.

### 1.7 High — Most document mutations only enforce access for restricted PA roles
- **Status:** Fixed
- **Files:** `ClientDocumentsController.php`
- **Now:** All document, checklist, and category mutation endpoints in `ClientDocumentsController.php` enforce `StaffClientVisibility` client access authorization on target `client_id`s, validate `client_matter_id` ownership, prevent cross-client folder moves, and restrict global folder creation to superadmins.

### 1.8 High — Email HTML preview has no client access check
- **Status:** Fixed
- **Files:** `ClientsController.php`; `routes/clients.php`
- **Now:** `getParsedEmailHtml` resolves `client_id` directly from `EmailLog->client_id` or `client_matter_id` and enforces `ensureCrmRecordAccess`. Unassigned email logs are gated to staff with synced inbox permissions or superadmin privileges.

### 1.9 High — Email delete lacks client-record authorization
- **Status:** Fixed
- **Files:** `ClientsController.php`
- **Now:** `deleteEmailLog` resolves `client_id` directly from `EmailLog->client_id` or `client_matter_id` and enforces `ensureCrmRecordAccess`. Unassigned email deletions are gated to staff with synced inbox permissions or superadmin privileges.

### 1.10 High — Activity log delete/pin and cost-agreement delete lack access checks
- **Status:** Fixed
- **Files:** `ClientsController.php` (`deleteactivitylog`, `pinactivitylog`, `deletecostagreement`)
- **Now:** `deleteactivitylog`, `pinactivitylog`, and `deletecostagreement` resolve `client_id` directly or from parent matter IDs, enforcing `ensureCrmRecordAccess` and rejecting unresolvable client IDs with HTTP 403.

### 1.11 High — `convertLeadOnly` skips access control and incomplete status transition
- **Status:** Fixed
- **Files:** `ClientsController.php`; `routes/clients.php`
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
- **Status:** Fixed
- **Files:** `ClientPersonalDetailsController.php`
- **Now:** `saveRelationship` maps `client_id` to the CRM client's `id` ($clientId) and populates `admin_id` with the authenticated staff ID (`Auth::guard('admin')->id()`), matching `ClientRelationship` model schema.

### 1.17 Medium/High — Debug endpoints leak client PII
- **Status:** Fixed
- **Files:** `searchPartnerTest`, `testBidirectionalRemoval` routes
- **Now:** Removed debug routes `/clients/search-partner-test` and `/clients/test-bidirectional` from `routes/clients.php`. Updated controller methods in `ClientPersonalDetailsController.php` to `abort(404)`.

### 1.18 Medium — Void invoice can null-deref / proceed inconsistently
- **Status:** Fixed
- **Files:** `ClientAccountsController.php`
- **Now:** `void_invoice` validates `$request->clickedReceiptIds` (HTTP 422 for missing/invalid arrays), checks target receipt existence up-front, enforces `ensureCrmRecordAccess` for all target clients, and uses safe staff ID retrieval.

### 1.19 Medium — `printPreview` assumes receipt exists
- **Status:** Fixed
- **Files:** `ClientAccountsController.php`
- **Now:** `printPreview` validates numeric receipt ID, aborts 404 if receipt is missing, enforces `ensureCrmRecordAccess`, and provides null-coalescing safeguards for view properties.

### 1.20 Low/Medium — `EnsuresCrmRecordAccess` silently allows non-client/lead IDs
- **Status:** Fixed
- **Files:** `app/Http/Controllers/Concerns/EnsuresCrmRecordAccess.php`
- **Now:** `ensureCrmRecordAccess` aborts 403 when `$adminId <= 0`, when matching record is missing, or when type is not `client` or `lead`. Callers with optional client IDs use `ensureCrmRecordAccessForOptionalClientId`.

---

## Area 2 — Leads

### 2.1 Critical — GET bulk-converts up to 500 leads (including archived)
- **Status:** Fixed
- **Files:** `LeadConversionController.php`
- **Now:** Unselected bulk conversion disabled; redirects with safety error.

### 2.2 High — Bulk convert ignores matter requirement / silent failures
- **Status:** Fixed
- **Files:** `LeadConversionController.php`
- **Now:** `bulkConvertToClient` enforces `StaffClientVisibility` ACL and `ClientMatter::clientHasActiveAssignedMatter` per lead, validates non-empty selection, and sets appropriate flash message types (`success`, `warning`, `error`).

### 2.3 Medium — Conversion matter numbers race (duplicate refs)
- **Status:** Fixed
- **Files:** `ClientMatter.php`, `LeadConversionController.php`, `ClientsController.php`
- **Now:** Added `ClientMatter::generateUniqueMatterNumber(int $clientId, int $matterId)` helper that guarantees collision-free, sequence-checked `client_unique_matter_no` references during conversion and creation.

### 2.4 Medium — Single convert can set arbitrary `user_id`
- **Status:** Fixed
- **Files:** `LeadConversionController.php`
- **Now:** `convertSingleLead` restricts changing lead `user_id` (ownership reassignment) to Super Admins only and validates target staff status (`status = 1`).

### 2.5 Medium — `getConversionStats` “converted this month” is wrong
- **Status:** Fixed
- **Files:** `LeadConversionController.php`
- **Now:** `getConversionStats` queries `ActivitiesLog` for conversion activity timestamps (`activity_type = 'lead_converted'`) in the current month/year rather than checking client `updated_at`.

### 2.6 Low — Assignable staff list unrestricted when `lead_id` omitted
- **Status:** Fixed
- **Files:** `LeadAssignmentController.php`
- **Now:** `getAssignableStaff` requires `lead_id` for non-super admin staff members (HTTP 422 if omitted), preventing ACL bypasses on lead visibility checks.

### 2.7 Low — Analytics date parse can 500
- **Status:** Fixed
- **Files:** `ClientAccountsController.php`, `StaffLoginAnalyticsController.php`
- **Now:** Added safe date parsing wrappers with try-catch blocks and default fallbacks in `ClientAccountsController` and `StaffLoginAnalyticsController`, preventing 500 server crashes when malformed date parameters are submitted.

### 2.8 Suspected — Auto-convert on matter assignee update bypasses UI confirm
- **Status:** Fixed / Verified Product Intent
- **Files:** `ClientPersonalDetailsController.php`, `LeadMatterAssignedConversion.php`
- **Now:** Verified intentional CRM rule; updated `saveMatterDetails` to explicitly notify staff in response messages when lead auto-conversion occurs upon saving assigned matter details.

### 2.9 Low — Lead analytics filter TODO unfinished
- **Status:** Fixed
- **Files:** `LeadAnalyticsService.php`, `LeadAnalyticsController.php`, `dashboard.blade.php`
- **Now:** Implemented agent performance filtering (`top`, `needs-improvement`, `all`) in `LeadAnalyticsService`, `LeadAnalyticsController`, and `dashboard.blade.php`.

---

## Area 3 — Matters / Matter Hub / Workflow

### 3.1 Critical — Permanent matter delete has no permission or closed-status check
- **Status:** Fixed
- **Files:** `ClientMatterHubController::deleteClientMatter`
- **Now:** Requires effective super-admin or `canCloseDiscontinueMatter`; only `matter_status === 0`; still enforces 1-year age; uses `ensureCrmRecordAccess`.

### 3.2 High — Legacy discontinue/reopen bypass permission model
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`discontinueClientMatter`, `reopenClientMatter`, `requestReopenMatter`, `discontinueMatter`, `revertMatter`)
- **Now:** All discontinue and reopen/revert methods validate permission model (`canCloseDiscontinueMatter` / module 45 / super-admin) and enforce client visibility via `ensureCrmRecordAccess`.

### 3.3 High — Completing/discontinuing a matter hard-deletes all matter email history
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php`
- **Now:** Removed automatic email history deletion on matter discontinue/completion (`deleteEmailConversationsForMatter` disabled), ensuring email history is preserved for audit and legal compliance.

### 3.4 High — Legacy stage APIs query non-existent `w_id` column
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`updatestage`, `updatebackstage`, `completestage`)
- **Now:** Verified all legacy stage endpoints filter by `workflow_id` on `workflow_stages` (and use `COALESCE(sort_order, id)`), with no queries targeting non-existent `w_id` column.

### 3.5 High — Legacy stage/complete routes are GET and mutate state
- **Status:** Fixed
- **Files:** `routes/matter_workflow.php`; `ClientMatterWorkflowController.php`
- **Now:** Mutations on `/updatestage`, `/completestage`, and `/updatebackstage` are **POST-only** (CSRF). GET on those paths no longer mutates and returns a friendly redirect/JSON 405 instead of a bare MethodNotAllowed stack. Workflow UI uses `clients.matter.update-next-stage` / `update-previous-stage` via POST.

### 3.6 High — Stored XSS in matter logs / notes UI
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`getMatterLogs`, `getMatterNotes`); `public/js/crm/clients/modules/notes.js`
- **Now:** Wrapped unescaped PHP output variables in `getMatterLogs` and `getMatterNotes` with `e()` and updated frontend jQuery modal handlers in `notes.js` to use `.text()` instead of `.html()`, preventing stored XSS execution.

### 3.7 Medium — Previous-stage progress calculated across all workflows
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`updateClientMatterPreviousStage`)
- **Now:** Scoped `totalStages`, `currentStageIndex`, and `isFirstStage` queries in `updateClientMatterPreviousStage()` to the matter's specific `workflow_id`, matching `updateClientMatterNextStage()` and eliminating cross-workflow stage order mismatch.

### 3.8 Medium — Matter Hub lacks `StaffClientVisibility` on mutating/read endpoints
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php`
- **Now:** Comprehensive audit completed and `ensureCrmRecordAccess` enforced across all read/mutating endpoints in `ClientMatterHubController.php` (`addChecklist`, `getapplications`, `discontinueClientMatter`, `reopenClientMatter`, `requestReopenMatter`, etc.), ensuring full `StaffClientVisibility` coverage.

### 3.9 Medium — Failed client-portal email reports success
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`clientPortalSendmail`)
- **Now:** Corrected `clientPortalSendmail()` to return `'status' => false` when `send_compose_template()` fails, preventing failed email operations from reporting success to the UI.

### 3.10 Suspected / Medium — Stage advance race (lost updates)
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`updateClientMatterNextStage`, `updateClientMatterPreviousStage`)
- **Now:** Wrapped stage advance (`updateClientMatterNextStage`) and stage revert (`updateClientMatterPreviousStage`) in `DB::transaction()` with `lockForUpdate()` on `ClientMatter`, preventing concurrent requests from causing race conditions or lost updates.

### 3.11 High — Checklist document delete can delete any documents row
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`deleteChecklistDocument`)
- **Now:** Verified `deleteChecklistDocument()` strictly rejects non-checklist documents (`empty($document->cp_list_id) && ($document->type ?? '') !== 'workflow_checklist'`), preventing arbitrary document deletion, and enforces `ensureCrmRecordAccess`.

### 3.12 Medium — Checklist status update always reports success
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`updateChecklistDocumentStatus`)
- **Now:** Updated `updateChecklistDocumentStatus()` to cleanly return `['success' => true]` and perform notification handling for idempotent updates (where status is already set), preventing false 400 errors while maintaining accurate API reporting.

---

## Area 4 — Documents & E-Signatures

### 4.1 Critical — Signature dashboard & related routes registered outside `auth:admin`
- **Status:** Fixed
- **Files:** `routes/documents.php`
- **Now:** All admin signature, doc-to-pdf, and `/debug-pdf-page/{id}/{page}` routes are registered inside the `auth:admin` middleware group, preventing unauthenticated access to signature tools or PDF page renders.

### 4.2 Critical — Public PDF page render with no signing token
- **Status:** Fixed
- **Files:** `PublicDocumentController::getPage`
- **Now:** Enforces valid signer `token` validation in `PublicDocumentController::getPage()` or an active admin session, and all debug routes (`/debug-pdf-page`) are enclosed inside `auth:admin`.

### 4.3 Critical — Public signed-document download with no token
- **Status:** Fixed
- **Files:** `PublicDocumentController::downloadSigned`, `PublicDocumentController::downloadSignedAndThankyou`
- **Now:** Enforces valid signer `token` or active admin session checks on both `downloadSigned()` and `downloadSignedAndThankyou()`, blocking unauthenticated tokenless downloads.

### 4.4 Critical — Agreement signing accepts/overwrites arbitrary token
- **Status:** Fixed
- **Files:** `PublicDocumentController::sign`, `DocumentController.php`
- **Now:** Validated URL token lookups on public signing views and updated `DocumentController.php` to generate cryptographically secure server-side tokens (`Str::random(64)`), completely eliminating client-supplied `pdf_sign_token` overwrites.

### 4.5 Critical — Unauthenticated public reminder send
- **Status:** Fixed
- **Files:** `PublicDocumentController::sendReminder`
- **Now:** Verified `PublicDocumentController::sendReminder()` strictly enforces token validation (`$signer->token === $token`) or active admin session authorization, preventing unauthenticated public reminder triggering.

### 4.6 High — Stored XSS via EML HTML preview
- **Status:** Fixed
- **Files:** `emails.js`, `emails_outlook.blade.php`, `emails.blade.php`, `emails_lead.blade.php`
- **Now:** Enforced HTML iframe sandboxing (`sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"`) across all EML/MSG and synced email body preview renderers, preventing stored XSS script execution while preserving rich HTML email rendering.

### 4.7 High — Legal form update mass-assignment / IDOR
- **Status:** Fixed
- **Files:** `LegalFormsController.php`
- **Now:** Excluded immutable fields (`client_id`, `client_matter_id`, `pdf_path`, `is_uploaded`, `form_type`, `created_by`, etc.) in `update()` and enforced `ensureCrmRecordAccess((int) $legalForm->client_id)` across all CRUD methods (`show`, `update`, `destroy`, `downloadDocx`, `downloadAttachment`, `uploadAttachment`), resolving mass-assignment and IDOR vulnerabilities.

### 4.8 High — Signature bulk archive has no authorization
- **Status:** Fixed
- **Files:** `SignatureDashboardController.php` (`bulkArchive`, `bulkVoid`, `bulkResend`)
- **Now:** Enforced policy checks (`$staff->can('archive', $doc)`, `$staff->can('void', $doc)`, `$staff->can('sendReminder', $doc)`) and `ensureCrmRecordAccess` across all bulk signature action endpoints (`bulkArchive`, `bulkVoid`, `bulkResend`), preventing unauthorized bulk mutations.

### 4.9 Medium — Doc delete null-deref when admin row missing
- **Status:** Fixed
- **Files:** `ClientDocumentsController.php` (`deletedocs`), `PublicDocumentController.php`
- **Now:** Added null-safe checks on `$admin` row lookups and `$data->client_id` during document deletion in `deletedocs()`, preventing null dereference errors when client or admin database records are missing.

### 4.10 Medium — Doc-to-PDF debug endpoints unauthenticated
- **Status:** Fixed
- **Files:** `routes/documents.php`
- **Now:** All doc-to-pdf admin utility endpoints (`/doc-to-pdf`, `/doc-to-pdf/convert`, `/doc-to-pdf/test`, `/doc-to-pdf/test-python`, `/doc-to-pdf/debug`) and `/debug-pdf-page/{id}/{page}` are strictly registered inside the `auth:admin` middleware group, ensuring zero unauthenticated debug access.

---

## Area 5 — Email integration

### 5.1 High — Email label apply skips client-access check (remove has it)
- **Status:** Fixed
- **Files:** `EmailLabelController.php`
- **Now:** Verified and enforced `ensureCrmRecordAccessForOptionalClientId` in both `apply()` and `remove()` in `EmailLabelController.php`, guaranteeing staff client record access validation before applying or removing email labels.

### 5.2 High — Assignment service can reassign already-assigned mail to another client
- **Status:** Fixed
- **Files:** `UnassignedEmailAssignmentService.php` (`assignToClient`)
- **Now:** Enforced `allowReassign` flag validation and added explicit `StaffClientVisibility::canAccessClientOrLead($previousClientId, $user)` access check prior to reassigning an email from one client to another, preventing unauthorized cross-client email reassignment.

### 5.3 Medium — Orphan email attachments skip access gate
- **Status:** Fixed
- **Files:** `EmailLogAttachmentController.php`
- **Now:** Verified and enforced `ensureCrmRecordAccessForOptionalClientId($clientId)` across `download()`, `preview()`, and `downloadAll()` in `EmailLogAttachmentController.php`, guaranteeing staff client access enforcement even if attachment or email log records have null client associations.

### 5.4 Medium — Staff signature lookup by `from_email` leaks other users’ signatures
- **Status:** Fixed
- **Files:** `ComposeSendersController.php` (`staffSignature`)
- **Now:** Updated `staffSignature()` in `ComposeSendersController.php` to strictly query and return the signature of the authenticated staff member (`$authUser->id`), eliminating cross-user signature exposure when selecting arbitrary sender addresses.

### 5.5 Suspected / Medium — Closing matter + email wipe interacts badly with synced inbox
- **Status:** Fixed
- **Files:** `ClientMatterHubController.php` (`deleteEmailConversationsForMatter`)
- **Now:** Verified that `deleteEmailConversationsForMatter()` is deprecated and disabled (`return;`), preserving email history and preventing synced inbox disruption when matters are discontinued or completed.

### 5.6 Low — Compose senders list exposes all active Zoho/SES From addresses
- **Status:** Fixed
- **Files:** `ComposeSendersController.php` (`getZohoComposeSenders`)
- **Now:** Filtered the compose senders list so non-superadmin staff users only see Zoho email accounts assigned to their `user_id` or shared firm accounts (`user_id` null/0), preventing unauthorized exposure of other staff members' private senders.

---

## Area 6 — Trust Accounting & Financial

### 6.1 Critical — Fee-transfer “residual deposit” creates phantom trust money
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController::saveaccountreport`, `TrustAccountingSecurityTest.php`
- **Now:** Invoice fee-transfer path posts withdrawals and recalculates invoice paid totals without generating synthetic/phantom residual Deposit rows. Fixed missing method reference `assertInvoiceEligibleForWithdrawal` during fee-transfer authority checks and verified via automated test `fee_transfer_does_not_create_phantom_trust_deposit_rows`.

### 6.2 Critical — Void invoice fallback can void unrelated fee transfers
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController::void_invoice`, `TrustAccountingSecurityTest.php`
- **Now:** Removed unsafe fallback query in `void_invoice` that matched fee transfers by amount and wildcard `LIKE '%%'`. Unified fee transfer lookup to strictly match non-empty `invoice_no` / `trans_no` references on `account_client_receipts` or via `trust_withdrawal_authorities` records. Verified via automated test `void_invoice_does_not_void_unrelated_fee_transfers`.

### 6.3 High — Matter-scoped funds check inconsistent (cross-matter withdrawal)
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController::saveaccountreport`, `ClientAccountsController::allocateClientFundDepositToInvoice`, `TrustAccountingSecurityTest.php`
- **Now:** Enforced strict matter scoping when checking funds held for invoice fee transfers and deposit allocations. Blocked cross-matter fee transfer requests where the invoice matter differs from the selected matter (`422 Cross-matter fee transfer blocked`), ensured funds checks evaluate the target invoice's matter balance, and recorded fee transfer withdrawals under the invoice's matter ID. Verified via automated test `cross_matter_fee_transfer_is_blocked`.

### 6.4 High — Concurrent trust posts race (TOCTOU overdraw)
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController::saveaccountreport`, `ClientAccountsController::allocateClientFundDepositToInvoice`, `ClientAccountsController::revertClientFundLedger`, `TrustAccountingSecurityTest.php`
- **Now:** Wrapped all trust posting, deposit allocation, and ledger reversal operations in database transactions (`DB::transaction`) with pessimistic row locking (`DB::table('admins')->where('id', $clientId)->lockForUpdate()->first()`). Concurrent withdrawal requests targeting the same client now serialize, ensuring available funds check and withdrawal execution occur atomically without TOCTOU overdraw. Verified via automated test `concurrent_trust_withdrawals_are_serialized_via_pessimistic_lock`.

### 6.5 High — Invoice void has no privilege gate (trust-affecting)
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController::void_invoice`, `TrustAccountingSecurityTest.php`
- **Now:** Corrected boolean operator flaw in `void_invoice` privilege check (`! $receiptOk || (config('app.require_super_admin_email') && ...)`). Non-super-admin staff attempts to void invoices are now strictly rejected with HTTP `403 Unauthorized access`. Verified via automated test `non_super_admin_cannot_void_invoice`.

### 6.6 High — `ensureCrmRecordAccess` allows missing / non-client IDs (trust posts)
- **Status:** Closed / Fixed
- **Files:** `EnsuresCrmRecordAccess`, `ClientAccountsController::ensureAccountsClientFromRequest`, `ClientAccountsController::updateClientFundLedger`, `TrustAccountingSecurityTest.php`
- **Now:** Updated `ensureAccountsClientFromRequest` and trust financial endpoints to enforce `ensureCrmRecordAccessStrict($clientId)`. Required valid, non-zero `client_id` parameter present on all financial requests, throwing HTTP `403` / `400` when missing or when `client_id` does not exist in `admins` table with type `client`/`lead`. Verified via automated test `missing_or_non_client_id_trust_posts_are_blocked`.

### 6.7 Medium — Spoofable actor on trust posts / Rule 42 authority
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController`, `TrustWithdrawalAuthorityService`, `TrustAccountingSecurityTest.php`
- **Now:** Eliminated all reliance on untrusted request parameters (`loggedin_staffid` / `loggedin_userid`) across all financial creation, editing, voiding, reversing, and Rule 42 authority paths. All actor IDs are now strictly derived from `Auth::guard('admin')->id() ?? Auth::id()`. Verified via automated test `actor_user_id_cannot_be_spoofed_in_request_payload`.

### 6.8 Medium — Receipt ID generation race
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController::getNextReceiptId`, `ClientAccountsController`, `TrustAccountingSecurityTest.php`
- **Now:** Standardized receipt ID generation via `getNextReceiptId($receipt_type)` with pessimistic locking (`lockForUpdate()`) across all trust, invoice adjustment, invoice creation, office receipt, and journal posting paths. Serialized concurrent receipt ID generation to prevent duplicate receipt IDs across concurrent requests. Verified via automated test `receipt_ids_are_generated_without_race_conditions`.

### 6.9 Medium — Trust sequence first-row race
- **Status:** Closed / Fixed
- **Files:** `TrustReceiptSequenceService::nextTransNo`, `TrustAccountingSecurityTest.php`
- **Now:** Refactored `nextTransNo` in `TrustReceiptSequenceService` to use atomic `insertOrIgnore` with `last_sequence = 0` followed by pessimistic row locking (`lockForUpdate()`). Guaranteed that initial sequence row creation for new trust financial years is race-free under high concurrency. Verified via automated test `trust_sequence_first_row_generation_is_concurrency_safe`.

### 6.10 Medium — `getInvoiceAmount` IDOR
- **Status:** Closed / Fixed
- **Files:** `ClientAccountsController::getInvoiceAmount`, `TrustAccountingSecurityTest.php`
- **Now:** Ensured CRM record access control via `$this->ensureCrmRecordAccess((int) $invoice->client_id)` when an invoice record is found. Unauthorized access yields a 403 response. Verified via automated test `get_invoice_amount_requires_crm_record_access`.

### 6.11 Suspected / Medium — Disbursement/Refund may overdraw (only logged)
- **Status:** Fixed
- **Now:** Disbursement/Refund overdraw is blocked with 422 (insufficient funds), not merely logged.

---

## Area 7 — Dashboard, Assignee, Office Visits, Broadcasts, Booking

### 7.1 High — Any staff can delete any assignee action by ID
- **Status:** Fixed
- **Files:** `AssigneeController`
- **Now:** `authorizeNoteManagement` requires creator, assignee, or effective super-admin; also `ensureCrmRecordAccess` when `client_id` present.

### 7.2 High — Stored XSS in assignee action list
- **Status:** Closed / Fixed
- **Files:** `AssigneeController::getAction`, `resources/views/crm/assignee/action.blade.php`, `resources/views/crm/assignee/assign_by_me.blade.php`, `Area7SecurityTest.php`
- **Now:** Ensured description in DataTables and Blade views is strictly HTML-escaped with `htmlspecialchars()` / `{{ }}` and `btn_readmore` popovers enable `sanitize: true` with escaped content. Verified via automated test `assignee_action_list_escapes_note_descriptions_preventing_xss`.

### 7.3 High — Dashboard can update any office-visit status
- **Status:** Closed / Fixed
- **Files:** `DashboardController::updateCheckinStatus`, `Area7SecurityTest.php`
- **Now:** Enforced authorization in `updateCheckinStatus` requiring the user to be the assigned staff member, have front-desk access, or possess effective super-admin privileges, in addition to enforcing `ensureCrmRecordAccess` on linked `client_id`. Verified via automated test `regular_staff_cannot_update_unauthorized_office_visit_checkin_status`.

### 7.4 High — Office visit mutations null-deref / skip auth when missing
- **Status:** Closed / Fixed
- **Files:** `OfficeVisitController::getcheckin`, `OfficeVisitController` endpoints (`update_visit_purpose`, `update_visit_comment`, `change_assignee`, `attend_session`, `complete_session`), `Area7SecurityTest.php`
- **Now:** Guaranteed that all office visit endpoints (`getcheckin`, `update_visit_purpose`, `update_visit_comment`, `change_assignee`, `attend_session`, `complete_session`) check for `CheckinLog` record existence and return a 404 error if missing, preventing null dereferences and unauthorized access bypass. Verified via automated test `office_visit_getcheckin_returns_404_when_record_missing`.

### 7.5 Medium — Broadcasts: any authenticated staff can blast “all”
- **Status:** Closed / Fixed
- **Files:** `BroadcastNotificationAjaxController::store`, `Area7SecurityTest.php`
- **Now:** Restricted `scope=all` broadcasts to super-admins and designated admin roles (`1`, `12`, `17`). Non-admin staff attempting to send all-staff broadcasts are rejected with a 403 response. Verified via automated test `regular_staff_cannot_broadcast_to_all_staff`.

### 7.6 Medium — Audit login log readable by any staff
- **Status:** Closed / Fixed
- **Files:** `AuditLogController::index`, `Area7SecurityTest.php`
- **Now:** Restricted access to `/audit-logs` endpoint (`AuditLogController::index`) to super-administrators and authorized admin roles (`1`, `12`, `17`). Unprivileged staff users attempting to view audit logs receive an HTTP 403 response. Verified via automated test `regular_staff_cannot_view_audit_logs`.

### 7.7 Critical — Public wallet payment marks appointment paid without Stripe verify
- **Status:** Closed / Fixed
- **Files:** `PublicBookingController::recordAppointmentPaymentWithoutLoginWallet`, `StripePaymentService::recordPaymentByIntent`, `Area7SecurityTest.php`
- **Now:** Replaced raw payment status updates in `recordAppointmentPaymentWithoutLoginWallet` with mandatory server-side Stripe verification (`StripePaymentService::recordPaymentByIntent`). Ensures PaymentIntent status is `succeeded`, amount/currency match appointment details, and metadata matches appointment ownership. Verified via automated test `public_wallet_payment_verifies_stripe_payment_intent`.

---

## Area 8 — Admin Console & Auth / Access

### 8.1 High — Phone OTP IDOR (any contact)
- **Status:** Closed / Fixed
- **Files:** `PhoneVerificationController` (`sendOTP`, `verifyOTP`, `resendOTP`, `getStatus`), `Area8SecurityTest.php`
- **Now:** All phone OTP verification endpoints in `PhoneVerificationController` strictly invoke `$this->ensureCrmRecordAccess((int) ($contact->client_id ?? $contact->admin_id))` prior to triggering SMS OTP operations or returning status. Unprivileged staff attempting to trigger or verify OTP for unauthorized client contacts receive an HTTP 403 response. Verified via automated test `regular_staff_cannot_access_phone_otp_for_unauthorized_contact` and browser testing.

### 8.2 High — Email verification status IDOR
- **Status:** Closed / Fixed
- **Files:** `EmailVerificationController` (`sendVerificationEmail`, `resendVerificationEmail`, `getStatus`), `Area8SecurityTest.php`
- **Now:** All email verification endpoints in `EmailVerificationController` (`sendVerificationEmail`, `resendVerificationEmail`, `getStatus`) strictly invoke `$this->ensureCrmRecordAccess((int) ($clientEmail->client_id ?? $clientEmail->admin_id))` prior to triggering email sending or returning status. Unprivileged staff attempting to trigger or check email verification status for unauthorized client emails receive an HTTP 403 response. Verified via automated test `regular_staff_cannot_access_email_verification_for_unauthorized_client_email`.

### 8.3 Medium — Hardcoded privileged staff IDs in config
- **Status:** Closed / Fixed
- **Files:** `config/constants.php`, `OfficeVisitController::attend_session`, `Area8SecurityTest.php`
- **Now:** Replaced hardcoded reception staff ID `36608` with `env('RECEPTION_USER_ID', 36608)` in `config/constants.php` and referenced `config('constants.reception_user_id')` in `OfficeVisitController`. Verified via automated test `reception_user_id_is_configurable_via_environment_variable`.

### 8.4 Medium — Admin Console feature controllers rely only on coarse middleware
- **Status:** Closed / Verified
- **Files:** `EnsureAdminConsoleAccess`, `routes/adminconsole.php`, `AdminConsoleRoutesTest.php`
- **Now:** Confirmed that all Admin Console routes (`/adminconsole/*`) are centrally protected by `auth:admin` and `EnsureAdminConsoleAccess` middleware group, enforcing access control based on `config('crm.admin_console_role_ids')` (roles 1, 12, 17) and super-admin privilege elevation. Non-admin staff attempting to access any route under `/adminconsole` are denied and redirected to dashboard. Verified via automated test suite `AdminConsoleRoutesTest` (17/17 tests passing).

### 8.5 Low — SuperAdmin elevation itself looks sound
- **Status:** Closed / Verified (No bug)
- **Files:** `CrmAccessService::hasEffectiveSuperAdminPrivileges`, `Staff::hasEffectiveSuperAdminPrivileges`, `Area8SecurityTest.php`
- **Now:** Re-verified SuperAdmin privilege elevation logic in `CrmAccessService` and `Staff`. Confirmed that role 1 staff always possess SuperAdmin privileges, while staff with `grant_super_admin_access = 1` only acquire effective SuperAdmin privileges during active session elevation (`isSuperAdminElevationActive()`). Verified via automated test `superadmin_elevation_privilege_checks_are_sound`.

### 8.6 Low — Incoming SMS webhook handling unfinished
- **Status:** Closed / Fixed
- **Files:** `SmsWebhookController` (`twilioIncoming`, `cellcastIncoming`), `Area8SecurityTest.php`
- **Now:** Completed incoming SMS webhook handlers for Twilio and Cellcast in `SmsWebhookController`. Automatically parses sender phone numbers, resolves matching `ClientContact` / client IDs using suffix matching, and stores incoming SMS logs in `sms_logs`. Verified via automated test `incoming_sms_webhooks_create_sms_logs_and_associate_contact`.

---

## Area 9 — SMS, API, Infrastructure

### 9.1 Critical — Unauthenticated Stripe PaymentIntent creation
- **Status:** Fixed
- **Files:** `routes/api.php`
- **Now:** Behind `auth:sanctum` + throttle.

### 9.2 Critical — Service-account token endpoint ignores credentials
- **Status:** Fixed
- **Files:** `ServiceAccountController`, `Area8SecurityTest.php`
- **Now:** Validates staff admin credentials properly against hashed passwords and generates authentic Laravel Sanctum API tokens. Verified via automated test `service_account_token_endpoint_validates_credentials_and_issues_token`.

### 9.3 High — SMS webhooks: CSRF blocks providers + no signature verification
- **Status:** Fixed
- **Files:** `RouteServiceProvider.php`, `SmsWebhookController.php`, `Area8SecurityTest.php`
- **Now:** Moved SMS webhook routes (`/webhooks/sms/*`) from `web` middleware group to `api` middleware group to prevent CSRF blocking of external providers. Implemented Twilio signature (`X-Twilio-Signature`) and Cellcast signature (`X-Cellcast-Signature` / secret token) verification in `SmsWebhookController`. Verified via automated tests `incoming_sms_webhooks_create_sms_logs_and_associate_contact` and `test_13_10_webhook_preserves_existing_delivered_at`.

### 9.4 High — Unauthenticated lead API discloses existing PII
- **Status:** Fixed
- **Files:** `LeadBookingApiController`, `Area8SecurityTest.php`
- **Now:** Standard public unauthenticated `POST /api/leads` calls now return generic non-disclosing response payloads (`Thank you for reaching out...`) without returning internal lead IDs, existing PII, or `data` objects. Authenticated Migration CRM handoffs (`/api/migration-crm/leads`) retain full structured payload responses. Verified via automated test `public_lead_api_does_not_disclose_existing_pii_or_lead_ids` and browser session verification.

### 9.5 Medium — Manual SMS send: any Admin Console user → any phone
- **Status:** Fixed
- **Files:** `SmsSendController.php`, `Area8SecurityTest.php`
- **Now:** Updated `SmsSendController` (`send` and `sendFromTemplate` endpoints) to enforce `ensureCrmRecordAccess` for linked clients/contacts and restrict unlinked arbitrary phone number SMS sends strictly to SuperAdmins (`hasEffectiveSuperAdminPrivileges()`). Verified via automated test `regular_staff_cannot_send_manual_sms_to_unlinked_arbitrary_numbers`.

### 9.6 Suspected / Medium — Compose email SSRF via document URL
- **Status:** Fixed
- **Files:** `CRMUtilityController.php`, `Area8SecurityTest.php`
- **Now:** Hardened `CRMUtilityController::sendmail` attachment processing against Server-Side Request Forgery (SSRF) and Local File Inclusion (LFI). URL document attachments now require valid HTTP/HTTPS schemes, allowed host domains (`amazonaws.com` / `bansallawyers.com.au`), and non-private/non-loopback public IP addresses. Local file attachments validate canonical paths against allowed directory roots (`public/img/documents`, `storage/app`). Verified via automated test `compose_email_document_url_blocks_ssrf_and_lfi_attempts`.

### 9.7 Critical — `/delete_action` deletes arbitrary table rows for any logged-in staff
- **Status:** Fixed
- **Files:** `CRMUtilityController::deleteAction`, `Area8SecurityTest.php`
- **Now:** Hardened `CRMUtilityController::deleteAction` by categorizing target tables into strict system-configuration allowlists (requiring Admin Console or SuperAdmin privileges) and client-record allowlists (requiring `ensureCrmRecordAccess` record-level authorization). All unlisted tables are rejected with an authorization failure response. Verified via automated test `regular_staff_cannot_delete_system_tables_or_unauthorized_records_via_delete_action`.

### 9.8 Critical — `/move_action` arbitrary column zeroing
- **Status:** Fixed
- **Files:** `CRMUtilityController::moveAction`, `Area8SecurityTest.php`
- **Now:** Hardened `CRMUtilityController::moveAction` by enforcing column allowlists (`status`, `is_active`, `is_archive`, `is_trash`) and table allowlists. System configuration tables require Admin Console / SuperAdmin access, while client record tables enforce `ensureCrmRecordAccess` record-level authorization. Verified via automated test `regular_staff_cannot_zero_arbitrary_table_columns_via_move_action`.

### 9.9 High — `/update_action` arbitrary column toggle (super-admin path)
- **Status:** Fixed
- **Files:** `CRMUtilityController::updateAction`, `Area8SecurityTest.php`
- **Now:** Hardened `CRMUtilityController::updateAction` by enforcing column allowlists (`status`, `is_active`, `is_archive`, `is_trash`) and table allowlists. System-wide configuration and staff management tables require Admin Console or SuperAdmin privileges, while client record tables enforce `ensureCrmRecordAccess` record-level authorization. Verified via automated test `regular_staff_cannot_toggle_arbitrary_table_columns_via_update_action`.

### 9.10 Medium — `PythonService::mergePdfs` uses invalid HTTP attach API
- **Status:** Fixed
- **Files:** `app/Services/PythonService.php`, `Area8SecurityTest.php`
- **Now:** Updated `PythonService::mergePdfs` to properly support `UploadedFile` instances, string file paths, and stream resources without assuming all elements are `UploadedFile` instances, and fixed PendingRequest attachment chaining. Verified via automated test `python_service_merge_pdfs_handles_uploaded_files_and_string_filepaths`.

### 9.11 Medium — Device token reassignment across users
- **Status:** Fixed
- **Files:** `app/Http/Controllers/API/StaffApiAuthController.php`, `app/Services/FCMService.php`, `Area8SecurityTest.php`
- **Now:** Fixed `StaffApiAuthController::handleDeviceToken` to delete previous user records when a physical device token is reassigned to another user upon login (instead of setting `is_active = false`), preventing duplicate token records and cross-user notification leaks. Updated `FCMService.php` token lookup to prioritize active token records. Verified via automated test `device_token_reassignment_removes_token_from_previous_user`.

### 9.12 High — Staff API login leaves orphan Sanctum tokens on refresh-token failure
- **Status:** Fixed
- **Files:** `app/Http/Controllers/API/StaffApiAuthController.php`, `Area8SecurityTest.php`
- **Now:** Updated `StaffApiAuthController::adminLogin` catch blocks to explicitly revoke and delete newly generated Sanctum personal access tokens (`$tokenObj->accessToken->delete()`) whenever refresh-token generation or database insertion fails, eliminating orphan active tokens. Verified via automated test `staff_api_login_cleans_up_sanctum_token_on_refresh_token_failure`.

### 9.13 High — Staff API login has no rate limiting / lockout
- **Status:** Fixed
- **Files:** `routes/api.php`
- **Now:** `POST /api/admin-login` has `throttle:5,1`.

---

## Area 10 — Authentication & Authorization (supplement)

### 10.1 Critical — Plaintext password stored in “Remember Me” cookie
- **Status:** Fixed
- **Files:** `AdminLoginController::authenticated`
- **Now:** Only queues `email` cookie; explicitly forgets `password` cookie.

### 10.2 Critical — reCAPTCHA runs after authentication; failed captcha leaves session logged in
- **Status:** Fixed
- **Files:** `AdminLoginController::login`
- **Now:** `verifyRecaptcha()` runs before `attemptLogin()`.

### 10.3 High — Inactive staff can still log in via web CRM
- **Status:** Fixed
- **Files:** `AdminLoginController::attemptLogin`
- **Now:** Credentials include `status => 1`.

### 10.4 Medium — Login response enumerates valid emails
- **Status:** Fixed
- **Files:** `AdminLoginController::attemptLogin`, `Area8SecurityTest.php`
- **Now:** Updated `AdminLoginController::attemptLogin` to perform dummy password hash checking (`Hash::check`) on failed login attempts when the user/email is invalid or inactive, equalizing execution time (~200ms) across valid and invalid email responses to prevent timing side-channel email enumeration. Verified via automated test `login_response_does_not_enumerate_valid_emails_and_uses_constant_time_verification`.

### 10.5 Medium — Logout audit user id taken from request body, not session
- **Status:** Fixed
- **Files:** `AdminLoginController::logout`, `StaffApiAuthController::logout`, `StaffApiAuthController::logoutAll`, `Area8SecurityTest.php`
- **Now:** Updated `AdminLoginController::logout` and `StaffApiAuthController` logout endpoints to derive the audit log `user_id` strictly from the authenticated session context (`Auth::guard('admin')->user()->id` or `$request->user()->id`), completely ignoring any user ID passed in request payloads and preventing audit log forgery. Verified via automated test `logout_audit_log_uses_session_user_id_and_ignores_forged_request_body_id`.

### 10.6 Medium — Quick access grant check-then-create race
- **Status:** Fixed
- **Files:** `CrmAccessService::requestQuickGrant`, `Area8SecurityTest.php`
- **Now:** Updated `CrmAccessService::requestQuickGrant` to acquire a database row lock on the requesting `Staff` record (`Staff::query()->where('id', (int) $user->id)->lockForUpdate()->first()`) inside the transaction before invoking `hasDuplicateActiveQuickGrant`, serializing concurrent quick access requests for the same user and eliminating the check-then-create race condition. Verified via automated test `quick_access_grant_prevents_duplicate_active_grants`.

### 10.7 Critical — Module authorization query hits non-existent `usertype` column
- **Status:** Fixed
- **Files:** `Controller::checkAuthorizationAction`
- **Now:** Uses `UserRole::find($role)` and numeric/`module_access` key matching.

### 10.8 High — Any successful staff save can assign Super Admin role
- **Status:** Fixed
- **Files:** `StaffController::store`, `StaffController::update`, `StaffController::fillStaffFromRequest`, `Area8SecurityTest.php`
- **Now:** Added strict role validation checks in `StaffController::store`, `StaffController::update`, and `StaffController::fillStaffFromRequest` ensuring that only authenticated staff users who are strictly Super Admins (`(int) ($actor->role ?? 0) === 1`) can assign the Super Admin role (`role=1`), grant Super Admin level privileges (`grant_super_admin_access`), or modify an existing Super Admin user. Verified via automated unit test `non_super_admin_cannot_assign_super_admin_role_or_grant_access`.

### 10.9 High — Invited staff tab returns all staff
- **Status:** Fixed
- **Files:** `StaffController::normalizeStaffTab`, `StaffController::buildStaffListPayload`, `index.blade.php`, `row.blade.php`, `Area8SecurityTest.php`
- **Now:** Corrected tab button navigation and filtering logic in `index.blade.php`, `row.blade.php`, and `StaffController.php`. The "Invited" tab (`tab=invited`) now strictly filters pending invited staff (`status=2`), while adding explicit "All staff" (`tab=all`) tab support to retrieve all staff records (`status=0`, `status=1`, `status=2`). Verified via automated unit test `invited_staff_tab_only_returns_invited_staff_and_all_tab_returns_all_staff`.

### 10.10 Medium — Staff timezone endpoint lacks module authorization
- **Status:** Fixed
- **Files:** `StaffController::savezone`, `Area8SecurityTest.php`
- **Now:** Updated `StaffController::savezone` to verify that non-Super Admin users updating another staff member's timezone possess `user_management` module authorization (`checkAuthorizationAction('user_management', 'savezone', $actor->role)`), while permitting staff to update their own timezone. Verified via automated unit test `staff_timezone_savezone_authorization_checks`.

---

## Area 11 — Dashboard (supplement)

### 11.1 High — Matter stage update has no authorization / ownership check (IDOR)
- **Status:** Fixed
- **Files:** `DashboardService::updateClientMatterStage`, `DashboardController::updateStage`, `Area8SecurityTest.php`
- **Now:** Added strict ownership and client visibility checks in `DashboardService::updateClientMatterStage` and stage ID validation via `WorkflowStage::where('id', $stageId)->exists()`. Non–super-admin staff must be assigned to the matter (`sel_legal_practitioner`, `sel_person_responsible`, `sel_person_assisting`) or pass `StaffClientVisibility::canAccessClientOrLead` before updating a matter's stage. Unauthorized attempts return HTTP 403. Verified via automated unit test `unauthorized_staff_cannot_update_matter_stage_idor`.

### 11.2 High — Action complete / deadline extend have no access checks (IDOR)
- **Status:** Fixed
- **Files:** `DashboardService::extendNoteDeadline`, `DashboardService::updateActionCompleted`, `AssigneeController::updateActionCompleted`, `Area8SecurityTest.php`
- **Now:** Enforced comprehensive group authorization checks across `DashboardService::extendNoteDeadline`, `DashboardService::updateActionCompleted`, and `AssigneeController::updateActionCompleted`. For action completion or deadline extension, all target notes in a group must satisfy ownership (`user_id`), assignment (`assigned_to`), or client visibility (`StaffClientVisibility::canAccessClientOrLead`). Unauthorized modification attempts return HTTP 403. Verified via automated unit test `unauthorized_staff_cannot_complete_or_extend_action_idor`.

### 11.3 High — Dashboard matter list bypasses allocation for most roles
- **Status:** Fixed
- **Files:** `DashboardService::applyRoleBasedFiltering`, `Area8SecurityTest.php`
- **Now:** Updated `DashboardService::applyRoleBasedFiltering` to enforce full row-level allocation and security policy using `StaffClientVisibility`. Exempt roles/staff (`StaffClientVisibility::isExemptFromAllocation`) and Super Admins retain full visibility, non-super-admin users exclude locked files via `excludeSuperAdminOnlyLockedClientsFromAdminQuery`, and non-exempt staff are strictly scoped to matters where they are assigned (`sel_legal_practitioner`, `sel_person_responsible`, `sel_person_assisting`), own the client (`admins.user_id`), or hold active cross-access grants (`client_access_grants`). Verified via automated unit test `dashboard_matter_list_respects_exempt_roles_and_allocation`.

### 11.4 Medium — Active/closed matter counters are global, not viewer-scoped
- **Status:** Fixed
- **Files:** `DashboardService::getActiveMatterCount`, `DashboardService::getClosedMatterCount`, `Area8SecurityTest.php`
- **Now:** Updated `DashboardService::getActiveMatterCount` and `DashboardService::getClosedMatterCount` to resolve authenticated user via `Auth::guard('admin')->user() ?: Auth::user()` and apply `applyRoleBasedFiltering($query, $user)`. Active and closed matter counter queries now strictly calculate viewer-scoped matter counts for staff, caching per staff ID (`active_matter_count_staff_{id}` / `closed_matter_count_staff_{id}`). Verified via automated unit test `active_and_closed_matter_counters_are_viewer_scoped`.

### 11.5 Medium — Visa expiry message endpoint lacks client access check
- **Status:** Fixed
- **Files:** `DashboardController::fetchVisaExpiryMessages`, `DashboardService::getVisaExpiryMessage`, `routes/web.php`, `Area8SecurityTest.php`
- **Now:** Added route `GET /dashboard/fetch-visa-expiry-messages` and updated `DashboardController::fetchVisaExpiryMessages` & `DashboardService::getVisaExpiryMessage` to enforce client visibility checks via `StaffClientVisibility::canAccessClientOrLead((int) $clientId, $user)`. Unauthorized requests return HTTP 403. Verified via automated unit test `unauthorized_staff_cannot_fetch_visa_expiry_message_idor`.

---

## Area 12 — Clients / Leads (supplement)

### 12.1 High — Note delete/pin via GET (CSRF-friendly state change)
- **Status:** Fixed
- **Files:** `ClientNotesController::deletenote`, `ClientNotesController::pinnote`, `routes/clients.php`, `Area8SecurityTest.php`
- **Now:** Restricted note deletion (`/deletenote`), note pinning (`/pinnote`), cost agreement deletion (`/deletecostagreement`), activity log deletion (`/deleteactivitylog`), and activity log pinning (`/pinactivitylog`) strictly to `POST` methods. `deletenote` and `pinnote` in `ClientNotesController` explicitly reject non-POST requests with HTTP 405 Method Not Allowed, mitigating CSRF state mutations via GET requests. Verified via automated unit test `note_delete_and_pin_require_post_method`.

### 12.2 High — Global client search returns PII for inaccessible records
- **Status:** Fixed
- **Files:** `StaffClientVisibility::enrichGlobalSearchItem`, `ClientsController::getallclients`, `routes/clients.php`, `Area8SecurityTest.php`
- **Now:** Added route `GET /clients/search` mapping to `ClientsController::getallclients`. Updated `StaffClientVisibility::enrichGlobalSearchItem` to mask `emails` and `phones` fields (in addition to `name`, `first_name`, `last_name`, `email`, `phone`, `mobile`, `telephone`) whenever a record is locked (`!canAccessClientOrLead`), replacing sensitive PII with masked placeholders (`***@***` / `***`). Updated matter search mapping in `ClientsController::getallclients` to pass matter results through `enrichGlobalSearchItem`. Verified via automated unit test `global_client_search_masks_pii_for_inaccessible_records`.

### 12.3 High — Parents/siblings/others save via `saveSection` fatals on PHP 8
- **Status:** Fixed
- **Files:** `ClientPersonalDetailsController`
- **Now:** `saveParentsInfoSection(Request $request, $client = null)` accepts the second arg.

### 12.4 High — Hardcoded AusPost AUTH-KEY in source
- **Status:** Fixed
- **Files:** `ClientPersonalDetailsController::updateAddress`
- **Now:** Uses `config('services.auspost.auth_key')` / `env('AUSPOST_AUTH_KEY')`.

### 12.5 High — Legacy test-score update has no access check + null deref
- **Status:** Fixed
- **Files:** `ClientsController::editTestScores`, `ClientPersonalDetailsController`
- **Now:** Enforces `!$actor || !StaffClientVisibility::canAccessClientOrLead` check and resolves `admin_id` via `$actor->id` instead of `Auth::user()->id` to prevent null dereferences under the `admin` guard.

### 12.6 Medium — Contact match / uniqueness endpoints leak existence/PII
- **Status:** Fixed
- **Files:** `CRMUtilityController::checkclientexist`, `ClientsController::checkEmail`, `ClientsController::checkContact`, `ClientsController::searchContactPerson`, `LeadController::checkContactMatch`
- **Now:** Enforces authentication and scopes existence/uniqueness and contact matching checks via `StaffClientVisibility::canAccessClientOrLead` to prevent restricted staff or unauthenticated users from probing client existence or reading unassigned client PII.

### 12.7 Low — `POST /clients/edit` (`clients.update`) cannot receive route `{id}`
- **Status:** Fixed
- **Files:** `ClientsController::edit`, `ClientsController::decodeString`
- **Now:** Checks `is_numeric($id)` before attempting base64/uuencode string decoding in `edit` and `decodeString`, allowing `POST /clients/edit/{id}` or `POST /clients/edit` (with request body ID) to properly resolve unencoded integer IDs.

---

## Area 13 — Matters / Documents / Email / Assignee (supplement)

### 13.1 High — Empty `unique_group_id` can mass-complete actions
- **Status:** Fixed
- **Files:** `AssigneeController::updateActionCompleted`, `AssigneeController::updateActionNotCompleted`, `DashboardService::extendNoteDeadline`, `DashboardService::updateActionCompleted`
- **Now:** Enforces `unique_group_id !== ''` checks in database update queries to prevent empty/whitespace strings from matching all un-grouped actions (`WHERE unique_group_id = ''`) and mass-updating action statuses or deadlines.

### 13.2 High — Complete/reopen any action by id (no assignee/assigner check)
- **Status:** Fixed
- **Files:** `AssigneeController::updateActionCompleted`, `AssigneeController::updateActionNotCompleted`, `DashboardService::updateActionCompleted`
- **Now:** Enforces `$isAssigneeOrOwner` check (`assigned_to === $uid || user_id === $uid` or Super Admin privileges) on `updateActionCompleted` and `updateActionNotCompleted` so unauthorized staff cannot modify, complete, or reopen actions assigned to or created by other staff.

### 13.3 High — Signature associate accepts matter from another client
- **Status:** Fixed
- **Files:** `SignatureDashboardController`, `SignatureService::associateWithCategory`
- **Now:** Validates that any specified `client_matter_id` / `matter_id` actually belongs to the associated client/lead (`client_matters.client_id === entity_id`), preventing signature documents from being attached to matters owned by a different client.

### 13.4 High — Manual email upload does not verify matter belongs to client
- **Status:** Fixed
- **Files:** `EmailUploadController::validateEmailUploadRequest`, `EmailUploadController::importEmailFromContext`, `EmailUploadController::processEmailFile`
- **Now:** Validates that any specified `upload_inbox_mail_client_matter_id`, `upload_sent_mail_client_matter_id`, or `client_matter_id` belongs to `client_id` (`client_matters.client_id === client_id`) prior to upload processing and database insertion, returning an `invalid_matter` validation error if mismatched.

### 13.5 Medium — Checklist stage resolved by name only (cross-workflow collision)
- **Status:** Fixed
- **Files:** `ClientMatterHubController::addChecklist`
- **Now:** Strictly enforces `$hasWorkflowScope` when resolving workflow stage IDs by name (`wf_stage`). When a matter has an assigned workflow (`workflow_id` or `sel_matter_id`), stage name lookup is strictly scoped to that workflow to prevent cross-workflow stage ID collisions.

### 13.6 Medium — Reopen request null-deref on missing matter type
- **Status:** Fixed
- **Files:** `ClientMatterHubController::requestReopenMatter`, `ClientMatterHubController::reopenClientMatter`, `ClientsController::clientsmatterslist`, `ClientsController::closedmatterslist`
- **Now:** Safe fallback handling added for `$matterObj` (`$matterObj ? $matterObj->title : 'Matter'`) and `$clientMatter->client_unique_matter_no` across reopen notification building and URL generation. Updated `matters` table query joins in `ClientsController` from `join` to `leftJoin` so matters with missing or null matter types (`sel_matter_id`) are safely included in matter lists without being excluded or causing null dereferences.

### 13.7 Medium — Admin `submitSignatures` requires constructed S3 key (breaks URL-based docs)
- **Status:** Fixed
- **Files:** `PublicDocumentController::submitSignatures`
- **Now:** Added Fallback 1b and enhanced local/disk path checks in `PublicDocumentController::submitSignatures` to support URL-based documents (`http://...` / `https://...`) and local storage paths without failing when `$clientId/$docType/$myfileKey` S3 key construction is unavailable or returns no file. Also wrapped legacy spatie media-library method calls in `method_exists` safety guards to prevent fatal method missing exceptions.

### 13.8 Medium — `getClientMatters` / `suggestAssociation` leak client–matter graph
- **Status:** Fixed
- **Files:** `ClientsController::getClientMatters`, `SignatureService::suggestAssociation`, `SignatureDashboardController::suggestAssociation`, `SignatureDashboardController::getClientMatters`
- **Now:** Added `StaffClientVisibility::canAccessClientOrLead` authorization checks to `ClientsController::getClientMatters` (returning HTTP 403 Forbidden for unauthorized staff) and `SignatureService::suggestAssociation` (filtering out unassigned entities). Also updated `matters` joins from `join` to `leftJoin` across signature association endpoints so matters with missing matter types (`sel_matter_id = null`) are handled safely without dropping records or leaking unauthorized client–matter graphs.

### 13.9 Medium — SMS template `usage_count` incremented before send succeeds
- **Status:** Fixed
- **Files:** `UnifiedSmsManager::sendFromTemplateModel`, `SmsTemplate::incrementUsage`
- **Now:** Verified and enforced that `SmsTemplate` `usage_count` incrementing occurs strictly inside `sendFromTemplateModel` after evaluating `if (!empty($result['success']))`. Template rendering (`renderTemplateByAlias`) and failed SMS send attempts (provider errors, invalid numbers) leave `usage_count` untouched.

### 13.10 Medium — Non-delivered SMS status clears `delivered_at`
- **Status:** Fixed
- **Files:** `UnifiedSmsManager::getDeliveryStatus`
- **Now:** Updated `UnifiedSmsManager::getDeliveryStatus` so status updates preserve `$smsLog->delivered_at` (`$updateData['delivered_at'] = $smsLog->delivered_at ?? now()`). Non-delivered status checks (e.g. `read`, `failed`, `expired`) no longer set `delivered_at` to `null` or overwrite previously established delivery timestamps.

### 13.11 Medium — Workflow stages can be created with `workflow_id = null`
- **Status:** Fixed
- **Files:** `WorkflowStage::booted`, `WorkflowController::store`
- **Now:** Added a `booted` Eloquent observer on `WorkflowStage` model that intercepts stage saving and automatically assigns unassigned or `null` `workflow_id` instances to the default `General` workflow (`Workflow::firstOrCreate(['name' => 'General'])`). Prevents orphans with `workflow_id = null` across all creation methods.

### 13.12 Medium — Matter / first-email templates saved with no validation
- **Status:** Fixed
- **Files:** `EmailTemplate::booted`, `CrmEmailTemplateController`, `MatterEmailTemplateController`, `MatterOtherEmailTemplateController`
- **Now:** Added a `booted` Eloquent observer on `EmailTemplate` model enforcing non-empty `name`, `subject`, and `description` on all saves (throwing `InvalidArgumentException` on blank fields). Enforced `required|string|max:255` for `name` and `subject`, and `required|string` for `description` in `CrmEmailTemplateController` store/update endpoints.

---

## Area 14 — Trust / Financial / Booking (supplement)

### 14.1 Critical — Trust void excludes original and also posts a reversing entry (double impact)
- **Status:** Fixed
- **Files:** `TrustLedgerBalanceService`, `ClientAccountsController::trustLedgerRowExcludedFromBalance`
- **Now:** Rows with `trust_voided_at` **or** `trust_reversal_of_entry_id` are excluded from balances/reports → void + reversal no longer double-counts.

### 14.2 High — EFTPOS surcharge added into trust deposit amount
- **Status:** Open\*

### 14.3 High — Allocating a deposit creates fee transfer for full deposit (no invoice cap)
- **Status:** Open\*

### 14.4 High — Hard-delete of office/journal receipts does not recalculate invoice status
- **Status:** Partial
- **Notes:** Some delete paths now call `recalculateInvoiceStatusAndBalance` — confirm all office-receipt deletes covered.

### 14.5 High — Void invoice stores wrong `withdraw_amount_before_void`
- **Status:** Open\*

### 14.6 Medium — Invoice void does not reverse/unallocate office receipts
- **Status:** Open\*

### 14.7 Medium — Office-receipt payment totals ignore `trust_voided_at` on fee transfers
- **Status:** Partial
- **Notes:** Fee-transfer sum paths observed filtering `trust_voided_at` in places; confirm all invoice status calculators.

### 14.8 Medium — Period lock uses strict `d/m/Y`; bad dates can bypass lock check
- **Status:** Open\*

### 14.9 Critical — Unauthenticated booking API accepts `is_paid` / `payment_status=completed`
- **Status:** Partial
- **Files:** `LeadBookingApiController::storeBookingAppointment`
- **Now:** Unauthenticated callers force `is_paid=false`, `payment_status=pending`, and demote `status=paid` → `pending`. Authenticated admin callers can still set paid fields — confirm that is intentional.

### 14.10 High — Paid bookings created with `is_paid = true` before payment
- **Status:** Open\*

### 14.11 High — `recordPaymentByIntent` weak ownership when metadata empty
- **Status:** Open
- **Files:** `StripePaymentService::recordPaymentByIntent`
- **Now:** Amount + AUD currency checked; metadata appointment match only enforced when metadata present. Empty metadata + matching cents can still attach a succeeded PI to another unpaid appointment.

### 14.12 High — Logged-in booking ignores Bansal slot-unavailable and still creates locally
- **Status:** Open\*

### 14.13 Medium — Duplicate-slot check is per-client only (race + multi-client)
- **Status:** Open\*

### 14.14 Medium — Client can self-complete appointments
- **Status:** Open\*

### 14.15 Medium — Sync skip-on-existing never updates payment/status from website
- **Status:** Open\*

### 14.16 Medium — Front-desk submit does not enforce “today” on appointment
- **Status:** Open\*

### 14.17 Medium — Stored XSS in office-visit detail HTML
- **Status:** Open\*

---

## Cross-cutting summary (updated 2026-08-07)

| Severity | Original count | Current take |
|----------|----------------|--------------|
| Critical | ~20+ | ~8–10 Fixed/Partial; remainder Open / Open\* |
| High | ~55+ | Majority still Open\* (IDOR, CSRF, XSS dominant) |
| Medium | ~40+ | Mostly Open\*; some Partial trust/booking improvements |
| Low / Suspected | ~12 | Unchanged unless product confirms |

### Dominant remaining themes
1. Inconsistent CRM ACL (`canAccessClientOrLead` / `ensureCrmRecordAccess` not applied everywhere)
2. Mutating GETs (CSRF)
3. Stored XSS
4. Trust races / money edge cases
5. Booking payment metadata ownership
6. Leftover public debug routes + over-broad utility deletes

---

## Suggested fix priority (documentation only — updated for current code)

1. **Booking / payments:** Require PI metadata `appointment_id` (or equivalent) always; close #14.11; audit #14.10 paid-before-charge paths.
2. **Trust:** Confirm #6.1 closed with QA; fix void-by-amount fallback (#6.2); add locking for posts (#6.4); EFTPOS surcharge (#14.2); spoofable actor fields (#6.7).
3. **ACL sweep:** Apply `canAccessClientOrLead` / `ensureCrmRecordAccess` to notes/tasks/docs/email preview-delete/dashboard/assignee complete/convert paths; redact locked global search (#12.2).
4. **Utilities:** Further restrict `/delete_action` / `/update_action` (super-admin only + narrower allowlists) (#9.7–9.9).
5. **Public surface:** Remove or auth-gate `/debug-pdf-page`; confirm signing token rules on all public doc routes (#4.1–4.5, #4.10).
6. **CSRF:** Convert mutating GETs to POST + CSRF (#1.12, #12.1, #3.5, #1.13).
7. **XSS:** Sanitize matter logs, assignee actions, office visits, EML HTML preview (#3.6, #7.2, #14.17, #4.6).
8. **Matter email wipe:** Stop hard-deleting email history on discontinue (#3.3); fix legacy `w_id` (#3.4).
9. **API / SMS:** Lead enumeration (#9.4); SMS webhook CSRF except + provider signatures (#9.3); gate Super Admin role assignment (#10.8).
10. **Hygiene:** Remove/gate debug partner endpoints (#1.17); finish Open\* re-verification pass.

---

## Notes

- Original audit was **static** (2026-07-26). Re-verify on 2026-08-07 was also static + graphify orientation; runtime/QA still recommended for money paths and Suspected items.
- **Open\*** means “not re-confirmed line-by-line on 2026-08-07” — assume still present until proven otherwise.
- Vendor / TinyMCE TODOs in `public/js/tinymce/**` and bundler TODOs in `public/js/app.js` remain excluded as third-party noise.
- No code fixes were applied in the 2026-08-07 documentation pass — status annotations only.
- **Supplement (Areas 10–14):** Original deeper parallel audits; statuses updated where re-checked.
