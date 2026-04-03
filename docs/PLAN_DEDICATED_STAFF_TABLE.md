# Plan: Dedicated Staff Table Migration

**Status:** In progress — Phase 1 & 2 DONE  
**Created:** Feb 2026  
**Scope:** Extract staff users from `admins` table into a dedicated `staff` table; keep clients/leads in `admins`.

---

## ✅ Completed (Do Not Redo)

- **Staff table created** — Migration `2026_02_14_000000_create_staff_table.php` has been run.
- **Staff data migrated** — Migration `2026_02_14_000001_copy_staff_from_admins_to_staff.php` has copied all staff (role != 7) from `admins` to `staff`, preserving IDs.
- **Auth config updated** — `admin` guard now uses `staff` provider; CRM login authenticates against `Staff` model.
- **Code references updated** — `role != 7` checks replaced with `Staff` model / `instanceof` where appropriate; client validation uses `type` (with role fallback) for future role removal:
  - `DocumentPolicy::create` → `$user instanceof Staff`
  - `ClientPortalController::adminLogin` → uses `Staff` model
  - Client portal guards → `$user instanceof Admin` (clients = Admin, staff = Staff)
  - `LeadController`, `ClientsController` → validate client/lead via `type` with role fallback
  - `ClientPortalController::forgotPassword`, `refresh` → use `type` with role fallback for client check

**Do not** re-run staff table creation or data copy migrations. Subsequent steps build on this.

---

## Deep Audit: role != 7 / Staff vs Admin

Audit of all code that distinguishes staff (role != 7) from clients/leads (role = 7). Items needing replacement with `Staff` model are flagged.

### ✅ Already Using Staff (no change needed)

| Location | Usage |
|----------|-------|
| `AssigneeController` | `Staff::where()`, `Staff::orderby()` for assignees |
| `OfficeVisitController` | `Staff::orderby()` for staff list |
| `DashboardService::getAssignees()` | `Staff::select()->where('role','!=',1)` (excludes super admin) |
| `BroadcastNotificationService::allRecipients()` | `Staff::query()->where('status',1)` |
| `ActiveStaffService` | Uses `Staff` model throughout |
| `StaffLoginAnalyticsService` | `Staff::find()` |
| `BookingAppointment` | `belongsTo(Staff::class)` for assigned_by |
| Views: assign_by_me, assign_to_me, action_completed, modals, client-management | `Staff::where('status',1)` for staff dropdowns |

### ✅ Correct: Admin for Clients (role = 7)

These use `Admin::where('role', 7)` or `where('type','client')` to fetch **clients/leads**. Do not replace with Staff.

- ClientsController, LeadController, ClientImportService, ClientExportService, etc.
- OfficeVisitController, CheckinLog, DashboardService (client lookup)
- ArtSheetController, VisaTypeSheetController, TrSheetController, EoiRoiSheetController
- SignatureDashboardController, ClientReferenceService, Form956Controller, ReportController
- Console commands: UpdateClientAges, ResetSampleAppointments, FixDuplicateClientReferences, VisaExpireReminderEmail
- BansalAppointmentSync services
- `ClientQueries` trait, `Lead` model
- Views: companies/detail, clients/detail, offices/view, offices/viewclient

### ⚠️ May Need Staff Replacement

| File | Line | Current | Action |
|------|------|---------|--------|
| `ProcessServiceAccountTokens` | 51 | `Admin::where('status', 1)->get()` | If service tokens are for **staff** API access, switch to `Staff::where('status', 1)`. If for clients, keep Admin. |
| `LeadAnalyticsService` | 100 | `Admin::where('type', 'admin')` | `admins.type` is typically `client`/`lead`. Likely broken. Replace with `Staff::query()` for agent metrics. |

### ✅ Intentional (keep as-is)

- **Migrations** (`copy_staff_from_admins_to_staff`, etc.): Historical; copy logic uses `role != 7`.
- **BroadcastNotificationService** line 505: Comment only; implementation already uses Staff.

### Summary

- **Staff fetches:** All main staff lists/dropdowns use `Staff` model. No remaining `Admin::where('role','!=',7)` in application code.
- **Client fetches:** All use `Admin::where('role', 7)` or type filters; correct.
- **Review recommended:** ProcessServiceAccountTokens, LeadAnalyticsService.

---

## Risks and Mitigation

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| **Session collision** – Staff and client IDs overlap, causing login to wrong account | ~~Critical~~ **RESOLVED** | ~~Medium~~ N/A | ✅ Only staff use sessions; clients use API only (no CRM login) |
| **FK constraint violations** – Orphaned FKs after migration | High | Low | Verify all staff IDs exist in staff table before changing FKs; use transactions; use mapping table for ID translation |
| **Password reset broken** – Staff can't reset password | High | Low | Add `staff` broker to auth config; test before go-live |
| **Client Portal API broken** – Clients can't log in | Critical | Low | Keep `api` guard on `admins` provider; test Client Portal after migration |
| **Polymorphic references incorrect** – Messages/notifications point to wrong table | Medium | Medium | Backfill type columns carefully; add tests for mixed sender/recipient |
| **Performance degradation** – More joins for staff/client queries | Low | Low | Add indexes on FK columns; monitor query performance |
| **Data loss on rollback** – New staff/client records created post-migration | Medium | Low | Keep staff in admins during migration; only drop after stability |
| **Code references old model** – Some controller still uses Admin for staff | ~~Medium~~ **Largely resolved** | Low | Main staff queries use Staff; audit above. ProcessServiceAccountTokens, LeadAnalyticsService may need review. |
| **All staff locked out** – Auth config error | Critical | Low | Test login in staging; have rollback ready; deploy in maintenance window |
| **FK migration incomplete** – Some tables still point to admins | Medium | Medium | Checklist of all 30+ tables; verify each FK after migration; mapping table ensures consistency |
| **ID mapping errors** – Mapping table incorrect after copy | High | Low | Verify mapping count = staff count; spot-check sample mappings; use transactions |

**Top 3 risks to address first:**
1. **FK migration with ID mapping** – Must use mapping table since IDs overlap; verify all 30+ FK updates use mapping correctly.
2. **All staff locked out** – Thorough staging testing; maintenance window with rollback plan.
3. **Client Portal API unchanged** – Ensure `api` guard stays on `admins`; no impact to mobile app authentication.

---

## Executive Summary

The `admins` table currently stores **staff** (internal users) and **clients/leads** (customers), distinguished by `role`: `role = 7` = client, `role != 7` = staff (~96 staff, ~9,345 clients/leads). This plan outlines migrating staff into a dedicated `staff` table, introducing a new auth guard, and updating all foreign key references.

**Estimated effort:** High (multi-day to multi-week).  
**Risk:** High. Auth, session, and FK changes touch core authentication and many controllers/models. Requires careful testing and rollback plan.

---

## Current State

### Staff Identification

| Criteria | Staff | Clients/Leads |
|----------|-------|---------------|
| `role` | `!= 7` (1, 12, 13, 16, etc.) | `= 7` |
| Count | ~96 | ~9,345 |
| Auth | Yes (login to CRM) | Clients use Client Portal; leads no login |

### Auth Configuration (`config/auth.php`) — UPDATED

- Default guard: `admin` (uses `staff` provider)
- Provider `admins` → `App\Models\Admin` (clients/leads)
- Provider `staff` → `App\Models\Staff`
- Guard `admin` → `staff` provider (CRM login = Staff model)
- Guard `api` → `admins` provider (Client Portal = Admin model)
- Sessions store `user_id` = `staff.id` for CRM

### Staff-Specific Columns (in `admins`)

| Column | Usage |
|--------|-------|
| `position` | Job title (AdminConsole) |
| `team` | Department (AdminConsole, ActiveUserService filter) |
| `permission` | Granular access (Notes, Documents, etc.) |
| `time_zone` | Optional (StaffController::savezone) |
| `office_id` | FK to branches |
| `role` | FK to user_roles (staff roles) |
| `show_dashboard_per` | Dashboard permission |
| `is_migration_agent` | Migration agent flag |
| `marn_number`, `business_*`, etc. | Migration agent details (staff who are also agents) |

---

## Phase 1: Create Staff Table and Model

### 1.1 `staff` Table Schema

```sql
-- staff table (new)
CREATE TABLE staff (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(255) NULL,
    last_name VARCHAR(255) NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    country_code VARCHAR(20) NULL,
    phone VARCHAR(100) NULL,
    -- telephone, profile_img removed (Feb 2026): use phone+country_code, static avatar.png
    status TINYINT DEFAULT 1,
    verified TINYINT DEFAULT 0,
    
    -- Staff-specific
    role INT NULL,                    -- FK to user_roles
    position VARCHAR(255) NULL,
    team VARCHAR(255) NULL,
    permission TEXT NULL,
    office_id BIGINT UNSIGNED NULL,
    show_dashboard_per TINYINT DEFAULT 0,
    time_zone VARCHAR(50) NULL,
    
    -- Migration agent (staff can be migration agents)
    is_migration_agent TINYINT DEFAULT 0,
    marn_number VARCHAR(100) NULL,
    legal_practitioner_number VARCHAR(100) NULL,
    company_name VARCHAR(255) NULL,
    business_address TEXT NULL,
    business_phone VARCHAR(100) NULL,
    business_mobile VARCHAR(100) NULL,
    business_email VARCHAR(255) NULL,
    tax_number VARCHAR(100) NULL,
    ABN_number VARCHAR(100) NULL,
    company_website VARCHAR(500) NULL,
    
    -- Archive
    is_archived TINYINT DEFAULT 0,
    archived_by BIGINT UNSIGNED NULL,  -- Self-referential: staff who archived
    archived_on TIMESTAMP NULL,
    
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (office_id) REFERENCES branches(id) ON DELETE SET NULL,
    FOREIGN KEY (role) REFERENCES user_roles(id) ON DELETE SET NULL,
    FOREIGN KEY (archived_by) REFERENCES staff(id) ON DELETE SET NULL
);
```

**Notes:**
- Removed `visa_expiry_verified_at` / `visa_expiry_verified_by` – these are for verifying CLIENT data, not staff data. Keep on `admins` table where client verification audit lives.
- `archived_by` is self-referential (staff archiving another staff).
- **Missing from clients:** `client_id`, `client_counter`, `dob`, `age`, `gender`, `marital_status`, passport, visa, EOI fields, etc. (all client-specific).

### 1.2 Create `Staff` Model

```php
<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Kyslik\ColumnSortable\Sortable;

class Staff extends Authenticatable
{
    use Notifiable, HasApiTokens, HasFactory, Sortable;

    protected $table = 'staff';
    protected $guard = 'admin';  // Staff use the 'admin' guard

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password',
        'country_code', 'phone',  // telephone, profile_img removed
        'status', 'verified',
        'role', 'position', 'team', 'permission', 'office_id',
        'show_dashboard_per', 'time_zone',
        'is_migration_agent', 'marn_number', 'legal_practitioner_number',
        'company_name', 'business_address', 'business_phone', 'business_mobile',
        'business_email', 'tax_number', 'ABN_number', 'company_website',
        'is_archived', 'archived_by', 'archived_on',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'status' => 'integer',
        'verified' => 'integer',
        'show_dashboard_per' => 'integer',
        'is_migration_agent' => 'integer',
        'is_archived' => 'integer',
        'archived_on' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $sortable = [
        'id', 'first_name', 'last_name', 'email', 'status', 'created_at', 'updated_at',
    ];

    // Relationships
    public function office()
    {
        return $this->belongsTo(\App\Models\Branch::class, 'office_id');
    }

    public function usertype()
    {
        return $this->belongsTo(\App\Models\UserRole::class, 'role', 'id');
    }

    public function archivedBy()
    {
        return $this->belongsTo(\App\Models\Staff::class, 'archived_by');
    }

    // Clients assigned to this staff member
    public function assignedClients()
    {
        return $this->hasMany(\App\Models\Admin::class, 'agent_id');
    }

    // Attribute accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
```

### 1.3 Migration: `create_staff_table`

- Create table as above
- No data copy yet (Phase 3)

---

## Phase 2: Auth Guard and Provider

### 2.1 Update `config/auth.php`

**Based on your requirements:**
- **Staff ONLY** use CRM (login via web/session)
- **Clients** use Client Portal mobile app (API only, no CRM login)
- Clients interact with CRM data via API in "Client Portal" tab

```php
'defaults' => [
    'guard' => 'admin',
    'passwords' => 'staff',  // CHANGE: Staff password resets by default
],

'providers' => [
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,  // Keep for clients (API only)
    ],
    'staff' => [
        'driver' => 'eloquent',
        'model' => App\Models\Staff::class,  // NEW: Staff provider
    ],
    'providers' => [
        'driver' => 'eloquent',
        'model' => App\Provider::class,
    ],
],

'guards' => [
    'admin' => [
        'driver' => 'session',
        'provider' => 'staff',  // CHANGE: Staff use admin guard (CRM login)
    ],
    'web' => [
        'driver' => 'session',
        'provider' => 'staff',  // CHANGE: Web is for staff CRM access
    ],
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'admins',  // KEEP: Clients use API guard (Client Portal app)
    ],
    'provider' => [
        'driver' => 'session',
        'provider' => 'providers',
    ],
],
```

**Key decisions:**
- `admin` and `web` guards → Staff (CRM access)
- `api` guard → Clients/Admins (Client Portal mobile app)
- No guard changes needed for middleware (still `auth:admin` for CRM routes)

### 2.2 Middleware

- AdminConsole and CRM routes use `auth:admin` — after change, `admin` guard uses `staff` provider, so no middleware change if guard name stays.
- If new guard name `staff`: update all `auth:admin` → `auth:staff` in routes/middleware.

**Recommendation:** Keep guard name `admin` but point to `staff` provider. Minimizes route/middleware changes.

### 2.3 Login Controller

- `AdminLoginController`: After credentials check, authenticate against `Staff` model.
- Update to use `Staff::where('email', $email)->first()` and `Auth::guard('admin')->login($staff)`.

### 2.4 Sessions Table

- Sessions table stores `user_id`. After migration, `user_id` = `staff.id` (not `admins.id`).
- **Critical:** Run migration **after** data copy. During transition, consider storing `staff_id` in session or a mapping.
- **Important:** After auth config change, ALL STAFF MUST RE-LOGIN. Flush all sessions before go-live.

---

## Phase 3: Data Migration

### 3.1 Migration: `copy_staff_from_admins_to_staff`

```php
// Migration pseudocode
DB::transaction(function () {
    // Step 1: Copy staff to staff table (new auto-increment IDs)
    $staffRows = DB::table('admins')
        ->where('role', '!=', 7)
        ->whereNull('is_deleted')
        ->orderBy('id')  // Consistent order
        ->get();

    foreach ($staffRows as $row) {
        $newStaffId = DB::table('staff')->insertGetId([
            // Do NOT set 'id' - let auto-increment generate new IDs
            'first_name' => $row->first_name,
            'last_name' => $row->last_name,
            'email' => $row->email,
            'password' => $row->password,
            'country_code' => $row->country_code,
            'phone' => $row->phone,
            // telephone, profile_img removed
            'status' => $row->status,
            'verified' => $row->verified,
            'role' => $row->role,
            'position' => $row->position,
            'team' => $row->team,
            'permission' => $row->permission,
            'office_id' => $row->office_id,
            'show_dashboard_per' => $row->show_dashboard_per,
            'time_zone' => $row->time_zone,
            'is_migration_agent' => $row->is_migration_agent,
            'marn_number' => $row->marn_number,
            'legal_practitioner_number' => $row->legal_practitioner_number,
            'company_name' => $row->company_name,
            'business_address' => $row->business_address,
            'business_phone' => $row->business_phone,
            'business_mobile' => $row->business_mobile,
            'business_email' => $row->business_email,
            'tax_number' => $row->tax_number,
            'ABN_number' => $row->ABN_number,
            'company_website' => $row->company_website,
            'is_archived' => $row->is_archived,
            // archived_by will be updated after all staff copied
            'archived_on' => $row->archived_on,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);

        // Step 2: Record mapping
        DB::table('admin_staff_id_mapping')->insert([
            'old_admin_id' => $row->id,
            'new_staff_id' => $newStaffId,
        ]);
    }

    // Step 3: Update self-referential FKs (archived_by) using mapping
    DB::statement('
        UPDATE staff s
        JOIN admin_staff_id_mapping m ON s.archived_by = m.old_admin_id
        SET s.archived_by = m.new_staff_id
        WHERE s.archived_by IS NOT NULL
    ');
});
```

**Decision: MUST USE OPTION B (New IDs + Mapping Table)**

Reason: Database analysis shows ID overlap (staff 1-45515, clients 2-45956). Cannot preserve IDs.

**ID strategy:**

- **Option A – Preserve IDs:** Copy `admins.id` to `staff.id`. Simplifies FK migration: tables pointing to staff keep same ID. Downside: `staff` and `admins` can't share IDs for clients.
- **Option B – New IDs:** Generate new `staff.id`. Need `staff_id` column on all tables that referenced staff; migrate FKs; more work.

**Database Analysis Results:**
- Staff: 96 records, ID range 1 - 45515
- Clients: 9345 records, ID range 2 - 45956
- **⚠️ OVERLAP DETECTED** - IDs are interleaved (staff and clients share same range)

**Decision: MUST USE OPTION B (New IDs + Mapping Table)**

Create `admin_staff_id_mapping` table:
```sql
CREATE TABLE admin_staff_id_mapping (
    old_admin_id BIGINT UNSIGNED NOT NULL,
    new_staff_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (old_admin_id),
    UNIQUE KEY (new_staff_id),
    FOREIGN KEY (new_staff_id) REFERENCES staff(id) ON DELETE CASCADE
);
```

Migration will:
1. Copy staff to `staff` table with NEW auto-increment IDs starting from 1
2. Record mapping: `old_admin_id` (from admins) → `new_staff_id` (in staff)
3. Use mapping to update all FK columns

### 3.2 Verify Row Count and Mapping

```php
// Verification checks
$staffCount = DB::table('staff')->count();
$mappingCount = DB::table('admin_staff_id_mapping')->count();
$originalStaffCount = DB::table('admins')->where('role', '!=', 7)->whereNull('is_deleted')->count();

assert($staffCount === $originalStaffCount, "Staff count mismatch");
assert($mappingCount === $originalStaffCount, "Mapping count mismatch");

// Spot-check: Compare email, name for sample staff
$sample = DB::table('admin_staff_id_mapping')
    ->join('admins', 'admin_staff_id_mapping.old_admin_id', '=', 'admins.id')
    ->join('staff', 'admin_staff_id_mapping.new_staff_id', '=', 'staff.id')
    ->select([
        'admins.id as old_id',
        'staff.id as new_id',
        'admins.email as old_email',
        'staff.email as new_email',
        'admins.first_name as old_name',
        'staff.first_name as new_name',
    ])
    ->limit(10)
    ->get();

foreach ($sample as $row) {
    assert($row->old_email === $row->new_email, "Email mismatch for ID {$row->old_id}");
    assert($row->old_name === $row->new_name, "Name mismatch for ID {$row->old_id}");
}

echo "✓ Verification passed: {$staffCount} staff copied, mapping table complete\n";
```

---

## Phase 4: Foreign Key Updates

### 4.1 Tables That Reference Staff (Change FK to `staff`)

| Table | Column | Current FK | New FK | Notes |
|-------|--------|-----------|--------|-------|
| `admins` | `agent_id` | admins (staff) | staff | Clients assigned to staff agent |
| `admins` | `archived_by` | admins | staff | Staff who archived |
| `admins` | `visa_expiry_verified_by` | admins | staff | Staff who verified |
| `admins` | `user_id` (leads) | admins (staff) | staff | Lead assigned to staff |
| `booking_appointments` | `assigned_by_admin_id` | admins | staff | Rename to `assigned_by_staff_id` |
| `client_eoi_references` | `checked_by` | admins | staff | Staff verification |
| `client_eoi_references` | `created_by`, `updated_by` | admins | staff | Audit trail |
| `client_art_references` | `verified_by`, `created_by`, `updated_by` | admins | staff | Staff actions |
| `client_matters` | `sel_migration_agent`, `sel_person_responsible`, `sel_person_assisting` | admins | staff | Staff handling matter |
| `clientportal_details_audit` | `updated_by` | admins | staff | Staff making update |
| `client_emails` | `verified_by` | admins | staff | Staff verification |
| `client_contacts` | `verified_by` | admins | staff | Staff verification |
| `documents` | `created_by`, `user_id`, `checklist_verified_by` | admins | staff | Staff actions |
| `signature_activities` | `created_by` | admins | staff | Staff note |
| `email_labels` | `user_id` | admins | staff | Staff label owner |
| `notes` | `user_id`, `assigned_to` | admins | staff | Staff notes |
| `activities_log` | `created_by` | admins | staff | Staff action |
| `anzsco_occupations` | `created_by`, `updated_by` | admins | staff | Admin data |
| `sms_templates` | `created_by` | admins | staff | Staff template |
| `tags` | `created_by`, `updated_by` | admins | staff | Staff actions |
| `cost_assignment_forms` | `agent_id` | admins | staff | Migration agent |
| `form956` | `agent_id` | admins | staff | Migration agent |
| `mail_reports` | `user_id` | admins | staff | Staff email sender |
| `messages` | `sender_id` | admins | **POLYMORPHIC** | Staff OR client - see 4.1d |
| `message_recipients` | `recipient_id` | admins | **POLYMORPHIC** | Staff OR client |
| `notifications` | `sender_id`, `receiver_id` | admins | **POLYMORPHIC** | Staff OR client |
| `applications` | `user_id` | admins | staff | Staff creating application |
| `checkin_log` | `user_id` | admins | staff | Staff user |
| `checkin_history` | `created_by` | admins | staff | Staff action |
| `phone_verifications` | `verified_by` | admins | staff | Staff verification |
| `email_verifications` | `verified_by` | admins | staff | Staff verification |
| `staff_login_logs` | `user_id` | admins | staff | Staff login logs |
| `sessions` | `user_id` | admins | staff | Staff sessions (clients use API only) |

**REMOVED from migration (client-only, stay on admins):**
- ~~`device_tokens.user_id`~~ – Client Portal mobile app only
- ~~`refresh_tokens.user_id`~~ – Client Portal mobile app only
- ~~`companies.contact_person_id`~~ – Clients only, not staff

### 4.1a Companies `contact_person_id` – CLIENT ONLY

**Your Answer:** Only clients are company contact persons, not staff.

**Solution:** Keep `contact_person_id` FK on `admins` (clients table). No change needed.

```sql
-- companies table
FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE  -- Company record
FOREIGN KEY (contact_person_id) REFERENCES admins(id) ON DELETE SET NULL  -- Client contact person
```

### 4.1b Tokens (`device_tokens`, `refresh_tokens`) – CLIENT ONLY

**Your Answer:** Only clients use Client Portal mobile app; staff don't login to mobile app.

**Solution:** Keep `user_id` FK on `admins` (clients). No polymorphic handling needed.

**Action:** No changes to `device_tokens` or `refresh_tokens` tables. These are Client Portal only.

### 4.1c Activities Log `created_by` – MIXED

**Issue:** `activities_log.created_by` is staff for CRM actions, but Client Portal activities have `source='client_portal'` and may not have `created_by` set.

**Solution:** `created_by` always references staff (system actions). Client-initiated activities use `client_id` field.

**Action:** Change `created_by` FK to `staff`; `client_id` stays on `admins`.

### 4.1d Messages/Notifications – MIXED (Staff-to-Client via API)

**Your Answer:** Clients use Client Portal mobile app and interact with CRM through API (one tab shows data).

**Issue:** `messages.sender_id` and `notifications.sender_id/receiver_id` can be:
- Staff sending to client (via CRM)
- Client sending to staff (via Client Portal API)
- Staff sending to staff (internal messages)

**Current migration status:** Message system uses `message_recipients` table (Oct 2025 migration).

**Solution:** Add polymorphic type columns for senders/recipients.

**Migration steps:**
1. Add columns to tables:
```sql
ALTER TABLE messages ADD COLUMN sender_type ENUM('staff', 'client') DEFAULT 'staff';
ALTER TABLE message_recipients ADD COLUMN recipient_type ENUM('staff', 'client') DEFAULT 'client';
ALTER TABLE notifications ADD COLUMN sender_type ENUM('staff', 'client') DEFAULT 'staff';
ALTER TABLE notifications ADD COLUMN receiver_type ENUM('staff', 'client') DEFAULT 'client';
```

2. Backfill based on role:
```sql
-- Backfill messages.sender_type
UPDATE messages m
JOIN admins a ON m.sender_id = a.id
SET m.sender_type = IF(a.role = 7, 'client', 'staff');

-- Similar for message_recipients, notifications
```

3. Update models with polymorphic relationships:
```php
// Message model
public function sender()
{
    return $this->morphTo('sender', 'sender_type', 'sender_id', 'id');
}

// In Staff model
public function sentMessages()
{
    return $this->morphMany(Message::class, 'sender');
}

// In Admin model (clients)
public function sentMessages()
{
    return $this->morphMany(Message::class, 'sender');
}
```

**After staff migration:** Update `sender_id` for staff messages to new staff.id using mapping table.

### 4.2 Tables That Reference Clients (Stay on `admins`)

- `client_id` on: client_eoi_references, client_art_references, documents, notes, activities_log, client_matters, client_relationships, clientportal_details_audit, booking_appointments, etc.
- `admin_id` on companies (company record in admins)
- `related_client_id` on client_* tables

**No change** for client references.

### 4.3 Migration Strategy for FKs (Using Mapping Table)

**Since we're using Option B (new IDs), all FK migrations must use the mapping table.**

**Example migration for one table:**

```php
// Example: Update admins.agent_id (clients assigned to staff)
DB::transaction(function () {
    // Step 1: Add temp column
    Schema::table('admins', function (Blueprint $table) {
        $table->unsignedBigInteger('agent_id_new')->nullable()->after('agent_id');
    });

    // Step 2: Copy mapped IDs
    DB::statement('
        UPDATE admins a
        JOIN admin_staff_id_mapping m ON a.agent_id = m.old_admin_id
        SET a.agent_id_new = m.new_staff_id
        WHERE a.agent_id IS NOT NULL
    ');

    // Step 3: Verify no nulls where agent_id was set
    $unmapped = DB::table('admins')
        ->whereNotNull('agent_id')
        ->whereNull('agent_id_new')
        ->count();
    if ($unmapped > 0) {
        throw new Exception("Found {$unmapped} unmapped agent_id values");
    }

    // Step 4: Drop old FK and column
    Schema::table('admins', function (Blueprint $table) {
        $table->dropColumn('agent_id');
    });

    // Step 5: Rename new column
    Schema::table('admins', function (Blueprint $table) {
        $table->renameColumn('agent_id_new', 'agent_id');
    });

    // Step 6: Add FK to staff
    Schema::table('admins', function (Blueprint $table) {
        $table->foreign('agent_id')->references('id')->on('staff')->onDelete('set null');
    });
});
```

**Repeat for all 30+ tables in Phase 4.1 list.**

**Staff-only FK columns to migrate (with mapping):**
- `admins`: agent_id, archived_by, visa_expiry_verified_by, user_id (leads)
- `booking_appointments`: assigned_by_admin_id → assigned_by_staff_id
- `client_eoi_references`: checked_by, created_by, updated_by
- `client_art_references`: verified_by, created_by, updated_by
- `client_matters`: sel_migration_agent, sel_person_responsible, sel_person_assisting
- `clientportal_details_audit`: updated_by
- `client_emails`, `client_contacts`: verified_by
- `documents`: created_by, user_id, checklist_verified_by
- `signature_activities`: created_by
- `email_labels`: user_id
- `notes`: user_id, assigned_to
- `activities_log`: created_by
- `anzsco_occupations`: created_by, updated_by
- `sms_templates`: created_by
- `tags`: created_by, updated_by
- `cost_assignment_forms`, `form956`: agent_id
- `mail_reports`: user_id
- `applications`: user_id
- `checkin_log`: user_id
- `checkin_history`: created_by
- `phone_verifications`, `email_verifications`: verified_by
- `staff_login_logs`: user_id
- `sessions`: user_id

**Messages/notifications:** Update after polymorphic type columns added (see 4.1d).

### 4.4 Sessions and Password Resets

**Sessions:**
- `sessions.user_id` → references `staff.id` after migration for staff sessions.
- **Your answer:** Only staff use CRM (session-based login). Clients use mobile app (API only).
- **Solution:** Sessions table is STAFF ONLY. No client sessions. No collision risk.
- **Action:** After migration, `sessions.user_id` references new `staff.id` (from mapping table).

**Password Resets:**
- Current: `password_reset_tokens` table with `email` column (no FK).
- Laravel checks `email` against provider model (Staff or Admin).
- **Action:** Update `config/auth.php` passwords config:
  - `admins` broker → uses `admins` provider (clients)
  - `staff` broker → uses `staff` provider (staff password resets)
- Staff password reset: use broker `staff`; clients use broker `admins`.

**Updated `config/auth.php` passwords section:**

```php
'passwords' => [
    'admins' => [
        'provider' => 'admins',  // For clients
        'table' => 'password_reset_tokens',
        'expire' => 15,
    ],
    'staff' => [
        'provider' => 'staff',  // For staff
        'table' => 'password_reset_tokens',
        'expire' => 15,
    ],
    'providers' => [
        'provider' => 'providers',
        'table' => 'password_reset_tokens',
        'expire' => 60,
    ],
],
```

**Default broker:** Change `defaults.passwords` to `'staff'` (staff use default reset form).

---

## Phase 5: Model and Controller Updates

### 5.1 Models – BelongsTo Staff

For each model in Phase 4.1, add or update:

```php
// Example: Document
public function createdBy(): BelongsTo
{
    return $this->belongsTo(Staff::class, 'created_by');
}
```

**Models to update (partial list):**

- `Admin` (agent, archivedBy, visaExpiryVerifications, etc.)
- `ClientEoiReference`
- `ClientArtReference`
- `ClientMatter`
- `Document`
- `Note`
- `ActivitiesLog`
- `Company` (contactPerson)
- `BookingAppointment`
- `Tag`, `SmsTemplate`, `AnzscoOccupation`
- `RefreshToken`, `DeviceToken`
- `Message`, `MessageRecipient`, `Notification`
- `EmailLabel`, `SignatureActivity`
- `CostAssignmentForm`, `Form956`
- `MailReport`, `Application`
- `CheckinLog`, `CheckinHistory`
- `PhoneVerification`, `EmailVerification`
- `ClientEmail`, `ClientContact`
- `ClientPortalDetailAudit`
- `StaffLoginLog`

### 5.2 Models – HasMany Staff

- `Branch`: `staff()` instead of `admins()` for office_id.
- `UserRole`: staff relationship if needed.

### 5.3 Controllers – Auth::user()

All `Auth::user()` and `auth()->user()` in CRM/AdminConsole return `Staff` after migration. Update type hints if used:

```php
/** @var Staff $user */
$user = Auth::user();
```

No logic change if `Staff` has same interface (id, first_name, last_name, email, etc.).

### 5.4 Controllers – Staff Queries

Replace:

```php
Admin::where('role', '!=', 7)->...
```

with:

```php
Staff::query()...
```

**Files to update:**

- `ClientController` (adminconsole) – clients only; `StaffController` – use `Staff` for CRUD
- `ActiveUserService` – use `Staff`
- Dashboard, assignee, booking views – staff dropdowns
- `Lead` model – `user_id` → `staff_id` (or keep user_id pointing to staff)
- All views that list staff: `Admin::where('role','!=',7)` → `Staff::all()` or `Staff::active()`

### 5.5 Views

- `AdminConsole.system.clients.*` – clients (role=7); `AdminConsole.staff.*` – staff from Staff table
- Dropdowns: `Admin::where('role','!=',7)` → `Staff::where('status', 1)`
- Links: `adminconsole.staff.view` – pass `Staff` id

---

## Phase 6: Remove Staff from Admins (Optional, Deferred)

**Do NOT drop staff rows from admins in initial release.** Keep them for rollback. Optionally:

1. Add `migrated_to_staff_at` timestamp on admins for staff rows.
2. In a later phase, after stability, delete staff rows from admins (or soft-delete).

---

## Phase 7: Client Portal and API

### 7.1 Client Portal Auth

- Clients (`role=7`) stay in `admins`.
- Client Portal login: use existing `auth:api` with Sanctum, or create separate `client` guard.
- **Current state:** Client Portal API uses `auth:api` which currently points to `admins` provider.

**After staff migration:**

```php
// Option 1: Keep API guard for clients
'guards' => [
    'api' => [
        'driver' => 'sanctum',
        'provider' => 'admins',  // Clients only
    ],
    // ... staff guards use 'staff' provider
],
```

**OR Option 2: Separate client guard:**

```php
'guards' => [
    'client' => [
        'driver' => 'sanctum',
        'provider' => 'admins',
    ],
    'staff-api' => [
        'driver' => 'sanctum',
        'provider' => 'staff',
    ],
],
```

**Recommendation:** Option 1 (keep `api` for clients). Staff API uses `admin` guard (session-based) or add `staff-api` if needed.

**Client Portal API routes:** Already use `auth:api` middleware; no change needed if `api` guard stays on `admins`.

### 7.2 Sanctum Tokens

- Staff tokens: `personal_access_tokens` with `tokenable_type` = `App\Models\Staff`
- Client tokens: `tokenable_type` = `App\Models\Admin`
- No migration needed for existing client tokens (they already point to Admin model).
- Staff API tokens (if any): Generate new tokens after Staff model is live.

### 7.3 WebSocket/Pusher Channels

- If using WebSocket for messaging: Update channel authorization to handle both Staff and Admin models.
- Private channels: Check user type before authorizing.

---

## Testing Checklist

- [ ] **Staff Auth:**
  - [ ] Staff login (AdminConsole, CRM dashboard)
  - [ ] Staff logout
  - [ ] Password reset for staff (use `staff` broker)
  - [ ] Session persistence (staff stays logged in across page loads)
  - [ ] Session flush on migration (all staff must re-login)
  
- [ ] **AdminConsole – User Management:**
  - [ ] Users list (active, inactive, invited)
  - [ ] Create new staff user
  - [ ] Edit existing staff user
  - [ ] View staff details
  - [ ] Archive/unarchive staff
  - [ ] Team and office filters work
  
- [ ] **CRM – Staff Operations:**
  - [ ] Client assignment (agent_id) — assign client to staff
  - [ ] Document creation (created_by)
  - [ ] EOI verification (checked_by)
  - [ ] Active users dashboard (team filter, session tracking)
  - [ ] Booking appointments (assigned_by_admin_id → assigned_by_staff_id)
  - [ ] Notes (user_id, assigned_to)
  - [ ] Activity logs (created_by for staff actions)
  
- [ ] **Client Portal:**
  - [ ] Client login (unchanged, uses `admins` table)
  - [ ] Client API endpoints (auth:api)
  - [ ] Client messaging (sender_id/recipient_id with type check)
  - [ ] Client notifications
  - [ ] Device/refresh tokens for clients
  
- [ ] **Mixed References:**
  - [ ] Messages: staff-to-staff, staff-to-client, client-to-staff
  - [ ] Notifications: all sender/receiver combinations
  - [ ] Companies: contact_person_id (staff or client)
  - [ ] Tokens: device_tokens and refresh_tokens for both types
  
- [ ] **FK Integrity:**
  - [ ] No orphaned FKs (all staff references resolve)
  - [ ] No constraint violations
  - [ ] Cascades work (delete staff → set null on FKs where appropriate)
  
- [ ] **Performance:**
  - [ ] Staff queries don't accidentally include clients
  - [ ] Client queries don't accidentally include staff
  - [ ] ActiveUserService team filter works with Staff model
  - [ ] Dashboard loads without errors

---

## Rollback Plan

1. **Pre-migration:** 
   - Full DB backup (pg_dump or mysqldump).
   - Code backup (git tag: `pre-staff-migration`).
   - Document current staff count and sample IDs.
   
2. **Rollback migrations (in reverse order):**
   - Reverse FK migrations (point back to admins).
   - Drop polymorphic type columns.
   - Drop `staff` table.
   - Revert auth config to `admins` provider.
   
3. **Code revert:** 
   - `git checkout pre-staff-migration`
   - Restore Admin model usage, ClientController/StaffController, ActiveUserService, etc.
   - Redeploy application code.
   
4. **Session flush:** 
   - Truncate `sessions` table or restart session driver.
   - All users (staff and clients) must re-login.
   
5. **Verify rollback:**
   - Staff can log in with original credentials.
   - Client Portal works.
   - No FK constraint violations.
   
**Rollback window:** Plan for 1-2 hour maintenance window to execute rollback if issues detected within first 4 hours of go-live.

---

## Migration Order (Summary)

| Step | Action | Dependencies |
|------|--------|--------------|
| 1 | Create `staff` table migration | None |
| 2 | Create `Staff` model with relationships | Step 1 |
| 3 | Migration: copy staff from admins to staff (preserve IDs or create mapping) | Steps 1-2 |
| 4 | Add polymorphic type columns: `user_type` (tokens), `sender_type`/`recipient_type` (messages/notifications) | None (prep) |
| 5 | Backfill type columns based on `role` check | Steps 3-4 |
| 6 | Update `config/auth.php`: admin/web guards → staff provider; add staff password broker | Step 3 |
| 7 | **FLUSH ALL SESSIONS** – Staff must re-login | Step 6 |
| 8 | Update `AdminLoginController` to authenticate Staff | Step 6 |
| 9 | Migration: Change FKs to staff for staff-only columns (see Phase 4.1) | Step 3 |
| 10 | Update all models: BelongsTo Staff for staff FKs; polymorphic for mixed | Steps 2, 9 |
| 11 | Update `ClientController`/`StaffController` and AdminConsole to use Staff | Steps 2, 10 |
| 12 | Update `ActiveUserService` to query Staff model | Steps 2, 10 |
| 13 | Update all views and controllers with staff queries (`Admin::where('role','!=',7)` → `Staff::`) | Steps 2, 10 |
| 14 | Test thoroughly (see Testing Checklist) | All previous steps |
| 15 | Deploy to production with maintenance window | Step 14 |
| 16 | Monitor for 48h; keep admins staff rows for rollback | Step 15 |

**Critical path:** Steps 3, 6-9 are breaking changes. Must be deployed together in a single release.

**Deployment window:** Recommended 2-4 hour maintenance window for production deployment.

---

## Pre-Deployment Checklist

**1 Week Before:**
- [ ] All code changes complete and merged to `develop`
- [ ] Staging environment matches production schema
- [ ] Full migration run in staging (from fresh DB backup)
- [ ] All tests passing (unit, integration, manual checklist)
- [ ] Rollback plan tested in staging
- [ ] Communication sent to staff: "Maintenance window on [DATE], all staff must re-login"

**1 Day Before:**
- [ ] Final code review and QA sign-off
- [ ] Database backup plan verified (auto-backup + manual backup)
- [ ] Rollback scripts ready and tested
- [ ] On-call engineer assigned for deployment
- [ ] Monitoring alerts configured (login failures, FK violations, API errors)

**Deployment Day (Maintenance Window):**
- [ ] T-30min: Announce maintenance start; block staff logins
- [ ] T-0: Take full DB backup
- [ ] T+5: Deploy code (git tag: `staff-migration-v1.0`)
- [ ] T+10: Run migrations (create staff, copy data, update FKs)
- [ ] T+15: Flush sessions
- [ ] T+20: Update auth config (restart app if needed)
- [ ] T+25: Smoke tests: staff login, client portal login, dashboard
- [ ] T+30: Open to staff; monitor logs for 30 minutes
- [ ] T+60: End maintenance window; announce completion
- [ ] T+4h: Final check; decide go/no-go for keeping migration

**Post-Deployment (48h monitoring):**
- [ ] Monitor login success rate (staff and clients)
- [ ] Monitor FK constraint violations
- [ ] Check for orphaned records
- [ ] Verify no session bleed (staff logged in as client or vice versa)
- [ ] Spot-check: staff CRUD, client assignment, EOI verification
- [ ] If stable after 48h: Schedule Phase 6 (remove staff from admins) for future release

---

## Migration Order (Summary)

## Open Questions & Answers

1. **ID preservation:** Is there ID overlap between staff and clients in `admins`?
   - ✅ **ANSWERED:** Yes, overlap detected. Staff: 1-45515 (96 records), Clients: 2-45956 (9,345 records)
   - **Decision:** MUST use Option B (new IDs + mapping table)
   
2. **Client Portal guard:** Separate `client` guard or reuse `admins` with role check?
   - ✅ **ANSWERED:** Keep `api` guard for clients (points to `admins` provider); staff use `admin` guard (session, points to `staff` provider)
   
3. **Migration agents:** Some staff have `is_migration_agent=1`. Keep agent fields on Staff?
   - ✅ **ANSWERED:** Yes. Staff table includes all migration agent fields (`marn_number`, `business_*`, etc.)
   
4. **Companies `contact_person_id`:** Staff or client? 
   - ✅ **ANSWERED:** CLIENT ONLY. Contact persons are clients, not staff. Keep FK on `admins`.
   
5. **Leads (`user_id`):** Lead's assigned user is staff. Migrate to `staff_id`?
   - ✅ **ANSWERED:** Keep column name `user_id` but change FK to `staff` (using mapping table)
   
6. **Messages/Notifications polymorphic:** How to handle sender/recipient being staff OR client?
   - ✅ **ANSWERED:** Add `sender_type` / `recipient_type` columns; backfill based on role check; polymorphic relationships
   
7. **Tokens (device_tokens, refresh_tokens):** Staff AND clients use these. How to split?
   - ✅ **ANSWERED:** CLIENT ONLY. Only Client Portal mobile app uses tokens. No changes needed.
   
8. **Session collision:** Staff and clients share sessions table; `user_id` could collide.
   - ✅ **ANSWERED:** NO COLLISION. Only staff use CRM sessions. Clients use API only (no web login). Sessions table is staff-only.
   
9. **ActiveUserService:** Filters by role and team (staff-only fields). Update to use Staff model?
   - ✅ **ANSWERED:** Yes. Change `Admin::query()` to `Staff::query()` in `getActiveUsers()`.
   
10. **Email verification (`email_verified_at`):** Staff or clients?
    - ✅ **ANSWERED:** Clients only (Client Portal). Remove from Staff table or mark as unused.

---

## File Change Summary

| Category | Files | Notes |
|----------|-------|-------|
| **New** | `app/Models/Staff.php`, migrations for staff table and FK updates | Staff model, create_staff_table, copy_staff_data, update_fks_to_staff, add_polymorphic_type_columns |
| **Config** | `config/auth.php` | Add `staff` provider and guard, update default broker to `staff` |
| **Controllers** | `AdminLoginController`, `ClientController` (clients), `StaffController` (staff), many CRM controllers | ~15 files: use Staff instead of Admin for staff operations |
| **Models** | ~30+ models with Admin FK to staff | Add BelongsTo Staff or polymorphic relationships |
| **Services** | `ActiveUserService` | Change Admin query to Staff |
| **Views** | AdminConsole users views, dashboard, assignee, booking, client detail | Replace `Admin::where('role','!=',7)` with `Staff::` queries |
| **Routes** | No route changes if guard name stays `admin` | Middleware stays `auth:admin` |
| **Migrations** | 4-6 new migrations | create_staff_table, copy_staff_data, add_polymorphic_columns, backfill_types, update_fks_to_staff, add_password_broker |

**Estimated file changes:** 50-70 files (models, controllers, views, migrations).

---

*End of plan. Do not apply until reviewed and approved.*

---

## Recommendation

**Proceed with caution.** A dedicated staff table is architecturally sound and will simplify future development, but the migration is **high-risk and high-effort**. Key concerns:

1. **Auth changes are breaking** – All staff must re-login; potential for lockout if misconfigured.
2. **30+ FK updates** – High chance of missing a reference or introducing bugs.
3. **Polymorphic complexity** – Messages, notifications, and tokens need careful type handling.
4. **Session collision risk** – Staff and client sessions must be isolated.

**Alternative: Incremental approach**

Instead of full migration, consider:

1. **Phase 0 (low-risk):** Add `is_staff` boolean to `admins` (= `role != 7`). Use scopes for staff queries.
2. **Phase 1 (medium-risk):** Create `staff` table and copy data, but KEEP staff in `admins` too (dual-write for 1-2 months).
3. **Phase 2 (high-risk):** After stability, switch auth to `staff` table and drop admins staff rows.

This spreads risk over multiple releases and allows rollback at each phase.

**Final recommendation:** If you proceed with full migration, allocate 2-3 weeks for development, 1 week for testing, and a 4-hour maintenance window for deployment. Have a dedicated engineer on-call for 48 hours post-deployment.

