# Plan: Unify Lead Reminder Tables into `lead_reminders`

## Executive Summary

Merge the five visa-type-specific lead reminder tables (`lead_tr_reminders`, `lead_visitor_reminders`, `lead_student_reminders`, `lead_pr_reminders`, `lead_employer_sponsored_reminders`) into a single `lead_reminders` table with a `visa_type` discriminator column. This mirrors the pattern used for `matter_reminders` (client checklist reminders) and aligns with `lead_matter_references` unification.

---

## Current State

### Tables (all have identical schema)

| Table | Purpose |
|-------|---------|
| `lead_tr_reminders` | TR Sheet Checklist tab – lead email/sms/phone reminder audit |
| `lead_visitor_reminders` | Visitor Visa Sheet Checklist tab |
| `lead_student_reminders` | Student Visa Sheet Checklist tab |
| `lead_pr_reminders` | PR Application Sheet Checklist tab |
| `lead_employer_sponsored_reminders` | Employer Sponsored Visa Sheet Checklist tab |

### Schema (identical across all five)

```php
- id (bigint, PK)
- lead_id (bigint, FK → admins)
- type (string, 20)   // 'email' | 'sms' | 'phone'
- reminded_at (timestamp)
- reminded_by (nullable FK → staff)
- created_at, updated_at
```

### Usage Points

| Location | What it does |
|----------|---------------|
| `config/sheets/visa_types.php` | Each visa type has `lead_reminders_table` key; PHPDoc header describes it |
| `app/Services/VisaSheetService.php` | `recordLeadChecklistSent()` inserts into config-driven table when checklist sent to a lead |
| `app/Http/Controllers/CRM/VisaTypeSheetController.php` | `buildChecklistTabWithLeads()` queries reminder counts/latest per `lead_id` + `type` for lead rows in Checklist tab |
| `app/Http/Controllers/CRM/CRMUtilityController.php` | Calls `recordLeadChecklistSent()` when checklist is sent (no direct changes needed) |
| `database/migrations/2026_02_20_100000_create_lead_visa_checklist_references_tables.php` | Creates the five reminder tables |

### Code Flow

- **Write path**: `VisaSheetService::recordLeadChecklistSent()` inserts one row per checklist-sent event (type=`email`) into the table for the sheet type (determined by matter’s nick_name/title).
- **Read path**: `buildChecklistTabWithLeads()` loops over lead rows and, for each, runs 6 queries (email/sms/phone × max/count) against `leadRemindersTable` filtered by `lead_id` and `type`.
- **Reminder loop gate**: The entire reminder block (client + lead) runs only when `$remindersTable` (matter_reminders) exists. Lead rows additionally require `leadRemindersTable`; if missing, lead reminder counts are null. The view `visa-type-sheet.blade.php` setup message references only `reminders_table`, not `lead_reminders_table`—no view changes needed.

---

## Proposed New Schema

### `lead_reminders` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `visa_type` | string(50), indexed | `tr`, `visitor`, `student`, `pr`, `employer-sponsored` |
| `lead_id` | bigint FK | → `admins` |
| `type` | string(20) | `email`, `sms`, `phone` |
| `reminded_at` | timestamp | When reminder was sent |
| `reminded_by` | bigint nullable FK | → `staff` |
| `created_at`, `updated_at` | timestamps | |

### Indexes

- `(visa_type, lead_id, type)` – composite index (e.g. `lead_reminders_visa_lead_type_idx`) for the main query pattern
- `lead_id` – index (Laravel foreign key adds this by default)
- Foreign keys: `lead_id` → `admins`, `reminded_by` → `staff`

**Note:** `reminded_at` is NOT nullable (matches original schema).

---

## Implementation Plan

### Phase 1: Database Migration

1. **Create migration** `2026_02_26_000000_create_lead_reminders_and_migrate.php`:
   - Create `lead_reminders` with schema above (including composite index `(visa_type, lead_id, type)`)
   - Map old tables to `visa_type` values:
     - `lead_tr_reminders` → `tr`
     - `lead_visitor_reminders` → `visitor`
     - `lead_student_reminders` → `student`
     - `lead_pr_reminders` → `pr`
     - `lead_employer_sponsored_reminders` → `employer-sponsored`
   - For each old table: if it exists, migrate rows with the correct `visa_type`, then drop it
   - Handle empty tables (e.g. fresh installs where the original migration created empty tables)

   **Up pseudocode:** Create `lead_reminders` with `Schema::create()`. Map `$oldTables = ['tr' => 'lead_tr_reminders', ...]`. For each `$visaType => $tableName`: `if (Schema::hasTable($tableName)) { foreach (DB::table($tableName)->get() as $row) { DB::table('lead_reminders')->insert([...]); } Schema::dropIfExists($tableName); }`. For large tables (>10k rows), use `->orderBy('id')->chunk(500, ...)` to avoid memory issues.

2. **Migration order**: Use a timestamp **after** `2026_02_20_100000` (creates the five reminder tables) and `2026_02_25_000000` (lead_matter_references unification). Use `2026_02_26_000000_create_lead_reminders_and_migrate.php`. Note: Multiple migrations share the `2026_02_20_100000` prefix; the lead checklist one is `create_lead_visa_checklist_references_tables`.

3. **Atomicity (optional)**: Wrap the migration body in `DB::transaction(function () { ... })` so that if any step fails, the entire migration rolls back. The existing `matter_reminders` and `lead_matter_references` migrations do not use transactions; add only if desired for additional safety.

4. **`down()` implementation** must:
   - Recreate the five original tables with exact schema from `2026_02_20_100000` (see Migration Pseudocode below)
   - Copy rows from `lead_reminders` back to the correct old table by `visa_type`
   - Drop `lead_reminders`

5. **Migration pseudocode** (down): Mirror the pattern used in `lead_matter_references` and `matter_reminders` migrations.

```php
$oldTables = [
    'tr' => 'lead_tr_reminders',
    'visitor' => 'lead_visitor_reminders',
    'student' => 'lead_student_reminders',
    'pr' => 'lead_pr_reminders',
    'employer-sponsored' => 'lead_employer_sponsored_reminders',
];

// 1. Recreate the five tables (exact schema from 2026_02_20_100000)
foreach (array_values($oldTables) as $tableName) {
    Schema::create($tableName, function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('lead_id')->index();
        $t->string('type', 20);
        $t->timestamp('reminded_at');
        $t->unsignedBigInteger('reminded_by')->nullable();
        $t->timestamps();
        $t->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
        $t->foreign('reminded_by')->references('id')->on('staff')->onDelete('set null');
    });
}

// 2. Copy rows back by visa_type (filtered query—avoids loading all rows, no unknown-type handling)
foreach ($oldTables as $visaType => $tableName) {
    $rows = DB::table('lead_reminders')->where('visa_type', $visaType)->get();
    foreach ($rows as $row) {
        DB::table($tableName)->insert([
            'lead_id' => $row->lead_id,
            'type' => $row->type,
            'reminded_at' => $row->reminded_at,
            'reminded_by' => $row->reminded_by,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }
}

// 3. Drop unified table
Schema::dropIfExists('lead_reminders');
```

**Note:** Rows with unrecognized `visa_type` (e.g. typos, manual inserts) are not copied during rollback; they remain only in `lead_reminders` until it is dropped.

---

### Phase 2: Configuration Update

**File:** `config/sheets/visa_types.php`

- Set `lead_reminders_table` to `'lead_reminders'` for all five visa types.
- Keep config key name; only change the value.
- Update PHPDoc header: change `lead_reminders_table: per-type lead reminder table (e.g. lead_tr_reminders)` → `lead_reminders_table: lead_reminders (unified table)`.
- `reference_type` (already present) provides the `visa_type` filter value for queries: `tr`, `visitor`, `student`, `pr`, `employer-sponsored`.

---

### Phase 3: Code Changes

#### 3.1 `app/Services/VisaSheetService.php` – `recordLeadChecklistSent()`

**Current:** Uses `$config['lead_reminders_table']` and inserts (no `visa_type`).

**Change:** Add `visa_type` to the insert. Use `$refType = $config['reference_type'] ?? $sheetType`.

```php
// Add visa_type to insert payload
DB::table($remindersTable)->insert([
    'visa_type' => $refType,
    'lead_id' => $leadId,
    'type' => 'email',
    'reminded_at' => $now,
    'reminded_by' => $staffId,
    'created_at' => $now,
    'updated_at' => $now,
]);
```

#### 3.2 `app/Http/Controllers/CRM/VisaTypeSheetController.php` – `buildChecklistTabWithLeads()`

**Current:** Lead reminder queries use only `lead_id` and `type` (no visa filtering needed because each sheet type had its own table).

**Change:** Add `->where('visa_type', $refType)` to every lead reminder query so rows from other visa types don’t appear on the wrong sheet.

```php
// Before
DB::table($leadRemindersTable)->where('lead_id', $row->client_id)->where('type', 'email')->max('reminded_at')

// After
DB::table($leadRemindersTable)->where('visa_type', $refType)->where('lead_id', $row->client_id)->where('type', 'email')->max('reminded_at')
```

Apply the same pattern for all 6 queries (email/sms/phone × max/count).

---

### Phase 4: Modify Original Migration (Optional)

**File:** `database/migrations/2026_02_20_100000_create_lead_visa_checklist_references_tables.php`

- **Option A (recommended):** Do **not** modify. Run the new unification migration after it. The new migration migrates data and drops the five tables.
- **Option B:** If the original migration has **never** run in production, you could change it to create only `lead_reminders` instead of the five tables. This would require code/config changes at the same time and is riskier for existing installs.

---

### Phase 5: Optional – Eloquent Model

Create `app/Models/LeadReminder.php` for future use:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadReminder extends Model
{
    protected $table = 'lead_reminders';
    protected $fillable = ['visa_type', 'lead_id', 'type', 'reminded_at', 'reminded_by'];
    protected $casts = ['reminded_at' => 'datetime'];
    
    public function scopeOfVisaType($query, string $visaType) {
        return $query->where('visa_type', $visaType);
    }
}
```

Migration can stay DB-facade based; model is for future refactors.

---

## Migration Data Mapping

| Old Table | visa_type |
|-----------|-----------|
| lead_tr_reminders | `tr` |
| lead_visitor_reminders | `visitor` |
| lead_student_reminders | `student` |
| lead_pr_reminders | `pr` |
| lead_employer_sponsored_reminders | `employer-sponsored` |

---

## Interplay with Other Unifications

| Plan | Relationship |
|------|--------------|
| **Lead Matter References** (`LEAD_MATTER_REFERENCES_UNIFICATION_PLAN.md`) | Uses `lead_matter_references`; unrelated schema. Lead reminders are a separate audit trail (email/sms/phone). The two unifications touch different tables (`lead_*_references` vs `lead_*_reminders`) and can run in either order; this plan uses `2026_02_26` (after `2026_02_25`). |
| **Matter Reminders** | Already unified as `matter_reminders`. Same pattern (visa_type discriminator); different table for client matters. |
| **Lead Visa Checklist References** | `2026_02_20_100000` creates both the lead reference tables and the lead reminder tables. Lead reminders live in separate tables with no FKs from lead references to reminders. |

---

## Rollback Strategy

- Implement full `down()` in migration to:
  1. Recreate the five original tables with exact schema
  2. Copy rows from `lead_reminders` back to the correct table by `visa_type`
  3. Drop `lead_reminders`
- After rollback, revert config and code so `lead_reminders_table` points back to the old table names.
- **Caveat:** If you rollback the unification migration, the five tables are recreated and repopulated. The application must be reconfigured (restore old config values) and code reverted.

---

## Deployment

Migration and code **must** be deployed together. Deploy order: run `php artisan migrate` (which creates `lead_reminders`, migrates data, and drops the five old tables), then deploy the updated code/config in the same release.

---

## Testing Checklist

- [ ] Migration runs on a DB with the five existing tables (with or without data)
- [ ] Migration runs on a fresh DB (five tables created empty by prior migrations, then unified)
- [ ] Migration correctly migrates existing data with correct `visa_type`
- [ ] `recordLeadChecklistSent()` inserts into `lead_reminders` with correct `visa_type`
- [ ] Checklist tab shows correct email/sms/phone counts for lead rows per visa type
- [ ] No cross-visa-type leakage (TR lead reminders don’t appear on Visitor sheet, etc.)
- [ ] Rollback (`php artisan migrate:rollback`) restores old tables and data
- [ ] `DiagnoseVisaSheet`: Does not validate `lead_reminders_table`; no changes needed there.
- [ ] Partial-state: If `matter_reminders` exists but `lead_reminders` is missing (e.g. migration not yet run), Checklist tab loads; lead rows show null reminder counts, no errors.

**Optional:** Extend `DiagnoseVisaSheet` to verify `lead_reminders_table` exists when `lead_reference_table` is `lead_matter_references`, to catch "migration not run" scenarios. If the table is missing, reminder counts will be null for leads but the tab will still function.

---

## Files to Modify

| File | Action |
|------|--------|
| `database/migrations/` | New migration: create `lead_reminders`, migrate data, drop old tables |
| `config/sheets/visa_types.php` | Set `lead_reminders_table` → `'lead_reminders'` for all five; update PHPDoc header |
| `app/Services/VisaSheetService.php` | Add `visa_type` to insert in `recordLeadChecklistSent()` |
| `app/Http/Controllers/CRM/VisaTypeSheetController.php` | Add `visa_type` filter in `buildChecklistTabWithLeads()` for lead reminder queries |
| `app/Models/LeadReminder.php` | Optional new model |
| `app/Console/Commands/DiagnoseVisaSheet.php` | Optional: add `lead_reminders_table` validation |

---

## Risks & Considerations

1. **Deployment order**: Migration and code must be deployed together. If code is deployed first (pointing to `lead_reminders`), the table may not exist and writes fail. If migration runs first, old tables are dropped and old code would fail.
2. **Lead vs client scope**: Lead reminders are for leads only (`lead_id` → admins where type=lead). Client checklist reminders use `matter_reminders`.
3. **Index performance**: Composite index `(visa_type, lead_id, type)` matches the query pattern `WHERE visa_type = ? AND lead_id = ? AND type = ?`.
4. **Same lead in multiple visa types**: A lead could theoretically appear in multiple Checklist tabs (e.g. TR and Visitor) if they have matters in both. With `visa_type`, each sheet sees only its own reminders—correct behavior.
5. **N+1 query pattern**: The Checklist tab runs 6 queries per lead row (email/sms/phone × max/count). With many leads per page, consider a future optimization: batch-load reminders for all lead IDs on the current page in 3–6 queries total, then assign in PHP. Not required for initial implementation.

---

## Implementation Order

1. Create and run the migration (Phase 1).
2. Update config (Phase 2) and application code (Phase 3) in the same deploy.
3. Optionally add `LeadReminder` model (Phase 5).
4. Run full testing checklist.

---

## Timeline Estimate

| Phase | Effort |
|-------|--------|
| Phase 1: Migration | ~1 hour |
| Phase 2: Config | ~5 min |
| Phase 3: Code changes | ~1 hour |
| Phase 5: Model (optional) | ~15 min |
| Testing | ~1 hour |
| **Total** | **~3–4 hours** |
