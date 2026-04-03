# Plan: Unify Lead Reference Tables into `lead_matter_references`

## Executive Summary

Merge the five visa-type-specific lead reference tables (`lead_tr_references`, `lead_visitor_references`, `lead_student_references`, `lead_pr_references`, `lead_employer_sponsored_references`) into a single `lead_matter_references` table with a `type` discriminator column. This mirrors the existing pattern used for `client_matter_references`.

---

## Current State

### Tables (all have identical schema)

| Table | Purpose |
|-------|---------|
| `lead_tr_references` | TR Sheet Checklist tab – leads in follow-up queue |
| `lead_visitor_references` | Visitor Visa Sheet Checklist tab |
| `lead_student_references` | Student Visa Sheet Checklist tab |
| `lead_pr_references` | PR Application Sheet Checklist tab |
| `lead_employer_sponsored_references` | Employer Sponsored Visa Sheet Checklist tab |

### Schema (identical across all five)

```php
- id (bigint, PK)
- lead_id (bigint, FK → admins) -- lead appears in Checklist tab
- matter_id (bigint, FK → matters)
- checklist_sent_at (date, nullable)
- created_by (bigint nullable, FK → staff)
- updated_by (bigint nullable, FK → staff)
- created_at, updated_at
- UNIQUE(lead_id, matter_id)
```

### Usage Points

| Location | What it does |
|----------|---------------|
| `config/sheets/visa_types.php` | Each visa type has `lead_reference_table` key |
| `app/Services/VisaSheetService.php` | `recordLeadChecklistSent()` inserts/updates in config-driven table |
| `app/Http/Controllers/CRM/VisaTypeSheetController.php` | `buildChecklistTabWithLeads()` queries leads from config-driven table for Checklist tab |
| `app/Http/Controllers/CRM/CRMUtilityController.php` | Calls `recordLeadChecklistSent()` when checklist is sent to a lead (no direct changes needed) |

---

## Proposed New Schema

### `lead_matter_references` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `type` | string(50), indexed | `tr`, `visitor`, `student`, `pr`, `employer-sponsored` |
| `lead_id` | bigint FK | → `admins` (lead) |
| `matter_id` | bigint FK | → `matters` |
| `checklist_sent_at` | date nullable | When checklist was sent |
| `created_by` | bigint nullable FK | → `staff` |
| `updated_by` | bigint nullable FK | → `staff` |
| `created_at`, `updated_at` | timestamps | |

### Indexes

- `(type, lead_id, matter_id)` – unique constraint (business key)
- `type` – index for filtering by visa type
- `lead_id`, `matter_id` – indexes (Laravel foreign keys add these)
- Foreign keys: `lead_id` → `admins`, `matter_id` → `matters`, `created_by` / `updated_by` → `staff`

---

## Implementation Plan

### Phase 1: Database Migration

1. **Create migration** `2026_02_25_000000_create_lead_matter_references_and_migrate.php`:
   - **Timestamp**: Must be after `2026_02_20_100000` (creates the five tables) so data can be migrated.
   - Create `lead_matter_references` with schema above
   - Add unique constraint: `(type, lead_id, matter_id)`
   - Map old tables to type values:
     - `lead_tr_references` → `tr`
     - `lead_visitor_references` → `visitor`
     - `lead_student_references` → `student`
     - `lead_pr_references` → `pr`
     - `lead_employer_sponsored_references` → `employer-sponsored`
   - For each old table: if it exists, migrate rows with the correct `type`, then drop it
   - Implement `down()` to restore the five tables and migrate data back (see Migration Pseudocode below)

2. **Modify original migration** `2026_02_20_100000_create_lead_visa_checklist_references_tables.php`:
   - **Option A (recommended)**: Do **not** modify. Run the new migration after it. The new migration will migrate data and drop the five tables. If this is a fresh install, the five tables will be created by the original migration, then immediately migrated and dropped by the new one.
   - **Option B**: Update the original migration to create `lead_matter_references` instead of the five tables (only if the original migration has never run in production).

### Phase 2: Config Changes

3. **Update `config/sheets/visa_types.php`**:
   - Set `lead_reference_table` to `lead_matter_references` for all five visa types
   - Use existing `reference_type` for filtering (already present: `tr`, `visitor`, `student`, `pr`, `employer-sponsored`) – no new config key needed

### Phase 3: Code Changes

4. **`app/Services/VisaSheetService.php`** – `recordLeadChecklistSent()`:
   - Table remains config-driven (`lead_reference_table` → `lead_matter_references`)
   - Add `type` column to insert/update: `$refType = $config['reference_type'] ?? $sheetType`
   - Change unique lookup from `(lead_id, matter_id)` to `(type, lead_id, matter_id)`

5. **`app/Http/Controllers/CRM/VisaTypeSheetController.php`** – `buildChecklistTabWithLeads()`:
   - Table remains config-driven; after config update all will use `lead_matter_references`
   - Add `->where('lr.type', $refType)` to the lead query, where `$refType = $config['reference_type'] ?? $visaType`
   - Must add this filter **before** any joins to avoid cross-type pollution

### Phase 4: Model (Optional)

6. **Create `app/Models/LeadMatterReference.php`** (optional but recommended):
   - Mirror `ClientMatterReference` structure
   - `scopeOfType($query, $type)` for filtering
   - Relationships: `lead()` → Admin, `matter()` → Matter
   - Use for future refactoring; current code uses `DB::table()` so not strictly required initially

### Phase 5: Cleanup & Validation

7. **Drop old tables** in the migration (already part of Phase 1)
8. **Verify** no remaining references to the five old table names in code
9. **Run** Checklist tab for each visa type to confirm leads display correctly
10. **Test** `recordLeadChecklistSent()` for a lead in each visa type

---

## Migration Pseudocode

```php
// In migration up():
$oldTables = [
    'tr' => 'lead_tr_references',
    'visitor' => 'lead_visitor_references',
    'student' => 'lead_student_references',
    'pr' => 'lead_pr_references',
    'employer-sponsored' => 'lead_employer_sponsored_references',
];

Schema::create('lead_matter_references', function (Blueprint $t) {
    $t->id();
    $t->string('type', 50)->index();
    $t->unsignedBigInteger('lead_id')->index();
    $t->unsignedBigInteger('matter_id')->index();
    $t->date('checklist_sent_at')->nullable();
    $t->unsignedBigInteger('created_by')->nullable();
    $t->unsignedBigInteger('updated_by')->nullable();
    $t->timestamps();

    $t->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
    $t->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
    $t->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
    $t->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');

    $t->unique(['type', 'lead_id', 'matter_id'], 'lead_matter_ref_type_lead_matter_unique');
});

foreach ($oldTables as $type => $tableName) {
    if (!Schema::hasTable($tableName)) continue;
    $rows = DB::table($tableName)->get();
    foreach ($rows as $row) {
        DB::table('lead_matter_references')->insert([
            'type' => $type,
            'lead_id' => $row->lead_id,
            'matter_id' => $row->matter_id,
            'checklist_sent_at' => $row->checklist_sent_at,
            'created_by' => $row->created_by,
            'updated_by' => $row->updated_by,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }
    Schema::dropIfExists($tableName);
}

// down(): Reverse the migration
$oldTables = [
    'tr' => 'lead_tr_references',
    'visitor' => 'lead_visitor_references',
    'student' => 'lead_student_references',
    'pr' => 'lead_pr_references',
    'employer-sponsored' => 'lead_employer_sponsored_references',
];
foreach (array_values($oldTables) as $tableName) {
    Schema::create($tableName, function (Blueprint $t) use ($tableName) {
        $t->id();
        $t->unsignedBigInteger('lead_id')->index();
        $t->unsignedBigInteger('matter_id')->index();
        $t->date('checklist_sent_at')->nullable();
        $t->unsignedBigInteger('created_by')->nullable();
        $t->unsignedBigInteger('updated_by')->nullable();
        $t->timestamps();
        $t->foreign('lead_id')->references('id')->on('admins')->onDelete('cascade');
        $t->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
        $t->foreign('created_by')->references('id')->on('staff')->onDelete('set null');
        $t->foreign('updated_by')->references('id')->on('staff')->onDelete('set null');
        $t->unique(['lead_id', 'matter_id'], $tableName . '_lead_matter_unique');
    });
}
foreach ($oldTables as $type => $tableName) {
    $rows = DB::table('lead_matter_references')->where('type', $type)->get();
    foreach ($rows as $row) {
        DB::table($tableName)->insert([
            'lead_id' => $row->lead_id,
            'matter_id' => $row->matter_id,
            'checklist_sent_at' => $row->checklist_sent_at,
            'created_by' => $row->created_by,
            'updated_by' => $row->updated_by,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }
}
Schema::dropIfExists('lead_matter_references');
```

---

## VisaSheetService Code Changes (detail)

**Current (line ~69):**
```php
$exists = DB::table($refTable)
    ->where('lead_id', $leadId)
    ->where('matter_id', $matterId)
    ->exists();
```

**After:** Add `type` to lookup and to insert/update:
```php
$refType = $config['reference_type'] ?? $sheetType;
$exists = DB::table($refTable)
    ->where('type', $refType)
    ->where('lead_id', $leadId)
    ->where('matter_id', $matterId)
    ->exists();
```
And in both `update()` and `insert()`, include `'type' => $refType`.

---

## Config Change Summary

**Before** (per visa type):
```php
'tr' => [
    'lead_reference_table' => 'lead_tr_references',
    ...
],
'visitor' => [
    'lead_reference_table' => 'lead_visitor_references',
    ...
],
// etc.
```

**After** (unified):
```php
'tr' => [
    'lead_reference_table' => 'lead_matter_references',
    'reference_type' => 'tr',  // already exists – used for both client_matter_references and lead_matter_references
    ...
],
'visitor' => [
    'lead_reference_table' => 'lead_matter_references',
    'reference_type' => 'visitor',
    ...
],
// etc. – all share lead_matter_references; filter by reference_type
```

No new config key. Code reads `reference_type` (already used for `client_matter_references`); controller and service add `type` column / `WHERE type = ?` for `lead_matter_references`.

---

## Query Change in buildChecklistTabWithLeads()

**Before:**
```php
$leadRefTable = $config['lead_reference_table'];  // e.g. lead_tr_references
$leadQuery = DB::table($leadRefTable . ' as lr')
    ->join('admins as a', 'lr.lead_id', '=', 'a.id')
    ->join('matters as m', 'lr.matter_id', '=', 'm.id')
    ->whereIn('lr.matter_id', $matterIds)
    ...
```

**After:**
```php
$leadRefTable = $config['lead_reference_table'];  // now 'lead_matter_references' for all
$refType = $config['reference_type'] ?? $visaType;
$leadQuery = DB::table($leadRefTable . ' as lr')
    ->where('lr.type', $refType)  // Critical: filter by visa type
    ->join('admins as a', 'lr.lead_id', '=', 'a.id')
    ->join('matters as m', 'lr.matter_id', '=', 'm.id')
    ->whereIn('lr.matter_id', $matterIds)
    ...
```

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| Data loss during migration | Run migration in transaction; test on staging with production-like data before deploying |
| Unique constraint violations | Old tables had per-table uniqueness. New table: (type, lead_id, matter_id). Same lead+matter in two types (e.g. tr and visitor) would yield two rows – allowed and correct. |
| Config typo | Use existing `reference_type`; no new keys. Values validated by sheet routing (`visaType` param). |
| Migration rollback | Implement full `down()` that recreates five tables, migrates back, drops unified table |

---

## Out of Scope (for reference)

- **Lead reminders** (`lead_tr_reminders`, etc.): Separate tables, not part of this plan. Could be unified similarly in a future `lead_reminders` table with `type` column if desired.
- **client_matter_references**: Already unified; no changes.
- **DiagnoseVisaSheet**: Does not validate `lead_reference_table`; no changes needed.

## Post-Implementation Fix (applyFilters)

During review, a pre-existing bug was fixed: `applyFilters()` used hardcoded `latest_matter` alias, but the Checklist tab’s client query uses `cm`. `applyFilters()` now accepts an optional `$matterAlias` parameter (default `latest_matter`); `buildChecklistTabWithLeads()` passes `'cm'` so branch/assignee filters work on the Checklist tab.

---

## File Checklist

| File | Action |
|------|--------|
| `database/migrations/2026_02_25_000000_create_lead_matter_references_and_migrate.php` | Create (new migration with up + down) |
| `config/sheets/visa_types.php` | Set `lead_reference_table` → `lead_matter_references` for all 5 types |
| `app/Services/VisaSheetService.php` | Add `type` to insert/update; lookup by (type, lead_id, matter_id) |
| `app/Http/Controllers/CRM/VisaTypeSheetController.php` | Add `->where('lr.type', $refType)` to lead query in `buildChecklistTabWithLeads()` |
| `app/Models/LeadMatterReference.php` | Create (optional, for future refactoring) |

## Deployment Order

Deploy in a single release:

1. **Migration** – `php artisan migrate` creates `lead_matter_references`, migrates data, drops five tables
2. **Config + code** – Deploy together. If config points to `lead_matter_references` but code lacks the `type` filter, each Checklist tab would show leads from all visa types. If code has the filter but old config pointed to dropped tables, queries would fail.
