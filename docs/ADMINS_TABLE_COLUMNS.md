# Admins Table – Column Reference

This document describes each column in the `admins` table, its usage in the application, and flags columns that appear unused. The table stores both **staff/users** and **CRM leads/clients** (distinguished by `role` and `type`).

---

## Column removal guide

Use this section to decide what can be dropped. **Critical** and **Recommended to keep** must not be removed without a full refactor.

### Critical – do not remove

These columns are essential for auth, soft delete, or core CRM behaviour. Removing them will break the application.

| Column | Reason |
|--------|--------|
| **id** | Primary key. |
| **email**, **password**, **remember_token** | Authentication. |
| **client_id**, **client_counter**, **role**, **type** | Identity and CRM (lead vs client). |
| **first_name**, **last_name**, **status**, **verified** | Core profile and listing. |
| **is_deleted** | Soft delete; Lead model scope and many controllers filter `whereNull('is_deleted')`. |
| **created_at**, **updated_at** | Timestamps. |
| **is_archived** | Lead/client archive state. |
| **cp_status**, **cp_code_verify** | Client portal access. |
| **show_dashboard_per** | Dashboard permission. |
| **australian_study**, **specialist_education**, **regional_study** | EOI defaults (columns exist and are read). |
| **is_migration_agent**, **is_company** | Migration agent and company flags. |

### Recommended to keep

In active use; keep unless you are intentionally deprecating the feature.

| Column | Usage |
|--------|--------|
| **phone**, **country_code**, **country**, **state**, **city**, **address**, **zip** | Contact and address. (**telephone** removed.) |
| **company_website**, **passport_number** | Profile and documents. (**profile_img** removed – use static avatar.png.) |
| **user_id**, **agent_id**, **office_id** | Assignments and structure. |
| **source**, **tagname**, **related_files** | CRM and merge. |
| **dob**, **age**, **gender**, **marital_status**, **country_passport**, **visa_type**, **visaExpiry**, **visaGrant** | Client personal and visa. |
| **contact_type**, **email_type**, **followup_date**, **lead_status** | Lead/client workflow. |
| **archived_on**, **archived_by** | Archive audit. |
| **qualification_level**, **qualification_name**, **naati_test**, **py_test**, **naati_date**, **py_date** | EOI and qualifications. |
| **specialist_education_date**, **australian_study_date**, **regional_study_date** | EOI dates. |
| **total_points** | EOI points. (**nati_language**, **py_field**, **regional_points** removed.) |
| **dob_verified_***, **phone_verified_***, **visa_expiry_verified_*** | Verification audit. |
| **dob_verify_document** | DOB verification. |
| **marn_number**, **legal_practitioner_number**, **business_address**, **business_phone**, **business_email**, **tax_number** | Migration agent / Form956 / export. (**business_fax** is marked for deletion.) |
| **ABN_number**, **business_mobile** | Company and agent. |
| **company_name**, **smtp_*** (if ever used from admins), **service_token**, **token_generated_at** | Company and API. |
| **email_verified_at** | Client portal. |
| **not_picked_call** | Client detail flag. |
| **time_zone**, **position**, **team**, **permission** | **Staff-only. KEEP – staff system is ACTIVE.** Used in AdminConsole StaffController, views, and ActiveUserService (team filter). Among 96 staff users: position 97.9% adoption, team 91.7%, permission 89.6%, time_zone 1% (optional). These appear "nearly empty" in table stats because 99% of rows are clients/leads who never use them. See **Staff Management Columns** below. |

### Safe to delete

No (or negligible) code references. You can drop these columns; optionally remove from `Admin::$fillable` and any migrations.

| Column | Note |
|--------|------|
| **latitude** | No references in app. DB has 7 legacy rows (&lt; 0.1%); still safe to delete. |
| **longitude** | No references in app. DB has 7 legacy rows (&lt; 0.1%); still safe to delete. |
| **visa_opt** | No references in app. DB has 1 legacy row; still safe to delete. |
| **followers** | No references in app. DB has 3 legacy rows; still safe to delete. |
| **tags** | App uses `tagname` and Tag model; this column unused. |
| **staff_id** | Only in `Admin::$fillable`; never read/written. |
| **smtp_host**, **smtp_port**, **smtp_enc**, **smtp_username**, **smtp_password** (on admins) | SMTP in use is on `emails` table; admins columns unused. |
| **preferredIntake** | Only in export JSON; no form. (Keep if export needs it.) |
| **applications** (column on admins) | App uses `applications` table; this column never used. |
| **wp_customer_id** | No references (legacy). |
| **experience_job_title**, **experience_country** (on admins) | Only used as audit `meta_key`; admins columns never read/written. |

### Marked for deletion – remove code first, then drop column

Remove or refactor the listed code before dropping the column.

| Column | Remove / refactor before drop |
|--------|--------------------------------|
| **decrypt_password** | ServiceAccountTokenService, GenerateServiceAccountToken job, ClientImportService. |
| **primary_email** | My Profile form, receipt/quotation/printpreview emails, appointments blade, ClientAccountsController fallback. |
| **gst_no**, **is_business_gst**, **gstin**, **gst_date** | My Profile, AdminConsole user forms, CRMUtilityController, return setting view. |
| ~~**assignee**~~ | **Dropped Phase 3.** |
| **rating** | ClientsController (rating UI), ClientQueries, Admin sortable. |
| **att_email**, **att_phone**, **att_country_code** | LeadController (write, search), ClientsController (search, merge, office fallback), import/export. |
| ~~**relevant_work_exp_aus**, **naati_py**, **service**, **lead_quality**, **comments_note**, **lead_id**~~ | **Dropped Phase 3.** |
| **prev_visa** | Route `POST /saveprevvisa`, ClientNotesController::saveprevvisa, ClientsController merge. |
| **is_visa_expire_mail_sent** | VisaExpireReminderEmail command (commented out in Kernel). |
| ~~**nomi_occupation**, **skill_assessment**, **high_quali_aus**, **high_quali_overseas**, **relevant_work_exp_over**, **married_partner**~~ | **Dropped Phase 3.** |
| **company_fax** | CRMUtilityController, AdminConsole view. |
| **exempt_person_reason** | Form956Controller, ClientsController export, AgentDetails. |
| **is_star_client** | ClientsController merge. |
| **business_fax** | AdminConsole user view (Business Fax); ClientsController (export); AgentDetails model. |

*Note: **time_zone**, **position**, **team**, **permission** were previously listed here but have been moved to **Recommended to keep** – staff system is ACTIVE (see Staff Management Columns below).*

---

## Identity & Keys

| Column | Type | Usage |
|--------|------|--------|
| **id** | integer, PK | Primary key; used everywhere. |
| **client_id** | varchar | Client display ID (e.g. CLI-001). Used in client portal, documents, exports. |
| **client_counter** | varchar | Used for generating unique client_id. |
| **role** | integer | Legacy; clients/leads identified by `type`, not role. |
| **type** | varchar | `'lead'` or `'client'` for CRM; distinguishes leads vs clients. |

---

## Core Identity

| Column | Usage |
|--------|--------|
| **first_name**, **last_name** | Name; used everywhere. |
| **staff_id** | In `Admin::$fillable` only; never read/written. See **Safe to delete**. |
| **email** | Login/contact; unique; used for auth and search. |
| **password**, **decrypt_password** | Auth. |
| **remember_token** | Laravel auth. |

---

## Contact & Address

| Column | Usage |
|--------|--------|
| **phone**, **country_code** | Primary contact; used in leads, clients, search. (**telephone** removed.) |
| **country**, **state**, **city**, **address**, **zip** | Address; used in forms, merge, export. |
| **latitude**, **longitude** | ⚠️ **Not referenced in code – likely unused.** |
| ~~**att_email**, **att_country_code**, **att_phone**~~ | **Dropped Phase 2.** |

---

## Profile & Status

| Column | Usage |
|--------|--------|
| *profile_img* | **Removed.** Use static avatar.png; accessor on Admin/Staff. |
| **status** | Active/inactive; used in lists and filters. |
| **verified** | Verification flag. |
| **user_id** | Creator/owner (e.g. who created the lead); used in Lead (createdBy), ClientAccountsController, Document policy. |

---

## Company / Business (Staff & Migration Agents)

| Column | Usage |
|--------|--------|
| **company_name**, **company_website**, **primary_email** | Company info. |
| **gst_no**, **gstin**, **gst_date**, **is_business_gst** | GST. |
| **ABN_number**, **company_fax** | ABN and fax. |
| **marn_number**, **legal_practitioner_number**, **exempt_person_reason** | Migration agent / legal. |
| **business_address**, **business_phone**, **business_mobile**, **business_email**, **tax_number** | Business contact and tax. (**business_fax** marked for deletion.) |
| **is_migration_agent** | Migration agent flag. |
| **is_company** | Company lead/client flag; company details in `companies` table. |

---

## Email / SMTP

| Column | Usage |
|--------|--------|
| **smtp_host**, **smtp_port**, **smtp_enc**, **smtp_username**, **smtp_password** | Per-user SMTP. |
| **service_token**, **token_generated_at** | Service-account / API token (e.g. appointments). |
| **email_verified_at** | Read in Client Portal API. |

---

## Client Portal

| Column | Usage |
|--------|--------|
| **cp_status**, **cp_random_code**, **cp_code_verify**, **cp_token_generated_at** | Client portal access and verification. |

---

## Client/Lead – Personal & CRM

| Column | Usage |
|--------|--------|
| **dob** | Date of birth; age calculated from it. |
| **age**, **gender**, **marital_status** | Demographics; used in forms and export. |
| **country_passport**, **passport_number** | Passport; import/export and portal. |
| **visa_type**, **visaExpiry**, **visaGrant** | Visa info. |
| **preferredIntake** | ⚠️ Only in export JSON – minimal use; could be legacy. |
| **applications** | ⚠️ **No direct read/write in app** – application data lives in `applications` table. |
| ~~**assignee**~~ | **Dropped Phase 3.** |
| **followers** | ⚠️ **No references in app – likely unused.** |
| **source** | Lead/client source; used in LeadController, ClientsController, LeadAnalyticsService, import/export. |
| **tagname** | Tags. |
| **rating** | Client rating. |
| **agent_id** | Assigned agent; used in assignedClients(). |
| **tags** | Tags (text). |
| **office_id** | Branch/office; used in Admin->office(). |
| **time_zone**, **position** | Staff-only (AdminConsole). KEEP – staff system is ACTIVE. |
| **related_files** | Comma-separated related client IDs; used in merge, personal details, views. |

---

## EOI / Points (Clients)

| Column | Usage |
|--------|--------|
| **total_points** | EOI points; used in ClientOccupation, ClientPortal, client detail. *(nomi_occupation, skill_assessment, high_quali_aus, high_quali_overseas, relevant_work_exp_aus, relevant_work_exp_over, naati_py, married_partner: **dropped Phase 3.**)* |
| **qualification_level**, **qualification_name**, **experience_job_title**, **experience_country** | Denormalized quals/experience; used in ClientPersonalDetailsController, PointsService. |
| **naati_test**, **py_test**, **naati_date**, **py_date** | NAATI/PY. |
| **australian_study**, **australian_study_date**, **specialist_education**, **specialist_education_date**, **regional_study**, **regional_study_date** | EOI qualification dates. |
| *nati_language*, *py_field*, *regional_points* | **Removed.** No form UI; import/export references removed. |
| **dob_verified_date**, **dob_verified_by**, **phone_verified_date**, **phone_verified_by** | Verification audit. |
| **dob_verify_document** | DOB verification document; used in validation and import/export. |
| **visa_expiry_verified_at**, **visa_expiry_verified_by** | Visa expiry verification. |
| **is_star_client** | Used in ClientsController (merge). |

---

## Lead-Specific

| Column | Usage |
|--------|--------|
| **lead_status** | Lead status; used in Lead model, LeadController, LeadAnalyticsService. |
| **contact_type**, **email_type** | Primary contact/email type; used in leads, clients, ClientContact, ClientEmail, portal. |
| **followup_date** | Follow-up date. |
| ~~**lead_quality**~~, ~~**service**~~, ~~**comments_note**~~, ~~**lead_id**~~ | **Dropped Phase 3.** |
| **visa_opt** | ⚠️ **No references in app – likely unused.** |
| **prev_visa** | Previous visa (JSON/text); used in merge and ClientNotesController. |
| **team**, **permission** | Staff-only (AdminConsole, ActiveUserService). KEEP – staff system is ACTIVE. |
| **is_visa_expire_mail_sent** | Visa expiry email flag. |
| **is_deleted** | Soft delete for leads/clients; used in queries and Lead model. |
| **wp_customer_id** | ⚠️ **No references in app – likely unused (legacy WooCommerce?).** |
| **not_picked_call** | “Not picked call” flag; used in ClientsController and detail view. |
| **show_dashboard_per** | Dashboard permission. |

---

## Archive & Audit

| Column | Usage |
|--------|--------|
| **is_archived**, **archived_by**, **archived_on** | Archive state; used in Lead scopes and elsewhere. |
| **created_at**, **updated_at** | Timestamps. |

---

## Columns That Appear Unused

See **Column removal guide → Safe to delete** for the full list. Summary: **latitude**, **longitude**, **visa_opt**, **followers**, **tags**, **staff_id**, **smtp_*** (on admins), **preferredIntake**, **applications** (column), **wp_customer_id**, **experience_job_title**, **experience_country** (on admins).

---

## Empty Columns (database check)

**Definition:** A column is listed here if **every row** has `NULL` or (for text) empty string. Checked against **9,441 rows** in `admins`.

**38 columns are currently empty.**

**Usage review (first 14 empty columns):**

See **Column removal guide** above for: **Critical**, **Recommended to keep**, **Safe to delete**, and **Marked for deletion**.

| Column | In DB | Usage in code | Verdict |
|--------|--------|----------------|---------------|
| **staff_id** | Empty | Only in `Admin::$fillable`; no controller or view reads/writes it. | ✅ Yes |
| **decrypt_password** | Empty | **Used:** `ServiceAccountTokenService` and `GenerateServiceAccountToken` job use it as password fallback; `ClientImportService` sets null. | 🗑️ **Marked for deletion** (legacy) |
| **primary_email** | Empty | **Used:** My Profile form, receipt/quotation/printpreview emails, appointments blade, `ClientAccountsController` (client email fallback). | 🗑️ **Marked for deletion** (legacy) |
| **gst_no** | Empty | **Used:** My Profile, AdminConsole user create/edit; `ClientController` (adminconsole) saves it. | 🗑️ **Marked for deletion** (legacy) |
| **is_business_gst** | Empty | **Used:** `CRMUtilityController` saves it; return setting view (GST Yes/No). | 🗑️ **Marked for deletion** (legacy) |
| **gstin** | Empty | **Used:** `CRMUtilityController` saves it; return setting view (GSTIN field). | 🗑️ **Marked for deletion** (legacy) |
| **gst_date** | Empty | **Used:** `CRMUtilityController` saves it; return setting view; import command date list. | 🗑️ **Marked for deletion** (legacy) |
| **smtp_host** | Empty | In `Admin::$fillable` only. SMTP in use comes from **`emails`** table / `Email` model, not `admins`. | ✅ Yes |
| **smtp_port** | Empty | Same as smtp_host – app uses `emails` table for SMTP. | ✅ Yes |
| **smtp_enc** | Empty | Same as smtp_host – app uses `emails` table for SMTP. | ✅ Yes |
| **smtp_username** | Empty | Same as smtp_host – app uses `emails` table for SMTP. | ✅ Yes |
| **smtp_password** | Empty | Same as smtp_host – app uses `emails` table for SMTP. | ✅ Yes |
| **preferredIntake** | Empty | Only appears in client export JSON; no form or controller sets/reads it. | ✅ Yes (if export doesn’t need it) |
| **applications** | Empty | **Not used.** All code uses the `applications` **table**; the `admins.applications` **column** is never read or written. | ✅ Yes |

**Remaining empty columns – deep usage review:**

| Column | In DB | Usage in code | Safe to drop? |
|--------|--------|----------------|---------------|
| ~~**assignee**~~ | — | **Dropped Phase 3.** | — |
| **followers** | Empty | **Not referenced** anywhere in app or views. | ✅ Yes |
| **rating** | Empty | **Used.** `ClientsController` reads/writes rating (client rating UI); `ClientQueries` filters by rating; `Admin` sortable. Remove/refactor that code before dropping. | 🗑️ **Marked for deletion** |
| **tags** | Empty | **Not used.** App uses `tagname` (comma-separated tag IDs) and `Tag` model; the `admins.tags` **text** column is never read or written. | ✅ Yes |
| **att_email** | Empty | **Used for clients/leads:** LeadController (write, search), ClientsController (client search, merge), import/export. Remove references before dropping. | 🗑️ **Marked for deletion** (no data; drop after code refactor) |
| **att_phone** | Empty | **Used only for clients/leads:** same as att_email. Staff “office phone” fallback exists in code . | 🗑️ **Marked for deletion** (no data; drop after code refactor) |
| ~~**relevant_work_exp_aus**~~, ~~**naati_py**~~, ~~**service**~~, ~~**lead_quality**~~, ~~**comments_note**~~, ~~**lead_id**~~ | — | **Dropped Phase 3.** | — |
| **prev_visa** | Empty | Route `POST /saveprevvisa` → `ClientNotesController::saveprevvisa()`; merge in `ClientsController` copies it. Remove route, `saveprevvisa`, and merge refs before dropping. | 🗑️ **Marked for deletion** |
| **is_visa_expire_mail_sent** | All NULL | Only used by `VisaExpireReminderEmail` command (commented out in `Kernel.php`). Remove command refs before dropping. | 🗑️ **Marked for deletion** |
| **is_deleted** | All NULL | **Critical – do not remove.** `Lead` model global scope `whereNull('is_deleted')`; soft delete sets it; `ClientsController` (merge sets `is_deleted=1`), `ReportController`, `EoiRoiSheetController`, `ArtSheetController`, `SignatureDashboardController`, `ClientQueries`, `SignatureService`, `UpdateClientAges`, `FixDuplicateClientReferences` all filter `whereNull('is_deleted')`. | ❌ No (keep) |

**Other empty columns (brief notes):**

| Column | Notes |
|--------|--------|
| **wp_customer_id** | Empty | **Unused.** No references in app (legacy WooCommerce?). | 🗑️ **Marked for deletion** |
| **experience_job_title** | Empty | **Unused on admins.** Only used as `meta_key` in clientportal_details_audit; **admins** column never read/written. | 🗑️ **Marked for deletion** |
| **experience_country** | Empty | **Unused on admins.** Same as experience_job_title – audit meta_key only. | 🗑️ **Marked for deletion** |
| **specialist_education_date** | Empty | **In use.** `ClientPersonalDetailsController` read/write; client edit form (blade + edit-client.js). Column empty = no data saved yet. | ❌ No (keep) |
| **legal_practitioner_number** | Empty | **In use.** AdminConsole user view; `Form956Controller` (agent LPN); ClientsController (export list); AgentDetails model. | ❌ No (keep) |
| **business_fax** | Empty | AdminConsole user view; ClientsController (export); AgentDetails model. Remove refs before dropping. | 🗑️ **Marked for deletion** |
| **company_fax** | Low fill | **Marked for deletion.** CRMUtilityController; AdminConsole view. | 🗑️ **Marked for deletion** |
| **exempt_person_reason** | Low fill | **Marked for deletion.** Form956Controller, export, AgentDetails. | 🗑️ **Marked for deletion** |
| **is_star_client** | Low fill | **Marked for deletion.** ClientsController merge. | 🗑️ **Marked for deletion** |
| cp_random_code | Client portal code – empty |
| cp_token_generated_at | Client portal token time – empty |
| archived_by | Who archived – empty |

**Columns with very low fill (&lt; 1% of rows) – what you can remove:**

| Column | Verdict | Reason |
|--------|--------|--------|
| **latitude** | 🗑️ **Can remove** | No references in app. |
| **longitude** | 🗑️ **Can remove** | No references in app. |
| **visa_opt** | 🗑️ **Can remove** | No references in app. |
| **profile_img** | **Removed** | Replaced with static avatar.png; accessor provides URL. |
| **company_website** | ❌ Keep | ClientController (adminconsole), LeadController, CRMUtilityController. |
| **passport_number** | ❌ Keep | LeadController writes to admins; passport tables/API use same name. |
| **archived_on** | ❌ Keep | ClientsController sets when archiving. |
| **att_country_code** | 🗑️ Marked for deletion | With att_email/att_phone; remove when refactoring that group. |
| **company_fax** | 🗑️ **Marked for deletion** | Remove CRMUtilityController and AdminConsole view refs before dropping. |
| ~~**nomi_occupation, skill_assessment, high_quali_aus, high_quali_overseas, relevant_work_exp_over, married_partner**~~ | **Dropped Phase 3.** | — |
| **exempt_person_reason** | 🗑️ **Marked for deletion** | Remove Form956Controller, ClientsController export, AgentDetails refs before dropping. |
| **ABN_number** | ❌ Keep | LeadController, Company, ClientController (adminconsole). |
| **business_mobile** | ❌ Keep | ClientController (adminconsole), Form956Controller, ClientsController, AgentDetails. |
| **is_star_client** | 🗑️ **Marked for deletion** | Remove ClientsController merge refs before dropping. |
| **marn_number, business_address, business_phone, business_email, tax_number** | ❌ Keep | StaffController (staff), Form956Controller, ClientsController, AgentDetails. |

**Quick reference:** See **Column removal guide** at the top for the four categories: **Critical**, **Recommended to keep**, **Safe to delete**, **Marked for deletion**.

---

## Staff Management Columns (KEEP)

**Status:** Staff system is **ACTIVELY USED** (verified Feb 2026).

| Column | Staff adoption (96 staff) | Usage |
|--------|---------------------------|-------|
| **position** | 94/96 = 97.9% | Job title. AdminConsole create/edit forms, user view. |
| **team** | 88/96 = 91.7% | Department. AdminConsole forms, ActiveUserService team filter. |
| **permission** | 86/96 = 89.6% | Granular access (Notes, Documents, etc.). AdminConsole forms. |
| **time_zone** | 1/96 = 1.0% | Optional. `StaffController::savezone()` only; not in main create/edit forms. Consider for removal if feature is deprecated. |

**Why these appear "nearly empty":** 99% of the table (9,345 rows) is clients/leads who never use staff-only fields. Among the 96 staff users (1% of table), adoption is 90–98%. Wrong denominator: 94/9,441 = 1% (misleading). Right denominator: 94/96 = 97.9% (actual usage).

---

## Implementation Plan (Apply Recommendations)

**Phase order:** Phases 1–5 have code dependencies; run in sequence. Phase 0 is independent and lowest risk.

| Phase | Focus | Columns | Risk | Status |
|-------|-------|---------|------|--------|
| 0 | Immediate safe deletions | 16 | Very low | ✅ **Applied** |
| 1 | GST and business fax | 6 | Medium | ✅ **Applied** |
| 2 | Alternative contact (att_*) | 3 | Medium | ✅ **Applied** |
| 3 | Legacy BansalCRM lead | 13 | Medium–high | ✅ **Applied** |
| 4 | Other legacy (decrypt_password, primary_email, etc.) | 7 | Medium | ✅ **Applied** |
| 5 | time_zone (optional) | 1 | Low | Pending |

### Phase 0: Immediate safe deletions (zero code refactor) ✅ APPLIED

**Risk:** Very low. No code references; optional legacy data loss (7–7–1–3 rows in lat/long/visa_opt/followers).

**Steps:**
1. Backup database: `pg_dump -h DB_HOST -U DB_USER -t admins DB_NAME > admins_backup_$(date +%Y%m%d).sql` (PostgreSQL) or equivalent for MySQL
2. Remove dropped columns from `Admin::$fillable` first (prevents any future accidental assignment)
3. Create single migration to drop: `staff_id`, `tags`, `wp_customer_id`, `applications`, `smtp_host`, `smtp_port`, `smtp_enc`, `smtp_username`, `smtp_password`, `experience_job_title`, `experience_country`, `latitude`, `longitude`, `visa_opt`, `followers`
4. **Decision:** If client export uses `preferredIntake`, skip dropping it; otherwise add to migration
5. Run migration in staging; verify app (login, client/lead lists, staff management)
6. Deploy to production

**Estimated columns removed:** 16–17

---

### Phase 1: GST and business fax (remove code first) ✅ APPLIED

**Risk:** Medium. Requires removing form fields and controller logic.

**Steps:**
1. Remove GST fields from: `my_profile.blade.php`, `returnsetting.blade.php` (crm/settings), AdminConsole `createclient.blade.php`, `editclient.blade.php`, `ClientController` (gst_no save)
2. Remove GST logic from `CRMUtilityController` (is_business_gst, gstin, gst_date)
3. Remove `company_fax` from `CRMUtilityController`, AdminConsole view, My Profile
4. Remove `business_fax` from AdminConsole user view, ClientsController export, `AgentDetails` model
5. Remove `gst_date` from `ImportLoginDataFromMySQL` command dateFields array
6. Remove columns from `Admin::$fillable` and `AgentDetails`
7. Create migration to drop: `gst_no`, `is_business_gst`, `gstin`, `gst_date`, `company_fax`, `business_fax`
8. Test; deploy

---

### Phase 2: Alternative contact fields (att_email, att_phone, att_country_code) ✅ APPLIED

**Risk:** Medium. Used in search, merge, import/export, and office phone fallback.

**Steps:**
1. Remove from `LeadController`: write (save att_* from request), search (att_phone in phone query)
2. Remove from `ClientsController`: search (att_email, att_phone), merge (select/update att_phone, att_email), office fallback (att_phone, att_country_code for document/email)
3. Remove from `ClientImportService` (att_email, att_phone, att_country_code mapping)
4. Remove from `ClientExportService` (att_email, att_phone, att_country_code in export array)
5. Remove any Blade/JS form fields for att_* in lead/client create/edit views
6. Create migration to drop columns (att_* not in Admin::$fillable)
7. Test; deploy

---

### Phase 3: Legacy BansalCRM lead columns ✅ APPLIED

**Risk:** Medium–high. Lead model scopes and analytics.

**Steps (completed):**
1. Removed `Lead::scopeQuality()`, `Lead::scopeAssignedTo()`, `Lead::assignedAgent()`, `Lead::assignToUser()`, `Lead::isAssigned()`
2. Removed assignee from LeadController, lead/company detail views, ClientsController (merge, analytics), LeadAssignmentController
3. Removed lead_quality from Lead sortable config, leads index filter/table, lead detail display, LeadAnalyticsService
4. Removed service, comments_note from LeadController (save/filter), leads index, lead detail
5. Refactored LeadConversionController bulk sync: in-place conversion; removed `Admin::where('lead_id', $id)` usage
6. Removed from LeadController save/update: `relevant_work_exp_aus`, `naati_py`, `nomi_occupation`, `skill_assessment`, `high_quali_aus`, `high_quali_overseas`, `relevant_work_exp_over`, `married_partner`
7. Migration `2026_02_11_000003_drop_bansal_lead_columns_from_admins_table` dropped: `assignee`, `lead_quality`, `service`, `comments_note`, `lead_id`, `relevant_work_exp_aus`, `naati_py`, `nomi_occupation`, `skill_assessment`, `high_quali_aus`, `high_quali_overseas`, `relevant_work_exp_over`, `married_partner`

*Note: `rating` is dropped in Phase 4 (client column, not lead).*

---

### Phase 4: Other legacy fields ✅ APPLIED

**Risk:** Medium. Various controllers and services.

**Steps (completed):**
1. Remove `decrypt_password` fallback from `ServiceAccountTokenService`, `GenerateServiceAccountToken` job; remove `ClientImportService` setting to null; update `AdminFactory` (remove decrypt_password)
2. Remove `primary_email` from: `my_profile.blade.php`, `reciept.blade.php`, `quotaion.blade.php`, `printpreview.blade.php`, `appointments.blade.php`, `ClientAccountsController` (fallback to `email` where used)
3. Remove route `POST /saveprevvisa` (routes/clients.php), `ClientNotesController::saveprevvisa`, merge copy in ClientsController
4. Remove or archive `VisaExpireReminderEmail` command and Kernel schedule entry
5. Remove rating from: ClientsController (rating UI/update), ClientQueries (rating filter), Admin sortable
6. Remove exempt_person_reason from Form956Controller, ClientsController export, AgentDetails
7. Remove is_star_client from ClientsController merge
8. Remove columns from `Admin::$fillable` and AgentDetails
9. Migration `2026_02_11_000004_drop_phase4_legacy_columns_from_admins_table` dropped: `decrypt_password`, `primary_email`, `prev_visa`, `is_visa_expire_mail_sent`, `rating`, `exempt_person_reason`, `is_star_client`

---

### Phase 5: time_zone (optional)

**Risk:** Low. Only 1 staff has data; separate update method.

**Steps:**
1. Confirm time_zone feature is not needed
2. Remove route `POST /staff/savezone` (routes/adminconsole.php), `StaffController::savezone()`, and any view that calls it (staff timezone feature)
3. Remove from `Admin::$fillable`
4. Create migration to drop `time_zone`
5. Test; deploy

---

### Pre-flight checklist (before any phase)

- [ ] Database backup completed and verified
- [ ] All changes tested in staging (including critical flows: login, client/lead CRUD, staff management, export/import)
- [ ] Grep run for each column: `grep -r "column_name" app/ resources/ routes/` (including Blade, JS if applicable)
- [ ] Database seeders and factories checked for column references (e.g. AdminFactory, LeadFactory)
- [ ] Rollback plan ready: migration `down()` method tested, backup restorable
- [ ] Error logs monitored after deployment

### Rollback (if needed)

1. Restore database from backup
2. Revert code (git revert)
3. Re-add columns to `Admin::$fillable` if reverted
4. Deploy; verify

---

*Generated from database schema and codebase review. Empty-column check: Feb 2026 (9,441 rows). Implementation plan added Feb 2026. Plan reviewed and updated Feb 2026.*
