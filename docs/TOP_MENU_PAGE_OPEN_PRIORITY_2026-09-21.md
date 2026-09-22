# Top Menu Bar — Page Open Speed Priority (Decision Doc)

**Date:** 2026-09-21  
**Problem reported:** Top menu bar feels too slow to open / navigate.  
**Scope:** Staff CRM top bar (`main-topbar` in `header_client_detail.blade.php`) and every module page reachable from it.  
**Action:** **Documentation only — do not fix / do not apply.**  
**Companion:** [MODULE_PAGE_SPEED_AUDIT_2026-09-18.md](MODULE_PAGE_SPEED_AUDIT_2026-09-18.md) (broader first-load asset map).

---

## How to read this doc

Use this to decide **what to optimize first**. There are two separate delays:

| Layer | What the user feels | Where it lives |
|-------|---------------------|----------------|
| **A. Top bar itself** | Every CRM page is slow before content; dropdowns / badge area feel sticky | Server work inside `resources/views/Elements/CRM/header_client_detail.blade.php` on **every** authenticated layout render |
| **B. Destination pages** | After clicking a top-bar item, the next page takes long to appear | Route → controller → page JS/CSS for that module |

**Priority key**

| Priority | Meaning | Decision guidance |
|----------|---------|-------------------|
| **P0 — Critical** | Hits every (or most) page loads, or is a daily high-traffic destination that is clearly heavy | Do first; largest shared win |
| **P1 — High** | Frequent staff path; known Slow-risk shell or heavy libs | Next after P0 |
| **P2 — Medium** | Significant on some roles/days; backend mostly OK but frontend tax remains | Batch after P0/P1 |
| **P3 — Low** | Already lean, rare path, or interaction-only (not first open) | Defer unless complaints are specific |
| **Verify** | Needs browser timing (TTFB / LCP) on staging before ranking higher | Measure, then promote |

**Speed status** (same spirit as the 2026-09-18 audit)

| Status | Meaning |
|--------|---------|
| **Fast** | Lean path |
| **OK** | Acceptable for CRM |
| **Slow-risk** | Likely to feel slow on open |
| **Shell-tax** | Cost paid on *all* pages because of shared layout / top bar |
| **Interaction-slow** | Page opens OK; a later action is slow |

---

## Executive verdict

1. **Layer A is the most likely root of “top menu takes too long.”** The header runs **multiple DB queries and notification work on every CRM page**, not only when a menu is clicked.
2. **Layer B** then adds destination cost: Client detail, Outlook/emails-style surfaces, Booking list/calendar, and Analytics are the heaviest opens from the top bar.
3. Shared layout still adds ~**1.7 MB** local CSS/JS on every page (see companion audit) — that tax sits under both layers.
4. **No code changes were made** for this document.

---

## Part A — Top bar cost (shared by every module page)

**File:** `resources/views/Elements/CRM/header_client_detail.blade.php`  
**Included from:** `layouts/crm_client_detail.blade.php`, `layouts/crm_client_detail_dashboard.blade.php`

### A1. Work that runs on (almost) every page render

| # | Work in header | Cache today? | Est. impact | Priority |
|---|----------------|--------------|-------------|----------|
| 1 | `BookingAppointment::where(status=pending, is_paid=1)->count()` for bookings badge | **No** | Extra COUNT every page | **P0** |
| 2 | `StaffPersonalCalendarFeedService::staffFilterOptions()` to build booking calendar dropdown | **No** | Staff list + per-row calendar type mapping every page | **P0** |
| 3 | Notifications block: `reassertUnreadForReceiver()` (load up to 50 + possible writes), then `pendingAlertsForStaff()` (**calls reassert again**), unread `Notification` COUNT, then last 8 notifications | **No** (write path on render) | Highest header risk — DB read + possible UPDATEs every page | **P0** |
| 4 | `IncomingEmailSyncService::countUnassignedSyncedInboxMail()` for inbox badge | **No** | COUNT on `email_logs` with visibility filters (when inbox sync nav allowed) | **P0** / **P1** by role volume |
| 5 | `DashboardService::getPendingOpenTaskCount()` for tasks badge | **Yes** — `Cache::remember` 60s | Moderate; already mitigated | **P2** |
| 6 | Permission / route-active PHP (`canAccessAdminConsole`, inbox flags, nav active map) | N/A | Cheap | **P3** |
| 7 | Center search Tom Select init (`js-data-example-ajaxccsearch`) | Client-side | Init cost each page; search itself AJAX (OK) | **P2** (init) / **P3** (query) |
| 8 | Global shell CSS/JS under the same layouts | Partial `filemtime` | ~1.7 MB parse/transfer tax every navigation | **P0** (shared with all modules) |

### A2. What “slow to open” often means here

| Symptom | Likely layer |
|---------|--------------|
| Every page feels slow even for “simple” Admin / list screens | **A** (header queries + shell) |
| Clicking Bookings / Clients / Emails feels slow *after* click | **B** (destination) |
| Bell / badges / booking dropdown feel delayed | **A** items 1–4 |
| Mobile “ellipsis” topbar toggle feels laggy | Mostly CSS/JS + shell; measure; may be **A** + shell |

### A3. Layer A — recommended decision order (advisory)

1. **P0:** Stop or cache booking pending COUNT; do not rebuild full staff calendar options on every SSR.  
2. **P0:** Move notification reassert + recent list off the critical path (defer / AJAX / cache); avoid double `reassertUnreadForReceiver`.  
3. **P0:** Cache or AJAX the unassigned-mail COUNT (same pattern as tasks badge refresh).  
4. **P0 / parallel:** Shrink or defer global shell assets (companion audit §1 / §11).  
5. **P2:** Keep task badge on cache; optionally SSR placeholder + existing `refreshCrmNavPendingTaskCount` only.

---

## Part B — Top menu destinations (priority to decide what to open-optimize)

Routes below are the primary targets from the top bar. Ratings combine companion audit + header destination risk.

### B1. Scorecard — menu → page open

| Top-bar entry | Primary route(s) | Open speed | Opt. status | Priority | Why it ranks here |
|---------------|------------------|------------|-------------|----------|-------------------|
| **(Shared) Top bar + layout** | All CRM pages using header | **Shell-tax** | Partial | **P0** | Header DB work + ~1.7 MB shell on every navigation |
| **Client detail** *(via search or Client List → open)* | `/clients/detail/{id}` | **Slow-risk** | Partial | **P0** | TinyMCE + DataTables + `detail-main.js` (~210 KB) + large CSS on first paint |
| **Website Bookings — All Bookings** | `booking.appointments.index` | **Slow-risk** | Partial | **P0** | Still Yajra DataTables list path; header also preloads booking staff list |
| **Booking calendars** (Ajay / Michael / staff) | `booking.appointments.calendar*` | **Slow-risk** | Partial | **P1** | FullCalendar expected weight; heavy but intentional UX |
| **Outlook / heavy email UIs** *(if used from Clients / inbox paths)* | CRM emails / unassigned | **Slow-risk** | Partial | **P1** | Large JS/CSS bundles (~335 KB + ~174 KB Outlook) |
| **Analytics Dashboard** (Accounts dropdown, adminish) | `clients.analytics-dashboard` | **Slow-risk** | Partial | **P1** | Chart.js-class weight when loaded |
| **Clients — Client List** | `clients.index` | **OK → Slow-risk** | Optimized list / Partial FE | **P1** | Infinite scroll OK; TinyMCE still pulled on list |
| **Clients — Matter List** | `clients.clientsmatterslist` | **OK** | Optimized | **P2** | Paginate / infinite patterns |
| **Leads — Lead List** | `leads.index` | **OK → Slow-risk** | Optimized BE | **P1** | TinyMCE on index |
| **Leads — create / other party** | `leads.create` | **OK** | Optimized form cache | **P2** | Form-data caching helps |
| **Dashboard** | `dashboard` | **OK** | Optimized | **P2** | KPI cache; calendar adds weight but refresh is AJAX-friendly |
| **My Calendar** (icon) | staff personal calendar | **OK → Slow-risk** | Partial | **P2** | Calendar lib; verify under load |
| **Signature Dashboard** | `signatures.index` | **OK** | Optimized | **P3** | Paginate 20 |
| **In Person (Office visits)** | `office-visits.waiting` | **OK → Slow-risk** | Partial | **P2** | Paginated; TinyMCE include tax |
| **Front-Desk Check-In** | `front-desk.checkin.index` | **OK** | Verify | **Verify → P2** | Shell + live poll; measure on busy desk |
| **Tasks** | `assignee.tasks` | **OK** | Optimized | **P3** | SPA / infinite; no DataTables |
| **Unassigned Mail** | `clients.unassigned-emails` | **OK → Slow-risk** | Partial | **P1** | Depends on inbox size + same shell; badge COUNT is Layer A |
| **Accounts — Invoice / receipts lists** | invoice / receipt list routes | **OK** | Optimized | **P2** | Paginated; PDF generate is interaction-slow later |
| **Smart Email Import** | `emails.smart-import.index` | **OK** | Verify | **Verify** | Confirm under real import volume |
| **Communication Check** | `communication-check.index` | **OK** | Verify | **Verify** | Feature-flagged; measure if enabled |
| **Other Parties list** | `leads.other_parties.index` | **OK** | Optimized | **P3** | |
| **Booking Sync Status** | `booking.sync.dashboard` | **OK** | Verify | **P3** | Adminish / infrequent |
| **Admin Console** (profile menu) | `adminconsole.features.matter.index` etc. | **OK** | Partial | **P2** | Lists OK; some `?v={{ time() }}` cache-bust leftovers |
| **Profile / logout** | `my_profile`, logout | **Fast / OK** | — | **P3** | |
| **Document preview / Office→PDF** *(from detail, not top icon)* | embed / convert | **Interaction-slow** | Optimized queue where used | **P3** for “menu open”; **P1** if staff mean “opening docs” | Not first paint of top bar |

### B2. Priority backlog (decision order for destination pages)

Do **after or in parallel with Layer A**:

| Order | Priority | Target | Decision question |
|-------|----------|--------|-------------------|
| 1 | **P0** | Shared top bar + shell | Does TTFB drop if header queries are deferred/cached? |
| 2 | **P0** | Client detail first paint | Can TinyMCE / DataTables / detail-main load after first tab? |
| 3 | **P0** | Booking appointments **list** | Migrate off Yajra DataTables (same pattern as Tasks SPA)? |
| 4 | **P1** | Clients / Leads / Office list TinyMCE | Load editor only when compose/modal opens? |
| 5 | **P1** | Outlook / unassigned mail bundles | Split or defer non-critical JS/CSS? |
| 6 | **P1** | Booking calendars / Analytics | Acceptable lib cost, or lazy-init calendar/charts? |
| 7 | **P2** | Admin `time()` cache-bust, Accounts PDF paths, Front desk measure | Batch / verify |
| 8 | **P3** | Signatures, Tasks, Profile, rare admin links | Leave unless new complaints |

---

## Part C — Quick map: top-bar UI → code → cost type

```
main-topbar (header_client_detail.blade.php)
├── Left icons / dropdowns
│   ├── Dashboard, Signatures, Office, Front desk, Tasks     → mostly Layer B page cost
│   ├── Bookings badge COUNT + staffFilterOptions()          → Layer A P0 (every page)
│   ├── Clients / Leads / Accounts dropdowns                 → Layer B on click
│   └── Unassigned mail COUNT                                → Layer A P0/P1
├── Center global search (Tom Select)                        → Layer A init P2; AJAX OK
└── Right
    ├── Notifications (reassert + alerts + unread + latest 8) → Layer A P0
    └── Profile / Admin Console                              → Layer B mostly OK
```

---

## Part D — Suggested measurement plan (before coding)

Run on staging with a normal staff account and a Super Admin account:

1. **Network → document** for a “simple” page (e.g. Admin matter list or Tasks): note **TTFB** and total transfer.  
2. Temporarily compare (locally only, do not ship) a request with header badge queries stubbed vs full header — confirms Layer A share.  
3. Click each **P0/P1** top-bar item once cold / once warm; record LCP or “DOMContentLoaded”.  
4. Promote any **Verify** row that exceeds your agreed budget (example budgets to set as a team):  
   - Top-bar contribution to TTFB: e.g. &lt; 50–100 ms  
   - List page open: e.g. &lt; 2 s warm  
   - Client detail / Booking list: e.g. &lt; 3 s warm  

*(Exact budgets are team decisions — this doc does not set SLOs.)*

---

## Part E — Status summary for stakeholders

| Area | Current speed posture | Priority to act |
|------|----------------------|-----------------|
| Top bar shared server work | **Not optimized** (several uncached queries + notification writes on render) | **P0** |
| Global CSS/JS shell | **Partial** | **P0** |
| Client detail open | **Slow-risk** | **P0** |
| Bookings list open | **Slow-risk** | **P0** |
| Email / unassigned / analytics open | **Slow-risk** | **P1** |
| Clients / Leads list (TinyMCE tax) | **OK → Slow-risk** | **P1** |
| Dashboard / Tasks / Signatures / most Admin lists | **OK / Fast** | **P2–P3** |
| Document convert / PDF generate | **Interaction-slow** (not top-bar open) | Separate track |

---

## Out of scope / not done

- No production or code changes.  
- No Lighthouse numbers in this pass (static + code-path review only).  
- Functional bugs and security are covered elsewhere; this doc is **open-speed priority only**.

---

## Revision

| Date | Note |
|------|------|
| 2026-09-21 | Initial decision doc focused on top menu open latency (Layer A header + Layer B destinations). |
)
