# Dashboard & booking calendar — gap check and implementation plan

**Date:** 2026-09-15  
**Source doc reviewed:** “Dashboard & booking calendar (website bookings)” (status claimed: implemented local)  
**This repo check:** against current `BansalLaw_CRM` tree (no code changes made)

---

# Verdict

**Chosen approach (implemented):** keep **ajay / kunal only**. Deliver Admin default calendar, primary + Other calendars switcher, shared appointment modal on the dashboard, richer booking payload, and agenda status badges.

**As of 2026-09-15:** Eight-calendar restore and full calendar-v6 script extraction were tried, then **reverted**. Active model is ajay/kunal again (legacy type URLs 301 → Ajay). Shared dashboard modal + defaults/switcher remain.

---

## 1. What each piece in the source doc means

| Concept | What it is |
|---|---|
| **Dashboard calendar** | Widget on `/dashboard` (`x-dashboard.staff-calendar`): glance view of calendar + upcoming agenda list. |
| **Booking calendar (`calendar-v6`)** | Full ops UI at `/booking/calendar/{type}`: status changes, transfer, reschedule, cancel, stats, legend. |
| **Website bookings** | Rows in `booking_appointments`, usually filtered by consultant `calendar_type`. |
| **Calendar type keys** | Stable string keys (`paid`, `jrp`, `ajay`, …) that choose which consultant/feed a staff member sees. |
| **Default calendar for staff** | Which type opens first for that staff (Admin override → name/email hint → fallback). |
| **Desktop switcher** | One primary type pill + “Other calendars” dropdown for the rest. |
| **Shared appointment modal** | One Appointment Details UI (status / transfer / payment / cancel) used from dashboard and booking calendar. |
| **Agenda list** | Right-hand upcoming list on the dashboard. |
| **Modal contrast CSS** | Darker button text on tinted outline buttons so status actions stay readable. |

---

## 2. What exists today (as-built)

| Area | Current behaviour in this repo |
|---|---|
| Dashboard | `resources/views/crm/dashboard.blade.php` + `components/dashboard/staff-calendar.blade.php` + `public/js/dashboard-calendar.js` + styles in `public/css/dashboard.css` |
| Dashboard feed | `GET /dashboard/calendar-events` → `DashboardController::calendarEvents` → `StaffPersonalCalendarFeedService` (bookings + hearings + deadlines + personal events) |
| Booking calendar | `calendar-v6.blade.php`; routes allow **`ajay` and `kunal` only** |
| Config | `config/booking_calendar.php` maps only `ajay` / `kunal` consultant IDs; comment says legal CRM calendars are ajay/kunal only |
| Staff ↔ type | `bookingCalendarTypeForStaff()` returns `ajay` / `kunal` (consultant table or first-name hint), else `null` — **no** `defaultTypeForStaff()`, **no** `CALENDAR_TYPES`, **no** `STAFF_CALENDAR_HINTS` |
| Admin Console | `can_access_personal_calendar` only — **no** `default_calendar_type` column/UI |
| Click on dashboard event | `showEventDetail` → reminder modal or **simple read-only detail** — **not** booking appointment manage modal |
| Booking appointment modal | Large **inline script** inside `calendar-v6.blade.php` (`#eventModal`) — not extracted to shared JS/partial used by dashboard |
| Cancelled / no-show on dashboard feed | Hidden (`whereNotIn('status', ['cancelled', 'no_show'])`) — matches source doc intent |
| Stats on dashboard | Today / Week / **Overdue** (not “Upcoming” alone) |
| Agenda layout | Time + type chip + title; **no** status badge on the right |
| Tests | `tests/Unit/StaffPersonalCalendarFeedServiceTest.php` (not under `Services/`) — covers clamp/dedupe, not default-type or 8 calendars |
| Legacy URLs | `paid` / `jrp` / `education` / `tourist` / `adelaide` → **301 → ajay** (`routes/booking_admin.php`) |

---

## 3. Gap checklist (source doc vs repo)

| # | Source-doc claim | Status | Notes |
|---|---|---|---|
| 1 | Eight calendar types (`paid`, `jrp`, `education`, `tourist`, `adelaide`, `adelaide_education`, `ajay`, `arun`) | **Missing** | Only `ajay`, `kunal` (Michael). No `arun` / `adelaide_education` routes. |
| 2 | `StaffPersonalCalendarFeedService::CALENDAR_TYPES` | **Missing** | Constant / map not present. |
| 3 | `defaultTypeForStaff()` resolution order (Admin → hints → paid) | **Missing** | Only `bookingCalendarTypeForStaff()` for ajay/kunal. |
| 4 | `STAFF_CALENDAR_HINTS` (Ajay, Vijay, Shubham/Yadwinder, Arun→paid) | **Missing** | Hints today: Ajay→ajay; Michael/Kunal→kunal. |
| 5 | Column `staff.default_calendar_type` + migration `2026_09_15_143341_...` | **Missing** | No migration file; Staff model has no field. |
| 6 | Admin Console “Default website calendar” UI + partial | **Missing** | No `partials/default-calendar-type.blade.php`; form has personal-calendar checkbox only. |
| 7 | `StaffController` store/update validates calendar keys | **Missing** | No validation for default calendar type. |
| 8 | Dashboard primary + “Other calendars” switcher (in-page refetch) | **Missing** | Super Admin has My/All/Individual **staff** filter, not website calendar-type pills. |
| 9 | Booking calendar same switcher navigating `/booking/calendar/{type}` for all 8 | **Missing** | Nav is ajay/kunal (+ staff personal calendars). |
| 10 | Shared modal partial `event-modals.blade.php` | **Missing** | Modal lives inline in `calendar-v6`. |
| 11 | `public/js/booking-appointment-modal.js` | **Missing** | File does not exist. |
| 12 | `public/css/booking-appointment-modal.css` | **Missing** | File does not exist. |
| 13 | `DashboardController::bookingConsultantsForModal()` → `window.consultantsData` | **Missing** | Not in DashboardController / dashboard view. |
| 14 | Rich fields in `payloadFromBookingAppointment()` for modal (payment, language, consultant id, etc.) | **Partial** | Payload has id, client, status, meeting type, consultant **name**; lacks payment / transfer fields modal needs. |
| 15 | Slim agenda: `[time] name (type) [status badge]` + location | **Missing** | Current agenda uses type chip + title; no right-side status badge layout as specified. |
| 16 | Status colour legend removed under dashboard filters | **N/A / different** | Dashboard does not have that booking-style legend today. |
| 17 | `dashboard-optimized.blade.php` | **Missing** | Dashboard is `dashboard.blade.php`. |
| 18 | Separate `public/css/dashboard-calendar.css` | **Missing** | Styles live in `dashboard.css`. |
| 19 | Tests at `tests/Unit/Services/StaffPersonalCalendarFeedServiceTest.php` | **Path wrong** | File is `tests/Unit/StaffPersonalCalendarFeedServiceTest.php`. |
| 20 | Optional: one shared appointments query | **Not done** (optional) | Two feeds remain. |
| 21 | Optional: load shared modal JS on v6 instead of inline | **Not done** (optional) | Inline script remains. |
| 22 | Optional: persist last-selected calendar | **Not done** (optional) | — |

**Already aligned (keep):**

- Separate dashboard vs booking calendar pages (do not merge).
- Dashboard feed hides cancelled/no-show; booking calendar can show them.
- Dashboard has agenda sidebar; booking calendar does not.
- Core files that exist and should be extended: feed service, dashboard JS/component, `calendar-v6`, Admin staff form, Staff model.

---

## 4. Important product conflict to decide before coding

Current CRM policy (config + routes + redirects + tests like `BookingLegacyCalendarRedirectTest`) is:

> Legal CRM calendars are **ajay / kunal only**; older type URLs redirect to Ajay.

The source doc wants to **re-introduce (or newly introduce) eight website calendars**, including Employer Sponsored (`paid`), JRP, Education, Vijay (`tourist`), Adelaide, etc.

**Before implementation, confirm with stakeholders:**

1. Are the eight types required again on CRM booking + dashboard?  
2. Or should the source doc be rewritten to match **ajay/kunal-only** reality (and only implement default staff home + shared modal within that model)?  
3. What happens to existing `kunal` (Michael) mapping vs doc’s Arun/Ajay/Vijay hints?

This plan assumes the source doc is the **desired end state** (eight types). Adjust Phase 0 if product keeps ajay/kunal-only.

---

## 5. Step-by-step implementation plan (do not apply yet — checklist only)

### Phase 0 — Product lock

1. Confirm the eight keys and labels exactly.  
2. Confirm consultant IDs / `calendar_type` values already stored in `appointment_consultants` and `booking_appointments`.  
3. Decide: remove or keep 301 redirects for legacy types.  
4. Decide fallback default: doc says `paid`; current config default website type is `ajay`.  
5. Decide whether dashboard remains a **mixed personal calendar** (hearings/deadlines/personal events) with a website-type switcher, or becomes **website-bookings-only** when a type is selected.

### Phase 1 — Shared calendar type catalogue

1. Add a single source of truth, e.g. on `StaffPersonalCalendarFeedService` (or `config/booking_calendar.php`):
   - `CALENDAR_TYPES` key → label map (the eight types).  
2. Expand `config/booking_calendar.php`:
   - `local_consultant_id_by_calendar_type` for every type that is local-DB-backed.  
   - External service IDs if any type still uses API/merge.  
3. Replace hardcoded `['ajay','kunal']` allow-lists in:
   - `routes/booking_admin.php` (`whereIn('type', …)`)  
   - `BookingAppointmentsController` (`$validTypes`, validation `in:…`)  
   - `calendar-v6` nav / fallback loops  
4. Update or remove legacy redirects once new routes are live.  
5. Add/adjust feature tests for routing (replace or extend `BookingLegacyCalendarRedirectTest`).

### Phase 2 — Staff default calendar

1. Migration: nullable `staff.default_calendar_type` string.  
2. `Staff` model: fillable + cast/docblock.  
3. Implement `defaultTypeForStaff(Staff $staff): string`:
   1. Admin column if set and valid  
   2. Else `STAFF_CALENDAR_HINTS` (name/email)  
   3. Else configured fallback (`paid` per doc, or agreed default)  
4. Keep or refactor `bookingCalendarTypeForStaff()` so callers use the new resolver consistently (dashboard home type, booking deep-links, staff filter options).  
5. Unit tests for each resolution branch (Automatic / override / each hint / fallback).

### Phase 3 — Admin Console UI

1. Create `resources/views/AdminConsole/staff/partials/default-calendar-type.blade.php`:
   - Select: Automatic + each calendar label.  
2. Include on create / edit / view.  
3. `StaffController` store/update: validate `nullable|in:` known keys; save null for Automatic.  
4. Manual QA: set override → dashboard opens that type; Automatic → hints/fallback.

### Phase 4 — Desktop switcher UX

**Dashboard**

1. In `staff-calendar.blade.php`, render primary pill for home type + “Other calendars” dropdown.  
2. In `dashboard-calendar.js`, on type change: refetch `/dashboard/calendar-events?type=…` (or agreed query param) and refresh agenda; do not navigate away.  
3. Pass initial type from controller using `defaultTypeForStaff()`.

**Booking calendar**

1. Same primary + dropdown pattern in `calendar-v6`.  
2. Dropdown items link to `/booking/calendar/{type}`.  
3. Ensure titles/stats/legend still work per type.

### Phase 5 — Feed payload for modal

1. Extend `payloadFromBookingAppointment()` with fields the manage modal needs, e.g.:
   - `consultant_id`, payment fields, language, cancel reason, reschedule fields, encoded appointment id, etc. (match what calendar-v6 already puts on FullCalendar events).  
2. Confirm dashboard feed still hides cancelled/no-show; booking feed keeps showing them.  
3. Decide whether dashboard type filter shows **only** website bookings for that type, or mixed personal items + filtered bookings.

### Phase 6 — Shared appointment modal

1. Extract markup from `calendar-v6` `#eventModal` into  
   `resources/views/crm/booking/appointments/partials/event-modals.blade.php`.  
2. Extract actions into `public/js/booking-appointment-modal.js`.  
3. Extract contrast styles into `public/css/booking-appointment-modal.css`; load from the partial.  
4. Include partial on dashboard and on booking calendar.  
5. Add `DashboardController::bookingConsultantsForModal()` and expose `window.consultantsData` on dashboard.  
6. Wire dashboard grid + agenda clicks for `website_booking` / `booking_appointment_id` to open shared modal (not `showSimpleEventDetail`, not new tab).  
7. Fix duration math to always parse ISO strings with `new Date(...)`.  
8. Keep personal-event / reminder modals for non-booking kinds.

### Phase 7 — Agenda list + legend UX

1. Update agenda row HTML/CSS to:

   `[time]  Client name (Meeting type)     [Status badge]`  
   `        location`

2. Place status badge on the right of the name row.  
3. Do not add a booking-style status legend under dashboard filters (grid colours only).  
4. Prefer extending `dashboard.css` **or** introduce `dashboard-calendar.css` and include it — pick one and document it (source doc assumed a separate CSS file).

### Phase 8 — Tests and deploy checklist

1. Unit: `defaultTypeForStaff`, hints, Admin override, payload fields.  
2. Feature: Admin save default; dashboard events for type; booking route for each type; modal endpoints still authorize.  
3. Deploy checklist:
   1. Run migration  
   2. Hard-refresh `/dashboard`  
   3. Ajay with Automatic → primary **Ajay**  
   4. Unhinted staff with Automatic → **Employer Sponsored (`paid`)** (if product confirms)  
   5. Admin override → dashboard opens that type  
   6. Grid + list open modal (not new tab)  
   7. Agenda badge on right; no status legend under filters  

### Phase 9 — Optional follow-ups (explicitly later)

1. One shared appointments query for both UIs.  
2. Remove remaining inline modal script from `calendar-v6`; only shared JS.  
3. Persist last-selected type in session/localStorage after “Other calendars”.

---

## 6. Suggested file touch list (when implementing)

| Area | Create / change |
|---|---|
| Types + defaults | `app/Services/StaffPersonalCalendarFeedService.php`, `config/booking_calendar.php` |
| DB | new migration `*_add_default_calendar_type_to_staff_table.php`, `app/Models/Staff.php` |
| Admin | `StaffController.php`, staff create/edit/view, `partials/default-calendar-type.blade.php` |
| Routes | `routes/booking_admin.php` (allow-list + redirects) |
| Booking UI | `BookingAppointmentsController.php`, `calendar-v6.blade.php` |
| Dashboard | `DashboardController.php`, `staff-calendar.blade.php`, `dashboard-calendar.js`, `dashboard.css` (or new `dashboard-calendar.css`), `dashboard.blade.php` |
| Shared modal | `partials/event-modals.blade.php`, `public/js/booking-appointment-modal.js`, `public/css/booking-appointment-modal.css` |
| Tests | extend `tests/Unit/StaffPersonalCalendarFeedServiceTest.php`; routing/feature tests for types |

---

## 7. How to use this document

1. **Do not** treat the original “Status: implemented (local)” note as true for this repo.  
2. Run **Phase 0** product decisions first (especially eight types vs ajay/kunal-only).  
3. Implement phases in order; each phase should be independently testable.  
4. After code lands, rewrite the original narrative doc to “as-built” and point here for history/gaps.

---

## 8. Quick reference — source doc vs truth

| Source doc says | This repo today |
|---|---|
| Implemented locally | Spec only / not present |
| 8 website calendars | 2 (`ajay`, `kunal`) |
| Admin default calendar | Not present |
| Shared booking modal on dashboard | Read-only / simple detail only |
| Primary + Other calendars switcher | Not present (staff view filter for Super Admin instead) |
| `defaultTypeForStaff` + hints table | Not present |
| Separate modal JS/CSS assets | Not present |
