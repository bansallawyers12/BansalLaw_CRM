# High Priority: Application → Matter Migration Plan

This plan covers the two high-priority items that prevent confusion and fix bugs.

---

## Item 1: validTabNames

### Problem
- `detail.blade.php` and `companies/detail.blade.php` have `'application'` in `validTabNames`
- The actual Client Portal tab uses `data-tab="client_portal"`
- Notification URLs now point to `/client_portal`, not `/application`
- Having `'application'` without `'client_portal'` (in some files) can cause URLs like `/clients/detail/123/application` to be treated as a tab name instead of a matter ref, while `/clients/detail/123/client_portal` might not be recognised

### Current State
| File | validTabNames |
|------|---------------|
| `resources/views/crm/clients/detail.blade.php` | `..., 'application', 'workflow', 'checklists']` |
| `resources/views/crm/companies/detail.blade.php` | `..., 'application', 'checklists']` |
| `resources/views/crm/clients/tabs/client_portal.blade.php` | Has `'client_portal'` (no `'application'`) |

### Implementation Steps

**Step 1.1** – Update `resources/views/crm/clients/detail.blade.php` (around line 312–314)

```php
// BEFORE
$validTabNames = ['personaldetails', 'noteterm', 'personaldocuments', 'visadocuments', 
                  'eoiroi', 'emails', 
                  'formgenerations', 'formgenerationsL', 'application', 'workflow', 'checklists'];

// AFTER (keep both for backward compatibility with old bookmarks; client_portal is canonical)
$validTabNames = ['personaldetails', 'noteterm', 'personaldocuments', 'visadocuments', 
                  'eoiroi', 'emails', 
                  'formgenerations', 'formgenerationsL', 'client_portal', 'application', 'workflow', 'checklists'];
```

**Step 1.2** – Update `resources/views/crm/companies/detail.blade.php` (around line 327–329)

```php
// BEFORE
$validTabNames = ['companydetails', 'noteterm', 'personaldocuments', 'visadocuments',
                  'formgenerations', 'formgenerationsL', 'application', 'checklists'];

// AFTER
$validTabNames = ['companydetails', 'noteterm', 'personaldocuments', 'visadocuments',
                  'formgenerations', 'formgenerationsL', 'client_portal', 'application', 'checklists'];
```

**Optional (when old URLs are no longer needed):** Remove `'application'` and keep only `'client_portal'`.

### Verification
- Visit `/clients/detail/{id}/client_portal` → should open Client Portal tab, not treat `client_portal` as matter ID
- Visit `/clients/detail/{id}/application` → should still work (backward compat) if `'application'` is kept
- Run `php artisan view:clear` after changes

---

## Item 2: activities_logs.use_for

### Problem
- `activities_logs.use_for = 'application'` identifies matter/client-portal activity (stage changes, notes)
- Confusing for developers; “application” no longer matches current terminology

### Current State
| Location | Usage |
|----------|--------|
| `ClientPortalController.php` line 711 | `$obj->use_for = 'application';` (stage change log) |
| `ClientPortalController.php` line 751 | `$obj->use_for = 'application';` (stage change log) |
| `ClientPortalController.php` line 1629 | `->where('use_for', 'application')` (notes list) |
| `ClientPortalController.php` line 1675 | `$obj->use_for = 'application';` (create note) |
| `ClientPortalController.php` line 1692 | `->where('use_for','application')` (notes list) |
| `ClientPortalController.php` line 1763 | `$objs->use_for = 'application';` (create note) |
| `ClientNotesController.php` line 325 | `->where('use_for','application')` (view note exists) |
| `ClientNotesController.php` line 326 | `->where('use_for','application')` (view note fetch) |

**Note:** `use_for` is polymorphic. It can also store assignee IDs (integers) for appointments/tasks. Only string `'application'` is being migrated to `'matter'`.

### Implementation Steps

**Step 2.1** – Create migration

```bash
php artisan make:migration update_activities_logs_use_for_application_to_matter
```

**Step 2.2** – Migration content

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activities_logs')
            ->where('use_for', 'application')
            ->update(['use_for' => 'matter']);
    }

    public function down(): void
    {
        DB::table('activities_logs')
            ->where('use_for', 'matter')
            ->update(['use_for' => 'application']);
    }
};
```

**Step 2.3** – Code changes (before or after migration; deploy together)

| File | Line | Change |
|------|------|--------|
| `app/Http/Controllers/CRM/ClientPortalController.php` | 711 | `'application'` → `'matter'` |
| `app/Http/Controllers/CRM/ClientPortalController.php` | 751 | `'application'` → `'matter'` |
| `app/Http/Controllers/CRM/ClientPortalController.php` | 1629 | `'application'` → `'matter'` |
| `app/Http/Controllers/CRM/ClientPortalController.php` | 1675 | `'application'` → `'matter'` |
| `app/Http/Controllers/CRM/ClientPortalController.php` | 1692 | `'application'` → `'matter'` |
| `app/Http/Controllers/CRM/ClientPortalController.php` | 1763 | `'application'` → `'matter'` |
| `app/Http/Controllers/CRM/Clients/ClientNotesController.php` | 325 | `'application'` → `'matter'` |
| `app/Http/Controllers/CRM/Clients/ClientNotesController.php` | 326 | `'application'` → `'matter'` |

**Step 2.4** – Check for other references

Run:
```bash
grep -r "use_for.*application\|'application'" app/ --include="*.php"
```
Ensure no remaining `use_for = 'application'` or `where('use_for', 'application')` except in migrations.

### Verification
1. Run migration: `php artisan migrate`
2. Create a matter note → verify it appears in Client Portal notes
3. Move matter to next/previous stage → verify stage log appears
4. View matter note → verify modal opens and shows content
5. Check `activities_logs` table: `SELECT use_for, COUNT(*) FROM activities_logs GROUP BY use_for` → no rows with `use_for = 'application'`

### Rollback
To rollback:
1. Run `php artisan migrate:rollback` (reverts data)
2. Revert the 8 code changes above

---

## Execution Order

1. **validTabNames** (no migration, low risk)
   - Update both Blade files
   - Run `php artisan view:clear`
   - Smoke test client detail URLs

2. **activities_logs.use_for** (migration + code)
   - Create migration
   - Update 8 code locations
   - Run migration
   - Test matter notes and stage logs

---

## Post-Implementation Checklist

- [x] validTabNames updated in both files
- [x] `php artisan view:clear` run
- [x] Migration created and run
- [x] All 8 code references updated
- [ ] Matter notes: create, view (manual test)
- [ ] Stage changes: next/previous (manual test)
- [ ] Client Portal tab loads correctly (manual test)
- [ ] URLs `/clients/detail/{id}/client_portal` and `/clients/detail/{id}/application` behave as expected (manual test)

### Verification Fixes Applied (2026-02-27)

**Additional changes found during deep verification:**

1. **5 ActivitiesLog creations** (updateClientMatterNextStage, updateClientMatterPreviousStage, changeClientMatterWorkflow, discontinueClientMatter, reopenClientMatter) did not set `use_for = 'matter'`. The Client Portal accordion stage history filters by `use_for = 'matter'`, so those stage changes would not appear. **Fixed.**

2. **$currentTab checks** for discontinue/reopen notifications only checked `=== 'application'`. The canonical Client Portal tab uses `data-tab="client_portal"`, so notifications would not be sent when using the new tab. **Fixed** to accept both `'application'` and `'client_portal'`.
