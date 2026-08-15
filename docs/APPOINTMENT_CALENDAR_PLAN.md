# Appointment / calendar plan (status update)

Last updated: 2026-08-15  
Source of truth: **bansallawyers.com.au/book-an-appointment**

Website is the calendar source of truth. That **replaces** “relabel the old visa list in place.” Appointment leftovers must match the seven practice areas and the 10 / 30 / 60 products.

---

## Confirmed decisions (locked)

1. Live page posts to **`/api/booking-appointments`** (LeadBooking). PublicBooking leftovers cleaned anyway; fix edge cases later if needed.
2. Slot APIs follow the website: practice-area NOE **1–7** and durations **10 / 30 / 60**.
3. CRM staff booking offers the **same products as the website** (free 10, $150/30, $220/60).
4. **Adelaide is not used** for Lawyers booking (lists, creates, assignment, consultant calendars).

Historical immigration rows stay distinguishable via **`noe_scheme = immigration`**. New Lawyers / CRM traffic uses **`noe_scheme = crm`**. Do not reinterpret old rows.

---

## Source of truth vs CRM (current)

| Website | CRM after C | Status |
|---|---|---|
| Nature of enquiry: Criminal → Commercial (ids **1–7**) | Config is 1–7 only; leftover 9–12 remapped → **5** where CRM | **Done** |
| Same 1–7 on public site | Public `select_your_service` returns CRM practice areas | **Done** |
| Details of enquiry (required free text) | CRM `description`; public accepts `enquiry_details` / `description`; LeadBooking requires details | **Done** |
| 10 min free · 30 min $150 · 1 hour $220 | Products in `config/booking_service_products.php`; CRM modal + public lists + create paths | **Done** |
| In-person **Melbourne** · Phone · Video | Melbourne-only public location; video for paid 30/60 | **Done** |
| Free = first-time, 10-min eligibility | Not enforced in CRM | **Still optional / not C** |

---

## What was implemented (appointment / calendar)

### Shared catalogue
- `config/booking_nature_of_enquiry.php` — practice areas **1–7** only
- `config/booking_service_products.php` — form ids 1/2/3 → free 10 / standard 30 $150 / extended 60 $220
- `app/Support/BookingCatalogue.php` — NOE lists, products, Melbourne location, scheme inference, duration/amount helpers

### Create / API paths
- `PublicBookingController` — public lists; create paths use CRM NOE + products; `noe_scheme=crm`; Melbourne-only; no visa `noeToServiceType` on new bookings
- `LeadBookingApiController` — CRM NOE validation; Melbourne; default `noe_scheme=crm`; duration/amount from product when omitted; `description` → `enquiry_details`
- `ClientsController::addAppointmentBook` — `promo_free` / `paid` / `paid_extended`; duplicate-slot check restored; activity labels updated (Melbourne / Free Consultation)
- CRM modal + `appointments.js` — 10 min free, $150, $220 hour; video for paid products

### Slots / sync / assignment
- `HomeController` + PublicBooking slot endpoints map NOE + product via `BookingCatalogue`; fallback duration follows selected product
- `AppointmentSyncService` — infers `noe_scheme`; Melbourne coerce; practice-area NOE mapping; product-aware service_id / duration
- `ConsultantAssignmentService` — **ajay / kunal only** (legacy types no longer assigned)
- `BookedTimeSlotsToDisableService` — Melbourne-only location filter

### Mail / display Adelaide cleanup
- `AppointmentCancellation` / `AppointmentDetailedConfirmation` — Melbourne address + phone only
- `NotificationService` — Melbourne office phone
- `BookingAppointment` accessors — Adelaide historical rows show Melbourne office / address
- Booking edit blade — Adelaide rows display as Melbourne Office

### Sample data
- `SampleBookingAppointmentsSeeder` — rewritten for practice areas 1–7, products 10/30/60, Melbourne, ajay/kunal

### Schema (local status)
| Migration | Purpose | Local status |
|---|---|---|
| `2026_08_15_140000_add_noe_scheme_and_remap_legacy_crm_noe` | Add `noe_scheme`; remap CRM noe_id 9–12 → 5 | **Ran** |
| `2026_08_15_150000_narrow_appointment_consultants_calendar_type_to_ajay_kunal` | Remap/delete leftover consultants; narrow `calendar_type` to `ajay`/`kunal`; consultant `location` melbourne-only | **Ran** (local) |
| `2026_08_15_151000_drop_staff_marn_number` | Drop `staff.marn_number` | **Ran** (local) |
| `2026_08_15_152000_update_appointment_consultant_firm_emails` | Firm emails `@bansallawyers.com.au` | **Ran** (local) |

Verified locally: consultants Ajay (`ajay`) + Michael (`kunal`); `calendar_type` check = ajay/kunal; consultant location = melbourne; `staff.marn_number` gone; `noe_scheme` present.

### Review fixes applied after first C pass
- Restored CRM duplicate-slot guard
- LeadBooking duration/amount no longer default every booking to 10 min / $0
- Sync scheme inference so visa payloads are not forced to `crm`
- Slot fallback durations keyed by product
- Activity-log product/NOE labels aligned (Free / Standard / Extended + practice areas)

---

## Revised decision #2 (unchanged)

**Previous:** relabel visa catalogue, keep ids 1–8.  
**Now (done):** public `select_your_service` = CRM practice areas **1–7**. Visa list is leftover, not the live Lawyers dropdown. Historical immigration stays on `noe_scheme=immigration`.

---

## Workstream / PR status

| Track | Content | Status |
|---|---|---|
| **A** | Email / booking-edit copy (legal consultation; Adelaide address branches) | **Done** |
| **B** | Staff MARN UI/code removal | **Done** locally (run `151000` on other envs) |
| **C (expanded)** | NOE 1–7; products 10/30/60; Melbourne; `noe_scheme=crm`; slot maps; assignment | **Done** |
| **D** | Word templates | Not this chat |
| **E** | Drop `marn_number` column | **Done** locally (run `151000` on other envs) |
| **Consultant cleanup** | Narrow calendar_type enum; seeder ajay/kunal | **Done** locally (run `150000` / `152000` on other envs) |

---

## Still out of appointment leftover scope (unless expanded)

- Enforce “free = first-time client only” in CRM
- Promo-code engine (site may collect; API already has `promo_code`)
- Confirm live website Bansal slot API accepts `extended-consultation` + practice-area `enquiry_type` values (`criminal_law`, …); fix website or CRM mapping if slots 404
- Historical `booking_appointments.location = adelaide` DB values (creates no longer write Adelaide; UI/mail treat as Melbourne)

---

## Deploy checklist

1. On each non-local env, run migrations `150000`, `151000`, `152000` if not yet applied.
2. Smoke-test:
   - Public variable lists: 7 NOEs, 3 products, Melbourne only
   - CRM client modal: free / standard / extended; slots load for each
   - `POST /api/booking-appointments` with CRM noe_id + duration/amount
   - Calendar assignment lands on ajay or kunal
3. If slot API rejects new `specific_service` / `service_type` strings, align website calendar API or temporarily map until website is updated.

---

## File map (C core)

- `config/booking_nature_of_enquiry.php`
- `config/booking_service_products.php`
- `app/Support/BookingCatalogue.php`
- `app/Support/BansalDatetimeBackendHelper.php`
- `app/Http/Controllers/API/PublicBookingController.php`
- `app/Http/Controllers/API/LeadBookingApiController.php`
- `app/Http/Controllers/HomeController.php`
- `app/Http/Controllers/CRM/ClientsController.php`
- `app/Http/Controllers/CRM/BookingAppointmentsController.php`
- `app/Services/BansalAppointmentSync/*`
- `app/Services/Booking/BookedTimeSlotsToDisableService.php`
- `app/Mail/AppointmentCancellation.php`
- `app/Mail/AppointmentDetailedConfirmation.php`
- `app/Models/BookingAppointment.php` (`noe_scheme` fillable)
- `app/Models/AppointmentConsultant.php`
- `database/seeders/AppointmentConsultantSeeder.php`
- `database/seeders/SampleBookingAppointmentsSeeder.php`
- `resources/views/crm/clients/modals/appointment.blade.php`
- `resources/views/crm/booking/appointments/edit.blade.php`
- `public/js/crm/clients/modules/appointments.js`
- migrations under `database/migrations/2026_08_15_*`
