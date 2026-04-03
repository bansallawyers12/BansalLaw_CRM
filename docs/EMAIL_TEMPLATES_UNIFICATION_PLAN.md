# Plan: Unify Email Template Tables into `email_templates`

## Executive Summary

Merge the three email template tables (`crm_email_templates`, `matter_email_templates`, `matter_other_email_templates`) into a single `email_templates` table with a nullable `matter_id` and a `type` discriminator column. This simplifies queries, eliminates the three-way fallback in `gettemplates()`, and aligns with other unification patterns in the codebase (e.g. `lead_reminders`, `lead_matter_references`).

---

## Current State

### Tables and Semantics

| Table | Purpose | matter_id | Typical Count |
|-------|---------|-----------|---------------|
| `crm_email_templates` | Global templates for clients/leads/companies | N/A | Multiple (global) |
| `matter_email_templates` | "First email" template – one per matter | Required | 0–1 per matter |
| `matter_other_email_templates` | Additional matter-specific templates | Required | 0+ per matter |

### Schema (inferred from models and controllers)

**crm_email_templates**
```php
- id (bigint, PK)
- name (string)
- subject (string)
- description (text)
- created_at, updated_at
```

**matter_email_templates**
```php
- id (bigint, PK)
- matter_id (bigint, FK → matters)
- name (string)
- subject (string)
- description (text)
- created_at, updated_at
```

**matter_other_email_templates**
```php
- id (bigint, PK)
- matter_id (bigint, FK → matters)
- name (string)
- subject (string)
- description (text)
- created_at, updated_at
```

All three share the same logical columns: `id`, `name`, `subject`, `description`, `created_at`, `updated_at`. Matter-scoped tables add `matter_id`.

---

## Proposed New Schema

**Database:** PostgreSQL (supports partial unique indexes for `matter_first` constraint).

### `email_templates` table

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | Auto-increment |
| `type` | string(50), indexed | `crm`, `matter_first`, `matter_other` |
| `alias` | string(100) nullable, indexed | For legacy lookups (CustomMailService, Controller, CronJob); optional on CRM templates |
| `matter_id` | bigint nullable FK | → `matters`; null when `type = crm` |
| `name` | string | Template display name |
| `subject` | string | Email subject line |
| `description` | text | Email body (HTML) |
| `created_at`, `updated_at` | timestamps | |

### Type Enumeration

| type | matter_id | Meaning |
|------|-----------|---------|
| `crm` | NULL | Global template (ex-crm_email_templates) |
| `matter_first` | required | One "first email" per matter (ex-matter_email_templates) |
| `matter_other` | required | Additional templates per matter (ex-matter_other_email_templates) |

### Indexes

- `(type, matter_id)` – composite index for matter-scoped queries
- `type` – index for CRM-only queries
- `matter_id` – index (Laravel foreign key adds this)
- Partial unique index (PostgreSQL): `(matter_id) WHERE type = 'matter_first'` – ensures at most one first template per matter
- Foreign key: `matter_id` → `matters` (nullable, `onDelete('cascade')` when matter is deleted)

### Constraints

- Check constraint or application-level validation: `matter_id IS NOT NULL` when `type IN ('matter_first', 'matter_other')`
- Application logic ensures at most one `matter_first` per matter (current MatterEmailTemplate is one-per-matter)

---

## Implementation Plan

### Phase 1: Database Migration

1. **Create migration** `2026_02_27_000000_create_email_templates_and_migrate.php`:
   - Create `email_templates` with schema above
   - Map old tables to `type` values:
     - `crm_email_templates` → `crm`
     - `matter_email_templates` → `matter_first`
     - `matter_other_email_templates` → `matter_other`
   - For each old table: if it exists, migrate rows with the correct `type` (and `matter_id` where applicable), then drop it
   - Use `DB::transaction()` for atomicity (recommended)
   - Chunk large tables if needed (>10k rows) to avoid memory issues

   **Up pseudocode:**

   ```php
   DB::transaction(function () {
       Schema::create('email_templates', function (Blueprint $table) {
           $table->id();
           $table->string('type', 50)->index();
           $table->unsignedBigInteger('matter_id')->nullable()->index();
           $table->string('name');
           $table->string('subject')->nullable();
           $table->text('description')->nullable();
           $table->timestamps();
           $table->foreign('matter_id')->references('id')->on('matters')->onDelete('cascade');
           $table->index(['type', 'matter_id']);
       });
       // PostgreSQL partial unique index: one matter_first per matter
       DB::statement("CREATE UNIQUE INDEX email_templates_matter_first_unique ON email_templates (matter_id) WHERE type = 'matter_first'");

       $mappings = [
           'crm' => ['table' => 'crm_email_templates', 'matter_id' => null],
           'matter_first' => ['table' => 'matter_email_templates', 'matter_id' => 'matter_id'],
           'matter_other' => ['table' => 'matter_other_email_templates', 'matter_id' => 'matter_id'],
       ];

       foreach ($mappings as $type => $config) {
           if (Schema::hasTable($config['table'])) {
               $rows = DB::table($config['table'])->get();
               foreach ($rows as $row) {
                   DB::table('email_templates')->insert([
                       'type' => $type,
                       'matter_id' => $config['matter_id'] ? $row->{$config['matter_id']} : null,
                       'name' => $row->name,
                       'subject' => $row->subject ?? null,
                       'description' => $row->description ?? null,
                       'created_at' => $row->created_at,
                       'updated_at' => $row->updated_at,
                   ]);
               }
               Schema::dropIfExists($config['table']);
           }
       }
   });
   ```

2. **down() implementation** must:
   - Recreate the three original tables with exact schema (see Current State section for columns). Example for `crm_email_templates`: `id`, `name`, `subject`, `description`, `created_at`, `updated_at`. Add `matter_id` for matter tables. Add `matters` FK for matter-scoped tables.
   - Copy rows from `email_templates` back: filter by `type`, map columns, insert (omit `type`). Use `email_templates.id` for restored `id` if desired, or omit for new auto-increment.
   - Drop `email_templates`
   - **Note:** Restored rows get new auto-increment IDs unless explicitly set; original pre-migration IDs are not restored. For rollback, data restoration is primary; reverted code must reference old table names.

---

### Phase 2: New Model and Deprecate Old Models

1. **Create** `app/Models/EmailTemplate.php`:

   ```php
   <?php
   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;
   use Kyslik\ColumnSortable\Sortable;

   class EmailTemplate extends Model
   {
       use Sortable;

       protected $table = 'email_templates';
       protected $fillable = ['type', 'matter_id', 'name', 'subject', 'description'];
       public $sortable = ['id', 'name', 'subject', 'created_at', 'updated_at'];

       public const TYPE_CRM = 'crm';
       public const TYPE_MATTER_FIRST = 'matter_first';
       public const TYPE_MATTER_OTHER = 'matter_other';

       public function matter()
       {
           return $this->belongsTo(Matter::class, 'matter_id');
       }

       public function scopeOfType($query, string $type)
       {
           return $query->where('type', $type);
       }

       public function scopeCrm($query)
       {
           return $query->where('type', self::TYPE_CRM);
       }

       public function scopeForMatter($query, int $matterId)
       {
           return $query->where('matter_id', $matterId);
       }
   }
   ```

2. **Update** `app/Models/Matter.php`:
   - Update `otherEmailTemplates()` to query `EmailTemplate` with `type = matter_other`
   - Add `firstEmailTemplate()` for `type = matter_first`

   ```php
   public function otherEmailTemplates()
   {
       return $this->hasMany(EmailTemplate::class, 'matter_id')->where('type', EmailTemplate::TYPE_MATTER_OTHER);
   }

   public function firstEmailTemplate()
   {
       return $this->hasOne(EmailTemplate::class, 'matter_id')->where('type', EmailTemplate::TYPE_MATTER_FIRST);
   }
   ```

3. **Deprecate** `CrmEmailTemplate`, `MatterEmailTemplate`, `MatterOtherEmailTemplate` – keep stub classes that extend `EmailTemplate` and add `$table` / scopes for backward compatibility during transition, or remove once all usages are migrated.

---

### Phase 3: Controller & Route Consolidation

**Option A: Gradual migration (recommended)**  
- Create new `EmailTemplateController` that uses `EmailTemplate` model and filters by `type`.
- Keep existing controllers temporarily, but have them query `email_templates` via `EmailTemplate` with appropriate `type` scope.
- Eventually merge admin routes into a single controller with `type` as route/query param.

**Option B: Single controller from day one**  
- Replace `CrmEmailTemplateController`, `MatterEmailTemplateController`, `MatterOtherEmailTemplateController` with one `EmailTemplateController`.
- Routes:
  - `/admin/crm-email-template` → `type=crm`
  - `/admin/matter-email-template` → `type=matter_first`
  - `/admin/matter-other-email-template/{matterId}` → `type=matter_other`, scoped by matter

---

### Phase 4: Code Changes (Usage Points)

| Location | Current | Change |
|----------|---------|--------|
| **CRMUtilityController::gettemplates()** | Three separate model lookups (Crm → Matter → MatterOther) | Single query: `EmailTemplate::find($id)` |
| **CRMUtilityController::getmattertemplates()** | `MatterEmailTemplate::where('id', $id)->first()` only; variable misnamed `$CrmEmailTemplate` | Single query: `EmailTemplate::find($id)` |
| **CRMUtilityController::getComposeDefaults()** | `MatterEmailTemplate::where('matter_id', $matterId)->first()` + `MatterOtherEmailTemplate::where('matter_id', $matterId)->get()` | `EmailTemplate::forMatter($matterId)->ofType('matter_first')->first()` + `EmailTemplate::forMatter($matterId)->ofType('matter_other')->orderBy('id')->get()` |
| **deleteAction** (CRMUtilityController) | Table names: `crm_email_templates`, `matter_email_templates`, `matter_other_email_templates` | All pass `email_templates` – delete by `id` (IDs unique in unified table) |
| **Views (template dropdowns)** | `CrmEmailTemplate::all()` / `orderBy('id','desc')`, `MatterEmailTemplate::where('matter_id', ...)` | `EmailTemplate::crm()->get()`, `EmailTemplate::forMatter($matterId)->ofType('matter_first')->get()` (see Phase 5 for documents) |
| **VisaExpireReminderEmail** | `CrmEmailTemplate::where('id', 35)->first()` – hardcoded ID | Replace with config key or name lookup: `EmailTemplate::crm()->where('name', 'like', '%visa expire%')->first()` or `config('email_templates.visa_expiry_id')` |
| **Matter index (hasTemplate)** | `MatterEmailTemplate::where('matter_id', $list->id)->exists()` | `EmailTemplate::forMatter($list->id)->ofType('matter_first')->exists()` |
| **Admin controllers (store/update)** | Create/update without `type` | Add `type` on create: `type => TYPE_CRM` / `TYPE_MATTER_FIRST` / `TYPE_MATTER_OTHER` |
| **Admin views deleteAction** | Pass table name to JS | Pass `email_templates` |

---

### Phase 5: View Updates

| File | Change |
|------|--------|
| `crm/clients/detail.blade.php` | `CrmEmailTemplate::orderBy('id','desc')->get()` → `EmailTemplate::crm()->orderBy('id','desc')->get()` |
| `crm/clients/index.blade.php` | `CrmEmailTemplate::all()` → `EmailTemplate::crm()->orderBy('id','desc')->get()` |
| `crm/companies/detail.blade.php` | `CrmEmailTemplate::orderBy('id','desc')->get()` → `EmailTemplate::crm()->orderBy('id','desc')->get()` |
| `crm/leads/detail.blade.php` | `CrmEmailTemplate::all()` → `EmailTemplate::crm()->orderBy('id','desc')->get()` |
| `crm/leads/history.blade.php` | Same |
| `crm/clients/modals/emails.blade.php` | `CrmEmailTemplate::all()` → `EmailTemplate::crm()->orderBy('id','desc')->get()` |
| `crm/documents/index.blade.php` | `MatterEmailTemplate::where('matter_id', ...)->orderBy('id','asc')` → `EmailTemplate::forMatter(...)->ofType('matter_first')->orderBy('id','asc')->get()`. *Note: Preserves current behavior (first template only). If product requires both first + other, use `->whereIn('type', ['matter_first','matter_other'])`.* |
| `documents/index.blade.php` | Same as crm/documents |
| `AdminConsole/features/crmemailtemplate/index.blade.php` | `deleteAction(..., 'crm_email_templates')` → `deleteAction(..., 'email_templates')` |
| `AdminConsole/features/matteremailtemplate/index.blade.php` | `deleteAction(..., 'matter_email_templates')` → `deleteAction(..., 'email_templates')` |
| `AdminConsole/features/matterotheremailtemplate/index.blade.php` | `deleteAction(..., 'matter_other_email_templates')` → `deleteAction(..., 'email_templates')` |
| `AdminConsole/features/matter/index.blade.php` | `MatterEmailTemplate::where('matter_id', $list->id)->exists()/first()` → `EmailTemplate::forMatter($list->id)->ofType('matter_first')->exists()/first()` |

---

## Post-Implementation Review (2026-02-28)

### Issues Found and Fixed

1. **Alias column:** `CustomMailService`, `Controller`, and `CronJob` query `email_templates` by `alias`. The unified table had no `alias` column, which would cause SQL errors. **Fix:** Added migration `2026_02_28_000000_add_alias_to_email_templates.php` to add nullable `alias` column. Legacy code now works (returns null when no match, uses fallback content).

2. **fix_all_tables migration:** Still referenced dropped tables `crm_email_templates`, `matter_email_templates`, `matter_other_email_templates`. **Fix:** Replaced with `email_templates` in the table list.

3. **scopeForMatter null safety:** Documents views pass `sel_matter_id` which could theoretically be null. **Fix:** `scopeForMatter(?int $matterId)` now handles null via `$matterId ?? 0` to avoid type errors.

4. **Stale view cache:** `storage/framework/views` had compiled view referencing `CrmEmailTemplate`. **Fix:** Run `php artisan view:clear` after deployment.

---

## Migration Data Mapping

| Old Table | type | matter_id |
|-----------|------|-----------|
| crm_email_templates | `crm` | NULL |
| matter_email_templates | `matter_first` | row.matter_id |
| matter_other_email_templates | `matter_other` | row.matter_id |

---

## ID Handling

- IDs are unique within each old table but can overlap across tables (e.g. `crm_email_templates.id=1` and `matter_email_templates.id=1`).
- **Default migration (plain insert):** Omitting `id` from inserts lets PostgreSQL sequences assign new IDs. Migration order (crm → matter_first → matter_other) yields sequential IDs; old IDs are **not** preserved.
- **Impact:** `VisaExpireReminderEmail` hardcodes `id=35`. After migration, that ID may point to a different template (or none) unless:
  - **Option A (recommended):** Replace hardcoded ID with config or name lookup, e.g. `config('email_templates.visa_expiry_id')` or `EmailTemplate::crm()->where('name', 'like', '%Visa Expir%')->first()`. Run a one-off after migration to set the config value.
  - **Option B:** Preserve CRM IDs by inserting with explicit `id` for `crm_email_templates` only (first table). Risk: if `matter_*` tables have an id that collides with a CRM id, later inserts fail unless we offset. Complexity increases.
- **Recommendation:** Use Option A. Do not rely on ID preservation. Update `VisaExpireReminderEmail` before or during the same deploy.

---

## Interplay with Other Systems

| Plan | Relationship |
|------|--------------|
| Lead Reminders | Unrelated – different tables |
| Lead Matter References | Unrelated – different tables |
| Matter model | Update `otherEmailTemplates` and add `firstEmailTemplate` relationship |

---

## Rollback Strategy

- Implement full `down()` in migration: recreate three tables, copy data back by `type`, drop `email_templates`.
- Revert code and config to use old models and table names.
- **Caveat:** If new IDs were assigned, any code/store that saved new `email_templates.id` values would need to be reverted or reconciled.

---

## Deployment

1. Run migration (`php artisan migrate`) – creates `email_templates`, migrates data, drops old tables.
2. Deploy updated code (models, controllers, views) in the same release.
3. **Order:** Migration first, then deploy code, or run both in same deploy window.

---

## Testing Checklist

- [ ] Migration runs on DB with existing data in all three tables
- [ ] Migration runs on fresh DB (tables may be empty)
- [ ] `gettemplates()` and `getmattertemplates()` return correct subject/description for all template types
- [ ] `getComposeDefaults()` returns first template + other templates for a matter
- [ ] Template dropdowns in clients, leads, companies show correct lists
- [ ] Matter index shows correct "has template" indicator
- [ ] Admin CRUD for CRM, matter-first, matter-other templates works
- [ ] Delete from all three admin index pages works (table `email_templates`)
- [ ] VisaExpireReminderEmail finds correct template (config/name lookup; no hardcoded ID)
- [ ] Rollback restores three tables and data

---

## Files to Modify

| File | Action |
|------|--------|
| `database/migrations/` | New: `2026_02_27_000000_create_email_templates_and_migrate.php` |
| `app/Models/EmailTemplate.php` | New |
| `app/Models/Matter.php` | Update relationships |
| `app/Models/CrmEmailTemplate.php` | Deprecate or remove |
| `app/Models/MatterEmailTemplate.php` | Deprecate or remove |
| `app/Models/MatterOtherEmailTemplate.php` | Deprecate or remove |
| `app/Http/Controllers/AdminConsole/CrmEmailTemplateController.php` | Use EmailTemplate + type scope |
| `app/Http/Controllers/AdminConsole/MatterEmailTemplateController.php` | Use EmailTemplate + type scope |
| `app/Http/Controllers/AdminConsole/MatterOtherEmailTemplateController.php` | Use EmailTemplate + type scope |
| `app/Http/Controllers/CRM/CRMUtilityController.php` | Simplify gettemplates, getmattertemplates, getComposeDefaults |
| `app/Console/Commands/VisaExpireReminderEmail.php` | Replace hardcoded id 35 with config or name lookup; use EmailTemplate |
| `config/email_templates.php` (optional) | New config file for `visa_expiry_template_id` or similar |
| `resources/views/...` | Update model refs and deleteAction table names (see Phase 5) |
| `routes/adminconsole.php` | Optional: consolidate routes |

---

## Risks & Considerations

1. **ID overlap:** If IDs overlap across tables, migration must assign new IDs. Update VisaExpireReminderEmail and any other hardcoded IDs.
2. **Deployment order:** Migration and code must be deployed together.
3. **deleteAction table name:** All three admin index views must pass `email_templates`; IDs are unique in the unified table.
4. **One matter_first per matter:** Add a partial unique index in PostgreSQL: `CREATE UNIQUE INDEX email_templates_matter_first_unique ON email_templates (matter_id) WHERE type = 'matter_first'`. This enforces at most one `matter_first` per matter without limiting `matter_other`. Also add validation in the controller as a safeguard.

---

## Timeline Estimate

| Phase | Effort |
|-------|--------|
| Phase 1: Migration | ~1–1.5 hours |
| Phase 2: Model | ~30 min |
| Phase 3: Controllers | ~1 hour |
| Phase 4–5: Views & usage | ~1–2 hours |
| Testing | ~1.5 hours |
| **Total** | **~5–6 hours** |
