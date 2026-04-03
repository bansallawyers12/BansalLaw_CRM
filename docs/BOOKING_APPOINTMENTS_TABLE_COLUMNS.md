# Booking Appointments Table – Column Reference

**Table:** `booking_appointments`  
**Database:** `migration_manager_crm`  
**Last Updated:** February 2026

This document describes every column in the `booking_appointments` table, how it is used in the codebase, and its current status.

---

## Table of Contents

1. [Primary Key & Timestamps](#1-primary-key--timestamps)
2. [External Reference (Bansal)](#2-external-reference-bansal)
3. [Relationships](#3-relationships)
4. [Client Information](#4-client-information)
5. [Appointment Details](#5-appointment-details)
6. [Service Details](#6-service-details)
7. [Status & Lifecycle](#7-status--lifecycle)
8. [Payment Info](#8-payment-info)
9. [CRM-Specific Fields](#9-crm-specific-fields)
10. [Notification Tracking](#10-notification-tracking)
11. [Sync Metadata](#11-sync-metadata)
12. [Column Type Quick Reference](#12-column-type-quick-reference)

---

## 1. Primary Key & Timestamps

### `id` (bigint, NOT NULL, PK)
- **Type:** BigInt, auto-increment
- **Usage:** Primary key for all appointment records
- **Used in:** Every appointment query, relationships, foreign keys (`appointment_payments.appointment_id`), validation rules (`exists:booking_appointments,id`)
- **Status:** ✅ **Actively used**

---

### `created_at` (timestamp, nullable)
- **Type:** Timestamp without time zone, nullable
- **Usage:** Standard Laravel created timestamp; indexed for sorting
- **Used in:** Sorting, filtering, display, CSV export
- **Status:** ✅ **Actively used**

---

### `updated_at` (timestamp, nullable)
- **Type:** Timestamp without time zone, nullable
- **Usage:** Standard Laravel updated timestamp
- **Used in:** Sorting, activity tracking
- **Status:** ✅ **Actively used**

---

## 2. External Reference (Bansal)

### `bansal_appointment_id` (bigint, unique, NOT NULL)
- **Type:** Unsigned BigInt, unique constraint
- **Usage:** ID from Bansal booking website; used for sync and API calls
- **Used in:** `AppointmentSyncService`, `BansalApiClient`, `ClientPortalAppointmentController`, `BookingAppointmentsController`, `ClientsController` – sync, reschedule, update workflows; `appointment_payments` references appointment
- **Status:** ✅ **Actively used**

---

### `order_hash` (varchar, nullable)
- **Type:** String, nullable
- **Usage:** Payment order hash from Bansal; payment reconciliation
- **Used in:** `AppointmentSyncService`, `ClientPortalAppointmentController` – set on sync/create; `BookingAppointmentsController` – export; `resources/views/crm/booking/appointments/show.blade.php` – display
- **Status:** ✅ **Actively used**

---

## 3. Relationships

### `client_id` (integer, nullable)
- **Type:** Unsigned Integer, FK to `admins.id` (clients/leads)
- **Usage:** Links appointment to CRM client when matched
- **Used in:** `BookingAppointment::client()` relationship; `ClientMatchingService`; `ClientPortalAppointmentController` – filter by client; `Client_Portal_Postman_Collection` – appointments for authenticated client
- **Status:** ✅ **Actively used**

---

### `consultant_id` (bigint, nullable)
- **Type:** Unsigned BigInt, FK to `appointment_consultants.id`
- **Usage:** Assigned consultant for the appointment
- **Used in:** `BookingAppointment::consultant()` relationship; `ConsultantAssignmentService`, `BookingAppointmentsController`, `ClientPortalAppointmentController`, `ClientsController` – assign/update consultant; `scopeByCalendarType()`
- **Status:** ✅ **Actively used**

---

### `assigned_by_admin_id` (integer, nullable)
- **Type:** Unsigned Integer, FK to `admins.id`
- **Usage:** Admin/staff who assigned the consultant
- **Used in:** `BookingAppointment::assignedBy()` relationship; set when consultant is assigned via CRM; `PLAN_DEDICATED_STAFF_TABLE` – planned rename to `assigned_by_staff_id`
- **Status:** ✅ **Actively used**

---

## 4. Client Information

### `client_name` (varchar, NOT NULL)
- **Type:** String
- **Usage:** Client full name (from Bansal or CRM)
- **Used in:** `AppointmentSyncService`, `ClientPortalAppointmentController`, `ClientsController` – create/update; views (show, edit, index); API responses; CSV export; confirmation emails
- **Status:** ✅ **Actively used**

---

### `client_email` (varchar, NOT NULL)
- **Type:** String
- **Usage:** Client email address
- **Used in:** Sync, create, update, display, export, `NotificationService` – confirmation/reminder emails; Client Portal API – filter by client
- **Status:** ✅ **Actively used**

---

### `client_phone` (varchar, nullable)
- **Type:** String (50), nullable
- **Usage:** Client phone number
- **Used in:** Sync, create, display, export; `NotificationService` – SMS reminders; Client Portal API responses
- **Status:** ✅ **Actively used**

---

### `client_timezone` (varchar, default 'Australia/Melbourne')
- **Type:** String (50), default `Australia/Melbourne`
- **Usage:** Client's timezone for datetime display and API
- **Used in:** `AppointmentSyncService`, `ClientPortalAppointmentController`, `ClientsController` – set on create; `show.blade.php`, `edit.blade.php` – display; API responses
- **Status:** ✅ **Actively used**

---

## 5. Appointment Details

### `appointment_datetime` (datetime, NOT NULL)
- **Type:** DateTime
- **Usage:** Scheduled appointment date and time; indexed
- **Used in:** `BookingAppointment` casts, `scopeUpcoming()`, `scopePast()`, `scopeToday()`, `isUpcoming()`, `isOverdue()`, `shouldSendReminder()`; display, filtering, sync, reschedule, Bansal API
- **Status:** ✅ **Actively used**

---

### `timeslot_full` (varchar, nullable)
- **Type:** String (50), nullable, e.g. `"9:00 AM - 9:15 AM"`
- **Usage:** Display string for time slot
- **Used in:** `AppointmentSyncService`, `ClientPortalAppointmentController`, `ClientsController` – set from `appoint_time`; views – display; could be derived from `appointment_datetime` + `duration_minutes`
- **Status:** ✅ **Actively used**

---

### `duration_minutes` (integer, default 15)
- **Type:** Integer, default 15
- **Usage:** Appointment duration in minutes
- **Used in:** Sync, create, API responses, display; `scopeNeedsReminder()` – reminder logic
- **Status:** ✅ **Actively used**

---

### `location` (enum: melbourne, adelaide)
- **Type:** Enum, indexed
- **Usage:** Office location for in-person appointments
- **Used in:** Sync (derived from `inperson_address`), create, edit; `getFullAddressAttribute()` – address display; `scopeByLocation()`; filtering, API, views
- **Status:** ✅ **Actively used**

---

### `inperson_address` (tinyint, nullable)
- **Type:** Legacy: 1=Adelaide, 2=Melbourne
- **Usage:** Legacy format for Bansal API; redundant with `location`
- **Used in:** `AppointmentSyncService`, `ConsultantAssignmentService`, `BansalApiClient` – Bansal API compatibility; `ClientPortalAppointmentController`, `ClientsController` – create/slot APIs; `HomeController` – public booking; `show.blade.php` line 177 (dead: `location === 'inperson'` never true)
- **Status:** ⚠️ **Legacy / Redundant** – can be derived from `location` when calling Bansal

---

### `meeting_type` (enum: in_person, phone, video)
- **Type:** Enum, default `in_person`
- **Usage:** How the meeting is conducted
- **Used in:** Sync, create, reschedule; `ClientPortalAppointmentController` – update; views, API responses; Bansal API
- **Status:** ✅ **Actively used**

---

### `preferred_language` (varchar, default 'English')
- **Type:** String (50), default `English`
- **Usage:** Client's preferred language (English, Hindi, Punjabi)
- **Used in:** Sync, create, reschedule; `ClientPortalAppointmentController` – API (1=English, 2=Hindi, 3=Punjabi); `BookingAppointmentsController` – edit; `BansalApiClient`; views, API responses
- **Status:** ✅ **Actively used**

---

## 6. Service Details

### `service_id` (tinyint, nullable)
- **Type:** Legacy: 1=Paid, 2=Free (mapped to 1=Paid Migration Advice, 2=Free Consultation, 3=Overseas Enquiry)
- **Usage:** Service type identifier
- **Used in:** Sync, create; `ConsultantAssignmentService`; status logic (service 2 = free → confirmed); Bansal API mapping; indexed with `noe_id`
- **Status:** ✅ **Actively used**

---

### `noe_id` (tinyint, nullable)
- **Type:** Nature of Enquiry ID (1–8)
- **Usage:** Enquiry category: Permanent Residency, Temporary Residency, JRP/Skill Assessment, Tourist Visa, Education, Complex Matters, Visa Cancellation, International Migration
- **Used in:** Sync, create; `ConsultantAssignmentService`; Bansal API; indexed with `service_id`
- **Status:** ✅ **Actively used**

---

### `enquiry_type` (varchar, nullable)
- **Type:** String (100), e.g. `tr`, `tourist`, `education`, `pr_complex`
- **Usage:** Enquiry type slug/code
- **Used in:** Sync, create; display; API responses
- **Status:** ✅ **Actively used**

---

### `service_type` (varchar, nullable)
- **Type:** String (100), display name
- **Usage:** Human-readable service name
- **Used in:** Sync, create; display; API responses; CSV export
- **Status:** ✅ **Actively used**

---

### `enquiry_details` (text, nullable)
- **Type:** Text, nullable
- **Usage:** Client's enquiry description
- **Used in:** Sync, create; display; API responses; CSV export
- **Status:** ✅ **Actively used**

---

## 7. Status & Lifecycle

### `status` (enum)
- **Type:** Enum: `pending`, `paid`, `confirmed`, `completed`, `cancelled`, `no_show`, `rescheduled`; default `pending`; indexed
- **Usage:** Appointment lifecycle state
- **Used in:** `scopeActive()`, `scopeUpcoming()`, `scopePast()`, `scopePending()`, `scopeConfirmed()`, `scopeStatus()`, `scopeNeedsReminder()`; `getStatusBadgeAttribute()`, `statusBadge`; filtering, display, sync, Bansal API
- **Status:** ✅ **Actively used**

---

### `confirmed_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When appointment was confirmed
- **Used in:** Sync, create (free appointments); cast in model; display
- **Status:** ✅ **Actively used**

---

### `completed_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When appointment was marked completed
- **Used in:** Sync, CRM update; cast in model; display
- **Status:** ✅ **Actively used**

---

### `cancelled_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When appointment was cancelled
- **Used in:** Sync, CRM update; cast in model; display
- **Status:** ✅ **Actively used**

---

### `cancellation_reason` (text, nullable)
- **Type:** Text, nullable
- **Usage:** Reason for cancellation
- **Used in:** Sync, CRM update; display
- **Status:** ✅ **Actively used**

---

## 8. Payment Info

### `is_paid` (boolean, default false)
- **Type:** Boolean, default `false`
- **Usage:** Whether payment has been completed
- **Used in:** Sync, create; `isPaymentCompleted()`; display; API; CSV export
- **Status:** ✅ **Actively used**

---

### `amount` (decimal 10,2, default 0)
- **Type:** Decimal, pre-discount amount
- **Usage:** Original price before discounts
- **Used in:** Sync, create; `AppointmentSyncService`; display; API responses; CSV export
- **Status:** ✅ **Actively used**

---

### `discount_amount` (decimal 10,2, default 0)
- **Type:** Decimal, default 0
- **Usage:** Discount applied
- **Used in:** Sync, create; `show.blade.php` – display when > 0; API responses; CSV export
- **Status:** ✅ **Actively used**

---

### `final_amount` (decimal 10,2, default 0)
- **Type:** Decimal, default 0
- **Usage:** Amount after discount
- **Used in:** Sync, create; display; API; `isPaymentCompleted()` context
- **Status:** ✅ **Actively used**

---

### `promo_code` (varchar, nullable)
- **Type:** String (50), nullable
- **Usage:** Promotional code used
- **Used in:** Sync, create; `show.blade.php` – display; `BookingAppointmentsController` – export; API responses
- **Status:** ✅ **Actively used**

---

### `payment_status` (enum, nullable)
- **Type:** Enum: `pending`, `completed`, `failed`, `refunded`; nullable
- **Usage:** Payment state from Bansal
- **Used in:** Sync, create; `isPaymentCompleted()`; display; API
- **Status:** ✅ **Actively used**

---

### `payment_method` (varchar, nullable)
- **Type:** String (50), nullable, e.g. `stripe`
- **Usage:** Payment method used
- **Used in:** Sync, seeder; `show.blade.php` – display; `BookingAppointmentsController` – export
- **Status:** ✅ **Actively used**

---

### `paid_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When payment was completed
- **Used in:** Sync; cast in model; display
- **Status:** ✅ **Actively used**

---

## 9. CRM-Specific Fields

### `admin_notes` (text, nullable)
- **Type:** Text, nullable
- **Usage:** Internal notes editable by staff
- **Used in:** CRM edit; display; CSV export
- **Status:** ✅ **Actively used**

---

### `follow_up_required` (boolean, default false)
- **Type:** Boolean, default `false`
- **Usage:** Whether staff need to follow up
- **Used in:** CRM edit; migration `2025_12_27` – default value; display; CSV export
- **Status:** ✅ **Actively used**

---

### `follow_up_date` (date, nullable)
- **Type:** Date, nullable
- **Usage:** Scheduled follow-up date
- **Used in:** CRM edit; cast in model; display
- **Status:** ✅ **Actively used**

---

## 10. Notification Tracking

### `confirmation_email_sent` (boolean, default false)
- **Type:** Boolean, default `false`
- **Usage:** Whether confirmation email was sent
- **Used in:** `NotificationService` – set on send; `scopeNeedsReminder()` context; migration `2025_12_27` – default
- **Status:** ✅ **Actively used**

---

### `confirmation_email_sent_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When confirmation email was sent
- **Used in:** `NotificationService` – set on send; `show.blade.php` – display; cast in model
- **Status:** ✅ **Actively used**

---

### `reminder_sms_sent` (boolean, default false)
- **Type:** Boolean, default `false`
- **Usage:** Whether reminder SMS was sent
- **Used in:** `NotificationService` – set on send; `scopeNeedsReminder()`; migration `2025_12_27` – default
- **Status:** ✅ **Actively used**

---

### `reminder_sms_sent_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When reminder SMS was sent
- **Used in:** `NotificationService` – set on send; `show.blade.php` – display; cast in model
- **Status:** ✅ **Actively used**

---

## 11. Sync Metadata

### `synced_from_bansal_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When first synced from Bansal
- **Used in:** `AppointmentSyncService` – set on first sync; `BookingAppointmentsController` – export, show; `show.blade.php` – display
- **Status:** ✅ **Actively used**

---

### `last_synced_at` (datetime, nullable)
- **Type:** DateTime, nullable
- **Usage:** When last synced with Bansal
- **Used in:** `AppointmentSyncService`, `BookingAppointmentsController`, `ClientPortalAppointmentController`, `ClientsController`, `PushBansalAppointmentStatusJob` – set on sync; `show.blade.php` – display
- **Status:** ✅ **Actively used**

---

### `sync_status` (enum: new, synced, error)
- **Type:** Enum, default `new`; indexed
- **Usage:** Current sync state with Bansal
- **Used in:** Sync logic; `ClientPortalAppointmentController` – response; filtering; display
- **Status:** ✅ **Actively used**

---

### `sync_error` (text, nullable)
- **Type:** Text, nullable
- **Usage:** Last sync error message
- **Used in:** `BookingAppointmentsController`, `ClientPortalAppointmentController`, `ClientsController`, `PushBansalAppointmentStatusJob` – set on sync failure; `edit.blade.php` – display error
- **Status:** ✅ **Actively used**

---

## 12. Column Type Quick Reference

| Column | DB Type | Nullable | Default |
|--------|---------|----------|---------|
| id | bigint | NO | auto-increment |
| bansal_appointment_id | bigint | NO | unique |
| order_hash | varchar | YES | |
| client_id | integer | YES | |
| consultant_id | bigint | YES | |
| assigned_by_admin_id | integer | YES | |
| client_name | varchar | NO | |
| client_email | varchar | NO | |
| client_phone | varchar(50) | YES | |
| client_timezone | varchar(50) | YES | Australia/Melbourne |
| appointment_datetime | datetime | NO | |
| timeslot_full | varchar(50) | YES | |
| duration_minutes | integer | YES | 15 |
| location | enum | NO | melbourne/adelaide |
| inperson_address | tinyint | YES | Legacy 1/2 |
| meeting_type | enum | YES | in_person |
| preferred_language | varchar(50) | YES | English |
| service_id | tinyint | YES | |
| noe_id | tinyint | YES | |
| enquiry_type | varchar(100) | YES | |
| service_type | varchar(100) | YES | |
| enquiry_details | text | YES | |
| status | enum | YES | pending |
| confirmed_at | datetime | YES | |
| completed_at | datetime | YES | |
| cancelled_at | datetime | YES | |
| cancellation_reason | text | YES | |
| is_paid | boolean | YES | false |
| amount | decimal(10,2) | YES | 0 |
| discount_amount | decimal(10,2) | YES | 0 |
| final_amount | decimal(10,2) | YES | 0 |
| promo_code | varchar(50) | YES | |
| payment_status | enum | YES | |
| payment_method | varchar(50) | YES | |
| paid_at | datetime | YES | |
| admin_notes | text | YES | |
| follow_up_required | boolean | YES | false |
| follow_up_date | date | YES | |
| confirmation_email_sent | boolean | YES | false |
| confirmation_email_sent_at | datetime | YES | |
| reminder_sms_sent | boolean | YES | false |
| reminder_sms_sent_at | datetime | YES | |
| synced_from_bansal_at | datetime | YES | |
| last_synced_at | datetime | YES | |
| sync_status | enum | YES | new |
| sync_error | text | YES | |
| created_at | timestamp | YES | |
| updated_at | timestamp | YES | |

---

## Related Tables

- **appointment_consultants** – consultant definitions (FK: `consultant_id`)
- **admins** – clients and staff (FK: `client_id`, `assigned_by_admin_id`)
- **appointment_payments** – Stripe/payment records (FK: `appointment_id` → `booking_appointments.id`)
- **appointment_sync_logs** – sync history

---

## Note: Model-Only Fields (Not in Schema)

| Field | Notes |
|-------|-------|
| `slot_overwrite_hidden` | In model `$fillable` but **not a DB column**. Form/request param for Bansal slot availability API. |
| `user_id` | In model `$fillable`; used in `ClientsController` when creating from CRM. May exist via separate migration; `PLAN_USER_TO_CLIENT_STAFF_RENAME` – planned rename to `staff_id`. |
