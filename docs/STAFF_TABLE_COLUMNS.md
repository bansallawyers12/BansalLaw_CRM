# Staff Table – Columns to Copy from Admins

**Purpose:** Identify which `admins` table columns apply to **staff only** and should be included in the new `staff` table.

**Exclusion rule:** Staff are in `staff` table. Clients/leads are in `admins` (identified by `type`). Only staff-specific columns are copied.

---

## Columns to INCLUDE in `staff` Table

| # | Column | Type | Source | Notes |
|---|--------|------|--------|-------|
| 1 | `id` | bigint PK | New (auto-increment) | Do NOT copy from admins (ID overlap) |
| 2 | `first_name` | string(255) | admins | Required |
| 3 | `last_name` | string(255) | admins | Required |
| 4 | `email` | string(255) | admins | Unique, required for auth |
| 5 | `password` | string(255) | admins | Required for auth |
| 6 | `country_code` | string(20) | admins | Contact |
| 7 | `phone` | string(100) | admins | Contact |
| 8 | `status` | tinyint | admins | Active/inactive (default 1) |
| 9 | `verified` | tinyint | admins | Verification flag |
| 12 | `role` | integer | admins | FK to user_roles |
| 13 | `position` | string(255) | admins | Job title (AdminConsole) |
| 14 | `team` | string(255) | admins | Department (AdminConsole, ActiveUserService) |
| 15 | `permission` | text | admins | Granular access (Notes, Documents, etc.) |
| 16 | `office_id` | bigint unsigned | admins | FK to branches |
| 17 | `show_dashboard_per` | tinyint | admins | Dashboard permission |
| 18 | `time_zone` | string(50) | admins | Optional (StaffController::savezone) |
| 19 | `is_migration_agent` | tinyint | admins | Migration agent flag |
| 20 | `marn_number` | string(100) | admins | Migration agent |
| 21 | `legal_practitioner_number` | string(100) | admins | Migration agent |
| 22 | `company_name` | string(255) | admins | Migration agent |
| 23 | `company_website` | string(500) | admins | Migration agent |
| 24 | `business_address` | text | admins | Migration agent |
| 25 | `business_phone` | string(100) | admins | Migration agent |
| 26 | `business_mobile` | string(100) | admins | Migration agent |
| 27 | `business_email` | string(255) | admins | Migration agent |
| 28 | `tax_number` | string(100) | admins | Migration agent |
| 29 | `ABN_number` | string(100) | admins | Migration agent |
| 30 | `is_archived` | tinyint | admins | Archive state |
| 31 | `archived_by` | bigint unsigned | admins | FK to staff (self-referential) |
| 32 | `archived_on` | timestamp | admins | Archive date |
| 33 | `remember_token` | string(100) | admins | Laravel auth |
| 34 | `created_at` | timestamp | admins | |
| 35 | `updated_at` | timestamp | admins | |

**Total: 33 columns** (telephone, profile_img removed – use phone+country_code, static avatar.png)

---

## Columns to EXCLUDE (Client/Lead Only)

| Column | Reason |
|--------|--------|
| `client_id` | Client display ID (CLI-001) |
| `client_counter` | Client reference generation |
| `type` | lead vs client |
| `is_deleted` | Soft delete for leads/clients |
| `is_company` | Company lead/client flag |
| `agent_id` | Client's assigned agent (staff); staff don't have this |
| `user_id` | Lead's owner; staff don't have this |
| `cp_status`, `cp_random_code`, `cp_code_verify`, `cp_token_generated_at` | Client Portal (clients only) |
| `country`, `state`, `city`, `address`, `zip` | Not used in StaffController for staff (ClientController handles clients in admins) |
| `dob`, `age`, `gender`, `marital_status` | Client personal |
| `passport_number`, `country_passport` | Client |
| `visa_type`, `visaExpiry`, `visaGrant` | Client visa |
| `visa_expiry_verified_at`, `visa_expiry_verified_by` | Client verification (stored on client record) |
| `australian_study`, `australian_study_date`, etc. | EOI (clients) |
| `naati_test`, `py_test`, `naati_date`, `py_date` | EOI qualifications |
| `qualification_level`, `qualification_name` | EOI |
| `total_points` | EOI (nati_language, py_field, regional_points removed) |
| `source`, `tagname`, `related_files` | Lead/client CRM |
| `lead_status`, `contact_type`, `email_type`, `followup_date` | Lead/client |
| `email_verified_at` | Client Portal |
| `service_token`, `token_generated_at` | Service account (optional; excluded for minimal table) |
| `dob_verified_*`, `phone_verified_*` | Client verification |
| All dropped/deprecated columns | gst_*, att_*, decrypt_password, etc. |

---

## Verification

**StaffController store (staff create)** saves: first_name, last_name, email, password, country_code, phone, position, role, office_id, team, permission, show_dashboard_per, is_migration_agent, marn_number, company_name, business_address, business_phone, business_mobile, business_email, tax_number, status

**StaffController savezone** saves: time_zone

**StaffController update (staff edit)** saves: same as store + legal_practitioner_number (if present in form), company_website (migration agent)

All listed columns are covered. ✓
