# Company Employer-Sponsorship Implementation Plan (Updated)

## Implementation Status: COMPLETE ✓

All phases (0–5) have been implemented. See summary at end of document.

---

## User Decisions Applied

| # | Decision | Implementation |
|---|----------|----------------|
| 1 | Trust visibility | Show Trust section when `company_type` = "Trust". Add "Trust" to Business Type dropdown. |
| 2 | Company name + Trading names | Company name (single). "Does company have trading name?" (yes/no). If yes: multiple trading names (repeatable, new table `company_trading_names`). |
| 3 | Directors DOB | Store DOB per director in `company_directors` table. |
| 4 | Nomination link | Add `nominated_client_id` (FK to admins) OR `nominated_person_name` (when not in system). |
| 5 | Implementation order | Phase 0 (fix bugs) first, then new fields. |
| 6 | clients.update | **Broken** – POST /clients/edit has no `{id}` in URL; `ClientsController::edit($id)` receives null. Fix: use `saveSectionData` → POST /clients/save-section with `id` in body. |
| 7 | Section save strategy | Separate sections: each block has its own saveSectionData call. |

---

## Phase 0: Fix Existing Company Edit (Prerequisite)

### 0.1 Fix saveSectionData form selector
**File:** `public/js/clients/edit-client.js`
- Resolve form as `document.getElementById('editCompanyForm') || document.getElementById('editClientForm')`.
- Add null check: if no form, show error and return early.
- **Why:** Company edit uses `editCompanyForm`; personal edit uses `editClientForm`. Phone/email/address saves fail on company edit because `editClientForm` is missing.

### 0.2 Add companyInfo & contactPersonInfo to saveSection
**File:** `app/Http/Controllers/CRM/ClientPersonalDetailsController.php`
- Add `case 'companyInfo':` → `saveCompanySection()`
- Add `case 'contactPersonInfo':` → `saveContactPersonSection()`
- **Section names must be `companyInfo` and `contactPersonInfo`** – `displaySectionErrors(sectionName, errors)` looks for `sectionName + 'Edit'` (e.g. `companyInfoEdit`, `contactPersonInfoEdit`).
- Implement both methods. **Guard:** Only run when `$client->is_company`; return 400 if not.
- **saveCompanySection:** Validate and save `company_name`, `trading_name`, `ABN_number`, `ACN`, `company_type`, `company_website` to `companies` (upsert by `admin_id`).
- **saveContactPersonSection:** Validate and save `contact_person_id`, `contact_person_position` to `companies`.

### 0.3 Switch company edit to AJAX save
**File:** `resources/views/crm/clients/company_edit.blade.php`
- Replace `saveCompanyInfo()` and `saveContactPersonInfo()` to use `saveSectionData()` instead of `form.submit()`.
- **saveCompanyInfo:** Build `FormData` from `editCompanyForm`, call `saveSectionData('companyInfo', formData, callback)`. Callback: `toggleEditMode('companyInfo')` to show summary, optionally `window.location.reload()` for consistency.
- **saveContactPersonInfo:** Same pattern with `'contactPersonInfo'`.
- **Note:** Current `form.submit()` to `clients.update` is broken – route has no `{id}`, controller receives null.

### 0.4 Fix goBackWithRefresh
**File:** `public/js/clients/edit-client.js`
- Resolve type input as `document.querySelector('#editCompanyForm input[name="type"]') || document.querySelector('#editClientForm input[name="type"]')` (fallback to `window.currentClientType` if both null).

---

## Phase 1: Database Migrations

### 1.1 Add "Trust" to company_type options
- Update company_edit.blade.php, leads/create.blade.php: add `<option value="Trust">Trust</option>`.

### 1.2 Trading names – new table
**File:** `database/migrations/..._create_company_trading_names_table.php`

| Column | Type |
|--------|------|
| id | bigint PK |
| company_id | unsignedBigInteger FK → companies.id |
| trading_name | string(255) |
| is_primary | boolean default false |
| sort_order | integer default 0 |
| created_at, updated_at | timestamps |

- Add `has_trading_name` (boolean) to `companies` table.
- **Keep** `companies.trading_name` for backward compat (no migration).
- Display logic: if `company_trading_names` has records → use those; else fall back to `trading_name`.

### 1.3 Add employer sponsorship columns to companies
(Same as before – sponsorship, trust, workforce, financial, LMT, training fields.)

### 1.4 Create company_directors table
(Same as before – director_name, director_dob, director_role, is_primary, sort_order.)

### 1.5 Create company_nominations table
**Add columns:**
- `nominated_client_id` (unsignedBigInteger nullable, FK → admins.id)
- `nominated_person_name` (string 255 nullable) – when person not in system

---

## Phase 2: Section Structure (Separate Saves)

**Save pattern:** Per-section Save buttons only (same as personal detail edit – no Save All).

| Section | saveSection param | Handler | Fields |
|---------|-------------------|---------|--------|
| Company Info | companyInfo | saveCompanySection | company_name, has_trading_name, trading_names[], ABN, ACN, business_type, website |
| Contact Person | contactPersonInfo | saveContactPersonSection | contact_person_id, contact_person_position |
| Sponsorship | sponsorship | saveSponsorshipSection | sponsorship_type, status, dates, TRN, regional, adverse, previous |
| Trust | trust | saveTrustSection | trust_name, trust_abn, trustee_name, trustee_details |
| Directors | directors | saveDirectorsSection | directors[] |
| Financial | financial | saveFinancialSection | annual_turnover, wages_expenditure |
| Workforce | workforce | saveWorkforceSection | all workforce counts |
| Operations | operations | saveOperationsSection | business_operating_since, main_business_activity |
| LMT | lmt | saveLmtSection | all LMT fields |
| Training | training | saveTrainingSection | training_position_title, trainer_name |
| Nominations | nominations | saveNominationsSection | nominations[] (CRUD) |

---

## Phase 3: Models

- **Company:** Add `tradingNames()` hasMany, `has_trading_name` to fillable.
- **CompanyTradingName:** New model – `trading_name`, `is_primary`, `sort_order`, `company()` belongsTo.
- **CompanyDirector:** (as before)
- **CompanyNomination:** Add `nominated_client_id`, `nominated_person_name`, `nominatedClient()` belongsTo (search scope: type in ['client','lead']).

---

## Phase 4: UI Flow – Company Info Section

1. Company Name (single, required)
2. "Does this company have a trading name?" → Yes / No
3. If Yes: Show repeatable "Trading Name" fields with "Add another" button.
4. Business Type dropdown – add "Trust".
5. When Business Type = "Trust": Show Trust section (trust_name, trust_abn, trustee_name, trustee_details).

---

## Phase 5: UI Flow – Nomination Section

1. Position details (title, ANZSCO, description, salary, duration, etc.)
2. "Nominated person (visa applicant):"
   - Option A: Search/select existing client – **reuse** `route("api.search.contact.person")` (already excludes companies via `is_company = false`, scopes to client/lead).
   - Option B: "Not in our system" → show text field for **name only** (nominated_person_name)
3. TRN, status, dates, etc.

---

## Files to Create/Modify

| Action | File |
|--------|------|
| Modify | public/js/clients/edit-client.js |
| Modify | app/Http/Controllers/CRM/ClientPersonalDetailsController.php |
| Modify | resources/views/crm/clients/company_edit.blade.php |
| Create | database/migrations/..._create_company_trading_names_table.php |
| Create | database/migrations/..._add_employer_sponsorship_fields_to_companies_table.php |
| Create | database/migrations/..._create_company_directors_table.php |
| Create | database/migrations/..._create_company_nominations_table.php |
| Create | app/Models/CompanyTradingName.php |
| Create | app/Models/CompanyDirector.php |
| Create | app/Models/CompanyNomination.php |
| Modify | app/Models/Company.php |
| Modify | resources/views/crm/leads/create.blade.php (add Trust option) |
| Modify | app/Services/ClientEditService.php |
| Modify | resources/views/crm/companies/tabs/company_details.blade.php |

---

## User Decisions (Follow-up) – Final

| # | Question | Answer |
|---|----------|--------|
| 1 | Nominated person (not in system) – other details? | **No** – name only. |
| 2 | Trading names migration for existing data? | **2B** – Leave existing data as-is; only use new structure for new/edited records. Keep `trading_name` column for backward compat; new saves go to `company_trading_names`. |
| 3 | Primary trading name? | **Yes** – Add `is_primary` to `company_trading_names`. |
| 4 | Nomination client search scope? | **Only client/lead** – Search `admins` where `type` in ['client','lead']. |
| 5 | Save All button? | **Similar to personal detail** – Per-section Save buttons only (no Save All). |

---

## company_trading_names Table (Updated)

| Column | Type |
|--------|------|
| id | bigint PK |
| company_id | unsignedBigInteger FK → companies.id |
| trading_name | string(255) |
| is_primary | boolean default false |
| sort_order | integer default 0 |
| created_at, updated_at | timestamps |

- Keep `companies.trading_name` for backward compat; display logic: if `tradingNames` has records use those, else fall back to `trading_name`.

---

## Validation Rules

- **Nomination:** Either `nominated_client_id` OR `nominated_person_name` (not both; at least one when nomination has a person).
- **Trading names:** Only one `is_primary=true` per company.
- **Trust section:** Only visible when `company_type` = "Trust".

---

## Technical Notes

| Topic | Detail |
|-------|--------|
| **Company–Admin link** | `companies.admin_id` → `admins.id`. Company records belong to admin (client/lead) with `is_company = true`. |
| **saveSectionData URL** | Uses `/clients/save-section` (route: `clients.saveSection`). Hardcoded in edit-client.js; works for both edit pages. |
| **displaySectionErrors** | Expects `sectionName + 'Edit'` DOM id. Section param must match (e.g. `companyInfo` → `companyInfoEdit`). |
| **Address/Phone/Email on company edit** | Same components as personal edit. Fix 0.1 (form selector) enables these saves on company edit. |
| **Contact person search** | `ClientsController::searchContactPerson` already filters `is_company = false`, `type` in ['client','lead']. |

---

## Implementation Summary (Completed)

| Phase | Status | Notes |
|-------|--------|-------|
| Phase 0 | ✓ | saveSectionData form selector, companyInfo/contactPersonInfo handlers, AJAX save, goBackWithRefresh |
| Phase 1 | ✓ | Trust option, company_trading_names table, employer sponsorship columns, company_directors, company_nominations |
| Phase 2 | ✓ | All section handlers (sponsorship, trust, directors, financial, workforce, operations, LMT, training, nominations) |
| Phase 3 | ✓ | CompanyTradingName, CompanyDirector, CompanyNomination models; Company updated |
| Phase 4 | ✓ | Company Info UI: trading names flow, Trust section when type=Trust |
| Phase 5 | ✓ | Nomination UI: client/lead search or name-only, validation (either/or not both) |
| Leads create | ✓ | Trading names flow added; LeadController saves to company_trading_names |
| Company details view | ✓ | Employer sponsorship sections displayed (Sponsorship, Directors, Financial, etc.) |

---

## Post-Implementation Review Fixes (Feb 28, 2026)

| Fix | File | Change |
|-----|------|--------|
| Null-safe company name | company_details.blade.php | `$fetchedData->company->company_name` → `optional($fetchedData->company)->company_name` to avoid error when company is null |
| Empty company_website validation | ClientPersonalDetailsController | Merge empty string to null before validation in saveCompanySection (avoids `url` rule failure) |
| Empty company_website on lead create | LeadController | Normalize empty string to null when creating company record |

---

## Director Search/Link Feature (Feb 28, 2026)

| Component | Details |
|-----------|---------|
| Migration | `director_client_id` (nullable FK to admins), `director_name` nullable |
| Model | CompanyDirector: directorClient(), getDisplayNameAttribute() |
| Validation | Either director_client_id OR director_name per row; director_client_id must be valid client/lead (not company) |
| UI | Search/select client/lead OR "Not in system" name; "Add contact person as director" button |
| Display | directorClient name when linked, else director_name |

### Verification Fixes (Post-Implementation)

| Fix | File | Change |
|-----|------|--------|
| Validate director_client_id | ClientPersonalDetailsController | Ensure selected person exists, is client/lead, not company |
| Error key for displaySectionErrors | ClientPersonalDetailsController | Use `director_client_ids` so validation errors display inline |
| XSS in addDirectorRow | company_edit.blade.php | Escape prefillName and prefillClientId when building option HTML |
| Primary director fallback | ClientPersonalDetailsController | If primary row was skipped, set first director as primary |
