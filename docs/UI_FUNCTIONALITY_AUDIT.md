# Bansal Law CRM — UI & Functionality Audit

**Audit date:** 2026-09-02  
**Scope:** Complete web application (CRM staff UI, Admin Console, public signing/booking surfaces)  
**Method:** Static code review (routes, controllers, Blade views, public JS), graphify orientation, cross-reference with `cmr-bugs.md`  
**Action:** Documentation only — **no fixes applied**

---

## Severity key

| Level | Meaning |
|-------|---------|
| **Critical** | Core workflow broken, data integrity risk, or security issue visible in UI |
| **High** | Major feature broken, misleading UX, or significant accessibility gap |
| **Medium** | Partial feature, degraded UX, inconsistent behavior, or maintainability debt affecting users |
| **Low** | Dead code, polish, terminology drift, or future migration risk |

---

## Executive summary

Bansal Law CRM is a large Laravel monolith with **255+ Blade views**, heavy jQuery/DataTables usage, and an ongoing **Application → Matter** terminology migration. Most security-critical items from the July 2026 audit (`cmr-bugs.md`) are marked Fixed or Partial, but **UI/UX debt remains substantial**:

1. **Broken interactive flows** — e.g. dashboard “Add a task” after empty state, deprecated lead assignment routes still registered.
2. **Legacy error UX** — ~~widespread `alert()`~~ **fixed:** uses `crmAlert` → Toastify/`crmNotify` (SweetAlert2 fallback). See Appendix A / X-5.
3. **Dual code paths** — parallel legacy and modern matter/discontinue/reopen endpoints; duplicate notification URLs.
4. **Incomplete features exposed in UI or API** — bulk SMS (501), Python accounting test endpoint, document viewer stub.
5. **Accessibility gaps** — missing landmarks, empty `aria-labelledby`, keyboard-inaccessible task rows.
6. **Performance/maintainability** — monolithic JS files (`detail-main.js`, `outlook_emails.js`), DataTables lock-in, massive inline CSS in layouts.
7. **Debug surfaces in production UI** — `/debug-pdf-page` used for document preview, debug panels on signing pages, `debug_info` in void-invoice alerts.

For security, ACL, CSRF, and money-path issues, see **`cmr-bugs.md`** (~80+ items still Open/Open* as of 2026-08-07). This document focuses on **UI and user-facing functionality**.

---

## Module 1 — Authentication & Session

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| AUTH-1 | Medium | `routes/web.php` L78–80 | `GET /logout` redirects to login **without** destroying the session. Only `POST /logout` calls `AdminLoginController@logout`. Users bookmarking GET logout may believe they are signed out while session remains active. |
| AUTH-2 | Medium | `routes/web.php` L49–59 | `/clear-cache` uses default `auth` guard, not `auth:admin`. Any user on the default guard could trigger cache clears if such accounts exist. |
| AUTH-3 | Low | `resources/views/layouts/crm-login.blade.php` | Viewport meta includes deprecated `shrink-to-fit=no` — minor mobile scaling behavior on iOS. |

---

## Module 2 — Dashboard

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| DASH-1 | **High** | `public/js/dashboard.js` ~L449; route `/dashboard` | After completing all tasks, empty-state HTML injects a button calling `openCreateTaskModal()`, but **that function is not defined anywhere**. Working add-task flow uses `.add_my_task` popover (`add-task-popover.js`). **“Add a task” silently fails** after dynamic empty-state render. |
| DASH-2 | Medium | `resources/views/components/dashboard/task-item.blade.php`, `public/js/dashboard.js` | Task rows use `onclick` on `<div>`/checkbox (`openTaskDetail`, `handleTaskComplete`) without `role="button"`, `tabindex`, or keyboard handlers. **Keyboard and screen-reader users cannot operate tasks** from the list. |
| DASH-3 | Medium | `public/js/crm/dashboard/dashboard-page.js` ~L246–440 | Infinite scroll and refresh failures log to `console.error` only — **no user-visible toast** when “Load more” or dashboard refresh fails. |
| DASH-4 | Low | `resources/views/crm/dashboard.blade.php` | Loads both legacy `public/js/dashboard.js` and newer `public/js/crm/dashboard/dashboard-page.js`. Split responsibility increases regression risk (see DASH-1). |
| DASH-5 | Medium | `routes/web.php` L96 vs L258 | **Two different `completeTask` handlers:** `DashboardController@completeTask` (`dashboard.tasks.complete`) vs `AssigneeController@completeTask` (`tasks.complete`). Potential inconsistent task-completion behavior depending on which endpoint the UI calls. |
| DASH-6 | Medium | `routes/web.php` L98–104 vs L243–247 | **Duplicate notification/check-in endpoints:** named `/dashboard/*` routes and unnamed legacy root URLs (`/fetch-notification`, `/mark-notification-seen`, `/check-checkin-status`). Legacy GET `/check-checkin-status` vs POST `/dashboard/check-checkin-status`. |

---

## Module 3 — Clients (List, Detail, Tabs)

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| CLI-1 | **High** | `routes/clients.php` L44–45 | **Overlapping client edit routes:** `GET clients/edit/{id}` (`clients.edit`) and `Route::match(['get','post'], 'clients/edit/{id?}')` (`clients.update`) share the same URI pattern. Laravel resolves GET to the first registered route; `clients.update` name may not match actual GET behavior. |
| CLI-2 | Medium | `public/js/crm/clients/detail-main.js` | **50+ `alert()` calls** for errors/success (uploads, documents, invoices, pins). Blocks UI thread; poor mobile/accessibility UX. Toastify/SweetAlert2 already loaded in layout but not used consistently. |
| CLI-3 | Medium | `resources/views/crm/clients/invoicelist.blade.php` ~L1575–1587 | Void-invoice success path appends **`debug_info` reversal counts into `alert()`** shown to end users — developer diagnostics in production UI. |
| CLI-4 | Medium | `public/js/crm/clients/detail-main.js`; `docs/MODULE_OPTIMIZATION_REVIEW.md` §11 | **`detail-main.js` remains a very large monolith** with incomplete progressive extraction. Increases bug surface and page load time on client detail. |
| CLI-5 | Medium | `cmr-bugs.md` §1.3 | Client merge marked **Partial** — may omit edge tables. Merge wizard may show success while some related data is not migrated. |
| CLI-6 | Medium | `app/Http/Controllers/CRM/ClientsController.php` ~L4226–4269; `routes/clients.php` | `POST /clients/test-python-accounting` returns **mock JSON** (`python_service_available: false`, TODO for Python integration). Callable if discovered; misleading for any future UI hook. |
| CLI-7 | Medium | `routes/clients.php` L186 | `GET /documents/delete` — **destructive delete via GET**. CSRF-bypass pattern if URL is bookmarked, linked, or crawled. |
| CLI-8 | Low | `public/js/crm/clients/detail-main.js` ~L2374–2523 | Dead JS branches for **`migrationdocuments` tab** — tab removed from `$cdnTabIncludes` but handlers remain. |
| CLI-9 | Low | `resources/views/crm/clients/detail.blade.php` | Comments reference deprecated **Service Taken**, **Education**, **Interested Services** features. UI cleaned but comments indicate incomplete doc/JS cleanup. |
| CLI-10 | Low | `docs/ADMINS_TABLE_COLUMNS.md` | **`rating` column marked for deletion** but client-rating CSS remains in layouts; insights may show “No quality ratings available.” Orphaned UI. |
| CLI-11 | Low | `resources/views/crm/clients/modals/financial.blade.php`, `addclientmodal.blade.php` | Commission Invoice, General Invoice, Payment Details, payment-schedule flows **removed**; verify no stray menu links in custom deployments. |
| CLI-12 | Positive | `resources/views/crm/clients/tabs/_lazy_tab_shell.blade.php`, `tab-lazy-load.js` | Lazy tabs show spinner and error text on failure — good pattern for account and other heavy tabs. |

---

## Module 4 — Matters & Workflow

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| MAT-1 | **High** | `routes/matter_workflow.php` L23 vs `routes/crm_matter_hub.php` L13 | **Two discontinue flows:** `POST /clients/matter/discontinue` (validated JSON, completion checklist) vs `POST /crm/matter/discontinue` (legacy params `diapp_id`, echo JSON). UI may call either depending on age of JS. |
| MAT-2 | **High** | `routes/matter_workflow.php` L24 vs `routes/crm_matter_hub.php` L14 | **Two reopen flows:** `POST /clients/matter/reopen` vs `POST /crm/matter/revert`. Inconsistent behavior and error handling. |
| MAT-3 | Medium | `app/Http/Controllers/CRM/ClientMatterHubController.php` ~L1104–1144 | `getMatterLogs` emits **HTML via PHP inline** while other endpoints return JSON. TODO: refactor to JSON + frontend render. Harder to test; brittle architecture. |
| MAT-4 | Medium | `routes/crm_matter_hub.php` L11, L24 | **Application vs matter naming:** `/crm/matter/list` → `getapplications()`; `/crm/matter/ownership` → `application_ownership()`. Confusing for developers and support. |
| MAT-5 | Medium | `routes/crm_matter_hub.php` L20 | `GET /crm/matter/updateintake` — **no-op stub** (“Date field removed with applications table”). Dead route if still linked from old UI. |
| MAT-6 | Medium | `routes/matter_workflow.php` L13–18 | Legacy POST `/updatestage`, `/completestage`, `/updatebackstage` alongside preferred `/clients/matter/*` routes. Dual maintenance burden. |
| MAT-7 | Low | Same controller ~L1144 | Comment: **`opendocnote` / workflow checklist upload flow dead** (no modal, no handler). Dead affordance if icon markup remains. |
| MAT-8 | Low | `resources/views/crm/clients/tabs/workflow.blade.php` | Duplicate IDs `workflow-set-deadline` / `workflow-deadline-date` in modal vs inline branches (mutually exclusive per `$workflowInModal` — not a live bug but confusing for JS). |
| MAT-9 | Low | `resources/views/crm/clients/modals/applications.blade.php` | **Add Application / Interested Service modals removed** — file mostly comments. Orphan artifact. |

---

## Module 5 — Documents & E-Signatures

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| DOC-1 | Medium | `resources/views/crm/documents/edit.blade.php`, `modals/checklists.blade.php` | PDF page preview uses **`/debug-pdf-page/{id}/{page}`** with comment “temporary fix.” Auth-gated per `cmr-bugs.md` but debug-oriented naming in production UI. |
| DOC-2 | Medium | `routes/documents.php` | **Same URI patterns, different auth scopes:** e.g. `GET /documents/{id}/download-signed` registered for both public and admin groups. Route registration order may shadow staff admin paths. |
| DOC-3 | Medium | `routes/documents.php` L133–159 vs README L339 | Public `GET /documents/{id?}` documented as **stub** (redirect home). Public signing uses token-validated routes — general documents index is non-functional. |
| DOC-4 | Medium | `public/js/crm/clients/modules/documents.js` | Download failures use `alert()`; some errors only `console.error` before alert. |
| DOC-5 | Low | `resources/views/documents/sign.blade.php`, `crm/documents/sign.blade.php` | Hidden **Debug Info** panel + `toggleDebug()` still ship (display:none). Clutters signing UI; risk if toggled in support scenarios. |
| DOC-6 | Low | `resources/views/crm/signatures/show.blade.php` ~L1478–1479 | `viewDocument()` shows **`alert('Document viewer feature coming soon!')`** — stub with no HTML caller found. Planned viewer never built. |

---

## Module 6 — Accounts, Billing & Financial UI

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| FIN-1 | Medium | `public/js/crm/clients/modules/accounts.js` ~L241–248 | Office receipt modal missing → **`alert()`** “Please refresh” — no graceful recovery or retry. |
| FIN-2 | Medium | `public/js/crm/clients/modules/invoices.js` ~L32–39 | JSON parse / AJAX failures → **`console.error` only** when loading invoice lists for Quick Receipt. User sees empty/stale data. |
| FIN-3 | Medium | `resources/views/crm/clients/tabs/account.blade.php` | Account ledger lazy-loads — failure handling depends on `accounts.js` injecting error HTML; inconsistent with toast patterns elsewhere. |
| FIN-4 | Low | Removed invoice flows | Commission Invoice, General Invoice, Payment Details removed from modals. Ensure training materials don’t reference removed flows. |

*For money integrity (void invoice, fee transfers, booking payments), see `cmr-bugs.md` Areas 6 and 14 — many marked Fixed as of 2026-08-22.*

---

## Module 7 — Leads

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| LEAD-1 | **High** | `routes/web.php` L192–194 → `LeadAssignmentController.php` L28–31, L81 | **`assign` and `bulkAssign` return “Lead assignment has been deprecated.”** Routes still registered. UI or bookmarks hitting these show info flash instead of functional assignment. |
| LEAD-2 | Low | `resources/views/crm/leads/edit.blade.php` ~L469 | OTP **Resend** button starts disabled until countdown. If countdown JS fails, resend stays disabled with no fallback message. |
| LEAD-3 | Low | README vs `routes/web.php` | README documents `GET /leads/convert` but route is **`POST /leads/convert`**. Documentation drift. |

---

## Module 8 — Emails (Outlook / Client / Lead)

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| EMAIL-1 | Medium | `public/js/outlook_emails.js`; `docs/MODULE_OPTIMIZATION_REVIEW.md` §12 | **`outlook_emails.js` / `emails.js` remain large monoliths** — performance and maintainability debt on primary email UI. |
| EMAIL-2 | Medium | `public/js/crm/compose-matter-documents.js` ~L50–52 | Compose attachment load **`.fail()` silently clears** matter document list — user sees empty attachments with no error when API fails. |
| EMAIL-3 | Low | `resources/views/crm/emails_outlook.blade.php` | Three `#searchInput` definitions in mutually exclusive `@if` branches — valid per render but fragile for maintenance. |
| EMAIL-4 | Low | `resources/views/crm/clients/detail.blade.php` ~L1152, L1196 | Inbox/sent **reassign matter `<select>` starts disabled** until client chosen — intentional; may appear broken if JS init fails. |
| EMAIL-5 | Medium | `routes/clients.php` L128–133 vs `routes/adminconsole.php` L50–54 | **Duplicate email-label systems:** CRM `/email-labels/*` vs Admin Console `/adminconsole/features/email-labels/*` — different controllers. Potential inconsistent labels in UI. |

---

## Module 9 — Assignee & Tasks

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| TASK-1 | Low | `resources/views/crm/assignee/*.blade.php` | **Reassign / assignee-detail flows removed** but `#openassigneview` **modal shells remain** in multiple views — dead DOM. |
| TASK-2 | Medium | Same assignee views | Modals use **`aria-labelledby=""`** (empty) — WCAG dialog labeling failure. |
| TASK-3 | Medium | `resources/views/crm/assignee/tasks.blade.php` | Still includes **DataTables** — contributes to jQuery/DataTables lock-in and page weight. |
| TASK-4 | Low | `routes/web.php` L268–289 | Legacy `/action*` → `/tasks*` redirects (301). Old bookmarks may still exist in staff workflows. |

---

## Module 10 — Booking & Appointments

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| BOOK-1 | Medium | `resources/views/crm/booking/appointments/index.blade.php` ~L934–941 | View/Edit/Quick actions **render disabled** with tooltip “Not synced to CRM yet” when `bansal_appointment_id` missing. Correct guard but staff may perceive list as broken without header explanation. |
| BOOK-2 | Low | `routes/booking_admin.php` L30–34 | Five legacy calendar type URLs → `/booking/calendar/ajay` (301). |
| BOOK-3 | Low | `routes/booking_admin.php` L18–19 vs L55–57 | `POST .../update-datetime` and `PUT .../appointments/{id}` both call `update` — duplicate update paths. |
| BOOK-4 | Low | `cmr-bugs.md` Area 14 | Many booking/payment bugs marked Fixed; **residual QA** recommended on paid-before-charge and metadata ownership paths. |

---

## Module 11 — Admin Console

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| ADMIN-1 | **High** | `app/Http/Controllers/AdminConsole/Sms/SmsSendController.php` ~L148–160; `routes/adminconsole.php` L143 | **`POST .../sms/send/bulk` returns HTTP 501** “Bulk SMS feature coming soon” with TODO (CSV, scheduling). No Blade UI found — API stub only unless external client calls it. |
| ADMIN-2 | Low | `docs/APPLICATION_TO_MATTER_MIGRATION_PLAN.md` | Role UI may still show legacy **“APPLICATIONS”** permission labels while backend uses matters. |
| ADMIN-3 | Low | `routes/adminconsole.php` L115–118 | `/adminconsole/features/visa-document-type*` → `matterdocumenttype.*` (301). Visa→matter migration debt in URLs. |
| ADMIN-4 | Low | `docs/PLAN_USER_TO_CLIENT_STAFF_RENAME.md` | Phases 4–5 (DB column renames) planning only — future UI/route breakage if applied without full checklist. |

---

## Module 12 — Communication Check / Conflict Check

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| COMM-1 | Medium | `resources/views/crm/communication-check/index.blade.php` ~L100–109 | Banner states tool is **assistive only**; calls match logged Call Actions only (**no PBX**). Sets correct expectations but limits usefulness for phone workflows. |
| COMM-2 | Low | `docs/MODULE_OPTIMIZATION_REVIEW.md` §3 | Non-overview tabs with conflict UI may need **AJAX bundle** if opened without full reload — potential script load gap. |
| COMM-3 | Positive | `resources/views/crm/clients/partials/conflict-parties-card.blade.php` | Phase 5 UX (force clear, access-gated links) appears implemented. |

---

## Module 13 — Office Visits & Front Desk

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| OV-1 | Low | `routes/web.php` L231–237 vs `routes/office_visits.php` | **Parallel check-in flows:** front-desk wizard (`/front-desk/checkin/*`) and office-visits module. Staff may use either; behavior should stay aligned. |
| OV-2 | Low | `routes/office_visits.php` L6–10 | Route names `officevisits.*` (no hyphen) while URIs use `/office-visits`. Naming inconsistency. |
| OV-3 | Verify | `cmr-bugs.md` §14.17 | Office-visit detail XSS marked Fixed — verify in browser during QA. |

---

## Module 14 — SMS & Webhooks

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| SMS-1 | Medium | README vs `routes/sms.php` | **README drift:** documents Twilio webhooks but routes only register **Cellcast** webhooks. |
| SMS-2 | Medium | README vs `RouteServiceProvider.php` | README says `sms.php` uses `web` middleware; actual registration uses **`api` middleware** (no CSRF — correct for webhooks but docs wrong). |

---

## Cross-cutting — Layouts, Accessibility & JS Patterns

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| X-1 | Medium | `resources/views/layouts/crm_client_detail.blade.php` | **Massive inline CSS** (~2700+ lines including `<style>` block). Cache-unfriendly, hard to maintain; high page weight despite responsive `@media` rules. |
| X-2 | Medium | All three layouts (`crm-login`, `crm_client_detail`, `crm_client_detail_dashboard`) | **No skip link**, **no `<main role="main">` landmark** — screen reader navigation suffers across CRM. |
| X-3 | Medium | Multiple modals (assignee views, `crm/clients/modals/emails.blade.php`) | **`aria-labelledby=""`** empty on dialogs — WCAG failure. |
| X-4 | Medium | `docs/MODULE_OPTIMIZATION_REVIEW.md` §309–347 | **DataTables + jQuery lock-in** flagged as Blocking for modernization; still loaded on client detail + assignee tasks. Hurts performance; blocks lighter list UIs. |
| X-5 | Medium → **Fixed** | Codebase-wide | Native `alert()` / `window.alert()` replaced with **`crmAlert()`** (Toastify via `crmNotify`/`crmToast`; SweetAlert2 fallback). See Appendix A. |
| X-6 | Medium | Codebase-wide | **`console.error` only** in `dashboard-page.js`, `sidebar-tabs.js`, `invoices.js`, `compose-matter-documents.js` — silent failures for users. |
| X-7 | Low | `public/js/crm/clients/utils/dom-helpers.js` ~L259 | `@deprecated adjustMatterDocumentsPanelHeight` alias still present. |
| X-8 | Low | Application→Matter migration | Widespread **visa/application** naming in routes, methods, and DB table comments (`visa_document_types`, `addvisadocchecklist`, `viewapplicationnote`). User-facing labels mostly say “matter” but code/support docs diverge. |

---

## Appendix A — Native `alert()` inventory (X-5) — **FIXED 2026-09-02**

**Original audit:** ~342 native `alert()` / `window.alert()` call sites across 63 files (~38 pages).  
**Fix applied:** Replaced with existing CRM notification UI.

### What changed

| Change | Detail |
|--------|--------|
| API | New `window.crmAlert(message)` in `public/js/crm-notify.js` — infers success/warning/error/info, prefers **Toastify** (`crmToast` / `crmNotify`), falls back to **SweetAlert2**, never native `alert` |
| Patch | `window.alert` redirected to `crmAlert` wherever `crm-notify.js` loads |
| Call sites | All ~342 live calls rewritten to `crmAlert(...)` / `window.crmAlert(...)` |
| Shared partials | `components/crm-notify-assets.blade.php` + `components/crm-notify-scripts.blade.php` (Toastify + iziToast shim + crm-notify) |
| Layouts | Wired into `crm_client_detail`, `crm_client_detail_dashboard`, `crm-login`, and public/CRM document sign pages |

### Status

**Native browser alert dialogs removed from first-party CRM UI.** Remaining `confirm()` dialogs (if any) are out of scope for this pass.

---

## Legacy & deprecated features (UI remnants)

These features were removed or deprecated; verify staff training and bookmarks don’t reference them:

| Feature | Status | UI/JS remnants |
|---------|--------|----------------|
| Education system | Deprecated | Modal comments in `addclientmodal.blade.php`, `editclientmodal.blade.php` |
| Interested Services | Deprecated | `applications.blade.php`, `detail-main.js` comments |
| Service Taken | Deprecated | `detail.blade.php` comment |
| Lead assignment (assignee column) | Deprecated | Routes still active; controller redirects with info message |
| Appointment system (legacy) | Deprecated | `console.warn` in `detail-main.js`, `custom-form-validation.js` |
| Commission/General Invoice, Payment Details | Removed | Modal comments in financial views |
| Migration documents tab | Removed | Dead JS in `detail-main.js` |
| Trust compliance (VLSB) | Module removed 2026-08-22 | See `cmr-bugs.md` obsolete items |

---

## Recommended fix priority (documentation only)

### P0 — Broken user flows
1. **DASH-1** — Wire empty-state “Add task” to `.add_my_task` popover or implement `openCreateTaskModal`.
2. **MAT-1 / MAT-2** — Consolidate matter discontinue/reopen to `/clients/matter/*` only; update all JS callers.
3. **CLI-1** — Resolve `clients.edit` vs `clients.update` route overlap.
4. **LEAD-1** — Remove or replace deprecated lead assignment routes with clear UI messaging.

### P1 — UX consistency
5. Replace **`alert()`-heavy flows** — **Done (2026-09-02):** `crmAlert` + Toastify/`crmNotify` across former alert call sites (X-5 / Appendix A).
6. Add **user-visible errors** for silent AJAX failures (DASH-3, EMAIL-2, FIN-2).
7. Remove **debug surfaces** from production UI (`debug-pdf-page`, void-invoice `debug_info`, signing debug panels) (CLI-3, DOC-1, DOC-5).

### P2 — Accessibility & performance
8. Accessibility pass: landmarks, skip link, modal labels, keyboard task list (X-2, X-3, DASH-2, TASK-2).
9. Plan **DataTables migration** per `MODULE_OPTIMIZATION_REVIEW.md` (X-4).
10. Continue **JS modularization** (`detail-main.js`, `outlook_emails.js`).

### P3 — Hygiene
11. Remove dead UI (assignee modals, migrationdocuments JS, viewDocument stub, orphan modal files).
12. Align README with actual SMS routes and middleware (SMS-1, SMS-2).
13. Complete **Application→Matter** terminology in routes and method names (X-8).

---

## Related documents

| Document | Relevance |
|----------|-----------|
| `cmr-bugs.md` | Security, ACL, CSRF, XSS, money paths (~80+ Open/Open*) |
| `docs/MODULE_OPTIMIZATION_REVIEW.md` | JS monoliths, DataTables, lazy-load patterns |
| `docs/APPLICATION_TO_MATTER_MIGRATION_PLAN.md` | Terminology and role label debt |
| `docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md` | Access approval queue on dashboard |
| `docs/CONFLICT_CHECK_PHASE5.md` | Conflict parties UX |
| `docs/PLAN_USER_TO_CLIENT_STAFF_RENAME.md` | Future rename phases 4–5 |

---

## Notes

- This audit is **static** (code and routes). Browser QA on XAMPP/local and staging is recommended to confirm runtime behavior, especially booking sync and financial flows.
- Vendor TinyMCE TODOs under `public/js/tinymce/**` are excluded per `cmr-bugs.md` guidance.
- **No code changes** were made in this audit pass.
