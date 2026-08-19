# Bansal Law CRM

A Laravel-based Customer Relationship Management (CRM) platform for **Australian legal practice**. It covers the full client lifecycle—from lead intake through matter management, documents, billing, appointments, and trust accounting—with role-based staff access, row-level visibility controls, and in-app broadcast notifications.

---

## Table of Contents

1. [Core Functions](#core-functions)
2. [Business Workflows](#business-workflows)
3. [Technology Stack & Package Versions](#technology-stack--package-versions)
4. [Routes](#routes)
5. [API Endpoints](#api-endpoints)
6. [Authentication & Authorization](#authentication--authorization)
7. [Installation](#installation)
8. [Configuration](#configuration)
9. [Project Structure](#project-structure)

---

## Core Functions

### CRM & Staff Operations

| Module | Description |
|--------|-------------|
| **Dashboard** | Matter pipeline, deadlines, actions, check-in status, financial stats, and notification feeds |
| **Leads** | Lead capture, assignment, follow-up, analytics, bulk convert to clients |
| **Clients** | Individual and company profiles, relationships, tags, archive, global search with access controls |
| **Matters** | Per-client case tracking with configurable workflows, stages, deadlines, legal practitioner assignment |
| **Notes & Tasks** | Client/lead notes, assignee actions, matter tasks, pinned items, activity logs |
| **Assignee Module** | Tasks assigned to/by staff, completion tracking, action counts |
| **Office Visits** | Walk-in queue (waiting → attending → completed), front-desk check-in wizard |
| **Broadcasts** | In-app broadcast notifications to staff with read/unread history |
| **Audit Logs** | Staff login history (`StaffLoginLog`) at `/audit-logs` |
| **Staff Analytics** | Login analytics (daily/weekly/monthly/hourly trends) |

### Documents & Signatures

| Module | Description |
|--------|-------------|
| **Document Management** | Personal and matter document checklists; upload, rename, move, bulk upload |
| **E-Signatures** | Template-based signing workflow; staff sends link → client signs via token (no login) |
| **DOCX → PDF** | Local and Python microservice conversion (`python_services/`) |
| **Email Integration** | Compose/send mail, inbox/sent import (Python service), labels, attachments, smart import |
| **Legal Forms** | Short costs disclosure, long costs disclosure, authority to act (DOCX generation) |

### Financial & Trust

| Module | Description |
|--------|-------------|
| **Invoices** | Generate, adjust, void, email to client, PDF export |
| **Receipts** | Client fund, office, and journal receipts with ledger tracking |
| **Trust Accounting** | VLSB+C tooling: periods, trial balance, reconciliation, statements, auditors pack, Rule 42 authority types |
| **Payments** | Stripe integration (PaymentIntents); PayU hooks for legacy flows |
| **Quotations** | Service quotes with templates; convert accepted quotes to invoices |

### Scheduling & Communications

| Module | Description |
|--------|-------------|
| **Booking / Appointments** | FullCalendar-based scheduling (Melbourne office), consultant calendars, reminders, status updates, sync dashboard; service/location mapping via `BookingCatalogue` |
| **SMS** | Twilio and Cellcast providers; templates, bulk send, webhooks (Admin Console) |
| **Phone/Email Verification** | OTP and token-based verification on client contact records |

### Administration (Admin Console)

Accessible at `/adminconsole` by roles configured in `CRM_ADMIN_CONSOLE_ROLE_IDS` (default **1**, **12**, **17**):

- Matter types, workflows, and stages
- Document types and checklists
- Email and CRM email templates
- Staff, roles, teams, branches
- SMS dashboard, templates, and sending
- E-signature audit export

### Access Control (Cross-Access)

Row-level visibility for staff who are not allocated to a client/lead:

- **Quick access** — 15-minute grant (throttled)
- **Supervisor approval** — 24-hour grant via approver queue
- **Exempt roles/staff** — bypass allocation entirely (audited)
- Grants dashboard with CSV export at `/crm/access/*`

---

## Business Workflows

### 1. Lead → Client

```
Inquiry (web form / phone / walk-in)
  → Create lead (source, contact, interested services)
  → Assign to staff member
  → Follow-up notes & assignee actions
  → Send quotation (optional)
  → Convert to client (single or bulk)
  → Client profile created
```

### 2. Client Onboarding

```
Create/convert client
  → Collect personal or company details (AJAX section saves)
  → Verify phone (OTP) and email (token link)
  → Upload personal documents & checklists
  → Create matter(s) with workflow template
  → Assign case manager & legal practitioner
  → Generate costs disclosure / legal forms
```

### 3. Matter Lifecycle

```
Create matter on client profile
  → Select matter type & workflow
  → Progress through workflow stages (next/previous, complete, back-stage)
  → Attach matter documents & checklists
  → Track deadlines, court hearings, important dates
  → Notes, emails, and tasks on matter
  → Discontinue / reopen / delete matter
  → Close matter when complete
```

Staff matter AJAX lives under `/crm/matter/*` (not a public client portal). Document signing can auto-advance the matter to the next stage, except when the next stage is **Decision Received** (requires an outcome/note).

### 4. Document & E-Signature

```
Staff prepares document (upload or template)
  → Place signature fields
  → Send signing link via email
  → Client opens /sign/{id}/{token} (no login)
  → Client signs (Signature Pad)
  → Signed PDF stored; staff notified
  → Matter may auto-advance (see Matter Lifecycle)
  → Download / audit trail in signature dashboard
```

### 5. Invoice & Payment

```
Generate invoice on client/matter
  → Email PDF to client
  → Record payment (manual or Stripe)
  → Issue receipt (client fund / office / journal)
  → Update client ledger & trust accounts
  → Void or adjust as needed
```

### 6. Appointment Booking

```
Client requests slot (public API or staff CRM)
  → BookingCatalogue resolves service type, duration, and Melbourne location
  → Calendar checks disabled dates/slots
  → Appointment created with consultant & meeting type
  → Reminders sent (email/SMS) with Melbourne office details
  → Front-desk check-in on arrival
  → Status: scheduled → completed / cancelled
```

### 7. Cross-Access Grant

```
Staff searches client not in their allocation
  → Record shown as locked
  → Request quick (15 min) or supervisor (24 h) access
  → Approver reviews queue (/crm/access/queue)
  → Grant approved → temporary access to client/lead
  → Scheduled job expires stale grants (access:expire-grants)
```

---

## Technology Stack & Package Versions

Versions below match the current `composer.lock` / `package.json` (Jul 2026). Re-check with `composer show --installed` and `npm ls --depth=0` after upgrades.

### Runtime Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.3 (platform pin: 8.3.31) |
| Node.js | >= 22.0.0 (see `.nvmrc`) |
| npm | >= 11.0.0 |
| PostgreSQL | 12+ (primary) |
| Python | 3.x (optional, for DOCX→PDF and email parsing) |

### Backend (PHP / Composer)

| Package | Locked Version | Purpose |
|---------|----------------|---------|
| `laravel/framework` | 13.22.0 | Application framework |
| `laravel/sanctum` | 4.3.3 | SPA / mobile token authentication |
| `laravel/tinker` | 3.0.2 | REPL |
| `aws/aws-sdk-php` | 3.389.0 | AWS S3 and SES |
| `league/flysystem-aws-s3-v3` | 3.35.2 | S3 file storage driver |
| `barryvdh/laravel-dompdf` | 3.1.2 | PDF generation (invoices, receipts) |
| `phpoffice/phpword` | 1.4.0 | DOCX document generation |
| `phpoffice/phpspreadsheet` | 5.9.0 | Spreadsheet import/export |
| `stripe/stripe-php` | 21.0.0 | Payment processing |
| `twilio/sdk` | 8.11.6 | SMS (Twilio provider) |
| `webklex/php-imap` | 6.2.0 | IMAP inbox sync |
| `spatie/laravel-query-builder` | 7.3.0 | API query filtering |
| `yajra/laravel-datatables-oracle` | 13.1.5 | Server-side DataTables |
| `kyslik/column-sortable` | 8.0.0 | Sortable table columns |
| `guzzlehttp/guzzle` | 7.15.1 | HTTP client |
| `ezyang/htmlpurifier` | 4.19.0 | HTML sanitization |

### Frontend (npm)

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | ^8.0.16 | Asset bundler |
| `laravel-vite-plugin` | ^3.1.0 | Laravel Vite integration |
| `tailwindcss` | ^4.3.2 | Utility CSS (via `@tailwindcss/vite`) |
| `@tailwindcss/forms` | ^0.5.11 | Form styling |
| `alpinejs` | ^3.15.3 | Lightweight JS reactivity |
| `axios` | ^1.11.0 | HTTP client |
| `jquery` | 3.7.1 | Legacy Blade / DataTables glue |
| `bootstrap` | ^5.3.7 | CRM UI framework (copied to `public/`) |
| `@fortawesome/fontawesome-free` | ^7.3.0 | Icons |
| `@fullcalendar/core` | ^6.1.20 | Calendar (daygrid, timegrid, list, interaction) |
| `flatpickr` | ^4.6.13 | Date/time pickers (DD/MM/YYYY) |
| `datatables.net` / `datatables.net-bs5` | ^2.3.8 | Tables |
| `chart.js` | ^4.5.1 | Analytics charts |
| `sweetalert2` | ^11.26.25 | Confirm dialogs |
| `tinymce` | ^8.7.0 | Rich text editor |
| `tom-select` | ^2.6.2 | Searchable selects |
| `toastify-js` | ^1.12.0 | Toast notifications |
| `intl-tel-input` | ^29.1.2 | Phone number inputs |
| `signature_pad` | ^5.1.1 | Canvas signatures |

`npm install` runs `postinstall`, which copies vendor assets into `public/` (Bootstrap, Flatpickr, DataTables, TinyMCE, Font Awesome, etc.).

### Infrastructure & Services

| Component | Details |
|-----------|---------|
| **Database** | PostgreSQL (primary); MySQL/SQLite supported for dev/migration |
| **File Storage** | Local disk default; AWS S3 optional |
| **Session** | Redis by default (`SESSION_DRIVER`); file/database also supported |
| **Queue** | Database or Redis (`QUEUE_CONNECTION`) |
| **Mail** | AWS SES (system default), Zoho SMTP (staff compose); see `config/mail_routing.php` |
| **Notifications** | In-app broadcasts + polling (not WebSocket/Reverb) |
| **SMS** | Twilio and Cellcast |
| **Python Services** | `python_services/` — DOCX→PDF conversion, email upload parsing (default `http://localhost:5002`) |
| **Build** | Vite (`npm run build` / `npm run dev`) |

---

## Routes

The application defines **649 routes** across 12 route files (verified via `php artisan route:list`).

> **Important:** Not every route file inherits `auth:admin` from `web.php`. Middleware depends on how each file is registered — see [Route registration](#route-registration) below.

### Route registration

Routes are loaded by `App\Providers\RouteServiceProvider`:

| File | Loaded via | Middleware | Notes |
|------|------------|------------|-------|
| `routes/health.php` | `mapHealthRoutes()` | **None** | `/up` always reachable (ALB / CodeDeploy) |
| `routes/api.php` | `mapApiRoutes()` | `api` + `/api` prefix | Stateless JSON API |
| `routes/web.php` | `mapWebRoutes()` | `web` | Main CRM; includes nested requires |
| `routes/sms.php` | `mapSmsRoutes()` | `web` only | Public SMS webhooks (no auth) |
| `routes/console.php` | Laravel kernel | — | Scheduled Artisan commands |

Within `routes/web.php`:

| Included file | Inside `auth:admin` group? | Effective middleware |
|---------------|---------------------------|----------------------|
| `adminconsole.php` | No (required before login routes) | `web` + `auth:admin` + `adminconsole` (declared in file) |
| `clients.php` | Yes | `web` + `auth:admin` |
| `matter_workflow.php` | Yes | `web` + `auth:admin` |
| `crm_matter_hub.php` | Yes | `web` + `auth:admin` |
| `office_visits.php` | Yes | `web` + `auth:admin` |
| `booking_admin.php` | Yes | `web` + `auth:admin` |
| `documents.php` | No (required after auth group) | Mixed — see [Documents & signatures](#documents--signatures) |

### Route files

| File | Prefix / scope | Purpose |
|------|----------------|---------|
| `routes/web.php` | `/` | Login, dashboard, leads, assignee, trust accounting, broadcasts, front-desk check-in, audit logs |
| `routes/clients.php` | `/clients`, `/crm/access`, `/legal-forms`, `/documents/*` (client uploads) | Client CRUD, invoices, receipts, email, cross-access, legal forms |
| `routes/adminconsole.php` | `/adminconsole` | Matter types, workflows, staff, roles, offices, SMS admin, templates |
| `routes/matter_workflow.php` | `/crm/matter/*`, `/clients/matter/*`, `/updatestage` | Matter stage progression (staff AJAX) |
| `routes/crm_matter_hub.php` | `/crm/matter/*` | Matter logs, notes, ownership, document-move helpers |
| `routes/booking_admin.php` | `/booking/*` | Appointment calendar, CRUD, sync, export |
| `routes/office_visits.php` | `/office-visits/*`, `/checkin` | Walk-in queue management |
| `routes/documents.php` | `/sign/*`, `/signatures/*`, `/documents/*` | E-signature workflow, public signing, admin document CRUD |
| `routes/api.php` | `/api/*` | Public booking API, service-account tokens, Stripe PaymentIntents |
| `routes/sms.php` | `/webhooks/sms/*` | Twilio / Cellcast inbound webhooks (public) |
| `routes/health.php` | `/up` | Health check (zero middleware) |

### Middleware reference

| Middleware | Applies to |
|------------|------------|
| *(none)* | `/up` |
| `web` | All browser routes (session, CSRF) |
| `auth:admin` | CRM staff session (`Staff` model via `admin` guard) |
| `adminconsole` | Admin Console only (roles in `config('crm.admin_console_role_ids')`, default 1, 12, 17) |
| `api` | `/api/*` routes in `routes/api.php` |
| `auth:sanctum` | `/api/payments/create-payment-intent` |
| `auth` | `/clear-cache` (default guard: `admin`) |
| `can:trigger-manual-sync` | `POST /booking/sync/manual` |

### Public routes (no authentication)

#### Authentication & health

| Method | URI | Name | Description |
|--------|-----|------|-------------|
| GET | `/` | — | Redirect to `/login` |
| GET | `/login` | `crm.login` | Staff login form |
| POST | `/login` | `crm.login.post` | Authenticate staff |
| POST | `/logout` | `crm.logout` | End session |
| GET | `/logout` | `crm.logout.get` | Redirect to login |
| GET | `/up` | `health.up` | Health check (plain `OK` response) |
| GET/POST | `/exception` | `exception.index`, `exception.store` | Exception reporting form |

#### Email verification

| Method | URI | Name |
|--------|-----|------|
| GET | `/verify-email/{token}` | `clients.email.verify` |

#### E-signature (token-based, no login)

| Method | URI | Name |
|--------|-----|------|
| GET | `/sign/{id}/{token}` | `public.documents.sign` |
| POST | `/documents/{document}/sign` | `public.documents.submitSignatures` |
| GET | `/documents/{id?}` | `public.documents.index` (stub: redirects home; use email signing link) |
| GET | `/documents/{id}/page/{page}` | `public.documents.page` |
| GET | `/documents/{id}/download-signed` | `public.documents.download.signed` |
| GET | `/documents/{id}/download-signed-and-thankyou` | `public.documents.download_and_thankyou` |
| GET | `/documents/thankyou/{id?}` | `public.documents.thankyou` |
| POST | `/documents/{document}/send-reminder` | `public.documents.sendReminder` |

#### SMS webhooks (`routes/sms.php`)

| Method | URI | Name |
|--------|-----|------|
| POST | `/webhooks/sms/twilio/status` | `webhooks.sms.twilio.status` |
| POST | `/webhooks/sms/twilio/incoming` | `webhooks.sms.twilio.incoming` |
| POST | `/webhooks/sms/cellcast/status` | `webhooks.sms.cellcast.status` |
| POST | `/webhooks/sms/cellcast/incoming` | `webhooks.sms.cellcast.incoming` |

---

### CRM staff routes (`auth:admin`)

All routes below require a valid staff session unless noted.

#### Dashboard, profile & utilities

| Method | URI | Name |
|--------|-----|------|
| GET | `/dashboard` | `dashboard` |
| POST | `/dashboard/column-preferences` | `dashboard.column-preferences` |
| POST | `/dashboard/update-stage` | `dashboard.update-stage` |
| POST | `/dashboard/extend-deadline` | `dashboard.extend-deadline` |
| GET | `/dashboard/active-staff` | `dashboard.active-staff` |
| GET | `/my_profile` | `my_profile` |
| POST | `/my_profile` | `my_profile.update` |
| GET | `/change_password` | `change_password` |
| POST | `/change_password` | `change_password.update` |
| POST | `/session/super-admin-mode` | `crm.session.super-admin-mode` |
| GET | `/audit-logs` | `auditlogs.index` |
| GET | `/api-key` | `api` |
| POST | `/api-key` | `api.update` |
| GET | `/staff-login-analytics` | `staff-login-analytics.index` |

#### Leads (`/leads`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/leads` | `leads.index` |
| GET | `/leads/create` | `leads.create` |
| POST | `/leads/store` | `leads.store` |
| GET | `/leads/detail/{id}` | `leads.detail` |
| GET | `/leads/history/{id}` | `leads.history` |
| GET | `/leads/{id}/edit` | `leads.edit` |
| PUT/PATCH | `/leads/{id}` | `leads.update` / `leads.patch` |
| POST | `/leads/assign` | `leads.assign` |
| POST | `/leads/bulk-assign` | `leads.bulk_assign` |
| GET | `/leads/assignable-staff` | `leads.assignable_staff` |
| GET | `/leads/convert` | `leads.convert` |
| POST | `/leads/convert-single` | `leads.convert_single` |
| POST | `/leads/bulk-convert` | `leads.bulk_convert` |
| GET | `/leads/conversion-stats` | `leads.conversion_stats` |
| POST | `/leads/archive/{id}` | `leads.archive` |
| GET | `/leads/check-contact-match` | `leads.check.contact.match` |
| GET | `/leads/analytics` | `leads.analytics.index` |
| GET | `/leads/analytics/trends` | `leads.analytics.trends` |
| GET | `/leads/analytics/export` | `leads.analytics.export` |
| POST | `/leads/analytics/compare-agents` | `leads.analytics.compare` |

#### Clients (`/clients`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/clients` | `clients.index` |
| GET | `/clients/detail/{client_id}/{matter_ref?}/{tab?}` | `clients.detail` |
| GET | `/clients/edit/{id}` | `clients.edit` |
| POST | `/clients/edit` | `clients.update` |
| POST | `/clients/store` | `clients.store` |
| GET | `/clients/archived` | `clients.archived` |
| POST | `/clients/archive/{id}` | `clients.archive` |
| POST | `/clients/unarchive/{id}` | `clients.unarchive` |
| GET | `/clientsmatterslist` | `clients.clientsmatterslist` |
| GET | `/clientsclosedmatterslist` | `clients.closedmatterslist` |
| GET | `/clients/invoicelist` | `clients.invoicelist` |
| GET | `/clients/analytics-dashboard` | `clients.analytics-dashboard` |
| GET | `/clients/insights` | `clients.insights` |
| POST | `/clients/save-section` | `clients.saveSection` |
| GET | `/clients/export/{id}` | `clients.export` |
| POST | `/clients/import` | `clients.import` |

Client phone/email verification: `/clients/phone/*`, `/clients/email/*`.  
Client documents (upload/checklist): `/documents/add-*`, `/documents/upload-*`, `/documents/preview/{id}`, etc. (in `clients.php`).  
Financial routes: `/clients/saveinvoicereport`, `/clients/genInvoice/{id}`, `/clients/clientreceiptlist`, `/void_invoice`, etc.

#### Matter workflow

| Method | URI | Name |
|--------|-----|------|
| POST | `/crm/matter/load-matter-upsert` | — |
| POST | `/updatestage` | — |
| POST | `/completestage` | — |
| POST | `/updatebackstage` | — |
| POST | `/clients/matter/update-next-stage` | `clients.matter.update-next-stage` |
| POST | `/clients/matter/update-previous-stage` | `clients.matter.update-previous-stage` |
| POST | `/clients/matter/update-deadline` | `clients.matter.update-deadline` |
| POST | `/clients/matter/change-workflow` | `clients.matter.change-workflow` |
| POST | `/clients/matter/discontinue` | `clients.matter.discontinue` |
| POST | `/clients/matter/reopen` | `clients.matter.reopen` |
| POST | `/clients/matter/delete` | `clients.matter.delete` |
| GET | `/crm/matter/logs` | — |
| GET | `/crm/matter/notes` | — |
| POST | `/crm/matter/ownership` | — |
| POST | `/crm/matter/discontinue` | — |
| POST | `/crm/matter/revert` | — |
| POST | `/crm/matter/sendmail` | — |
| GET | `/upload-checklists` | `upload_checklists.index` |

#### Legal forms

| Method | URI | Name |
|--------|-----|------|
| POST | `/legal-forms` | `legal-forms.store` |
| GET | `/legal-forms/client-forms` | `legal-forms.client-forms` |
| POST | `/legal-forms/generate-scope-ai` | `legal-forms.generate-scope-ai` |
| GET | `/legal-forms/{legalForm}` | `legal-forms.show` |
| PUT | `/legal-forms/{legalForm}` | `legal-forms.update` |
| GET | `/legal-forms/{legalForm}/preview` | `legal-forms.preview` |
| GET | `/legal-forms/{legalForm}/download` | `legal-forms.download` |

#### Booking (`/booking`)

Calendar `{type}` accepts **`ajay`** or **`kunal`** only. Legacy calendar URLs redirect to `/booking/calendar/ajay`.

| Method | URI | Name |
|--------|-----|------|
| GET | `/booking/appointments` | `booking.appointments.index` |
| GET | `/booking/appointments/{id}` | `booking.appointments.show` |
| GET | `/booking/appointments/{id}/edit` | `booking.appointments.edit` |
| PUT | `/booking/appointments/{id}` | `booking.appointments.update` |
| GET | `/booking/appointments/{id}/json` | `booking.appointments.json` |
| GET | `/booking/calendar/{type}` | `booking.appointments.calendar` |
| POST | `/booking/appointments/{id}/update-status` | `booking.appointments.update-status` |
| POST | `/booking/appointments/{id}/update-consultant` | `booking.appointments.update-consultant` |
| POST | `/booking/appointments/{id}/update-meeting-type` | `booking.appointments.update-meeting-type` |
| POST | `/booking/appointments/{id}/update-datetime` | `booking.appointments.update-datetime` |
| POST | `/booking/appointments/{id}/add-note` | `booking.appointments.add-note` |
| POST | `/booking/appointments/{id}/send-reminder` | `booking.appointments.send-reminder` |
| POST | `/booking/appointments/bulk-update-status` | `booking.appointments.bulk-update-status` |
| GET | `/booking/appointments/export` | `booking.appointments.export` |
| GET | `/booking/sync/dashboard` | `booking.sync.dashboard` |
| GET | `/booking/sync/stats` | `booking.sync.stats` |
| POST | `/booking/sync/manual` | `booking.sync.manual` |
| GET/POST | `/booking/api/appointments` | `booking.api.appointments` |
| POST | `/booking/api/calendar-events` | `booking.api.calendar-events.store` |
| PUT | `/booking/api/calendar-events/{id}` | `booking.api.calendar-events.update` |
| DELETE | `/booking/api/calendar-events/{id}` | `booking.api.calendar-events.destroy` |
| GET | `/booking/api/calendar-stats/{type}` | `booking.api.calendar-stats` |
| GET | `/booking/api/calendar-events/reminders` | `booking.api.calendar-events.reminders` |

#### Office visits & front desk

| Method | URI | Name |
|--------|-----|------|
| GET | `/office-visits/waiting` | `officevisits.waiting` |
| GET | `/office-visits/attending` | `officevisits.attending` |
| GET | `/office-visits/completed` | `officevisits.completed` |
| GET | `/office-visits/create` | `officevisits.create` |
| POST | `/checkin` | — |
| POST | `/attend_session` | — |
| POST | `/complete_session` | — |
| GET | `/front-desk/checkin` | `front-desk.checkin.index` |
| POST | `/front-desk/checkin/lookup` | `front-desk.checkin.lookup` |
| POST | `/front-desk/checkin/submit` | `front-desk.checkin.submit` |
| POST | `/front-desk/checkin/create-lead` | `front-desk.checkin.create-lead` |

#### Assignee & actions

| Method | URI | Name |
|--------|-----|------|
| GET | `/assignee` | `assignee.index` |
| GET | `/assignee-completed` | — |
| GET | `/assigned_by_me` | `assignee.assigned_by_me` |
| GET | `/assigned_to_me` | `assignee.assigned_to_me` |
| GET | `/action` | `assignee.action` |
| GET | `/action/list` | `action.list` |
| GET | `/action/counts` | `action.counts` |
| GET | `/action_completed` | `assignee.action_completed` |

#### Cross-access (`/crm/access`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/crm/access/meta` | `crm.access.meta` |
| POST | `/crm/access/quick` | `crm.access.quick` |
| POST | `/crm/access/supervisor` | `crm.access.supervisor` |
| GET | `/crm/access/queue` | `crm.access.queue` |
| GET | `/crm/access/queue/data` | `crm.access.queue.data` |
| GET | `/crm/access/queue/mini` | `crm.access.queue.mini` |
| POST | `/crm/access/{grant}/approve` | `crm.access.approve` |
| POST | `/crm/access/{grant}/reject` | `crm.access.reject` |
| GET | `/crm/access/my-grants` | `crm.access.my-grants` |
| GET | `/crm/access/my-grants/data` | `crm.access.my-grants.data` |
| GET | `/crm/access/dashboard` | `crm.access.dashboard` |
| GET | `/crm/access/dashboard/stats` | `crm.access.dashboard.stats` |
| GET | `/crm/access/dashboard/summary` | `crm.access.dashboard.summary` |
| GET | `/crm/access/dashboard/data` | `crm.access.dashboard.data` |
| GET | `/crm/access/dashboard/export` | `crm.access.dashboard.export` |

#### Trust accounting (`/trust-accounting`) — 29 routes

| Method | URI | Name |
|--------|-----|------|
| GET | `/trust-accounting/periods` | `trust-accounting.periods.index` |
| POST | `/trust-accounting/periods` | `trust-accounting.periods.store` |
| POST | `/trust-accounting/periods/{period}/unlock` | `trust-accounting.periods.unlock` |
| GET | `/trust-accounting/guide` | `trust-accounting.guide` |
| GET | `/trust-accounting/practice-sequences` | `trust-accounting.practice-sequences.index` |
| GET | `/trust-accounting/audit-log` | `trust-accounting.audit-log.index` |
| GET | `/trust-accounting/bank-accounts` | `trust-accounting.bank-accounts.index` |
| POST | `/trust-accounting/bank-accounts` | `trust-accounting.bank-accounts.store` |
| GET | `/trust-accounting/reconciliation` | `trust-accounting.reconciliation.index` |
| POST | `/trust-accounting/reconciliation/lines` | `trust-accounting.reconciliation.lines.store` |
| POST | `/trust-accounting/reconciliation/match` | `trust-accounting.reconciliation.match` |
| GET | `/trust-accounting/reports` | `trust-accounting.reports.index` |
| GET | `/trust-accounting/reports/trial-balance` | `trust-accounting.reports.trial-balance` |
| GET | `/trust-accounting/reports/receipts-journal` | `trust-accounting.reports.receipts-journal` |
| GET | `/trust-accounting/reports/payments-journal` | `trust-accounting.reports.payments-journal` |
| GET | `/trust-accounting/reports/overdrawn-ledger` | `trust-accounting.reports.overdrawn-ledger` |
| GET | `/trust-accounting/reports/auditors-pack` | `trust-accounting.reports.auditors-pack` |
| GET | `/trust-accounting/statements` | `trust-accounting.statements.index` |
| GET | `/trust-accounting/statements/generate` | `trust-accounting.statements.generate` |
| GET | `/trust-accounting/statements/annual` | `trust-accounting.statements.annual` |
| POST | `/trust-accounting/statements/mark-sent` | `trust-accounting.statements.mark-sent` |
| GET | `/trust-accounting/archives` | `trust-accounting.archives.index` |
| GET | `/trust-accounting/archives/{archive}/download` | `trust-accounting.archives.download` |
| GET | `/trust-accounting/rule42-withdrawal-authority-types` | `trust-accounting.withdrawal-authority-types.index` |

#### Broadcasts & notifications

| Method | URI | Name |
|--------|-----|------|
| GET | `/all-notifications` | `crm.all-notifications` |
| POST | `/notifications/broadcasts/send` | `notifications.broadcasts.send` |
| GET | `/notifications/broadcasts/history` | `notifications.broadcasts.history` |
| GET | `/notifications/broadcasts/my-history` | `notifications.broadcasts.my-history` |
| GET | `/notifications/broadcasts/unread` | `notifications.broadcasts.unread` |
| GET | `/notifications/broadcasts/{batchUuid}/details` | `notifications.broadcasts.details` |

#### Staff JSON helpers (session auth, `/api` prefix on web stack)

| Method | URI | Name |
|--------|-----|------|
| GET | `/api/search-contact-person` | `api.search.contact.person` |
| GET | `/api/staff-login-analytics/daily` | `api.staff-login-analytics.daily` |
| GET | `/api/staff-login-analytics/weekly` | `api.staff-login-analytics.weekly` |
| GET | `/api/staff-login-analytics/monthly` | `api.staff-login-analytics.monthly` |
| GET | `/api/staff-login-analytics/summary` | `api.staff-login-analytics.summary` |

---

### Documents & signatures

| Scope | Middleware | Key routes |
|-------|------------|------------|
| Public signing | `web` only | `/sign/{id}/{token}`, `POST /documents/{document}/sign` |
| Signature dashboard | `auth:admin` | `/signatures`, `/signatures/create`, `/signatures/{id}` |
| Admin document CRUD | `auth:admin` | `/documents/create`, `POST /documents`, `/documents/{id}/edit`, `PATCH /documents/{id}` |
| Admin signing ops | `auth:admin` | `POST /documents/{document}/send-signing-link`, `GET /documents/{document}/sign` |
| Admin signed PDF | `auth:admin` | `/documents/{id}/preview-signed`, `/documents/{id}/download-signed` |
| Doc→PDF utilities | `auth:admin` | `/doc-to-pdf`, `/doc-to-pdf/convert` |

---

### Admin Console (`auth:admin` + `adminconsole`)

Prefix: `/adminconsole` — restricted to roles in `CRM_ADMIN_CONSOLE_ROLE_IDS` (default **1**, **12**, **17**), unless the user has effective Super Admin elevation.

#### Features (`/adminconsole/features/`)

| Section | URI prefix | Route name prefix |
|---------|------------|-------------------|
| Matter types | `/matter` | `adminconsole.features.matter.*` |
| Workflows & stages | `/workflow` | `adminconsole.features.workflow.*` |
| Personal document types | `/personal-document-type` | `adminconsole.features.personaldocumenttype.*` |
| Matter document types | `/matter-document-type` | `adminconsole.features.matterdocumenttype.*` |
| Document checklists | `/document-checklist` | `adminconsole.features.documentchecklist.*` |
| Email accounts | `/emails` | `adminconsole.features.emails.*` |
| Email labels | `/email-labels` | `adminconsole.features.emaillabels.*` |
| CRM email templates | `/crm-email-template` | `adminconsole.features.crmemailtemplate.*` |
| Matter email templates | `/matter-email-template` | `adminconsole.features.matteremailtemplate.*` |
| Matter other email templates | `/matter-other-email-template` | `adminconsole.features.matterotheremailtemplate.*` |
| SMS | `/sms/dashboard`, `/sms/send`, `/sms/templates` | `adminconsole.features.sms.*` |
| E-signature audit | `/esignature`, `/esignature/export` | `adminconsole.features.esignature.*` |

Legacy redirect: `/adminconsole/features/visa-document-type` → `/matter-document-type`.

#### Staff (`/adminconsole/staff/`)

| Method | URI | Name |
|--------|-----|------|
| GET | `/adminconsole/staff` | `adminconsole.staff.index` |
| GET | `/adminconsole/staff/active` | `adminconsole.staff.active` |
| GET | `/adminconsole/staff/create` | `adminconsole.staff.create` |
| POST | `/adminconsole/staff/store` | `adminconsole.staff.store` |
| GET | `/adminconsole/staff/edit/{id}` | `adminconsole.staff.edit` |
| PUT | `/adminconsole/staff/{id}` | `adminconsole.staff.update` |

#### System (`/adminconsole/system/`)

| Section | URI | Route name prefix |
|---------|-----|-------------------|
| Roles | `/roles` | `adminconsole.system.roles.*` |
| Teams | `/teams` | `adminconsole.system.teams.*` |
| Offices (branches) | `/offices` | `adminconsole.system.offices.*` |
| System clients | `/clients` | `adminconsole.system.clients.*` |
| Activity search | `/activity-search` | `adminconsole.system.activity-search.*` |

### Inspecting routes locally

```bash
php artisan route:list
php artisan route:list --path=clients
php artisan route:list --path=booking
php artisan route:list --name=crm.access
```

---

## API Endpoints

Base URL: `{APP_URL}/api`

### Public Booking API (no auth)

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/api/countries` | Country list for forms |
| POST | `/api/leads` | Create lead from external form |
| POST | `/api/booking-appointments` | Create booking appointment |
| GET | `/api/appointment-variable-lists` | Appointment configuration lists |
| POST | `/api/appointments/add-appointment-without-login` | Book without CRM login |
| POST | `/api/appointments/get-disabled-dates` | Calendar disabled dates |
| POST | `/api/appointments/get-disabled-slots` | Disabled time slots for a date |
| POST | `/api/appointments/get-booked-disabled-time-slots` | Booked slots to disable |
| POST | `/api/appointments/record-payment-without-login` | Record Stripe payment for booking |
| POST | `/api/appointments/record-payment-without-login-wallet` | Wallet payment variant |

### Payments

| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/api/payments/create-payment-intent` | `auth:sanctum` | Create Stripe PaymentIntent |

### Service Account

| Method | URI | Auth | Description |
|--------|-----|------|-------------|
| POST | `/api/service-account/generate-token` | Service credentials | Generate API token for integrations |

### CRM session JSON (web stack, not `routes/api.php`)

These URLs start with `/api/` but are registered in `routes/clients.php` and `routes/web.php` inside the **`web` + `auth:admin`** middleware group. They use the staff **session cookie**, not a Sanctum bearer token:

| Method | URI | Name |
|--------|-----|------|
| GET | `/api/search-contact-person` | `api.search.contact.person` |
| GET | `/api/staff-login-analytics/daily` | `api.staff-login-analytics.daily` |
| GET | `/api/staff-login-analytics/weekly` | `api.staff-login-analytics.weekly` |
| GET | `/api/staff-login-analytics/monthly` | `api.staff-login-analytics.monthly` |
| GET | `/api/staff-login-analytics/hourly` | `api.staff-login-analytics.hourly` |
| GET | `/api/staff-login-analytics/summary` | `api.staff-login-analytics.summary` |
| GET | `/api/staff-login-analytics/top-staff` | `api.staff-login-analytics.top-staff` |
| GET | `/api/staff-login-analytics/trends` | `api.staff-login-analytics.trends` |
| GET/POST | `/booking/api/appointments` | `booking.api.appointments` |
| POST | `/booking/api/calendar-events` | `booking.api.calendar-events.store` |
| GET | `/signatures/api/client-matters/{clientId}` | `signatures.client-matters` |

---

## Authentication & Authorization

### Guards & Providers

Configured in `config/auth.php`:

| Guard | Driver | Provider | Model | Used For |
|-------|--------|----------|-------|----------|
| `admin` | session | `staff` | `App\Models\Staff` | **CRM web login** (`/login`) |
| `web` | session | `admins` | `App\Models\Admin` | Legacy admin provider |
| `api` | sanctum | `admins` | `App\Models\Admin` | API token auth |

Default guard: `admin`

### Staff Web Login

- **URL:** `GET/POST /login`
- **Controller:** `App\Http\Controllers\Auth\AdminLoginController`
- **Guard:** `auth:admin` (Staff model)
- **Redirect after login:** `/dashboard`
- **Optional:** Google reCAPTCHA when `services.recaptcha.key` is configured
- **Middleware:** `guest:admin` on login; all CRM routes wrapped in `auth:admin`

### Admin Console Access

- **Middleware:** `EnsureAdminConsoleAccess`
- **Allowed roles:** Configurable via `CRM_ADMIN_CONSOLE_ROLE_IDS` (default **1**, **12**, **17**)
- **Super Admin elevation:** Non–role-1 staff with effective super-admin privileges (via `CrmAccessService`) may also access
- **Denied users:** Redirected to `/dashboard` with error message

### Role-Based Permissions

Staff roles (stored on `staff.role`) control feature access. Key role IDs referenced in code:

| Role ID | Typical Role |
|---------|--------------|
| 1 | Super Admin |
| 12 | Admin |
| 13 | Staff |
| 14 | Calling Team (quick access only) |
| 16 | Staff |
| 17 | Admin (cross-access exempt) |

Admin Console, matter configuration, and staff management require roles in `CRM_ADMIN_CONSOLE_ROLE_IDS` (default 1, 12, 17).

### Row-Level Visibility (Cross-Access)

Implemented via `App\Support\StaffClientVisibility` and `App\Services\CrmAccess\CrmAccessService`:

| Mechanism | Description |
|-----------|-------------|
| **Allocation** | Staff see clients/leads they are assigned to |
| **Exempt roles** | Roles 1, 17 bypass all restrictions (configurable) |
| **Exempt staff IDs** | Specific staff IDs bypass restrictions |
| **Quick grant** | 15-minute temporary access (Calling Team limited to this path) |
| **Supervisor grant** | 24-hour access after approver approval |
| **Strict allocation** | When `CRM_ACCESS_STRICT_ALLOCATION=true`, non-exempt staff only see allocated records |
| **Expiry job** | `access:expire-grants` (hourly scheduler) cleans stale grants |

Configuration: `config/crm_access.php` and `.env` variables (see [Configuration](#configuration)).

### Public Token Auth (No Login)

| Flow | Mechanism |
|------|-----------|
| **E-signature** | `/sign/{id}/{token}` — HMAC token validated per document |
| **Email verification** | `/verify-email/{token}` — one-time email confirmation |
| **Public documents** | `/documents/{id}` — access controlled by document settings |

Integration API tokens use **Laravel Sanctum** via `POST /api/service-account/generate-token`. There is no Passport OAuth server in this codebase.

---

## Installation

### 1. Clone and install dependencies

```bash
git clone https://github.com/bansallawyers12/BansalLaw_CRM.git
cd BansalLaw_CRM
composer install
npm install
```

`npm install` also runs `postinstall` (copies frontend vendor assets into `public/`).

### 2. Environment

```powershell
copy .env.example .env
php artisan key:generate
```

Set database credentials (PostgreSQL recommended):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bansal_law_crm
DB_USERNAME=postgres
DB_PASSWORD=
APP_TIMEZONE=Australia/Melbourne
```

For local sessions without Redis, set `SESSION_DRIVER=file` (or `database`).

### 3. Database

```bash
php artisan migrate --seed
php artisan storage:link
```

### 4. Build assets

```bash
npm run build
# Development:
npm run dev
```

### 5. Run

```bash
php artisan serve
# Separate terminal for queues:
php artisan queue:work
# Separate terminal for scheduler (production):
php artisan schedule:work
```

Access at `http://localhost:8000` → redirects to `/login`.

### Bootstrap admin

`database/seeders/SuperAdminBootstrapSeeder.php` creates a Super Admin staff row when you set:

```env
SUPERADMIN_BOOTSTRAP_EMAIL=admin1@gmail.com
SUPERADMIN_BOOTSTRAP_PASSWORD=your-secure-password
```

Then run:

```bash
php artisan db:seed --class=Database\\Seeders\\SuperAdminBootstrapSeeder
```

The seeder refuses to run if `SUPERADMIN_BOOTSTRAP_PASSWORD` is empty.

---

## Configuration

### Key Environment Variables

```env
APP_NAME="Bansal Law CRM"
APP_URL=http://localhost:8000

# Mail (AWS SES for system mail, Zoho for staff compose)
CRM_SYSTEM_MAILER=ses
CRM_PERSONAL_MAILER=zoho
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@bansallawyers.com.au
MAIL_FROM_NAME="Bansal Lawyers"
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-2

# Payments
STRIPE_KEY=
STRIPE_SECRET=

# Storage
FILESYSTEM_DISK=local
# AWS_BUCKET=... for S3

# Queue / session
QUEUE_CONNECTION=database
SESSION_DRIVER=file

# Python services (default port 5002)
PYTHON_SERVICE_URL=http://localhost:5002
PYTHON_CONVERTER_URL=http://localhost:5002
MAIL_PYTHON_EXECUTABLE=
MAIL_AUTO_DETECT_PYTHON=true

# Firm mailbox domains (inbox matching / auto-assignment; comma-separated)
APP_FIRM_EMAIL_DOMAINS=@bansallawyers.com.au,@bansaleducation.com.au
APP_PUBLIC_EMAIL=admin@bansallawyers.com.au
APP_INVOICE_EMAIL=admin@bansallawyers.com.au
APP_PUBLIC_WEBSITE_URL=https://www.bansallawyers.com.au

# Optional login CAPTCHA
RECAPTCHA_SITE_KEY=
RECAPTCHA_SITE_SECRET=
```


### CRM Cross-Access

```env
CRM_ACCESS_EXEMPT_ROLE_IDS=1,17
CRM_ACCESS_EXEMPT_STAFF_IDS=
CRM_ACCESS_QUICK_ONLY_ROLE_IDS=14
CRM_ACCESS_STRICT_ALLOCATION=false
CRM_ACCESS_ALLOCATION_ENABLED=true
CRM_ACCESS_QUICK_GRANT_MINUTES=15
CRM_ACCESS_SUPERVISOR_GRANT_HOURS=24
```

Full behaviour documented in `docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md`.

### Documentation

#### Operations & integration

| Document | Description |
|----------|-------------|
| [docs/AWS_SES_CRM_EMAIL_INSTRUCTIONS.md](docs/AWS_SES_CRM_EMAIL_INSTRUCTIONS.md) | Outbound email: AWS SES + Zoho SMTP setup, `.env`, routing, troubleshooting |
| [docs/CRM_EMAIL_S3_IMPLEMENTATION.md](docs/CRM_EMAIL_S3_IMPLEMENTATION.md) | Archiving sent emails (HTML + attachments) to S3 |
| [python_services/README.md](python_services/README.md) | Python microservices (email upload, DOCX→PDF) |
| [python_services/LINUX_DEPLOYMENT.md](python_services/LINUX_DEPLOYMENT.md) | Linux deployment for Python services |
| [python_services/QUICK_REFERENCE.md](python_services/QUICK_REFERENCE.md) | Python services command reference |

#### Product & schema reference

| Document | Description |
|----------|-------------|
| [docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md](docs/CROSS_ACCESS_IMPLEMENTATION_PLAN.md) | Allocated-only visibility, quick/supervisor access grants |
| [docs/CLIENT_INTAKE_FORM_INSTRUCTIONS.md](docs/CLIENT_INTAKE_FORM_INSTRUCTIONS.md) | Website lead form JSON → CRM import |
| [docs/theme.md](docs/theme.md) | UI colour tokens (Powder Blue & Soft Gold) |
| [docs/FONT_AWESOME_MIGRATION.md](docs/FONT_AWESOME_MIGRATION.md) | Font Awesome local FA7 + class migration (complete) |

#### Database column guides

| Document | Description |
|----------|-------------|
| [docs/ADMINS_TABLE_COLUMNS.md](docs/ADMINS_TABLE_COLUMNS.md) | `admins` table column removal plan |
| [docs/BOOKING_APPOINTMENTS_TABLE_COLUMNS.md](docs/BOOKING_APPOINTMENTS_TABLE_COLUMNS.md) | `booking_appointments` column guide |
| [docs/DOCUMENTS_TABLE_COLUMNS.md](docs/DOCUMENTS_TABLE_COLUMNS.md) | `documents` table column reference |

#### Migration / rename plans (partially complete)

| Document | Status |
|----------|--------|
| [docs/APPLICATION_TO_MATTER_MIGRATION_PLAN.md](docs/APPLICATION_TO_MATTER_MIGRATION_PLAN.md) | Remaining application→matter terminology work |
| [docs/PLAN_USER_TO_CLIENT_STAFF_RENAME.md](docs/PLAN_USER_TO_CLIENT_STAFF_RENAME.md) | Phases 1–3 done; DB renames 4–5 planned |

SendGrid docs were removed — use **AWS SES** instructions above.

---

## Project Structure

```
app/
├── Http/Controllers/
│   ├── CRM/              # Clients, leads, booking, documents, dashboard
│   ├── AdminConsole/     # System configuration
│   ├── API/              # Public booking + service-account tokens
│   └── Auth/             # AdminLoginController
├── Models/               # Staff, Lead, ClientMatter, Document, etc.
├── Services/             # Stripe, SMS, signatures, CrmAccess, dashboard
└── Support/              # StaffClientVisibility, BookingCatalogue, workflow helpers

routes/
├── web.php               # Main CRM routes
├── clients.php           # Client & financial routes
├── adminconsole.php      # Admin settings
├── api.php               # REST API
├── booking_admin.php     # Appointments
├── documents.php         # E-signatures
├── matter_workflow.php   # Matter stages
├── crm_matter_hub.php    # Matter hub utilities
├── office_visits.php     # Walk-in queue
├── sms.php               # SMS webhooks
├── health.php            # /up health check
└── console.php           # Scheduled commands

resources/views/          # Blade templates
resources/js/             # Vite entry (Alpine, Signature Pad)
public/                   # Static + postinstall vendor copies
public/build/             # Compiled Vite assets
python_services/          # DOCX→PDF, email parsing microservices
database/migrations/      # Schema
database/seeders/         # Bootstrap data
docs/                     # Ops and schema documentation
config/                   # auth, crm_access, mail_routing, services
```

### Key Models

| Model | Purpose |
|-------|---------|
| `Staff` | CRM users (authentication); solicitor/agent lookups use `staff`, not legacy `agent_details` |
| `Admin` | Client records (`admins` table; legacy naming) |
| `Lead` | Pre-client inquiries |
| `ClientMatter` | Matter/case on a client |
| `Document` | Uploaded and signed documents |
| `BookingAppointment` | Scheduled appointments (Melbourne location via `BookingCatalogue`) |
| `ClientAccessGrant` | Cross-access audit trail |
| `StaffLoginLog` | Staff login audit entries |
| `AccountAllInvoiceReceipt` | Invoices and receipts |

### Recent cleanup (Aug 2026)

Legacy surfaces removed or redirected as part of ongoing schema/API simplification:

- Matter hub AJAX under `/crm/matter/*` (former `/client-portal/*` staff paths)
- Nomination document types/checklists; Sanctum staff login / device tokens / FCM push
- `AgentDetails`, `client_spouse_details`, and payment-forms verification tables
- Client personal-detail side tables (points, qualifications, experiences, travel) and sponsorship/nomination handlers
- Workflow no longer freezes or gates stages on “Verification:*” names; signing can auto-advance except into **Decision Received**

---

## License

MIT License — see [opensource.org/licenses/MIT](https://opensource.org/licenses/MIT).
