# Application → Matter Migration Plan

This document lists remaining changes to complete the migration from "application" to "matter" terminology. These items require database migrations, coordinated multi-file updates, or careful verification—plan to do them together with other changes.

---

## 1. Database Migration: `activities_logs.use_for`

**Current state:** `activities_logs.use_for = 'application'` identifies matter/client-portal related activity (stage changes, notes).

**Files affected:**
- `app/Http/Controllers/CRM/ClientPortalController.php` (lines 711, 751, 1629, 1675, 1692, 1763)
- `app/Http/Controllers/CRM/Clients/ClientNotesController.php` (lines 325–326)
- `routes/clients.php` – route `/viewapplicationnote`

**Migration steps:**
1. Create migration to add `use_for = 'matter'` as alias OR update existing `'application'` → `'matter'`:
   ```php
   DB::table('activities_logs')->where('use_for', 'application')->update(['use_for' => 'matter']);
   ```
2. Update all PHP code to use `use_for = 'matter'`.
3. Optionally add route alias `/viewmatternote` → same controller, and update frontend config `viewApplicationNote` → `viewMatterNote`.

**Risk:** Medium – ensure all queries filtering by `use_for` are updated; test note viewing and stage logs.

---

## 2. Payment Schedule & Invoice Forms – OBSOLETE ✓

**Status:** All features removed. Payment schedule setup, Create Invoice from schedule, Commission Invoice, Edit/Add payment schedule, and workflow checklist upload no longer exist. See `docs/SECTION_2_PAYMENT_INVOICE_APPLICATION_TO_CLIENT_MATTER_PLAN.md` for details.

---

## 3. CacheService: Rename Keys

**Current state:**
- `PENDING_APPLICATIONS` constant
- `clientApplicationsKey()` method
- Used by `clearClientCache()` and `clearDashboardCache()` (no external callers found)

**File:** `app/Services/CacheService.php`

**Migration steps:**
1. Rename `PENDING_APPLICATIONS` → `PENDING_MATTERS`.
2. Rename `clientApplicationsKey()` → `clientMattersKey()`, return `"client_{$clientId}_matters"`.
3. Update `clearClientCache()` and `clearDashboardCache()` to use new names.
4. If Redis/cache has existing keys like `client_123_applications`, either clear them or add a one-time migration to copy/delete.

**Risk:** Low – methods appear unused externally; ensure no code caches to old keys.

---

## 4. Admin Roles: APPLICATIONS → Matters / Client Portal

**Current state:** Role create/edit UI shows "APPLICATIONS" section with permissions like "Can create applications".

**File:** `resources/views/AdminConsole/system/roles/create.blade.php` (and edit if exists)

**Migration steps:**
1. Change accordion header "APPLICATIONS" → "MATTERS" or "CLIENT PORTAL".
2. Update labels: "Can create applications" → "Can create matters", etc.
3. Keep `module_access[34]`, etc.—only change display labels.
4. Check role edit view and any role/permission seeders.

**Risk:** Low – UI only; backend permission IDs unchanged.

---

## 5. Modal IDs & CSS Classes (Optional, Lower Priority)

**Current state:** Some modal/class names still reference "application".

| Location | Current | Suggested |
|----------|---------|-----------|
| Modal `id="view_application_note"` | notes.blade.php | `view_matter_note` (requires JS updates) |
| Modal `id="create_applicationnote"` | notes.blade.php | `create_matternote` |
| Div `id="application_note_detail_view"` | notes.blade.php | `matter_note_detail_view` |
| CSS `.is_application` | layouts/crm_client_detail*.blade.php | `.is_matter` |
| CSS `.application_report_list`, `.application_report_data` | layouts | `.matter_report_list`, etc. |
| Class `viewapplicationnote` | ClientPortalController, notes.js | `viewmatternote` |

**Migration steps:**
1. Rename modal IDs and update all JS references (notes.js, custom-form-validation.js).
2. Rename CSS classes and update all Blade/JS selectors.
3. Rename `viewapplicationnote` class and config key `viewApplicationNote` → `viewMatterNote`.

**Risk:** Medium – many touchpoints; thorough regression testing needed.

---

## 6. Route & Controller Method Names (Optional)

**Current state:**
- Route: `GET /viewapplicationnote`
- Controller method: `viewapplicationnote()`
- Config key: `viewApplicationNote`

**Migration steps:**
1. Add route `GET /viewmatternote` pointing to same controller method.
2. Add route redirect: `/viewapplicationnote` → `/viewmatternote` (for backward compatibility).
3. Update `ClientDetailConfig.urls.viewApplicationNote` → `viewMatterNote` and URL in views.
4. Optionally rename controller method to `viewMatterNote()`.

**Risk:** Low if redirect is added; medium if breaking old URLs.

---

## 7. StoreClientRequest: `application_id` Field

**File:** `app/Http/Requests/StoreClientRequest.php`

**Current:** `'application_id' => 'nullable|string|max:255'`

**Migration steps:**
1. Determine if this is used for client import/creation—check ClientsController and import flows.
2. If used, add `client_matter_id` or `file_number` as new field; deprecate `application_id`.
3. Update any forms that submit this field.

**Risk:** Low – verify usage first.

---

## 8. `client_application_sent` → `client_portal_sent` ✅ DONE

**Completed:** Migration `2026_02_27_120000_rename_client_application_sent_to_client_portal_sent` created. Columns renamed; all code updated.

---

## 9. Post-Deploy Verification

After migrations:

1. Run `php artisan view:clear` to regenerate compiled views.
2. Test: Notification/message links open client detail with Client Portal tab.
3. Test: Discontinue/Revert Matter modals work.
4. Test: Matter notes (view/create) work.
5. Test: Application Ownership form posts to `/client-portal/ownership` and does not 404.
6. Test: Client Portal tab loads; matter workflow and checklists work.
7. If API consumers exist: verify they use `matter_info` and `client_matter_id`.

---

## Summary: Direct Fixes Already Applied (No Migration)

- Application Ownership form action: `/application/application_ownership` → `/client-portal/ownership`
- Modal title: "Application Ownership Ratio" → "Matter Ownership Ratio"
- Renamed `application.js` → `client_portal.js` and updated script references
