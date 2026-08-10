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
- **Status:** Open\*

### 3.8 Medium — Matter Hub lacks `StaffClientVisibility` on mutating/read endpoints
- **Status:** Open\* (some endpoints now call `ensureCrmRecordAccess`; coverage incomplete)

### 3.9 Medium — Failed client-portal email reports success
- **Status:** Open\*

### 3.10 Suspected / Medium — Stage advance race (lost updates)
- **Status:** Open\*

### 3.11 High — Checklist document delete can delete any documents row
- **Status:** Open\*

### 3.12 Medium — Checklist status update always reports success
- **Status:** Open\*

---

## Area 4 — Documents & E-Signatures

### 4.1 Critical — Signature dashboard & related routes registered outside `auth:admin`
- **Status:** Partial
- **Files:** `routes/documents.php`
- **Now:** Admin signature / doc-to-pdf routes are inside `auth:admin`. Unauthenticated `/debug-pdf-page/{id}/{page}` remains outside.

### 4.2 Critical — Public PDF page render with no signing token
- **Status:** Partial
- **Files:** `PublicDocumentController::getPage`
- **Now:** Requires valid signer `token` **or** admin session. Debug route (#4.1) still bypasses this.

### 4.3 Critical — Public signed-document download with no token
- **Status:** Partial
- **Files:** `PublicDocumentController::downloadSigned`
- **Now:** Requires signer token or admin session.

### 4.4 Critical — Agreement signing accepts/overwrites arbitrary token
- **Status:** Partial
- **Files:** `PublicDocumentController::sign`
- **Now:** Looks up signer by URL token; invalid/expired links rejected. Confirm send path no longer accepts client-supplied `pdf_sign_token` overwrite.

### 4.5 Critical — Unauthenticated public reminder send
- **Status:** Partial
- **Files:** `PublicDocumentController::sendReminder`
- **Now:** Public callers must supply matching signer token; admin session also allowed.

### 4.6 High — Stored XSS via EML HTML preview
- **Status:** Open\*

### 4.7 High — Legal form update mass-assignment / IDOR
- **Status:** Open\*

### 4.8 High — Signature bulk archive has no authorization
- **Status:** Open\* (routes now auth’d; policy checks still needed)

### 4.9 Medium — Doc delete null-deref when admin row missing
- **Status:** Open\*

### 4.10 Medium — Doc-to-PDF debug endpoints unauthenticated
- **Status:** Partial
- **Now:** Doc-to-pdf admin utilities are behind `auth:admin`. `/debug-pdf-page/{id}/{page}` still public.

---

## Area 5 — Email integration

### 5.1 High — Email label apply skips client-access check (remove has it)
- **Status:** Open\*

### 5.2 High — Assignment service can reassign already-assigned mail to another client
- **Status:** Open\*

### 5.3 Medium — Orphan email attachments skip access gate
- **Status:** Open\*

### 5.4 Medium — Staff signature lookup by `from_email` leaks other users’ signatures
- **Status:** Open\*

### 5.5 Suspected / Medium — Closing matter + email wipe interacts badly with synced inbox
- **Status:** Open\* (depends on #3.3)

### 5.6 Low — Compose senders list exposes all active Zoho/SES From addresses
- **Status:** Open\* (may be intentional)

---

## Area 6 — Trust Accounting & Financial

### 6.1 Critical — Fee-transfer “residual deposit” creates phantom trust money
- **Status:** Partial / likely fixed
- **Files:** `ClientAccountsController::saveaccountreport`
- **Now:** Invoice fee-transfer path posts withdrawals and recalculates invoice paid totals; synthetic residual Deposit for unused fee-transfer remainder not observed in current loop. Confirm with finance QA before closing permanently.

### 6.2 Critical — Void invoice fallback can void unrelated fee transfers
- **Status:** Open\*

### 6.3 High — Matter-scoped funds check inconsistent (cross-matter withdrawal)
- **Status:** Open\*

### 6.4 High — Concurrent trust posts race (TOCTOU overdraw)
- **Status:** Open

### 6.5 High — Invoice void has no privilege gate (trust-affecting)
- **Status:** Open\*

### 6.6 High — `ensureCrmRecordAccess` allows missing / non-client IDs (trust posts)
- **Status:** Partial
- **Notes:** Same as #1.20 — wrong type now blocked; missing id still soft-passes.

### 6.7 Medium — Spoofable actor on trust posts / Rule 42 authority
- **Status:** Partial
- **Now:** Some ledger inserts use `Auth::guard('admin')->id()`. Confirm all paths (incl. void / authority) no longer trust request `loggedin_staffid`.

### 6.8 Medium — Receipt ID generation race
- **Status:** Open\*

### 6.9 Medium — Trust sequence first-row race
- **Status:** Open\*

### 6.10 Medium — `getInvoiceAmount` IDOR
- **Status:** Open\*

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
- **Status:** Open\*

### 7.3 High — Dashboard can update any office-visit status
- **Status:** Open\*

### 7.4 High — Office visit mutations null-deref / skip auth when missing
- **Status:** Open\*

### 7.5 Medium — Broadcasts: any authenticated staff can blast “all”
- **Status:** Open\*

### 7.6 Medium — Audit login log readable by any staff
- **Status:** Open\*

### 7.7 Critical — Public wallet payment marks appointment paid without Stripe verify
- **Status:** Partial
- **Files:** `PublicBookingController::recordAppointmentPaymentWithoutLoginWallet`
- **Now:** Uses `StripePaymentService::recordPaymentByIntent`. Residual risk: optional metadata ownership when PI metadata empty (#14.11).

---

## Area 8 — Admin Console & Auth / Access

### 8.1 High — Phone OTP IDOR (any contact)
- **Status:** Open\*

### 8.2 High — Email verification status IDOR
- **Status:** Open\*

### 8.3 Medium — Hardcoded privileged staff IDs in config
- **Status:** Open\*

### 8.4 Medium — Admin Console feature controllers rely only on coarse middleware
- **Status:** Open\* (may be intentional)

### 8.5 Low — SuperAdmin elevation itself looks sound
- **Status:** No bug (unchanged)

### 8.6 Low — Incoming SMS webhook handling unfinished
- **Status:** Open\*

---

## Area 9 — SMS, API, Infrastructure

### 9.1 Critical — Unauthenticated Stripe PaymentIntent creation
- **Status:** Fixed
- **Files:** `routes/api.php`
- **Now:** Behind `auth:sanctum` + throttle.

### 9.2 Critical — Service-account token endpoint ignores credentials
- **Status:** Partial
- **Files:** `ServiceAccountController`
- **Now:** Validates admin email/password; disabled outside `local`/`testing`. Still returns mock token in local — keep out of production exposure.

### 9.3 High — SMS webhooks: CSRF blocks providers + no signature verification
- **Status:** Open\*

### 9.4 High — Unauthenticated lead API discloses existing PII
- **Status:** Open\*
- **Files:** `LeadBookingApiController`; `POST /api/leads`

### 9.5 Medium — Manual SMS send: any Admin Console user → any phone
- **Status:** Open\*

### 9.6 Suspected / Medium — Compose email SSRF via document URL
- **Status:** Open\*

### 9.7 Critical — `/delete_action` deletes arbitrary table rows for any logged-in staff
- **Status:** Partial
- **Files:** `CRMUtilityController::deleteAction`
- **Now:** Default branch uses an allowlist (matters, workflows, branches, templates, checklists, etc.). No longer fully arbitrary, but allowlisted hard-deletes remain too powerful for normal authenticated staff.

### 9.8 Critical — `/move_action` arbitrary column zeroing
- **Status:** Partial
- **Files:** `CRMUtilityController::moveAction`
- **Now:** Column allowlist (`status`, `is_active`, `is_archive`, `is_trash`) + role/super-admin style gate. Residual: still broad table targeting for authorized roles.

### 9.9 High — `/update_action` arbitrary column toggle (super-admin path)
- **Status:** Open\*

### 9.10 Medium — `PythonService::mergePdfs` uses invalid HTTP attach API
- **Status:** Open\*

### 9.11 Medium — Device token reassignment across users
- **Status:** Open\*

### 9.12 High — Staff API login leaves orphan Sanctum tokens on refresh-token failure
- **Status:** Open\*

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
- **Status:** Open\*

### 10.5 Medium — Logout audit user id taken from request body, not session
- **Status:** Open\*

### 10.6 Medium — Quick access grant check-then-create race
- **Status:** Open\*

### 10.7 Critical — Module authorization query hits non-existent `usertype` column
- **Status:** Fixed
- **Files:** `Controller::checkAuthorizationAction`
- **Now:** Uses `UserRole::find($role)` and numeric/`module_access` key matching.

### 10.8 High — Any successful staff save can assign Super Admin role
- **Status:** Open\*

### 10.9 High — Invited staff tab returns all staff
- **Status:** Open\*

### 10.10 Medium — Staff timezone endpoint lacks module authorization
- **Status:** Open\*

---

## Area 11 — Dashboard (supplement)

### 11.1 High — Matter stage update has no authorization / ownership check (IDOR)
- **Status:** Partial
- **Files:** `DashboardService::updateClientMatterStage`
- **Now:** Non–all-matters viewers must be assigned on the matter or pass `canAccessClientOrLead`. Viewers who “see all matters/actions” can still update broadly.

### 11.2 High — Action complete / deadline extend have no access checks (IDOR)
- **Status:** Open\* (assignee destroy side fixed in #7.1; dashboard complete/extend still confirm)

### 11.3 High — Dashboard matter list bypasses allocation for most roles
- **Status:** Open\*

### 11.4 Medium — Active/closed matter counters are global, not viewer-scoped
- **Status:** Open\*

### 11.5 Medium — Visa expiry message endpoint lacks client access check
- **Status:** Open\*

---

## Area 12 — Clients / Leads (supplement)

### 12.1 High — Note delete/pin via GET (CSRF-friendly state change)
- **Status:** Open\*

### 12.2 High — Global client search returns PII for inaccessible records
- **Status:** Open\*

### 12.3 High — Parents/siblings/others save via `saveSection` fatals on PHP 8
- **Status:** Fixed
- **Files:** `ClientPersonalDetailsController`
- **Now:** `saveParentsInfoSection(Request $request, $client = null)` accepts the second arg.

### 12.4 High — Hardcoded AusPost AUTH-KEY in source
- **Status:** Fixed
- **Files:** `ClientPersonalDetailsController::updateAddress`
- **Now:** Uses `config('services.auspost.auth_key')` / `env('AUSPOST_AUTH_KEY')`.

### 12.5 High — Legacy test-score update has no access check + null deref
- **Status:** Open\*

### 12.6 Medium — Contact match / uniqueness endpoints leak existence/PII
- **Status:** Open\*

### 12.7 Low — `POST /clients/edit` (`clients.update`) cannot receive route `{id}`
- **Status:** Open\*

---

## Area 13 — Matters / Documents / Email / Assignee (supplement)

### 13.1 High — Empty `unique_group_id` can mass-complete actions
- **Status:** Open\*

### 13.2 High — Complete/reopen any action by id (no assignee/assigner check)
- **Status:** Partial
- **Notes:** Destroy paths use `authorizeNoteManagement` (#7.1). Complete/reopen paths still need confirm.

### 13.3 High — Signature associate accepts matter from another client
- **Status:** Open\*

### 13.4 High — Manual email upload does not verify matter belongs to client
- **Status:** Open\*

### 13.5 Medium — Checklist stage resolved by name only (cross-workflow collision)
- **Status:** Open\*

### 13.6 Medium — Reopen request null-deref on missing matter type
- **Status:** Open\*

### 13.7 Medium — Admin `submitSignatures` requires constructed S3 key (breaks URL-based docs)
- **Status:** Open\*

### 13.8 Medium — `getClientMatters` / `suggestAssociation` leak client–matter graph
- **Status:** Open\*

### 13.9 Medium — SMS template `usage_count` incremented before send succeeds
- **Status:** Open\*

### 13.10 Medium — Non-delivered SMS status clears `delivered_at`
- **Status:** Open\*

### 13.11 Medium — Workflow stages can be created with `workflow_id = null`
- **Status:** Open\*

### 13.12 Medium — Matter / first-email templates saved with no validation
- **Status:** Open\*

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
