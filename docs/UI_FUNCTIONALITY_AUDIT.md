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
7. **Debug surfaces in production UI** — signing debug panels, `debug_info` in void-invoice alerts (PDF page preview renamed off `/debug-pdf-page`).

For security, ACL, CSRF, and money-path issues, see **`cmr-bugs.md`** (~80+ items still Open/Open* as of 2026-08-07). This document focuses on **UI and user-facing functionality**.

---

## Module 1 — Authentication & Session

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| AUTH-1 | Medium | `routes/web.php` | ~~`GET /logout` redirects to login **without** destroying the session.~~ **Fixed:** `GET /logout` now calls `AdminLoginController@logout` (same as POST). |
| AUTH-2 | Medium | `routes/web.php` | ~~`/clear-cache` uses default `auth` guard, not `auth:admin`.~~ **Fixed:** middleware is now `auth:admin`. |
| AUTH-3 | Low | `resources/views/layouts/crm-login.blade.php` | ~~Viewport meta includes deprecated `shrink-to-fit=no`.~~ **Fixed:** viewport is `width=device-width, initial-scale=1`. |

---

## Module 2 — Dashboard

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| DASH-1 | **High** | `public/js/dashboard.js`; route `/dashboard` | ~~Empty-state called undefined `openCreateTaskModal()`.~~ **Fixed:** dynamic empty-state uses `.add_my_task` + `DashboardAddTaskPopover.init()` (same as static empty state / header Add). |
| DASH-2 | Medium | `resources/views/components/dashboard/task-item.blade.php`, `public/js/dashboard.js` | ~~Task rows used `onclick` without keyboard/a11y affordances.~~ **Fixed:** content has `role="button"` + `tabindex="0"` + Enter/Space handler; checkbox/action buttons have accessible names; hover actions show on `:focus-within`. |
| DASH-3 | Medium | `public/js/crm/dashboard/dashboard-page.js` | ~~Infinite scroll and refresh failures log to `console.error` only — no user-visible toast.~~ **Fixed:** load-more failures toast (debounced); refresh already toasted and now consistent. |
| DASH-4 | Low | `resources/views/crm/dashboard.blade.php` | ~~Loads both legacy `dashboard.js` and newer `dashboard-page.js` with unclear ownership.~~ **Fixed:** ownership comments in Blade + both JS entry files (no risky merge). |
| DASH-5 | Medium | `routes/web.php` L96 vs L258 | ~~Two different `completeTask` handlers with divergent logic.~~ **Fixed:** `AssigneeController@completeTask` delegates to `DashboardService::completeTask` (same as dashboard); response includes both `success` and legacy `status`. |
| DASH-6 | Medium | `routes/web.php` L98–104 vs L243–247 | ~~Duplicate notification/check-in endpoints; GET vs POST mismatch for check-in status.~~ **Fixed:** layouts + Vite poller use named `dashboard.*` routes; check-in status accepts GET+POST; legacy root URLs kept as aliases. |

---

## Module 3 — Clients (List, Detail, Tabs)

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| CLI-1 | **High** | `routes/clients.php` | ~~Overlapping client edit routes: GET `clients.edit` and GET\|POST `clients.update` shared the same URI.~~ **Fixed:** `clients.edit` is GET-only; `clients.update` is POST-only (`/clients/edit/{id?}`). |
| CLI-2 | Medium | `public/js/crm/clients/detail-main.js` | ~~50+ native `alert()` calls.~~ **Fixed:** detail-main uses `crmAlert`/`crmToast` (native `alert` removed); companion detail scripts (`matter-reopen-actions`, `timeline-billing`) no longer fall back to `window.alert`. |
| CLI-3 | Medium | `resources/views/crm/clients/invoicelist.blade.php` | ~~Void-invoice success path appended `debug_info` reversal counts into user alerts.~~ **Fixed:** user message uses clean `obj.message` only; `debug_info` gated to `APP_DEBUG` and console; confirm uses `crmConfirm`. |
| CLI-4 | Medium | `public/js/crm/clients/detail-main.js` | ~~`detail-main.js` large monolith with incomplete extraction.~~ **Improved:** extracted document preview (`modules/document-preview.js`) and tags UI (`modules/client-tags.js`); further extraction continues. |
| CLI-5 | Medium | `cmr-bugs.md` §1.3 | ~~Client merge omitted edge tables; UI showed success without checking `status`.~~ **Fixed:** `ClientMergeService` migrates note attachments, lead_id/user_id mirrors, companies contact person + unique admin_id merge, opposing-party FKs, visa checklist refs; listing SPA respects `status:false`. |
| CLI-6 | Medium | `app/Http/Controllers/CRM/ClientsController.php`; `routes/clients.php` | ~~`POST /clients/test-python-accounting` returned mock JSON (`python_service_available: false`).~~ **Fixed:** route and `testPythonAccounting` method removed. |
| CLI-7 | Medium | `routes/clients.php`; `detail-main.js` | ~~`GET /documents/delete` allowed destructive delete via GET.~~ **Fixed:** route is `POST` only; client detail delete uses named URL + CSRF header. |
| CLI-8 | Low | `public/js/crm/clients/detail-main.js`; `modules/subtabs.js` | ~~Dead JS branches for `migrationdocuments` tab.~~ **Fixed:** removed obsolete matter-filter / tab-click handlers; matter docs still use `add_migration_doc` modal. |
| CLI-9 | Low | `resources/views/crm/clients/detail.blade.php` | ~~Comments for deprecated Service Taken / Education / Interested Services.~~ **Fixed:** removed stale comments and unused `getInterestedService*` ClientDetailConfig URLs. |
| CLI-10 | Low | layouts + insights + rating route | ~~Orphaned `client-rating` CSS and empty quality ratings UI after `rating`/`lead_quality` drops.~~ **Fixed:** removed CSS, Quality Mix card, rating click handler, and no-op `/change-client-status` route. |
| CLI-11 | Low | `modals/financial.blade.php`, `addclientmodal.blade.php` | ~~Verify no stray Commission/General Invoice / Payment Details links.~~ **Fixed:** confirmed no live UI; cleaned leftover removal comments. |
| CLI-12 | Positive | `tabs/_lazy_tab_shell.blade.php`, `tab-lazy-load.js` | Lazy tabs show spinner and error text on failure — good pattern; no change needed. |

---

## Module 4 — Matters & Workflow

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| MAT-1 | Fixed | `routes/matter_workflow.php` / `crm_matter_hub.php` | **Two discontinue flows unified:** UI uses `POST /clients/matter/discontinue` (checklist + JSON). Legacy `POST /crm/matter/discontinue` is a thin adapter; orphan `#discon_application` modal/JS removed. |
| MAT-2 | Fixed | `routes/matter_workflow.php` / `crm_matter_hub.php` | **Two reopen flows unified:** UI uses `POST /clients/matter/reopen`. Legacy `POST /crm/matter/revert` adapts to reopen; orphan `#revert_matter` modal/JS removed. |
| MAT-3 | Fixed | `ClientMatterHubController::getMatterLogs` | Matter logs now return **JSON**; `custom-form-validation.js` renders accordion HTML via `refreshMatterLogsAccordion`. |
| MAT-4 | Fixed | `routes/crm_matter_hub.php` | Renamed handlers to `listClientMatters` / `updateMatterOwnership` (legacy aliases kept). Named routes `crm.matter.list` / `crm.matter.ownership`. Dead ownership-ratio modal removed. |
| MAT-5 | Fixed | `routes/crm_matter_hub.php` | Removed dead `GET /crm/matter/updateintake`, `updatedates`, `updateexpectwin` (no-op / unauthenticated deadline GETs). Deadline updates use `POST /clients/matter/update-deadline`. |
| MAT-6 | Fixed | `routes/matter_workflow.php` | Legacy `POST /updatestage`, `/completestage`, `/updatebackstage` are thin adapters onto preferred `/clients/matter/*` handlers. Unused `ClientDetailConfig` stage URL keys removed. |
| MAT-7 | Fixed | `ClientMatterHubController` / workflow UI | **`opendocnote` / checklist upload already removed** — no live markup or handlers remain (JSON matter logs only). |
| MAT-8 | Fixed | `resources/views/crm/clients/tabs/workflow.blade.php` | Deadline checkbox/date IDs are unique per branch (`-modal` / `-inline`); JS binds via those IDs under `#workflow-tab`. |
| MAT-9 | Fixed | `resources/views/crm/clients/modals/matter-workflow-modals.blade.php` | Renamed from `applications.blade.php`; stripped stale Add Application / Interested Service comments. Live matter workflow modals kept; include updated in `addclientmodal`. |

---

## Module 5 — Documents & E-Signatures

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| DOC-1 | Fixed | `resources/views/crm/documents/edit.blade.php`, `modals/checklists.blade.php` | Staff PDF preview uses **`GET /documents/{id}/preview-page/{page}`** (`documents.preview.page` → `DocumentController::getPage`). Removed `/debug-pdf-page` closure. |
| DOC-2 | Fixed | `routes/documents.php` | Staff download/reminder URIs moved to **`/crm/documents/...`** so they no longer share paths with public token routes. Admin document routes register before public signing paths. |
| DOC-3 | Fixed | `routes/documents.php`, `PublicDocumentController` | Removed non-functional public `GET /documents/{id?}` stub (`public.documents.index`). Public signing remains token-only via `/sign/{id}/{token}`. |
| DOC-4 | Fixed | `public/js/crm/clients/modules/documents.js`, `ClientDocumentsController::download_document` | Downloads use AJAX + JSON; failures toast via `crmNotify`/`crmAlert` (no silent `console.error`). Local disk falls back to form POST. |
| DOC-5 | Low | `resources/views/documents/sign.blade.php`, `crm/documents/sign.blade.php` | Hidden **Debug Info** panel + `toggleDebug()` still ship (display:none). Clutters signing UI; risk if toggled in support scenarios. |
| DOC-6 | Low | `resources/views/crm/signatures/show.blade.php` ~L1478–1479 | `viewDocument()` shows **`alert('Document viewer feature coming soon!')`** — stub with no HTML caller found. Planned viewer never built. |

---

## Module 6 — Accounts, Billing & Financial UI

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| FIN-1 | Fixed | `public/js/crm/clients/modules/accounts.js` | Receipt modal open uses Bootstrap 5 fallback + one retry; missing/unopenable modal offers **Reload** via `crmConfirm` instead of a dead-end alert. |
| FIN-2 | Fixed | `public/js/crm/clients/modules/invoices.js` | Quick Receipt / invoice-list parse & AJAX failures toast via `crmNotify`/`crmAlert` (select still shows error option). |
| FIN-3 | Fixed | `public/js/crm/clients/modules/accounts.js` | Account tab lazy-load failures toast via `crmNotify`/`crmAlert` and show an in-tab **Retry** control (not error HTML alone). |
| FIN-4 | Fixed | Removed invoice flows | Verified: no live UI, README, or training docs reference Commission Invoice / General Invoice / Payment Details. Use Trust Account Entry, Office Receipt, or Tax Invoice only. Legacy table updated. |

*For money integrity (void invoice, fee transfers, booking payments), see `cmr-bugs.md` Areas 6 and 14 — many marked Fixed as of 2026-08-22.*

---

## Module 7 — Leads

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| LEAD-1 | Fixed | `routes/web.php`, `LeadAssignmentController`, `leads/index` | Removed deprecated `POST /leads/assign` and `/bulk-assign` plus orphan Assign Lead modal. Ownership uses **Assigned to** on lead/client detail; `GET /leads/assignable-staff` kept. |
| LEAD-2 | Fixed | `public/js/clients/edit-client.js` | OTP Resend: null-safe timer, safety timeout, and re-enable on send failure so Resend is not stuck disabled if countdown JS fails. |
| LEAD-3 | Fixed | `README.md` | Documented `POST /leads/convert` (was incorrectly listed as GET). Removed retired assign/bulk-assign rows. |

---

## Module 8 — Emails (Outlook / Client / Lead)

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| EMAIL-1 | Fixed | `outlook_emails.js`, `emails.js`, shared email JS | Removed duplicate upload sanitize/403 helpers from the monoliths (use `email-upload-filename.js`). Shared modules already load first (`matter-context.js`, `email-delete-confirm.js`). Further extraction remains optional. |
| EMAIL-2 | Fixed | `public/js/crm/compose-matter-documents.js` | Compose matter-document load failures toast via `crmNotify`/`crmAlert` and show an error row — no longer silently clear as empty. |
| EMAIL-3 | Fixed | `crm/partials/email-search-input.blade.php`; `emails_outlook.blade.php` | Single shared `#searchInput` partial included from all layout branches. |
| EMAIL-4 | Fixed | `clients/detail.blade.php`; `detail-main.js` | Reassign matter selects show “Select a client first” hint; enable only after matters load; AJAX errors re-lock + `crmAlert`. |
| EMAIL-5 | Fixed | CRM vs Admin `EmailLabelController`; `EmailLabelCatalogService` | Split clarified: Admin Console owns catalog CRUD; CRM `/email-labels` is list/apply/remove only (removed unused CRM `store`). Shared listing via `EmailLabelCatalogService`. |

---

## Module 9 — Assignee & Tasks

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| TASK-1 | Fixed | `resources/views/crm/assignee/*.blade.php` | Removed dead `#openassigneview` reassign modal shells and related CSS. |
| TASK-2 | Fixed | Same assignee views | Empty `aria-labelledby=""` removed with the dead modals; remaining `completionNotesModal` keeps a proper label. |
| TASK-3 | Fixed | `resources/views/crm/assignee/tasks.blade.php` | Migrated off Yajra/DataTables to Spatie paginator + HTML partials + `tasks-spa.js` infinite-scroll SPA (same pattern as completed tasks). |
| TASK-4 | Fixed | `routes/web.php` (legacy `/action*` block) | Confirmed intentional 301 bookmark compatibility; redirects grouped next to `/tasks*`; in-app links already use `/tasks*`; `TaskRoutesTest` asserts 301. |

---

## Module 10 — Booking & Appointments

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| BOOK-1 | Fixed | `resources/views/crm/booking/appointments/index.blade.php` | Added page note explaining greyed-out View/Edit/Quick Actions mean the booking is not synced to CRM yet; disabled buttons share a clearer tooltip/`aria-label`. Guard unchanged. |
| BOOK-2 | Fixed | `routes/booking_admin.php` (legacy calendar redirects) | Confirmed intentional 301 bookmark compatibility to `/booking/calendar/ajay`; comment + `BookingLegacyCalendarRedirectTest` asserts 301. In-app links already use `ajay`/`kunal`/staff. |
| BOOK-3 | Fixed | `routes/booking_admin.php`; `BookingAppointmentsController::updateDatetime` | `POST .../update-datetime` now calls dedicated `updateDatetime` (date/time only; merges existing meeting type/language) instead of sharing the full PUT `update` entrypoint. |
| BOOK-4 | Fixed | Area 14 residual (`14.10`/`14.11`) | Public/CRM create paths ignore client-supplied `is_paid`/`payment_status=completed`; `recordPaymentByIntent` requires `appointment_id` (or bansal id) in PI metadata; tests + `cmr-bugs.md` residual checklist updated. |

---

## Module 11 — Admin Console

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| ADMIN-1 | Fixed | `SmsSendController`; `routes/adminconsole.php` | Removed unused `POST .../sms/send/bulk` 501 stub (no Blade/UI consumers). Single/template send endpoints remain. |
| ADMIN-2 | Fixed | `permissions-accordion.blade.php`; `APPLICATION_TO_MATTER_MIGRATION_PLAN.md` §4 | Matters section already used matter labels; renamed leftover `applications` select-all CSS class → `matters`. Migration plan §4 marked Done. |
| ADMIN-3 | Fixed | `routes/adminconsole.php` (legacy visa-document-type) | Confirmed intentional 301 bookmark redirects to `matterdocumenttype.*`; added missing view redirect; `AdminConsoleRoutesTest` asserts 301. In-app nav already uses matter routes. |
| ADMIN-4 | Fixed | `docs/PLAN_USER_TO_CLIENT_STAFF_RENAME.md` | Phases 4–5 marked **BLOCKED** with hard gate (same-release migrations+code, approval, backup, no stray migration files). Risk mitigated as documentation/process; no Phase 4 migrations present in repo. |

---

## Module 12 — Communication Check / Conflict Check

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| COMM-1 | Fixed | `resources/views/crm/communication-check/index.blade.php` | Clarified assistive scope and added actionable **Phone / Recents** tip (log Call Actions, match by phone+time, link to Tasks → Call). Still no PBX; usefulness improved via workflow guidance. |
| COMM-2 | Fixed | `conflict-parties-card.blade.php`; `MODULE_OPTIMIZATION_REVIEW.md` §3 | Conflict UI script no longer `@push`ed (missing from AJAX tab HTML); inline + `data-cp-bound` so lazy Personal Details binds correctly. |
| COMM-3 | Confirmed | `conflict-parties-card.blade.php` | Phase 5 UX verified: force-clear override panel + access-locked match links present. |

---

## Module 13 — Office Visits & Front Desk

| ID | Severity | Location | Issue |
|----|----------|----------|-------|
| OV-1 | Fixed | `routes/web.php`; `routes/office_visits.php`; office-visits + front-desk UI | Documented complementary roles (wizard creates, In Person queues). Legacy `/office-visits/create` 301s to front-desk wizard; both UIs cross-link. |
| OV-2 | Fixed | `routes/office_visits.php` | Route names renamed `officevisits.*` → `office-visits.*` to match `/office-visits` URIs; callers + README updated. |
| OV-3 | Verified | `OfficeVisitController::getcheckin`; `SecurityBugFixes14Test::test_14_17_*` | Re-ran XSS escape coverage; user-controlled detail fields remain `e()` / `htmlspecialchars()` escaped. |

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
| X-3 | Medium | `crm/clients/modals/emails.blade.php` (assignee `#openassigneview` shells removed in TASK-1/2) | **`aria-labelledby=""`** empty on email upload dialogs — WCAG failure. |
| X-4 | Medium | `docs/MODULE_OPTIMIZATION_REVIEW.md` §309–347 | **DataTables + jQuery lock-in** flagged as Blocking for modernization; still loaded on client detail. Assignee open tasks migrated to SPA (TASK-3). Hurts performance; blocks lighter list UIs. |
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
| Interested Services | Deprecated | Removed from matter-workflow modals; residual comments may remain in `detail-main.js` |
| Service Taken | Deprecated | `detail.blade.php` comment |
| Lead assignment (legacy assign modal) | Removed | Use **Assigned to** on lead/client detail; `POST /leads/assign` / bulk-assign removed |
| Appointment system (legacy) | Deprecated | `console.warn` in `detail-main.js`, `custom-form-validation.js` |
| Commission/General Invoice, Payment Details | Removed | No UI/training remnants; staff use Trust Account Entry, Office Receipt, or Tax Invoice |
| Migration documents tab | Removed | Dead JS in `detail-main.js` |
| Trust compliance (VLSB) | Module removed 2026-08-22 | See `cmr-bugs.md` obsolete items |

---

## Recommended fix priority (documentation only)

### P0 — Broken user flows
1. ~~**DASH-1** — Wire empty-state “Add task” to `.add_my_task` popover or implement `openCreateTaskModal`.~~ **Done.**
2. **MAT-1 / MAT-2** — Consolidate matter discontinue/reopen to `/clients/matter/*` only; update all JS callers.
3. **CLI-1** — Resolve `clients.edit` vs `clients.update` route overlap.
4. ~~**LEAD-1** — Remove or replace deprecated lead assignment routes with clear UI messaging.~~ **Done.**

### P1 — UX consistency
5. Replace **`alert()`-heavy flows** — **Done (2026-09-02):** `crmAlert` + Toastify/`crmNotify` across former alert call sites (X-5 / Appendix A).
6. Add **user-visible errors** for silent AJAX failures (DASH-3). ~~FIN-2 / FIN-3 / EMAIL-2~~ **Done.**
7. Remove **debug surfaces** from production UI (void-invoice `debug_info`, signing debug panels) (CLI-3, DOC-5). PDF preview renamed (DOC-1 Fixed).

### P2 — Accessibility & performance
8. Accessibility pass: landmarks, skip link, modal labels, keyboard task list (X-2, X-3, DASH-2). ~~TASK-2~~ **Done.**
9. Plan **DataTables migration** per `MODULE_OPTIMIZATION_REVIEW.md` (X-4).
10. Continue **JS modularization** (`detail-main.js`; email list/reading/compose blocks still in `outlook_emails.js` / `emails.js` after EMAIL-1 helper cleanup).

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
