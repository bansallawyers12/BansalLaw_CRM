# UI regression tracking and full-application testing guide

**Purpose:** How to find, record, and prevent UI-breaking issues across Bansal Law CRM (panels that flash and close, clipped controls, duplicate actions, modals that won’t dismiss, layout breaks after lazy tab load, etc.).

**Scope:** Staff CRM UI (client detail, email, documents, tasks, billing, admin). Complements static audits in `docs/UI_FUNCTIONALITY_AUDIT.md` and security items in `cmr-bugs.md`.

**Action:** Documentation only — no code changes required to use this guide.

---

## 1. Common UI failure patterns (what to look for)

These patterns already caused real regressions in this codebase. Use them when triaging bugs and when reviewing new JS/CSS.

| Pattern | Typical symptom | Common cause in this app | Where it showed up |
|--------|------------------|---------------------------|-------------------|
| **Double-bound events** | Control toggles open then immediately closes; action runs twice (e.g. two tasks created) | Inline tab scripts re-run via `ClientTabLazy` + `$.globalEval`, or SSR script + lazy load, without `.off()` / guard flag | Personal/Matter **Bulk Upload**; pattern documented in `matter-tasks.js` (`__cdnMatterTasksModuleBound`) |
| **`:visible` / animation race** | Panel opens for one frame then hides | Toggle logic uses jQuery `:visible` while `slideDown` / `slideToggle` is running; second handler thinks state is “open” | Bulk upload dropzones |
| **`overflow: hidden` clipping** | Button or icon “missing”; only a sliver on the edge | Fixed-height flex panes (`checklist-table-container`, `outlook-list-pane`) + child at `width: 100%` pushing siblings off-screen | Client **Emails** filter beside search |
| **Wrong DOM scope** | Works on one tab, broken on another; wrong folder/category | Global selectors (`$('#bulk-upload-1')`) when multiple panes exist; need `#personaldocuments-tab` / `#matterdocuments-tab` scope | Documents bulk upload |
| **Lazy tab first visit vs direct URL** | Bug only when opening tab from sidebar, or only when landing on deep link | Active tab SSR’d vs `_lazy_tab_shell` + `/clients/detail-tab/{slug}` | All client detail tabs |
| **Bootstrap 4 → 5 API drift** | Modal won’t close, dropdown dead, `data-toggle` ignored | Mixed `data-bs-*` and legacy attributes | Modals, popovers (see `npm run verify:bootstrap-compat`) |
| **Z-index / portal** | Click X on modal does nothing; menu clipped | Backdrop above popover; context menu inside `overflow:hidden` pane | Update Task popover, document context menus |
| **Stale cache** | “Fix deployed” but UI unchanged | Browser cache on `public/js/*.js` and `public/css/*.css` (`?v=filemtime` helps but hard refresh still needed) | After every deploy |

When logging a bug, tag it with one or more pattern IDs above (e.g. `PATTERN:double-bound`, `PATTERN:overflow-clip`) so repeats are easy to search in GitHub issues or your backlog.

---

## 2. How to track issues (lightweight process)

### 2.1 Single source of truth

Pick one tracker (GitHub Issues, Jira, or a section in `docs/IMPROVEMENT_BACKLOG_*.md`) and use a **fixed template** for every UI defect:

```text
Title: [Area] Short symptom — e.g. [Client Emails] Filter icon hidden off list pane

Environment: local | staging | production
URL path: /clients/detail/.../emails
Browser: Chrome 14x / Edge / Firefox
Entry path: (A) direct URL to tab  (B) sidebar click after another tab  (C) hard refresh  (D) back button

Steps to reproduce:
1.
2.

Expected:
Actual:

Pattern tags: double-bound | overflow-clip | lazy-tab | modal-zindex | ...

Console errors: (paste or screenshot)
Network failures: (4xx/5xx on XHR)

Screenshot / screen recording: (attach)

Regression?: yes — commit or deploy before break
```

### 2.2 Severity (for prioritisation)

| Level | Rule of thumb |
|-------|----------------|
| **P0** | Cannot complete money/client work (send email, upload document, invoice, sign) |
| **P1** | Feature broken but workaround exists |
| **P2** | Layout/a11y degradation, wrong label, duplicate API call without user-visible corruption |
| **P3** | Cosmetic only |

### 2.3 Regression log after each release

After pushing `master` / `production`, record:

- Deploy date and commit SHA
- Smoke checklist section completed (see §4) — pass/fail per area
- Any new console errors on key pages (§3.3)

Keep the last 5 deploy rows in the tracker or a `docs/RELEASE_SMOKE_LOG.md` (optional).

---

## 3. Developer checks before merge (no full QA substitute)

### 3.1 Code review checklist (UI-related)

- [ ] New `$(document).on('click', ...)` on a **class selector** used in client detail: is there a **namespace** (`.off('click.foo')` before `.on`) or **`window.__…Bound` guard**?
- [ ] Tab-specific logic scoped to `#…-tab` or `#…-tab .selector`, not document-wide IDs.
- [ ] Toggle state: prefer **`data-*` or `aria-expanded`**, not `:visible` alone during animations.
- [ ] Flex layouts: children that share a row must use **`flex: 1; min-width: 0`**, not `width: 100%` + extra fixed-width siblings.
- [ ] Modals/popovers: close control tested with keyboard and click; z-index above backdrop.
- [ ] If Blade tab includes inline `<script>`, assume it may run **once per lazy load** — see `public/js/crm/clients/tab-lazy-load.js`.

### 3.2 Local commands (automated smoke, not E2E)

From project root (after `npm install` where needed):

| Command | What it catches |
|---------|------------------|
| `npm run verify:bootstrap-compat` | jQuery/Bootstrap 5 plugin and `data-bs-*` migration issues |
| `npm run verify:bootstrap5` | Bootstrap 5 bundle sanity |
| `php artisan test` | PHP/API regressions (not UI layout) |
| `php artisan route:list` | Broken or missing named routes used in JS `ClientDetailConfig.urls` |

There is **no** Playwright/Cypress suite in-repo today; full UI coverage is **manual + checklist** (§4) unless you add E2E later.

### 3.3 Browser DevTools (every UI bug report)

1. **Console** — red errors on load and on click (filter: Errors only).
2. **Network** — failed XHR/fetch when reproducing (403 CSRF, 419 session, 500).
3. **Elements** — inspect clipped control; check `overflow`, `display`, `visibility`, off-screen `transform`.
4. **Event Listeners** (Chrome) — on toggle button, count duplicate `click` handlers (symptom of double-bind).

### 3.4 Lazy-tab reproduction matrix (mandatory for client detail changes)

For any change under `resources/views/crm/clients/tabs/` or `public/js/crm/clients/`:

| # | Scenario | Action |
|---|----------|--------|
| L1 | Deep link | Open client URL with `?tab=` or path ending in `/emails`, `/personaldocuments`, etc. Hard refresh (Ctrl+F5). |
| L2 | Sidebar path | Open client on **Overview**, then click **Emails**, **Documents**, **Tasks**, **Billing** in order. |
| L3 | Revisit | On same client, leave tab and return **twice** (e.g. Documents → Tasks → Documents → Tasks). |
| L4 | Sub-tabs | Within Documents: switch folder sub-tabs (General / other folders); repeat Bulk Upload or upload zones. |
| L5 | Matter switch | Change matter dropdown (if visible); re-test the same UI. |

Fail if any toggle “flashes”, control is clipped, or action fires twice.

---

## 4. Full-application UI smoke checklist (manual)

Use **staging** or **production** with a **non-production test client/matter** where possible.  
**Time:** ~45–90 minutes for full pass; ~15 minutes for **critical path** (bold rows).

Always: **Ctrl+F5** once per area; test at **1280px** and **~768px** width.

### 4.1 Auth and shell

| ✓ | Area | Steps |
|---|------|--------|
| | Login / logout | Login as staff; logout; session cleared |
| | Top navigation | Open Dashboard, Clients, Mail (each loads without console errors) |
| | Notifications | Open notification panel; scroll/load more if present |

### 4.2 Dashboard

| ✓ | Area | Steps |
|---|------|--------|
| **|** | Tasks | Add task (header + empty state if applicable); complete task; modal/popover closes |
| | Calendar / widgets | Open any linked calendar or booking widget |

### 4.3 Clients — list and create

| ✓ | Area | Steps |
|---|------|--------|
| | Client list | Search, filter, pagination |
| | Add client | Open add modal; required validation; save or cancel |

### 4.4 Clients — detail tabs (use L1–L5 matrix)

| ✓ | Tab | Steps |
|---|-----|--------|
| **|** | Overview / Personal details | Edit field; save; no duplicate submits |
| **|** | Tasks | Add task; update task; **close (X) and save** on update popover |
| | Notes | Add note; edit if available |
| **|** | **Personal documents** | **Bulk Upload** open/close; single-file upload; preview file |
| **|** | **Matter documents** | Same as personal (matter-scoped) |
| | Not used documents | Move/restore if permitted |
| **|** | **Emails** | Search; **filter** toggle; folder/label filter; open email; attachments load |
| | Billing / Account | Ledger, invoice list (view only if no test data) |
| | Legal forms | Open list; generate if test matter allows |
| | Activity / Timeline | Filter bar; expand entries |

### 4.5 Mail (global)

| ✓ | Area | Steps |
|---|------|--------|
| **|** | Unassigned / assigned inbox | Folder tabs; filters; open message; assign flow if used |
| | Compose | Open compose; cancel |

### 4.6 Admin / settings (if role allows)

| ✓ | Area | Steps |
|---|------|--------|
| | Staff, roles | List loads |
| | Email templates / workflow | One edit screen opens without JS error |

### 4.7 Public / edge surfaces (optional)

| ✓ | Area | Steps |
|---|------|--------|
| | Client signing link | Open test envelope; sign or decline |
| | Booking | Public booking page loads |

**Sign-off row:** Tester name, date, commit SHA, browser, pass/fail notes.

---

## 5. Local in-app checklist (Manual UI Test Suite)

When `APP_ENV=local`, staff can open:

**`/system-errors?tab=ui_tests`**

(or **System Breakdown & Logs Monitor** → tab **Manual UI Test Suite** / header button **UI Test Suite**).

| Feature | Detail |
|---------|--------|
| Availability | **Local only** — returns 404 on staging/production |
| Progress | Saved in browser `localStorage` per machine |
| Report | **Copy report** button for pass/fail summary |
| Config | `config/ui_manual_tests.php` — add/edit sections and steps |
| Client deep links | Set `UI_MANUAL_TEST_CLIENT_DETAIL_URL` in `.env` to a test client URL (e.g. ending in `/personaldocuments`) so **Open page** links work for client-tab items |

This does **not** run automated browser tests; it tracks manual execution of the same flows as §4.

---

## 6. “Complete application” — what is realistic

| Approach | Coverage | Effort |
|----------|----------|--------|
| **§4 smoke checklist** / **§5 UI Test Suite** | High-value paths; catches most UI breaks users hit daily | Low–medium per release |
| **§3.3 + L1–L5** on every client-detail JS/Blade change | Targeted regression prevention | Medium per PR |
| **Static audits** (`UI_FUNCTIONALITY_AUDIT.md`, graphify queries) | Dead code, route mismatches, architecture | Periodic |
| **E2E (Playwright/Cypress)** | Repeatable full UI; best for critical paths | High setup; not in repo yet |
| **Visual regression (Percy, Chromatic)** | CSS/layout drift | Medium; needs baseline screenshots |

**Recommendation:** Treat §4 as the **release gate**; add §3.1 review for any PR touching `detail-main.js`, `outlook_emails.js`, client tab Blade, or `client-detail.css` / `outlook_emails.css`. Add E2E later for 5–10 scripts: login, client documents bulk upload, client emails filter, task update modal close.

---

## 7. Key files to know (orientation)

| Topic | Location |
|-------|----------|
| Lazy tab HTML load | `public/js/crm/clients/tab-lazy-load.js`, `resources/views/crm/clients/tabs/_lazy_tab_shell.blade.php` |
| Client tab navigation | `public/js/crm/clients/sidebar-tabs.js` |
| Document layout / viewport | `public/js/crm/clients/utils/dom-helpers.js` (`adjustClientDocumentsPanelHeight`) |
| Client documents CSS | `public/css/client-detail.css` |
| Client emails UI | `public/js/outlook_emails.js`, `public/css/outlook_emails.css`, `resources/views/crm/emails_outlook.blade.php` |
| Bulk upload (personal/matter) | `resources/views/crm/clients/tabs/personal_documents.blade.php`, `matter_documents.blade.php` |
| Manual UI Test Suite (local) | `config/ui_manual_tests.php`, `app/Support/UiManualTestCatalog.php`, `resources/views/system_breakdown/partials/ui_manual_tests_tab.blade.php` |
| Guard pattern example | `public/js/crm/clients/modules/matter-tasks.js` (`__cdnMatterTasksModuleBound`) |
| Bootstrap compat verify | `scripts/verify-bootstrap-compat.cjs`, `package.json` scripts |

Use **graphify** for cross-file questions, e.g. `graphify query "ClientTabLazy executeScripts globalEval"` or `graphify explain "bulk upload personal documents"`.

---

## 8. Quick triage flowchart

```text
User reports UI bug
        │
        ▼
Can reproduce on hard refresh? ──no──► Clear cache / try incognito / check deploy SHA
        │ yes
        ▼
Console errors? ──yes──► Fix JS/network first; retest UI
        │ no
        ▼
Only after sidebar navigation? ──yes──► Run L2–L5; suspect double-bind or lazy script
        │ no
        ▼
Control partially visible? ──yes──► Inspect overflow/flex/width in Elements panel
        │ no
        ▼
Modal/popover? ──yes──► z-index, backdrop, Bootstrap data API
        │ no
        ▼
Log issue with template (§2.1) + pattern tags; link PR if regression
```

---

## 9. Related documents

- `docs/UI_FUNCTIONALITY_AUDIT.md` — module-by-module static audit (2026-09)
- `docs/UI_AND_FUNCTIONALITY_BREAK_AUDIT_2026-09-28.md` — break-point oriented audit
- `docs/BREAKING_POINTS_AND_DEFAULT_ALERTS_AUDIT_2026-09-30.md` — alerts and breaking points
- `cmr-bugs.md` — security and data-path issues (not UI-only)

---

*Last updated: 2026-10-07. Maintainers: extend §4 when new major tabs or modules ship.*
