# Admins Table – Column Reference

Checked against PostgreSQL database `bansallaw_crm` on 31 October 2026.

`admins` stores CRM people only: leads, clients, and other parties (`type`, `is_other_party`). It does **not** store staff. Staff login and solicitor fields are on `staff` (20 rows). `agent_details` does not exist (`2026_08_15_184000_drop_agent_details_table`).

**247 rows** in `admins`. Non-null counts below are from that table on the check date.

`is_deleted` is a **timestamp** (set when the row is soft-deleted). It is not an integer flag. Every current row is null, which means nothing is soft-deleted right now.

---

## Current columns

| Column | Type | Non-null | Role |
|--------|------|----------|------|
| **id** | bigint, PK | 247 | Primary key. |
| **role** | integer | 0 | Present, unused on current rows. Clients and leads are identified by `type`. |
| **first_name** | varchar | 247 | Name. |
| **last_name** | varchar | 245 | Name. |
| **email** | varchar, NOT NULL | 247 | Contact. Unique login email for people is on `staff`, not here. |
| **password** | varchar, NOT NULL | 247 | Stored with the person row. Staff passwords are on `staff`. |
| **country** | integer | 0 | Address. No values in current data. |
| **state** | integer | 0 | Address. No values in current data. |
| **city** | varchar | 0 | Address. No values in current data. |
| **address** | text | 0 | Address. No values in current data. |
| **zip** | varchar | 0 | Address. No values in current data. |
| **status** | smallint, NOT NULL | 247 | Active/inactive. |
| **service_token** | varchar | 0 | Service-account token. No values in current data. |
| **token_generated_at** | timestamp | 0 | When `service_token` was issued. No values in current data. |
| **marital_status** | varchar | 88 | Personal details. |
| **remember_token** | varchar | 0 | Laravel remember-me token. |
| **created_at** | timestamp | 247 | Created. |
| **updated_at** | timestamp | 247 | Updated. |
| **client_counter** | varchar | 247 | Sequence used to build `client_id`. |
| **client_id** | varchar | 247 | Display id (for example CLI-001). |
| **archived_by** | bigint | 0 | Staff id who archived the row. No archived rows in current data. |
| **is_company** | boolean | 247 | Company lead/client. Company detail lives in `companies`. |
| **lead_status** | varchar | 247 | Lead status. |
| **followup_date** | timestamp | 86 | Follow-up. |
| **google_review_reminder_status** | varchar | 0 | Google review reminder outcome (`ClientsController`). No values yet. |
| **google_review_reminder_snooze_until** | timestamp | 0 | Snooze until this time. No values yet. |
| **is_deleted** | timestamp | 0 | Soft-delete time. Null means the row is live. |
| **is_archived** | smallint, NOT NULL | 247 | Archive flag. |
| **archived_on** | timestamp | 0 | When archived. No archived rows in current data. |
| **type** | varchar | 247 | `'lead'` or `'client'`. |
| **refer_by** | varchar | 5 | Who referred the person. |
| **dob** | date | 166 | Date of birth. |
| **age** | varchar | 165 | Stored age. |
| **gender** | varchar | 166 | Gender. |
| **dob_verified_date** | timestamp | 0 | DOB verification time. No values in current data. |
| **dob_verified_by** | bigint | 0 | Who verified DOB. No values in current data. |
| **country_code** | varchar | 218 | Phone country code. |
| **phone** | varchar | 244 | Phone. |
| **user_id** | bigint | 232 | Creator/owner. |
| **email_type** | varchar | 202 | Primary email type. |
| **contact_type** | varchar | 204 | Primary contact type. |
| **tagname** | text | 7 | Tag ids. |
| **is_other_party** | boolean, NOT NULL | 247 | Other-party record for matter pickers. |

---

## Columns with no data

These columns exist and are still part of the schema. Every one of the 247 rows is null:

`role`, `country`, `state`, `city`, `address`, `zip`, `service_token`, `token_generated_at`, `remember_token`, `archived_by`, `google_review_reminder_status`, `google_review_reminder_snooze_until`, `is_deleted`, `archived_on`, `dob_verified_date`, `dob_verified_by`.

Low fill, not empty: `refer_by` (5), `tagname` (7), `followup_date` (86), `marital_status` (88).

---

## Now on `staff`, not `admins`

These names from the older column guide are columns on `staff` (20 rows):

`first_name`, `last_name`, `email`, `password`, `country_code`, `phone`, `status`, `role`, `position`, `permission`, `office_id`, `show_dashboard_per`, `company_name`, `company_website`, `business_address`, `business_email`, `remember_token`, `created_at`, `updated_at`, `quick_access_enabled`, `grant_super_admin_access`, `is_solicitor`, `email_signature`, `can_delete_email_with_attachments`, `can_close_discontinue_matter`, `can_sync_inbox_emails`, `can_edit_final_invoice`, `can_view_all_synced_inbox_mail`, `can_pause_mailbox_inbox_sync`, `can_assign_emails_by_subject`, `can_use_timeline_billing`, `can_access_personal_calendar`, `default_calendar_type`, `can_manage_matter_team_access`, `has_full_crm_allocation_access`, `staff_calendar_visibility`, `can_access_admin_console`.

`team` is not a column on `staff` or `admins`. `marn_number` is not a column on either table. `legal_practitioner_number`, `business_phone`, `business_mobile`, and `tax_number` were dropped from `staff` in `2026_10_31_180000_drop_unused_staff_practitioner_columns`.

---

## Not on `admins` anymore

The February 2026 draft of this file still listed these as `admins` columns. They are not in the live table:

`staff_id`, `decrypt_password`, `telephone`, `profile_img`, `latitude`, `longitude`, `visa_opt`, `followers`, `tags`, `smtp_host`, `smtp_port`, `smtp_enc`, `smtp_username`, `smtp_password`, `preferredIntake`, `applications`, `wp_customer_id`, `experience_job_title`, `experience_country`, `primary_email`, `gst_no`, `is_business_gst`, `gstin`, `gst_date`, `assignee`, `rating`, `att_email`, `att_phone`, `att_country_code`, `relevant_work_exp_aus`, `relevant_work_exp_over`, `naati_py`, `service`, `lead_quality`, `comments_note`, `lead_id`, `prev_visa`, `is_visa_expire_mail_sent`, `nomi_occupation`, `skill_assessment`, `high_quali_aus`, `high_quali_overseas`, `married_partner`, `company_fax`, `exempt_person_reason`, `is_star_client`, `business_fax`, `time_zone`, `team`, `ABN_number`, `verified`, `agent_id`, `related_files`, `source`, `cp_status`, `cp_random_code`, `cp_code_verify`, `cp_token_generated_at`, `email_verified_at`, `not_picked_call`, `visa_type`, `visaExpiry`, `visaGrant`, `country_passport`, `passport_number`, `qualification_level`, `qualification_name`, `naati_test`, `py_test`, `naati_date`, `py_date`, `australian_study`, `australian_study_date`, `specialist_education`, `specialist_education_date`, `regional_study`, `regional_study_date`, `total_points`, `nati_language`, `py_field`, `regional_points`, `dob_verify_document`, `visa_expiry_verified_at`, `visa_expiry_verified_by`, `phone_verified_date`, `phone_verified_by`, `marn_number`.

Drop history for the ones removed on purpose is in `database/migrations`, including `2026_02_11_000003_drop_bansal_lead_columns_from_admins_table`, `2026_02_11_000004_drop_phase4_legacy_columns_from_admins_table`, `2026_08_15_151000_drop_staff_marn_number`, and `2026_08_15_191000_drop_unused_columns`.
