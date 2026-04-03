# Plan: Unify Matter Reminders Tables into `matter_reminders`

## Executive Summary

Merge the five visa-type-specific reminder tables (`tr_matter_reminders`, `visitor_matter_reminders`, `student_matter_reminders`, `pr_matter_reminders`, `employer_sponsored_matter_reminders`) into a single `matter_reminders` table with a `visa_type` discriminator column. This mirrors the existing pattern used for `client_matter_references`.

---

## Current State

### Tables (all have identical schema)

| Table | Purpose |
|-------|---------|
| `tr_matter_reminders` | TR Sheet Checklist tab – email/sms/phone reminder audit |
| `visitor_matter_reminders` | Visitor Visa Sheet Checklist tab |
| `student_matter_reminders` | Student Visa Sheet Checklist tab |
| `pr_matter_reminders` | PR Application Sheet Checklist tab |
| `employer_sponsored_matter_reminders` | Employer Sponsored Visa Sheet Checklist tab |

### Schema (identical across all five)

```php
- id (bigint, PK)
- client_matter_id (FK → client_matters)
- type (string, 20) -- 'email' | 'sms' | 'phone'
- reminded_at (timestamp)
- reminded_by (nullable FK → staff)
- created_at, updated_at
```

### Code Flow (important for implementation)

- **Checklist tab** is served exclusively by `buildChecklistTabWithLeads()` — this is the **only** active code path that queries reminder data.
- **Other tabs** (ongoing, lodged, discontinue) use `buildBaseQuery()`. The reminder subqueries in `buildBaseQuery` are **dead code** — they are guarded by `$tab === 'checklist'`, but when `tab` is checklist, `buildBaseQuery` is never called. During unification, either remove this block or update it for consistency/future-proofing.

### Usage Points

| Location | What it does |
|----------|---------------|
| `config/sheets/visa_types.php` | Each visa type has `reminders_table` key |
| `app/Models/ClientMatter.php` | `recordChecklistSent()` inserts into config-driven table |
| `app/Http/Controllers/CRM/VisaTypeSheetController.php` | `buildChecklistTabWithLeads()` queries reminder counts/latest per `client_matter_id` + `type`; `buildBaseQuery()` has dead reminder subquery block |
| `resources/views/crm/clients/sheets/visa-type-sheet.blade.php` | Mentions reminders table in setup message |
| `app/Console/Commands/DiagnoseVisaSheet.php` | Validates reminders table exists (per visa type) |

---

## Proposed New Schema

### `matter_reminders` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `visa_type` | string(50), indexed | `tr`, `visitor`, `student`, `pr`, `employer-sponsored` |
| `client_matter_id` | bigint FK | → `client_matters` |
| `type` | string(20) | `email`, `sms`, `phone` |
| `reminded_at` | timestamp | When reminder was sent |
| `reminded_by` | bigint nullable FK | → `staff` |
| `created_at`, `updated_at` | timestamps | |

### Indexes

- `(visa_type, client_matter_id, type)` – composite index for the main query pattern
- `client_matter_id` – index (Laravel foreign key adds this by default)
- Foreign keys: `client_matter_id` → `client_matters`, `reminded_by` → `staff`

---

## Implementation Plan

### Phase 1: Database Migration

1. **Create migration** `YYYY_MM_DD_HHMMSS_create_matter_reminders_and_migrate.php`:
   - Create `matter_reminders` with schema above (including composite index `(visa_type, client_matter_id, type)`)
   - For each old table: if it exists, migrate rows with the correct `visa_type`, then drop it
   - Handle empty tables (e.g. fresh installs where the five migrations created empty tables)

2. **Migration order**: Use a timestamp **after** `2026_02_16_000015` (last of the five reminder migrations), e.g. `2026_02_24_000000`.

3. **`down()` implementation** must:
   - Recreate the five original tables with **exact schema** from their migrations (including index names; note: `employer_sponsored_matter_reminders` uses `emp_sponsored_reminders_matter_type_idx`, others use `{table}_matter_type_idx`)
   - Copy rows from `matter_reminders` back to the correct old table by `visa_type`
   - Drop `matter_reminders`

---

### Phase 2: Configuration Update

**File:** `config/sheets/visa_types.php`

**Recommended approach (minimal change):**  
- Keep `reminders_table` in each visa type config; change its value from the old table name to `'matter_reminders'` for all five visa types.
- No structural change to config access — existing `$config['reminders_table']` continues to work.
- `reference_type` (already present) provides the `visa_type` filter value for queries: `tr`, `visitor`, `student`, `pr`, `employer-sponsored`.
- `isSetupRequired()` and the view already read from config; they will automatically show `matter_reminders` once config is updated.

**Alternative:** Create `config/sheets.php` with `'reminders_table' => 'matter_reminders'` and update all call sites to use `config('sheets.reminders_table')` — more DRY but requires more code changes.

---

### Phase 3: Code Changes

#### 3.1 `app/Models/ClientMatter.php` – `recordChecklistSent()`

**Current:** Uses `$config['reminders_table']` and inserts (no `visa_type`).

**Change:** Add `visa_type` to the insert. The table name will be `matter_reminders` after config update; `$refType` is already in scope from `$config['reference_type']`.

```php
// Add visa_type to insert payload
DB::table($remindersTable)->insert([
    'visa_type' => $refType,  // tr, visitor, student, pr, employer-sponsored
    'client_matter_id' => $this->id,
    'type' => 'email',
    'reminded_at' => $now,
    'reminded_by' => $staffId,
    'created_at' => $now,
    'updated_at' => $now,
]);
```

#### 3.2 `app/Http/Controllers/CRM/VisaTypeSheetController.php`

**buildChecklistTabWithLeads (primary — Checklist tab):**  
Uses `DB::table($remindersTable)->where('client_matter_id', ...)->where('type', ...)`. Add `->where('visa_type', $refType)` to every reminder query. `$refType` = `$config['reference_type']`.

```php
// Before (for client matters)
DB::table($remindersTable)->where('client_matter_id', $row->matter_internal_id)->where('type', 'email')->max('reminded_at')

// After
DB::table($remindersTable)->where('visa_type', $refType)->where('client_matter_id', $row->matter_internal_id)->where('type', 'email')->max('reminded_at')
```

Apply the same pattern for all 6 queries (email/sms/phone × max/count). Lead reminders use `leadRemindersTable` and are unchanged.

**buildBaseQuery (dead code — optional update):**  
The reminder subqueries only run when `$tab === 'checklist'`, but the Checklist tab uses `buildChecklistTabWithLeads`, so this block is never executed. Either:
- **Remove** the entire `if ($tab === 'checklist' && $remindersTable...)` block, or  
- **Update** it for future-proofing: use `selectRaw()` with bindings so `$refType` is passed safely:

```php
// Safe approach with bindings
$refType = $config['reference_type'] ?? $request->route('visaType', '');
$query->selectRaw(
    "(SELECT MAX(ar.reminded_at) FROM matter_reminders ar WHERE ar.client_matter_id = latest_matter.matter_id AND ar.visa_type = ? AND ar.type = 'email') as email_reminder_latest",
    [$refType]
);
// Repeat for the other 5 reminder columns
```

Avoid interpolating `$refType` directly into `DB::raw()`; use `selectRaw()` with the second parameter for bindings.

#### 3.3 `isSetupRequired()`

**No change needed.** It already checks `Schema::hasTable($config['reminders_table'])`. Once config points to `matter_reminders`, it will validate the unified table.

#### 3.4 Config change

Update each visa type's `reminders_table` from the old table name to `'matter_reminders'`.

---

### Phase 4: Supporting Files

| File | Change |
|------|--------|
| `resources/views/crm/clients/sheets/visa-type-sheet.blade.php` | **No change.** Uses `{{ $config['reminders_table'] ?? 'tr_matter_reminders' }}` — will show `matter_reminders` once config is updated. Optionally update fallback to `'matter_reminders'` for consistency. |
| `app/Console/Commands/DiagnoseVisaSheet.php` | **No change.** Uses `$config['reminders_table']` per visa type. After config update, all will point to `matter_reminders` and validation will pass. |

---

### Phase 5: Optional – Eloquent Model

Create `app/Models/MatterReminder.php` for future use:

```php
class MatterReminder extends Model
{
    protected $table = 'matter_reminders';
    protected $fillable = ['visa_type', 'client_matter_id', 'type', 'reminded_at', 'reminded_by'];
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
| tr_matter_reminders | `tr` |
| visitor_matter_reminders | `visitor` |
| student_matter_reminders | `student` |
| pr_matter_reminders | `pr` |
| employer_sponsored_matter_reminders | `employer-sponsored` |

---

## Rollback Strategy

- Implement full `down()` in migration to:
  1. Recreate the five original tables with exact schema (see Phase 1)
  2. Copy rows from `matter_reminders` back to the correct table by `visa_type`
  3. Drop `matter_reminders`
- After rollback, revert code/config so `reminders_table` points back to the old table names. The old migrations that create those tables will not re-run (they are already in the migrations table); the unification migration's `down()` recreates the tables.
- **Caveat:** If you rollback the unification migration, the five tables are recreated and repopulated. The application must be reconfigured to use them (restore old config values). A code revert is required.

## Deployment

Migration and code **must** be deployed together. The migration drops the old tables, so old code cannot run after the migration. Deploy order: run `php artisan migrate` (which creates `matter_reminders` and drops the five old tables), then deploy the updated code/config in the same release.

---

## Testing Checklist

- [ ] Migration runs on a DB with the five existing tables (with or without data)
- [ ] Migration runs on a fresh DB (five tables created empty by prior migrations, then unified)
- [ ] Migration correctly migrates existing data with correct `visa_type`
- [ ] `recordChecklistSent()` inserts into `matter_reminders` with correct `visa_type`
- [ ] Checklist tab shows correct email/sms/phone counts per visa type
- [ ] No cross-visa-type leakage (TR reminders don’t appear on Visitor sheet, etc.)
- [ ] `php artisan visa-sheet:diagnose {visa_type}` passes for each visa type
- [ ] Rollback (`php artisan migrate:rollback`) restores old tables and data
- [ ] Setup-required message displays `matter_reminders` when config is updated

---

## Files to Modify

| File | Action |
|------|--------|
| `database/migrations/` | New migration: create `matter_reminders`, migrate data, drop old tables |
| `config/sheets/visa_types.php` | Set `reminders_table` → `'matter_reminders'` for all five visa types |
| `app/Models/ClientMatter.php` | Add `visa_type` to insert in `recordChecklistSent()` |
| `app/Http/Controllers/CRM/VisaTypeSheetController.php` | Add `visa_type` filter in `buildChecklistTabWithLeads()`; optionally remove or update dead reminder block in `buildBaseQuery()` |
| `resources/views/crm/clients/sheets/visa-type-sheet.blade.php` | Optional: update fallback in setup message to `'matter_reminders'` |
| `app/Console/Commands/DiagnoseVisaSheet.php` | No change (uses config) |
| `app/Models/MatterReminder.php` | Optional new model |

---

## Risks & Considerations

1. **Deployment order**: Migration and code must be deployed together. If code is deployed first (pointing to `matter_reminders`), the table may not exist yet and writes will fail. If migration runs first, old tables are dropped and old code would fail when writing. **Best practice:** run migration, then deploy updated code in the same release window.
2. **Lead reminders**: `lead_*_reminders` tables (e.g. `lead_tr_reminders`) are separate and **not** in scope for this unification.
3. **SQL injection**: When updating `buildBaseQuery` (if not removed), use `selectRaw()` with bindings for `$refType` instead of interpolating into `DB::raw()`.
4. **Index performance**: Composite index `(visa_type, client_matter_id, type)` matches the query pattern `WHERE visa_type = ? AND client_matter_id = ? AND type = ?`.
5. **Database drivers**: Schema and raw SQL use standard names; should work on MySQL and PostgreSQL.

---

## Implementation Order

1. Create and run the migration (Phase 1).
2. Update config (Phase 2) and application code (Phase 3) in the same deploy.
3. Verify view and DiagnoseVisaSheet (Phase 4 — minimal or no changes).
4. Optionally add MatterReminder model (Phase 5).
5. Run full testing checklist.

## Timeline Estimate

| Phase | Effort |
|-------|--------|
| Phase 1: Migration | ~1–2 hours |
| Phase 2: Config | ~15 min |
| Phase 3: Code changes | ~2–3 hours |
| Phase 4: Views/console | ~15 min |
| Phase 5: Model (optional) | ~15 min |
| Testing | ~1–2 hours |
| **Total** | **~5–8 hours** |
