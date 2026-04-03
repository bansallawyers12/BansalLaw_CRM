# Plan: Rename "User" to Client/Staff Terminology

**Status:** Phases 1–3 **COMPLETED**. Phases 4–5 **PLANNING ONLY** — do not apply until approved.  
**Created:** 2026-02-14  
**Last Updated:** 2026-02-14

---

## Completion Status

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Code-only (variables, UI text, comments) | ✅ COMPLETED |
| 2 | JS/CSS identifiers (Assign Staff modal) | ✅ COMPLETED |
| 3 | Route/view rename (staff-login-analytics) | ✅ COMPLETED |
| 4 | Database migrations (column renames) | 📋 PLANNED — DO NOT APPLY |
| 5 | Codebase update for new column names | 📋 PLANNED — DO NOT APPLY |
| 6 | Testing | Pending |

---

## Overview

This plan covers renaming variables, UI text, comments, and database columns where:
- **Clients/Leads** are incorrectly called "user" → rename to "client"
- **Staff** are incorrectly called "user" → rename to "staff"

---

## Phase 0: Pre-requisites & Risk Mitigation

### 0.1 Before Starting
- [ ] Create a full database backup
- [ ] Create a new Git branch: `refactor/user-to-client-staff-rename`
- [ ] Run full test suite and document baseline
- [ ] Schedule maintenance window if production changes are involved

### 0.2 Scope Exclusions (Do NOT Change)
- `Auth::user()` — Laravel auth convention
- Policy method parameter `$user` — Laravel policy convention
- `$request->user()` in Client Portal API — authenticated client
- `device_tokens.user_id`, `refresh_tokens.user_id` — Client Portal; FK to admins (clients). **Keep as `user_id`** per existing plan (or rename to `client_id` only if no conflict)
- `usertype` / `UserRole` — role/type relationship name; leave as-is unless explicitly in scope
- Third-party files (tinymce, jquery.dataTables.js, etc.)

---

## Phase 1: Code-Only Changes (No Database Migrations)

Apply these changes first. No DB schema changes; safe to deploy independently.

### 1.1 Clients/Leads — Variable & Comment Renames

| # | File | Change |
|---|------|--------|
| 1 | `app/Http/Controllers/CRM/ClientsController.php` | `$userInfo` → `$clientInfo`, `$userPhone` → `$clientPhone` (lines 3162-3167) |
| 2 | `app/Http/Controllers/CRM/ClientsController.php` | `$user_type` → `$client_type` (lines 5548, 5576, 5587) |
| 3 | `app/Http/Controllers/CRM/AssigneeController.php` | `$user_name` → `$client_name` (lines 371-377) |
| 4 | `resources/views/crm/assignee/index.blade.php` | `$user_name` → `$client_name` (lines 114-121) |
| 5 | `resources/views/crm/assignee/completed.blade.php` | `$user_name` → `$client_name` (lines 99-106) |
| 6 | `resources/views/crm/assignee/assign_to_me.blade.php` | `$user_name` → `$client_name` (lines 110-112, 286-288) |
| 7 | `resources/views/crm/assignee/assign_by_me.blade.php` | `$user_name` → `$client_name` (lines 156, 165) |
| 8 | `resources/views/crm/assignee/action_completed.blade.php` | `$user_name` → `$client_name` (lines 278, 287) |
| 9 | `app/Http/Controllers/API/ClientPortalMessageController.php` | Log keys `'user_id'` → `'client_id'`, `'user_name'` → `'client_name'` (lines 1034-1035) |

### 1.2 Staff — UI Text (Labels, Buttons, Placeholders)

| # | File | Change |
|---|------|--------|
| 10 | `resources/views/crm/clients/modals/client-management.blade.php` | "Assign User" → "Assign Staff" (modal title, button) |
| 11 | `resources/views/crm/clients/modals/client-management.blade.php` | "Search or select users..." → "Search or select staff..." |
| 12 | `resources/views/crm/assignee/index.blade.php` | "Re-Assign User", "Assign User" → "Re-Assign Staff", "Assign Staff" |
| 13 | `resources/views/crm/assignee/completed.blade.php` | "Re-Assign User", "Assign User" → "Re-Assign Staff", "Assign Staff" |
| 14 | `resources/views/crm/assignee/assign_to_me.blade.php` | "Re-Assign User", "Assign User" → "Re-Assign Staff", "Assign Staff" (4 places) |
| 15 | `app/Http/Controllers/CRM/ClientsController.php` | Comment "Assign User popup" → "Assign Staff popup" (line 5617) |
| 16 | `resources/views/crm/assignee/action_completed.blade.php` | Comment "Assign user" → "Assign staff" (line 464) |

### 1.3 Staff — Model Comments

| # | File | Change |
|---|------|--------|
| 17 | `app/Models/Lead.php` | "Get the user who created this lead" → "Get the staff member who created this lead" (line 92-93) |

### 1.4 Staff — Service/API Response Keys (Client-Facing) — ✅ COMPLETED

| # | File | Change |
|---|------|--------|
| 18 | `app/Services/StaffLoginAnalyticsService.php` | `'user_name'` → `'staff_name'`, fallback `"User #..."` → `"Staff #..."` |
| 19 | `resources/views/crm/staff-login-analytics/index.blade.php` | `user.staff_name` |
| 20 | `resources/views/crm/broadcasts/index.blade.php` | `Staff #${recipient.receiver_id}` |

### 1.5 ImportUser Class Rename (Optional)

| # | File | Change |
|---|------|--------|
| 21 | `app/Imports/ImportUser.php` | Rename class `ImportUser` → `ImportAgent` (imports agent details, not users) |
| 22 | Any controller using `ImportUser` | Update `use` statements and instantiation |

**Note:** This class imports agent details (`AgentDetails` model), not users, so the rename would improve clarity. Only do this if agent import is still in use.

---

## Phase 2: JS/CSS Identifiers (Assign Staff Modal)

These changes affect DOM IDs, class names, and JS functions. Must be done atomically.

### 2.1 client-management.blade.php

| Element | Current | New |
|---------|---------|-----|
| CSS classes | `assign-user-modal`, `assign-user-header`, `assign-user-body`, `btn-assign-user` | `assign-staff-modal`, `assign-staff-header`, `assign-staff-body`, `btn-assign-staff` |
| Input ID | `user-search-input` | `staff-search-input` |
| Placeholder | "Search or select users..." | "Search or select staff..." |
| Span ID | `selected-users-display` | `selected-staff-display` |
| Menu ID | `userDropdownMenu` | `staffDropdownMenu` |
| Button IDs | `select-all-users`, `select-none-users` | `select-all-staff`, `select-none-staff` |
| Container ID | `users-list` | `staff-list` |
| Item class | `user-item`, `modern-user-item` | `staff-item`, `modern-staff-item` |
| Span classes | `user-name`, `user-branch` | `staff-name`, `staff-branch` |
| Button ID | `assignUser` | `assignStaff` |

### 2.2 addclientmodal.blade.php

| Element | Current | New |
|---------|---------|-----|
| Function | `filterUsers()` | `filterStaff()` |
| Selector | `.user-item` | `.staff-item` |
| Variable | `userName` | `staffName` |
| Button ID | `select-all-users` | `select-all-staff` |
| Variable | `userItem` | `staffItem` |
| `#select-all-users` references | All | `#select-all-staff` |

### 2.3 components/dashboard/modals.blade.php

| Element | Current | New |
|---------|---------|-----|
| Button ID | `dashboard-select-all-users` | `dashboard-select-all-staff` |
| Button ID (submit) | `dashboard_assignUser` | `dashboard_assignStaff` |
| Item class | `user-item`, `modern-user-item` | `staff-item`, `modern-staff-item` |

### 2.4 CSS in client-management.blade.php

Update all selectors that reference:
- `#create_action_popup .user-item`
- `.modern-user-item`
- `.btn-assign-user`

### 2.5 JavaScript Event Handlers

Update jQuery/JS event handlers that reference the button:

| File | Current | New |
|------|---------|-----|
| `resources/views/crm/assignee/index.blade.php` | `$('#assignUser').click()` | `$('#assignStaff').click()` |
| `resources/views/crm/assignee/completed.blade.php` | `$('#assignUser').click()` | `$('#assignStaff').click()` |
| `resources/views/crm/assignee/assign_to_me.blade.php` | `$('#assignUser').click()` | `$('#assignStaff').click()` |
| `resources/views/crm/assignee/action_completed.blade.php` | `$('#assignUser').click()` | `$('#assignStaff').click()` |
| `public/js/crm/clients/detail-main.js` | `$('#assignUser').on('click')` | `$('#assignStaff').on('click')` |
| `resources/views/components/dashboard/modals.blade.php` | Event handler for `#dashboard_assignUser` | Update to `#dashboard_assignStaff` |

---

## Phase 3: Route & View Path — ✅ COMPLETED

**Status:** Done. Route `user-login-analytics` → `staff-login-analytics`; controller `StaffLoginAnalyticsController`; service `StaffLoginAnalyticsService`; view directory `staff-login-analytics/`. Redirect added for old URL.

---

## Phase 4: Database Column Renames (Migrations)

**⚠️ DO NOT APPLY — PLANNING ONLY**

Column renames require migrations and will affect all code that reads/writes these columns. Execute only during a maintenance window with full backup.

### 4.1 Pre-Migration Verification

Before creating migrations, run:

```sql
-- Verify client_matters has user_id (code sets it but model fillable doesn't include it)
SELECT column_name, data_type FROM information_schema.columns 
WHERE table_schema = 'public' AND table_name = 'client_matters' AND column_name IN ('user_id', 'client_id');

-- List all tables with user_id
SELECT table_name, column_name FROM information_schema.columns 
WHERE table_schema = 'public' AND column_name = 'user_id' ORDER BY table_name;

-- Check existing foreign keys on user_id columns
SELECT tc.table_name, tc.constraint_name, kcu.column_name, ccu.table_name AS foreign_table
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage ccu ON ccu.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY' AND kcu.column_name = 'user_id';
```

### 4.2 Tables: Staff References (`user_id` → `staff_id`)

| # | Table | Column | FK Target | New Column | Notes |
|---|-------|--------|-----------|------------|-------|
| 1 | `admins` | `user_id` | admins (staff) | `staff_id` | Lead/client owner; admins table stores both |
| 2 | `staff_login_logs` | `user_id` | admins | `staff_id` | Staff who logged in |
| 3 | `notes` | `user_id` | staff | `staff_id` | Note creator (Note model already uses Staff) |
| 4 | `email_labels` | `user_id` | admins | `staff_id` | Label owner |
| 5 | `documents` | `user_id` | admins | `staff_id` | Document owner |
| 6 | `mail_reports` | `user_id` | admins | `staff_id` | Email sender |
| 7 | `applications` | `user_id` | admins | `staff_id` | Application creator |
| 8 | `checkin_log` | `user_id` | staff | `staff_id` | Attending staff |
| 9 | `sessions` | `user_id` | admins | `staff_id` | Staff session (Laravel sessions) |
| 10 | `account_client_receipts` | `user_id` | admins | `staff_id` | Staff who created receipt |
| 11 | `account_all_invoice_receipts` | `user_id` | admins | `staff_id` | Staff who created |
| 12 | `booking_appointments` | `user_id` | admins | `staff_id` | Assigned staff |
| 13 | `client_matters` | `user_id` | admins | `staff_id` | **Verify column exists first** |

### 4.3 Tables: Client References (`user_id` → `client_id`)

| # | Table | Column | FK Target | New Column | Notes |
|---|-------|--------|-----------|------------|-------|
| 1 | `device_tokens` | `user_id` | admins (clients) | `client_id` | Client Portal API only |
| 2 | `refresh_tokens` | `user_id` | admins (clients) | `client_id` | Client Portal API only |

**Note:** `device_tokens` and `refresh_tokens` FK to admins (clients, role=7). Rename only if no conflict with existing `client_id` in same table.

### 4.4 Migration File Templates

Create migrations in this order. Use ` doctrine/dbal` package (composer require doctrine/dbal) for `renameColumn` if using PostgreSQL.

#### Migration 1: admins.user_id → staff_id

```php
// database/migrations/YYYY_MM_DD_HHMMSS_rename_user_id_to_staff_id_on_admins.php
public function up()
{
    Schema::table('admins', function (Blueprint $table) {
        // admins.user_id may not have explicit FK - check with verification query
        $table->renameColumn('user_id', 'staff_id');
    });
}

public function down()
{
    Schema::table('admins', function (Blueprint $table) {
        $table->renameColumn('staff_id', 'user_id');
    });
}
```

#### Migration 2: staff_login_logs.user_id → staff_id

```php
// database/migrations/YYYY_MM_DD_HHMMSS_rename_user_id_to_staff_id_on_staff_login_logs.php
public function up()
{
    Schema::table('staff_login_logs', function (Blueprint $table) {
        $table->renameColumn('user_id', 'staff_id');
    });
}

public function down()
{
    Schema::table('staff_login_logs', function (Blueprint $table) {
        $table->renameColumn('staff_id', 'user_id');
    });
}
```

#### Migration 3: notes.user_id → staff_id

```php
// database/migrations/YYYY_MM_DD_HHMMSS_rename_user_id_to_staff_id_on_notes.php
public function up()
{
    Schema::table('notes', function (Blueprint $table) {
        $table->renameColumn('user_id', 'staff_id');
    });
}

public function down()
{
    Schema::table('notes', function (Blueprint $table) {
        $table->renameColumn('staff_id', 'user_id');
    });
}
```

#### Migration 4: email_labels.user_id → staff_id

```php
// Same pattern - renameColumn('user_id', 'staff_id')
```

#### Migration 5: documents.user_id → staff_id

```php
// Same pattern
```

#### Migration 6: mail_reports.user_id → staff_id

```php
// Same pattern
```

#### Migration 7: applications.user_id → staff_id

```php
// Same pattern
```

#### Migration 8: checkin_log.user_id → staff_id

```php
// Same pattern
```

#### Migration 9: sessions.user_id → staff_id

```php
// Same pattern - Laravel sessions table
```

#### Migration 10: account_client_receipts.user_id → staff_id

```php
// Same pattern
```

#### Migration 11: account_all_invoice_receipts.user_id → staff_id

```php
// Same pattern
```

#### Migration 12: booking_appointments.user_id → staff_id

```php
// Same pattern
```

#### Migration 13: client_matters.user_id → staff_id (conditional)

Only run if verification query confirms column exists.

#### Migration 14–15: device_tokens, refresh_tokens user_id → client_id

```php
// For device_tokens and refresh_tokens:
$table->renameColumn('user_id', 'client_id');
// Verify no client_id column already exists
```

### 4.5 Foreign Key Handling

If a table has a foreign key on `user_id`, the migration must:

1. Drop the foreign key constraint
2. Rename the column
3. Re-add the foreign key on the new column name

Example for a table with FK:

```php
public function up()
{
    Schema::table('email_labels', function (Blueprint $table) {
        $table->dropForeign(['user_id']);  // Use actual constraint name from verification query
    });
    Schema::table('email_labels', function (Blueprint $table) {
        $table->renameColumn('user_id', 'staff_id');
    });
    Schema::table('email_labels', function (Blueprint $table) {
        $table->foreign('staff_id')->references('id')->on('admins')->onDelete('set null');
    });
}
```

Run the FK verification query and adjust each migration accordingly.

### 4.6 Model Updates (Apply with Phase 5)

| Model | File | Changes |
|-------|------|---------|
| `Admin` | `app/Models/Admin.php` | Add `staff_id` to `$fillable` if present; remove `user_id` from fillable if listed |
| `Lead` | `app/Models/Lead.php` | `createdBy()`: `'user_id'` → `'staff_id'` in belongsTo |
| `StaffLoginLog` | `app/Models/StaffLoginLog.php` | `$fillable` / `$casts`: `user_id` → `staff_id` |
| `Note` | `app/Models/Note.php` | `$fillable`: `user_id` → `staff_id`; `user()`: `'user_id'` → `'staff_id'` |
| `EmailLabel` | `app/Models/EmailLabel.php` | `$fillable`: `user_id` → `staff_id`; relationship FK |
| `Document` | `app/Models/Document.php` | `$fillable`: `user_id` → `staff_id`; relationship FK; `$lead->user_id` → `$lead->staff_id` |
| `MailReport` | `app/Models/MailReport.php` | `$fillable`: `user_id` → `staff_id` |
| `Application` | `app/Models/Application.php` | Relationship: `'user_id'` → `'staff_id'` |
| `CheckinLog` | `app/Models/CheckinLog.php` | `$fillable`: `user_id` → `staff_id`; relationship FK |
| `ClientMatter` | `app/Models/ClientMatter.php` | Add `staff_id` to `$fillable` if column exists |
| `AccountClientReceipt` | `app/Models/AccountClientReceipt.php` | `$fillable`: `user_id` → `staff_id`; relationship |
| `AccountAllInvoiceReceipt` | `app/Models/AccountAllInvoiceReceipt.php` | `$fillable`: `user_id` → `staff_id`; relationship |
| `BookingAppointment` | `app/Models/BookingAppointment.php` | `$fillable`: `user_id` → `staff_id` |
| `DeviceToken` | `app/Models/DeviceToken.php` | `$fillable`: `user_id` → `client_id`; relationship FK |
| `RefreshToken` | `app/Models/RefreshToken.php` | `$fillable`: `user_id` → `client_id`; relationship FK; scope/query |

---

## Phase 5: Full Codebase Update After Migrations

**⚠️ DO NOT APPLY — PLANNING ONLY**

Apply these changes **in the same release** as Phase 4 migrations. The code must use the new column names *after* migrations have run.

### 5.1 Constants

| File | Line | Current | New |
|------|------|---------|-----|
| `config/constants.php` | 55 | `'reception_user_id'` | `'reception_staff_id'` |
| `config/constants.php` | — | Add `.env` | `RECEPTION_STAFF_ID` (document; keep `RECEPTION_USER_ID` as fallback during transition) |

| File | Change |
|------|--------|
| `app/Http/Controllers/CRM/OfficeVisitController.php` | `config('constants.reception_user_id')` → `config('constants.reception_staff_id')` |

### 5.2 Models (Complete List)

| Model | File | Changes |
|-------|------|---------|
| Lead | `app/Models/Lead.php` | `belongsTo(..., 'user_id', ...)` → `'staff_id'` |
| Note | `app/Models/Note.php` | `$fillable` `user_id`→`staff_id`; `user()` relationship `user_id`→`staff_id` |
| StaffLoginLog | `app/Models/StaffLoginLog.php` | `$fillable` `user_id`→`staff_id` |
| CheckinLog | `app/Models/CheckinLog.php` | `$fillable` `user_id`→`staff_id`; `belongsTo` FK `user_id`→`staff_id` |
| Document | `app/Models/Document.php` | `$fillable` `user_id`→`staff_id`; relationship; `$lead->user_id`→`$lead->staff_id` (line ~294) |
| EmailLabel | `app/Models/EmailLabel.php` | `$fillable` `user_id`→`staff_id`; relationship; scope `user_id`→`staff_id` |
| MailReport | `app/Models/MailReport.php` | `$fillable` `user_id`→`staff_id`; relationship |
| Application | `app/Models/Application.php` | relationship `user_id`→`staff_id` |
| AccountClientReceipt | `app/Models/AccountClientReceipt.php` | `$fillable` `user_id`→`staff_id`; relationship |
| AccountAllInvoiceReceipt | `app/Models/AccountAllInvoiceReceipt.php` | `$fillable` `user_id`→`staff_id`; relationship |
| BookingAppointment | `app/Models/BookingAppointment.php` | `$fillable` `user_id`→`staff_id` |
| ClientMatter | `app/Models/ClientMatter.php` | Add `staff_id` to `$fillable` if column exists |
| DeviceToken | `app/Models/DeviceToken.php` | `$fillable` `user_id`→`client_id`; relationship; scope `user_id`→`client_id` |
| RefreshToken | `app/Models/RefreshToken.php` | `$fillable` `user_id`→`client_id`; relationship; scope/query |

### 5.3 Controllers — staff_id Updates

| File | Approx. Lines | Change (all `user_id` → `staff_id` for staff context) |
|------|----------------|-------------------------------------------------------|
| `app/Http/Controllers/CRM/ClientsController.php` | 1987, 2682, 2781, 2835, 2855, 2921, 2947, 2980, 3008, 3026, 3061, 3081, 3112, 3285, 3293, 3366, 3374, 5015, 5363, 5428, 5551, 5555, 5603, 5664, 5875, 6003, 6204, 6577, 6685, 6738 | `$obj->user_id`, `->user_id`, `'user_id'=>` → `staff_id` |
| `app/Http/Controllers/CRM/Leads/LeadConversionController.php` | 79 (if exists) | `$client->user_id`, `$matter->user_id` → `staff_id` |
| `app/Http/Controllers/CRM/Leads/LeadController.php` | 492 | `'user_id'` → `'staff_id'` |
| `app/Http/Controllers/CRM/ClientAccountsController.php` | 188, 354, 414, 465, 626, 812, 848, 991, 1033, 1123, 1478, 1530, 1834, 1929, 2361, 2595, 2647, 3021, 3182, 3247, 3285, 3410, 3475, 3513, 3637, 3701, 3739, 3903, 4332, 4334, 4438, 4591, 4731, 4905, 4989, 5142 | `$obj->user_id`, `'user_id'`, `$fetch->user_id`, `$document->user_id`, `$receipt->user_id` → `staff_id`; `AccountClientReceipt::select('user_id',...)` → `staff_id` |
| `app/Http/Controllers/CRM/ClientPortalController.php` | 642, 653, 1501, 1580, 1615, 1718, 1821, 1880 | `$obj->user_id`, `$doclist->user_id` → `staff_id` |
| `app/Http/Controllers/CRM/AssigneeController.php` | 155, 265, 739 | `where('user_id',...)`, `notes.user_id`, `$newAction->user_id` → `staff_id` |
| `app/Http/Controllers/CRM/CRMUtilityController.php` | 1011, 1267 | `$obj->user_id`, `'user_id'` → `staff_id` |
| `app/Http/Controllers/CRM/OfficeVisitController.php` | 125, 337, 504, 584 | `$obj->user_id`, `$CheckinLog->user_id`, `$objs->user_id`, `reception_user_id` → `staff_id` / `reception_staff_id` |
| `app/Http/Controllers/Auth/AdminLoginController.php` | 117, 153 | `$obj->user_id` → `staff_id` |
| `app/Http/Controllers/API/ClientPortalController.php` | 70, 81, 100, 185, 209, 257, 261, 290, 294, 560, 591, 615, 655, 656, 672, 680, 711, 719, 729, 773, 787, 796, 926 | Client Portal: `user_id` in device_tokens, refresh_tokens → `client_id`; error messages may stay as `user_id` for backward compat |
| `app/Http/Controllers/API/ClientPortalMessageController.php` | 272, 574, 730, 846, 953, 966, 1051 | Log keys; some refer to client → `client_id` where appropriate |
| `app/Services/ActiveUserService.php` | 45, 46, 49, 172 | `user_id` in sessions table → `staff_id` (sessions migration) |
| `app/Services/StaffLoginAnalyticsService.php` | 27, 60, 99, 138, 170, 174, 182, 199, 247, 251, 256, 258, 259 | `user_id` in StaffLoginLog queries → `staff_id`; keep `user_id` param name for API compat or rename to `staff_id` |
| `app/Services/DashboardService.php` | 436, 523 | `'user_id'`, `$note->user_id` → `staff_id` |
| `app/Services/SignatureAnalyticsService.php` | 264 | `'user_id'` in array → `staff_id` |

### 5.4 Views — staff_id Updates

| File | Change |
|------|--------|
| `resources/views/crm/assignee/assign_to_me.blade.php` | `$list->user_id`, `$listC->user_id` → `staff_id` (lines ~98, 274) |
| `resources/views/crm/assignee/action_completed.blade.php` | `$list->user_id` → `staff_id` (line ~276) |
| `resources/views/crm/officevisits/index.blade.php` | `CheckinLog::where('user_id',...)`, `$list->user_id` → `staff_id` (lines 12–14, 130) |
| `resources/views/crm/clients/tabs/visa_documents.blade.php` | `$fetch->user_id` → `staff_id` (lines 152, 246) |
| `resources/views/crm/clients/tabs/client_portal.blade.php` | `$document->user_id` → `staff_id` (line ~352) |
| `resources/views/crm/clients/tabs/appointments.blade.php` | `$appointmentlist->user_id`, `$appointmentlistslast->user_id` → `staff_id` (lines 19, 73) |
| `resources/views/crm/clients/detail.blade.php` | `$appointmentlist->user_id` → `staff_id` (line ~1413) |
| `resources/views/emails/quotaion.blade.php` | `$fetchedData->user_id` → `staff_id` (line 18) |
| `resources/views/crm/clients/modals/client-management.blade.php` | `name="user_id"` in hidden input → `name="staff_id"` (lines 16, 821) — **only if form posts to code that expects staff_id** |

### 5.5 API & Routes

| File | Change |
|------|--------|
| `routes/api.php` | Broadcast channel auth: `user_id` in logs → `staff_id` if logging staff; keep as-is if channel is client |
| `app/Http/Controllers/API/ClientPortalController.php` | All `user_id` in device_tokens, refresh_tokens, personal_access_tokens → `client_id` for client context |

### 5.6 Request/Form Field Names

- Forms that post `user_id` (e.g. convert lead to client, assign staff) may need to send `staff_id` if backend expects it.
- Alternatively: keep form field `user_id` and map to `staff_id` in controller during transition.
- Decision: Prefer updating form `name="user_id"` → `name="staff_id"` for consistency.

### 5.7 StaffLoginAnalyticsService & Controller

The service queries `StaffLoginLog` which will have `staff_id` after migration. Update:

- `StaffLoginAnalyticsService`: All `->where('user_id', ...)`, `->select('user_id')`, `->groupBy('user_id')`, `$item->user_id` → `staff_id`
- Query param `user_id` in API: consider keeping for backward compat or add `staff_id` as alias
- `resources/views/crm/staff-login-analytics/index.blade.php`: `params.append('user_id', ...)` → `params.append('staff_id', ...)` (and update controller to read `staff_id`)

### 5.8 Verification Commands After Phase 5

```bash
# Should return no results (or only comments/docs) after Phase 5
rg "->user_id|'user_id'|\.user_id" app/ --type php
rg "user_id" resources/views/ --type blade
rg "user_id" app/Models/
```

### 5.9 client_matters Cleanup (If user_id Column Does NOT Exist)

If verification query in 4.1 shows `client_matters` has no `user_id` column:

| File | Remove/Change |
|------|---------------|
| `app/Http/Controllers/CRM/Leads/LeadConversionController.php` | Remove `$matter->user_id = ...` |
| `app/Http/Controllers/CRM/ClientsController.php` | Remove `$matter->user_id = ...` and `$obj->user_id = ...` (lines ~5552, 5555) |

---

## Phase 6: Testing Checklist

### 6.1 Client/Lead Features
- [ ] Lead creation and assignment (uses `admins.user_id` → becomes `staff_id`)
- [ ] Lead-to-client conversion (sets `user_id` in multiple places)
- [ ] Client detail view (displays client info, not user info)
- [ ] Client search and filtering

### 6.2 Staff Assignment Features
- [ ] Assign Staff modal (select staff, assign to client/note)
  - [ ] Modal opens and displays staff list correctly
  - [ ] Search/filter staff works
  - [ ] Select all/none buttons work
  - [ ] Assignment saves correctly
- [ ] Dashboard "Assign Staff" functionality
- [ ] Assignee pages (index, completed, assign_to_me, action_completed)
  - [ ] Staff names display correctly (not "user_name")
  - [ ] Re-assign staff works

### 6.3 Staff Activity Tracking
- [ ] Staff login analytics (page loads, data displays)
- [ ] Office visits / check-in log (staff check-ins work)
- [ ] Staff activity logs (`staff_login_logs` table)
- [ ] Reception staff notifications (uses `reception_staff_id`)

### 6.4 Document & Content Ownership
- [ ] Document upload and ownership (uses `documents.user_id` → becomes `staff_id`)
- [ ] Notes creation and assignment (uses `notes.user_id` → becomes `staff_id`)
- [ ] Email labels (staff-owned labels work)
- [ ] Mail reports (email tracking by staff)

### 6.5 Client Portal
- [ ] Client Portal login and authentication
- [ ] Client Portal messaging (unread count, send/receive)
- [ ] Client Portal API (device tokens, refresh tokens with `user_id` or `client_id`)
- [ ] Client Portal mobile app (if applicable)

### 6.6 Notifications & Communication
- [ ] Broadcast notifications (staff-to-staff)
- [ ] Client notifications (staff-to-client)
- [ ] SMS notifications

### 6.7 Regression Testing
- [ ] Full regression test suite
- [ ] Check all pages that list staff (dropdowns, selects)
- [ ] Check all pages that display client/lead info
- [ ] Verify no broken queries (grep for old column names)

---

## Execution Order Summary

| Phase | Description | Risk |
|-------|-------------|------|
| 1 | Code-only (variables, UI text, comments) | Low |
| 2 | JS/CSS identifiers (Assign Staff modal) | Low |
| 3 | Route/view rename (user-login-analytics) | Low |
| 4 | Database migrations (column renames) | **High** |
| 5 | Codebase update for new column names | Medium |
| 6 | Testing | — |

**Recommended approach:** Phases 1–3 are done. For Phase 4–5: schedule maintenance window, run migrations, deploy Phase 5 code changes, verify.

### Phase 4–5 Execution Order

1. **Backup** database.
2. **Run Phase 4 migrations** (all 13–15 migration files).
3. **Deploy Phase 5 code** (models, controllers, views, constants, services).
4. **Test** per Phase 6 checklist.
5. **Rollback** if needed: `php artisan migrate:rollback` (each migration), then revert Phase 5 code.

---

## Rollback Plan

- **Phase 1–2:** Revert commits; no DB impact.
- **Phase 3:** If route was renamed, update bookmarks/links or use route alias to maintain backward compatibility.
- **Phase 4:** Run `php artisan migrate:rollback` for each migration. Ensure `down()` correctly restores old column names.
- **Phase 5:** Revert code changes; if migrations rolled back, old column names work again.

---

## Additional Notes & Recommendations

### 1. Client Matters `user_id` Column Investigation
Before starting Phase 4, run this query to check if the column exists:
```sql
SELECT column_name FROM information_schema.columns 
WHERE table_name = 'client_matters' AND column_name = 'user_id';
```

If it doesn't exist, clean up the controller code that tries to set it.

### 2. Foreign Key Considerations
When renaming columns with foreign keys:
1. Drop the foreign key first
2. Rename the column
3. Re-create the foreign key with the new column name

Example migration:
```php
public function up()
{
    Schema::table('admins', function (Blueprint $table) {
        // Drop FK if it exists
        $table->dropForeign(['user_id']); // or specific constraint name
        
        // Rename column
        $table->renameColumn('user_id', 'staff_id');
        
        // Re-add FK (update this based on your target table: staff or admins)
        $table->foreign('staff_id')->references('id')->on('staff')->onDelete('set null');
    });
}
```

### 3. Staff Table Migration Dependency
If you're also implementing the dedicated `staff` table migration from `PLAN_DEDICATED_STAFF_TABLE.md`, coordinate the timing:
- Option A: Do staff table migration first, then rename columns to reference `staff` table
- Option B: Rename columns first (keeping FK to `admins`), then migrate to `staff` table and update FKs

**Recommended:** Option A (staff table first) to avoid renaming FKs twice.

### 4. Search for Hardcoded String References
After completing all phases, search for any missed references:
```bash
rg -i "assign user|assign-user" --type php --type blade
rg "user_id|user_name|userInfo|userName" app/ resources/
rg "filterUsers|assignUser" public/js/
```

### 5. API Breaking Changes
If Phase 4 renames `device_tokens.user_id` → `client_id`, this MAY break mobile API if:
- Mobile app sends `user_id` in requests
- API returns `user_id` in responses

**Mitigation:** Add API versioning or temporary field aliases during transition period.

---

## Quick Reference: Files Touched in Phase 5

| Category | Count | Files |
|----------|-------|-------|
| Config | 1 | `config/constants.php` |
| Models | 14+ | Lead, Note, StaffLoginLog, CheckinLog, Document, EmailLabel, MailReport, Application, AccountClientReceipt, AccountAllInvoiceReceipt, BookingAppointment, ClientMatter, DeviceToken, RefreshToken |
| Controllers | 12+ | ClientsController, LeadConversionController, LeadController, ClientAccountsController, ClientPortalController, AssigneeController, CRMUtilityController, OfficeVisitController, AdminLoginController, ClientPortalController (API), ClientPortalMessageController |
| Services | 4 | ActiveUserService, StaffLoginAnalyticsService, DashboardService, SignatureAnalyticsService |
| Views | 8+ | assign_to_me, action_completed, officevisits, visa_documents, client_portal, appointments, detail, quotaion, client-management |
| Migrations | 13–15 | One per table in Phase 4.2–4.3 |

---

*End of plan. Phases 4–5: DO NOT APPLY until approved. Review complete.*
