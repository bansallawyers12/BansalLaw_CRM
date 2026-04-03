# Checklist Tables Rename – Verification Report

**Date:** 2026-02-23  
**Migration:** `2026_02_23_000002_rename_checklist_tables`

---

## Summary

| Old Table              | New Table                    | Status   |
|------------------------|-----------------------------|----------|
| `document_checklists`  | `portal_document_checklists`| Renamed  |
| `upload_checklists`   | `matter_checklists`         | Renamed  |

---

## Database Verification

```
portal_document_checklists: exists
matter_checklists: exists
document_checklists: missing (correctly renamed)
upload_checklists: missing (correctly renamed)
```

---

## Code References Verified

### ✅ Updated to use new table names

| Location | Reference Type | Status |
|----------|----------------|--------|
| `App\Models\DocumentChecklist` | `$table = 'portal_document_checklists'` | ✅ |
| `App\Models\UploadChecklist` | `$table = 'matter_checklists'` | ✅ |
| `ClientPortalDocumentController` | `DB::table('portal_document_checklists')` (4 places) | ✅ |
| `DocumentChecklistController` | `Rule::unique('portal_document_checklists')` (2 places) | ✅ |
| `documentchecklist/index.blade.php` | `deleteAction(..., 'portal_document_checklists')` | ✅ |
| `uploadchecklist/index.blade.php` | `deleteAction(..., 'matter_checklists')` | ✅ |
| `Client_Portal_Postman_Collection.json` | Descriptions updated | ✅ |

### ✅ Uses Eloquent models (auto-resolve table)

All usages of `UploadChecklist` and `DocumentChecklist` models automatically use the new `$table`:

- `UploadChecklistController` – index, store, showByMatter
- `DocumentChecklistController` – index, store, edit, update
- `DocumentController` – email attachments, checklist files
- `CRMUtilityController` – template macros, email attachments
- `checklists.blade.php` – DocumentChecklist for personal/visa modals
- `clients/detail.blade.php`, `companies/detail.blade.php`, `leads/detail.blade.php`, `leads/history.blade.php` – checklist file selection
- `documents/index.blade.php`, `crm/documents/index.blade.php` – matter checklist files

### ⚠️ Intentionally unchanged

| Location | Reason |
|----------|--------|
| `2025_12_26_000001_fix_all_tables_primary_keys_and_duplicate_ids.php` | Migration runs *before* rename. Tables were `document_checklists` / `upload_checklists` when it ran. Historical reference only. |
| `routes/clients.php` – route names `upload_checklists.index`, `upload_checklists.matter` | Route names are identifiers; changing would break bookmarks/links. URLs (`/upload-checklists`) unchanged. |
| `resources/views/AdminConsole/system/roles/*.blade.php` – `data-class="document_checklist"` | CSS class and permission label; not database-related. |
| `public/css/custom.css` – `.document_checklist` | Styling class; not database-related. |

---

## No Foreign Keys

- `documents` table uses `checklist` (varchar) for the checklist *name*, not an ID. No FK to either table.
- `application_documents` uses `list_id` → `cp_doc_checklist`, a different table.
- No other tables have FKs to `document_checklists` or `upload_checklists`.

---

## Rollback

To rollback:
```bash
php artisan migrate:rollback
```
This will rename `portal_document_checklists` → `document_checklists` and `matter_checklists` → `upload_checklists`.  
**Note:** Code references would need to be reverted manually if rolling back.
