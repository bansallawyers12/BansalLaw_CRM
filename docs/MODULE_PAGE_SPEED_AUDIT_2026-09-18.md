# CRM Module & Page Speed Audit (Documentation Only)

**Audit date:** 2026-09-18  
**Scope:** Module-wise and page-wise **speed / load performance** only (staff CRM)  
**Related (not duplicated):** [MODULE_OPTIMIZATION_REVIEW.md](MODULE_OPTIMIZATION_REVIEW.md) (code structure / query patterns, 2026-08-27), [MODULE_BREAKPOINT_AUDIT_2026-09-17.md](MODULE_BREAKPOINT_AUDIT_2026-09-17.md) (break/at-risk features)  
**Method:** Static review — graphify orientation, layout/asset inventory, route → controller pagination/cache patterns, JS/CSS file sizes on disk. **No Lighthouse / browser timing runs; no code fixes applied.**  
**Action:** **Documentation only — do not fix / do not apply**

---

## Severity key (speed)

| Rating | Meaning |
|--------|---------|
| **Fast** | Paginated/cached server path + modest page JS; first paint dominated by shared shell only |
| **OK** | Acceptable for CRM; known deferrals (lazy tabs, infinite scroll) or optional heavy widgets |
| **Slow-risk** | Large first-load JS/CSS, eager heavy libs (TinyMCE/DataTables/calendar), or unbounded/CPU work on demand |
| **Interaction-slow** | Page shell OK; specific actions (Office→PDF/HTML, bulk upload, PDF generate) can take seconds |

| Optimization status (speed) | Meaning |
|-----------------------------|---------|
| **Optimized** | Pagination, caching, lazy load, or stable cache-bust already in place for the hot path |
| **Partial** | Backend lean but frontend still heavy, or some pages still bust cache with `time()` |
| **Not optimized** | Known speed tax still present; advisory only |

---

## Executive summary

1. **Every authenticated CRM page pays ~1.7 MB uncompressed** of shared layout CSS/JS (excl. Bootstrap CDN + Vite + page-specific scripts). That is the largest cross-cutting first-paint cost.
2. **Backend list paths are largely speed-optimized** (paginate / infinite scroll / `Cache::remember` on dashboard KPIs, emails, grants, form dropdowns) — see prior optimization review.
3. **Heaviest page surfaces:** Client detail (~210 KB `detail-main.js` + TinyMCE + DataTables + tab modules), Outlook emails (~335 KB JS + ~174 KB CSS), Dashboard (FullCalendar Vite + calendar JS), Booking appointments (DataTables JSON still on list path).
4. **Interaction bottlenecks (not first paint):** Office document preview (`OfficeToPdfConverterService` / PhpWord HTML), account PDF paths (`set_time_limit(300)`), email upload (`set_time_limit(180)`), inbox sync (queued — UI waits on poll).
5. **Cache-bust regression pockets:** Most hot pages use `filemtime()`; **8 Admin Console / client edit blades still use `?v={{ time() }}`**, forcing re-download every request.
6. **TinyMCE (~471 KB min)** is `@include`d on several **list** pages (clients index, leads index, office visits, assignee lists, staff index) even when the editor is only needed for compose/modals.

**Overall speed verdict:** **Partial** — server lists are mostly optimized; global shell + a few mega page bundles and on-demand converters dominate perceived slowness.

---

## Scorecard — modules (speed)

| Module | Pages / entry | Speed rating | Opt. status | Primary speed notes |
|--------|---------------|--------------|-------------|---------------------|
| Shared CRM shell | All `@extends` layout pages | **Slow-risk** (tax on all) | **Partial** | ~1.7 MB local assets every page; jQuery + `app.min.js` + `style.css` + `crm-theme.css` |
| Login | `/login` | **Fast** | **Optimized** | Slim `layouts.crm-login` |
| Dashboard | `/dashboard` | **OK** | **Optimized** | KPI cache (`kpi_cache_seconds` default 300); infinite scroll; AJAX `/dashboard/summary`; FullCalendar via Vite adds weight |
| Clients list / matters | `/clients`, matters listing | **OK** | **Optimized** | Paginate 20 + infinite scroll (`matters-listing-infinite.js`); TinyMCE still pulled on index |
| Client detail | `/clients/detail/{id}` | **Slow-risk** | **Partial** | Lazy tabs + module scripts; still loads TinyMCE + DataTables + large `detail-main.js` on first paint |
| Client notes / matter tasks | Detail tabs | **OK** | **Optimized** | Paginated services + Load more; modules lazy on tab visit |
| Documents (all tabs) | Detail tabs + preview | **OK** / **Interaction-slow** | **Partial** | Folder caps + queued uploads; preview convert is CPU/S3-bound |
| Accounts / billing | Detail tab + ledger pages | **OK** / **Interaction-slow** | **Optimized** | Lazy account HTML; row caps; PDF/email gen can raise `set_time_limit(300)` |
| Emails (Outlook UI) | CRM emails routes | **Slow-risk** | **Partial** | Lean list JSON + body-on-demand; **335 KB** `outlook_emails.js` + **174 KB** CSS on first load |
| Leads | `/leads`, detail, create/edit | **OK** | **Optimized** | Paginate + cached form data; TinyMCE on index |
| Tasks / assignee | `/tasks`, assign lists | **OK** | **Optimized** | Spatie + `tasks-spa.js` infinite scroll (no DataTables) |
| Booking / calendar | Appointments list + calendar | **Slow-risk** | **Partial** | Calendar JSON OK; **list still Yajra DataTables**; FullCalendar on calendar/dashboard |
| Office visits | Waiting / attending / completed | **OK** | **Partial** | Paginated; TinyMCE on index |
| Legal forms | Client tab + admin | **OK** | **Optimized** | Paginated AJAX list; HTML preview disk cache; AI queued |
| E-signatures | Signature dashboard / admin | **OK** | **Optimized** | Paginate 20; audit export may load docs in memory (export only) |
| Access grants | Grants UI | **Fast** | **Optimized** | Paginate + cached global counts |
| Admin Console (lists) | Matter/staff/types/workflows/… | **OK** | **Partial** | Paginated; several scripts still `?v={{ time() }}` |
| Staff login analytics | `/staff-login-analytics` | **OK** | **Optimized** | Chart data via AJAX endpoints |
| Front desk / check-in | Check-in flows | **OK** | **Needs runtime verify** | Layout shell only at rest; live poll endpoints separate |
| Notifications / broadcasts | Header + history | **OK** | **Optimized** | Infinite / AJAX history patterns |
| Python services | Preview/PDF/email parse | **Interaction-slow** | **Optimized** | Thread pool + TTL cache; still blocking CPU for convert |
| IMAP / inbox sync | Background + UI poll | **OK** (UI) | **Optimized** | Queued job; metadata-first parse |

---

## Scorecard — key pages (first load)

| Page / route | Layout | Extra page assets (order-of-magnitude) | Speed | Notes |
|--------------|--------|------------------------------------------|-------|-------|
| Login | `crm-login` | Small CSS | **Fast** | Outside main shell |
| Dashboard | `crm_client_detail_dashboard` | `dashboard.css/js`, calendar (~90 KB), FullCalendar Vite CSS | **OK** | Refresh without full reload |
| Clients index | `crm_client_detail` | Infinite scroll JS; **TinyMCE ~471 KB** | **OK→Slow-risk** | List is lean; editor tax unnecessary for browse |
| Client detail | same | `client-detail.css` ~276 KB, `detail-main.js` ~210 KB, TinyMCE, DataTables ~97 KB, many modules | **Slow-risk** | Lazy tabs help HTML; JS still front-loaded for several modules |
| Outlook emails | same | `outlook_emails.js` ~335 KB, CSS ~174 KB | **Slow-risk** | Backend list optimized; frontend bundle dominates |
| Legacy emails UI | same | `emails.js` ~136 KB | **OK** | Lighter than Outlook |
| Leads index | same | TinyMCE | **OK→Slow-risk** | Same editor tax |
| Tasks SPA | same | `tasks-spa.js` | **OK** | No DataTables |
| Booking appointments list | same | DataTables consumer + Yajra endpoint | **Slow-risk** | Last major Yajra surface |
| Booking calendar | same | FullCalendar | **Slow-risk** | Expected for calendar UX |
| Office visits | same | TinyMCE | **OK→Slow-risk** | |
| Accounts ledger lists | same | Modest | **OK** | Paginated |
| Legal forms (tab) | (inside detail) | Small AJAX | **OK** | Deferred until tab |
| Admin Console indexes | same | Small admin JS; some `time()` bust | **OK** | Re-fetch scripts every hit where `time()` used |
| Document preview embed | AJAX/iframe | LibreOffice/Python or PhpWord | **Interaction-slow** | Not first paint of detail page |

---

## 1. Global shell (affects every module page)

**Layouts:** `resources/views/layouts/crm_client_detail.blade.php`, `crm_client_detail_dashboard.blade.php`

**Always loaded (local, approximate uncompressed):**

| Asset | ~KB |
|-------|-----|
| `css/style.css` | 451 |
| `css/crm-theme.css` | 296 |
| `js/app.min.js` | 257 |
| `css/components.css` | 155 |
| `js/custom-form-validation.js` | 136 |
| `js/jquery.min.js` | 85 |
| `css/custom.css` | 66 |
| `js/tom-select.complete.min.js` | 51 |
| `js/intlTelInput.js` | 48 |
| `js/scripts.js` | 41 |
| `js/email-upload-filename.js` | 39 |
| `js/ts-init.js` | 31 |
| Other (flatpickr helpers, broadcasts, modal-ui, etc.) | ~50+ |
| **Subtotal (sampled shell)** | **~1,762 KB** |

Plus Bootstrap 5 (component includes), Font Awesome, SweetAlert2, Flatpickr, and (on many pages) Vite `resources/js/app.js`.

**Speed impact:** First paint and TTI on “simple” pages (e.g. Admin Console matter list) are still shell-bound. Gzip/Brotli on the web server helps transfer size; parse cost remains.

**Opt. status:** **Partial** — theme/modal assets use `filemtime`; core `style.css` / `app.min.js` / jQuery have no content-hash versioning in layout.

---

## 2. Dashboard

| Aspect | Status |
|--------|--------|
| Server | **Optimized** — `DashboardService`, eager loads, `Cache::remember` KPIs (`config/crm.php` → `dashboard.kpi_cache_seconds`, default 300s), list `list_per_page` default 10 |
| UX refresh | **Optimized** — `GET /dashboard/summary` avoids full reload |
| Frontend | **OK** — extracted CSS/JS + `filemtime` bust; FullCalendar Vite CSS + `dashboard-calendar.js` (~90 KB) add calendar weight |
| Speed rating | **OK** |

---

## 3. Clients / Matters

| Aspect | Status |
|--------|--------|
| List HTML | **Optimized** — paginate + infinite scroll |
| Detail SSR | **Partial** — active tab only; others via `/clients/detail-tab/{id}/{tab}` |
| Detail JS | **Slow-risk** — `detail-main.js` (~210 KB), `client-detail.css` (~276 KB), TinyMCE + DataTables required at top of `detail.blade.php` |
| Lazy modules | **Optimized** — `tab-lazy-load.js` + `lazyModuleScripts` for notes/docs/accounts/etc.; appointments still eager |
| Speed rating | List **OK**; Detail **Slow-risk** |

---

## 4. Documents

| Aspect | Status |
|--------|--------|
| Folder listing | **Optimized** — capped lean rows via `ClientDocumentFolderListService` |
| Upload | **Optimized** — queued jobs + status poll (videos and bulk files) |
| Preview | **Interaction-slow** — S3 fetch + LibreOffice/Python PDF or PhpWord/LegacyDoc HTML on `embed=1` |
| Speed rating | Browse **OK**; open/preview **Interaction-slow** |

---

## 5. Accounts / Billing

| Aspect | Status |
|--------|--------|
| Tab shell | **Optimized** — lazy AJAX ledger HTML; display row caps |
| List pages | **Optimized** — paginate; cached filter dropdowns |
| PDF / Hubdoc / heavy export | **Interaction-slow** — `ClientAccountsController` uses `set_time_limit(300)` on heavy paths |
| Speed rating | Browse **OK**; generate **Interaction-slow** |

---

## 6. Emails

| Aspect | Status |
|--------|--------|
| List API | **Optimized** — lean columns, paginate, body on demand |
| Sync | **Optimized** — queued; UI polls status |
| Outlook page weight | **Slow-risk** — `outlook_emails.js` ~335 KB + CSS ~174 KB |
| Upload | **Interaction-slow** — `EmailUploadController` `set_time_limit(180)` |
| Cache bust | **Optimized** — `filemtime` on main Outlook assets |
| Speed rating | **Slow-risk** (first load) / **OK** (subsequent list fetch) |

---

## 7. Leads / Tasks / Office visits

| Surface | Speed | Opt. |
|---------|-------|------|
| Leads index/detail | **OK** (TinyMCE tax on index) | **Optimized** backend (`LeadFormDataService` cache) |
| Tasks SPA | **OK** | **Optimized** (Spatie infinite; DataTables removed) |
| Office visits lists | **OK** | **Partial** (paginate + TinyMCE include) |

---

## 8. Booking / Calendar

| Aspect | Status |
|--------|--------|
| Calendar feed | **OK** — dedicated JSON services |
| Appointments **list** | **Slow-risk** — still `DataTables::of($query)` in `BookingAppointmentsController` |
| Migration note | Prior optimization review: migrate list → Spatie + Alpine; calendar already non-DataTables |
| Speed rating | Calendar **Slow-risk** (lib size expected); List **Slow-risk** (Yajra remaining) |

---

## 9. Legal Forms / E-sign / Access / Admin

| Module | Speed | Opt. |
|--------|-------|------|
| Legal Forms UI | **OK** | **Optimized** — paginate, preview cache, AI job poll |
| E-signature dashboard | **OK** | **Optimized** — paginate 20 |
| Access grants | **Fast** | **Optimized** |
| Admin Console CRUD lists | **OK** | **Partial** — paginate; **`?v={{ time() }}`** on several index scripts (see below) |

---

## 10. Asset cache-busting (speed-relevant leftovers)

Stable busting (`filemtime`) is used on dashboard, client detail, Outlook, layout theme. These still force **new URL every request** (`?v={{ time() }}`):

| File | Lines (approx.) |
|------|-----------------|
| `resources/views/crm/clients/edit.blade.php` | CSS + other-party / matter-assignee scripts |
| `resources/views/crm/clients/company_edit.blade.php` | other-party / matter-assignee scripts |
| `resources/views/AdminConsole/features/matter/index.blade.php` | `matter.js` |
| `resources/views/AdminConsole/features/documentchecklist/index.blade.php` | `document-checklist.js` |
| `resources/views/AdminConsole/staff/index.blade.php` | `staff.js` |
| `resources/views/AdminConsole/features/matterdocumenttype/index.blade.php` | `matter-document-type.js` |
| `resources/views/AdminConsole/features/personaldocumenttype/index.blade.php` | `personal-document-type.js` |
| `resources/views/AdminConsole/system/roles/index.blade.php` | `roles.js` |

**Opt. status:** **Not optimized** on those pages only.

---

## 11. Heavy libraries by page include

| Library | ~Size | Loaded when |
|---------|-------|-------------|
| TinyMCE `tinymce.min.js` | ~471 KB | `require-tinymce` — client detail, clients index, leads index, office visits, assignee pages, staff index, several Admin email-template pages |
| DataTables | ~97 KB + CSS | Client detail (`require-datatables`); Booking list AJAX consumer |
| FullCalendar (Vite) | (bundled) | Dashboard + booking calendar |
| Chart.js UMD | ~204 KB | Analytics / chart pages when referenced |
| `custom-form-validation.js` | ~136 KB | **Every** main-layout page |

---

## 12. Cross-cutting speed patterns

| Pattern | Speed status |
|---------|--------------|
| Pagination / infinite scroll | **Strong** on clients, leads, dashboard, emails, notes, matter tasks, legal forms, tasks SPA |
| Server caching | **Strong** on dashboard KPIs, access-grant counts, lead/admin form dropdowns, email folder caches |
| Job queues for slow work | **Strong** for inbox sync, video/doc upload, legal-form AI |
| Frontend code-split | **Partial** — client tabs lazy; emails/detail still large monoliths |
| Cache busting | **Mostly good**; Admin/edit `time()` pockets remain |
| Global CSS/JS budget | **Weak** — ~1.7 MB shell before page content |
| DataTables / Yajra | **Mostly retired**; booking list + client checklist remain |
| On-demand CPU (Office/PDF) | **Expected slow**; mitigated by queue/LibreOffice where configured |

---

## Highest-impact speed opportunities (advisory — not applied)

1. **Shrink or split the global shell** — defer `custom-form-validation.js`, intl-tel, and unused CSS on pages that do not need them; prefer hashed build assets over raw `style.css` + `app.min.js` on every request.
2. **Stop loading TinyMCE on list-only pages** — load only when compose/modal opens (clients/leads/office/assignee/staff indexes).
3. **Continue splitting `outlook_emails.js` / `detail-main.js`** — reduce first-byte parse on the two heaviest staff workflows.
4. **Replace booking appointments DataTables** with Spatie + infinite/Alpine (same pattern as tasks).
5. **Replace remaining `?v={{ time() }}`** with `filemtime` on Admin Console + client edit.
6. **Client detail:** drop DataTables once checklist is non-DT; keep TinyMCE behind compose interaction if possible.
7. **Measure in browser** (Lighthouse / DevTools Network on staging) to turn this static map into real TTFB / LCP numbers — not done in this audit.

---

## How to use this doc

- Ratings are **relative speed / first-load cost**, not functional correctness.
- Pair with [MODULE_OPTIMIZATION_REVIEW.md](MODULE_OPTIMIZATION_REVIEW.md) for query/N+1 detail and with [MODULE_BREAKPOINT_AUDIT_2026-09-17.md](MODULE_BREAKPOINT_AUDIT_2026-09-17.md) for broken features.
- Re-run after large JS extractions or shell changes; update the scorecards.
- **No fixes were applied** as part of this audit.
