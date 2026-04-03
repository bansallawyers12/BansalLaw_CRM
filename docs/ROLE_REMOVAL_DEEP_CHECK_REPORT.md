# Deep Check Report: Role Removal for Clients/Leads

**Date:** 2025-02-25  
**Scope:** Remove `role` usage for clients/leads (use `type` instead). Column kept for backward compat.

---

## Summary

Staff have been moved to the `staff` table. The `admins` table now holds only clients/leads, distinguished by `type` ('client' or 'lead'). The `role` column is deprecated for clients/leads.

---

## Changes Applied

### 1. Application Code ✓
| File | Change |
|------|--------|
| `Admin` model | Added `type` to fillable; role marked deprecated in comment |
| `SampleBookingAppointmentsSeeder` | `where('role', 7)` → `whereIn('type', ['client', 'lead'])`; removed `role` from create |
| `AdminFactory` | `role => 7` → `type => 'client'` |
| `LeadFactory` | Removed `role => 7`; `user_id` now uses `Staff::query()->value('id')` |
| `ClientPortalController` (API) | Removed `role` from getProfile and updateProfile responses |
| `ClientExportService` | Already uses `whereIn('type', ['client', 'lead'])` ✓ |
| `ClientImportService` | Already uses `whereIn('type', ['client', 'lead'])` and sets `type` ✓ |
| `ClientsController` export | Already uses `whereIn('type', ['client', 'lead'])` ✓ |
| `ClientPortalController` login | Already uses `whereIn('type', ['client', 'lead'])` ✓ |
| `AppointmentSyncService` | Already uses `whereIn('type', ['client', 'lead'])` ✓ |

### 2. Tests ✓
| File | Change |
|------|--------|
| `ClientEoiRoiControllerTest` | `role => 7` → `type => 'client'` |
| `AdminConsoleRoutesTest` | Same |
| `VisaExportImportTest` | Same |
| `DocumentPolicyTest` | Same |
| `PointsServiceTest` | Same |
| `ClientReferenceRaceConditionTest` | `role => 7` → `type => 'client'` in DB inserts |

### 3. Documentation ✓
| File | Change |
|------|--------|
| `BANSALCRM2_IMPORT_EXPORT_IMPLEMENTATION_GUIDE.md` | All `where('role', 7)` → `whereIn('type', ['client', 'lead'])`; removed `$client->role = 7` |

---

## Role Usage That Remains (Correct – Staff Only)

All `Auth::user()->role` usages in CRM/AdminConsole refer to **Staff** (admin guard uses Staff model). These are correct:
- `role == 1` (Super Admin)
- `role == 12`, `16`, etc. (other staff roles)
- `checkAuthorizationAction(..., Auth::user()->role)`
- StaffController, AssigneeController, etc.

---

## Potential Edge Cases

### 1. `LeadFactory` user_id
- **Change:** `Staff::query()->value('id')` (returns null if no staff)
- **Risk:** If `user_id` is NOT NULL and no staff exists, Lead factory fails
- **Mitigation:** Tests typically seed staff; production has staff

### 2. Admin `usertype()` relationship
- For clients with `role = null`, `usertype()` returns null
- `offices/viewclient.blade.php` loads `with(['usertype'])` but does not display it – safe

---

## Files Not Changed (No Role for Clients)

- `docs/PLAN_DEDICATED_STAFF_TABLE.md` – historical plan
- `docs/STAFF_TABLE_COLUMNS.md` – documentation
- `Client_Portal_Postman_Collection.json` – API examples (role in response removed from API)
- Migration comments (e.g. `booking_appointments.client_id` FK comment) – can update later

---

## Verification Checklist

- [ ] Run tests: `php artisan test`
- [ ] Client import/export works
- [ ] Client Portal API login and profile return no `role`
- [ ] New clients created via AdminFactory have `type` set
- [ ] Client list/export filters by `type` not `role`
