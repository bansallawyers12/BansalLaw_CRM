# Cross-access & allocated-only visibility — implementation plan

**v5.3** — When `CRM_ACCESS_EXEMPT_STAFF_IDS` is omitted or blank, `exempt_staff_ids` is the **same resolved list** as `approver_staff_ids` (so `.env` approver-only edits stay in sync). Canonical nine ids remain the PHP fallback when both env lists are empty/invalid. **as-built** sections aligned with the Laravel codebase (Phases A–D implemented; Phase E mostly done — see §15).

This document turns agreed product rules into build phases for the Laravel CRM (default DB driver in `config/database.php` is `pgsql`; MySQL may be used per environment).

---

## 1. Goals (summary)

| Area | Rule |
|------|------|
| Default visibility | **Allocated only** for **all** staff **except** configurable **exempt roles** (Super Admin, Admin, + extras) **and exempt staff IDs**. |
| Exempt staff | No quick-access or supervisor request flow; full access; still **logged** in same audit stream as `access_type = exempt`. Exemption applies to both role-based (`exempt_role_ids`) and id-based (`exempt_staff_ids`) entries. |
| Extra access | **Quick** (15 min, selectable reason) or **Supervisor-approved** (24 h from approval). Quick-flagged users may **also** request 24 h. |
| Record types | **Clients and leads** — same cross-access model for both. |
| Initiation | **Search only** (header/global search + equivalent APIs); other deep links enforce grant check or 403/redirect. |
| Context | **Office** from `branches` table (FK on `staff.office_id` + `client_matters.office_id`); **team** from `teams` table (`id`, `name`, `color`) — `staff.team` is an integer FK into `teams.id`. Both stored as id + label snapshot on each grant. |
| Enforcement | **Server-side**, on **every protected action**, using **server UTC** time. |
| Audit | One reporting stream: grants + exempt access; `access_type` = `quick` / `supervisor_approved` / `exempt`. |
| Approvers | **Super Admin (role 1)** + fixed staff ids: `36834, 36524, 36692, 36483, 36484, 36718, 36523, 36836, 36830`. |
| Approval queue UI | Visible **only** to Super Admin + approver staff ids; entry from **dashboard** and **popup**. |

---

## 2. Confirmed role IDs (from `user_roles` table)

| `user_roles.id` | Name | Visibility today | v3 treatment |
|----------------|------|-----------------|--------------|
| **1** | Super Admin | All | **Exempt** — no flow, logged as `exempt` |
| **12** | Person Responsible | All leads + all clients | **Restrict to allocated** in Phase C |
| **13** | Person Assisting | Clients/leads where PA on matter or `user_id` match | Already restricted; grant flow adds temp extension |
| **14** | Calling Team | All clients today | **Restrict**; **quick access only** (no supervisor path); `quick_access_enabled` defaults `true` for this role |
| **15** | Accountant | All clients today | **Restrict**; standard grant flow |
| **16** | Migration Agent | All clients today | **Restrict**; standard grant flow |
| **17** | Admin | All | **Exempt** — no flow, logged as `exempt` |

**Key decisions locked:**

- **Exempt roles** (bypass all flows, logged as `exempt`): `1` (Super Admin) and `17` (Admin). Config key: `CRM_ACCESS_EXEMPT_ROLE_IDS=1,17`.
- **Exempt staff IDs** (same bypass as exempt roles, regardless of their role): **omit or leave blank** `CRM_ACCESS_EXEMPT_STAFF_IDS` in `.env` and exempt ids **mirror** the resolved `approver_staff_ids` list (from `CRM_ACCESS_APPROVER_STAFF_IDS` + `$intList` fallbacks in `config/crm_access.php`). Set `CRM_ACCESS_EXEMPT_STAFF_IDS` only when exempt must **differ** from approvers. Canonical default for both lists when env is empty/invalid: `36834, 36524, 36692, 36483, 36484, 36718, 36523, 36836, 36830`. Checked in `CrmAccessService::isExemptRole` and `StaffClientVisibility::isExemptFromAllocation` — full client/lead access; logged as `exempt` daily.
- **Calling Team (14) — quick access only:** the supervisor approval path is **hard-blocked** for role 14 in the service layer. They may only get a 15-minute quick grant. In the setup migration, set `quick_access_enabled = true` for **all existing** role-14 staff. New role-14 staff: default `true` at creation.
- **Leads — same restriction as clients after Phase C:** everyone except exempt roles is restricted to allocated leads. This removes the current "Person Responsible sees all leads" exception.
- **Exempt logging:** write **one row per calendar day per staff + admin record combo** (not every page load). Keeps the audit table clean while full coverage is maintained.
- **Search results:** show non-allocated records as **locked** with "Request Access" CTA — never hide them. Required for search-only initiation.
- **Notifications:** use **existing** `Notification` model + `BroadcastNotificationCreated` broadcast. No email in v1.

---

## 3. Configuration (env overrides vs committed defaults)

File: `config/crm_access.php`. **Operational tweaks** (durations, strict flag, comma-separated id/role lists) ship via `.env` without code edits. **Changing the canonical nine default staff ids** still requires editing that file (or setting both env lists).

**Behaviour (as committed):** `approver_staff_ids` is parsed with `$intList()` from `CRM_ACCESS_APPROVER_STAFF_IDS` (fallback: canonical nine ids). **`exempt_staff_ids`** is the **same array** as `approver_staff_ids` when `CRM_ACCESS_EXEMPT_STAFF_IDS` is **unset or blank**; otherwise it is parsed from that variable (empty parse → canonical nine). Other keys (`exempt_role_ids`, `quick_access_only_role_ids`, durations, caps, `strict_allocation`) use `$intList` / `env()` as in the file.

> **Note:** Prefer reading `config/crm_access.php` for the exact implementation; the illustrative `return [...]` snippet was removed here to avoid drifting from code.

**Environment variables** (add to `.env` as needed; there is no committed `.env.example` in this repo at time of writing):

| Variable | Purpose |
|----------|---------|
| `CRM_ACCESS_EXEMPT_ROLE_IDS` | Comma-separated role IDs (default `1,17`). Empty/invalid env falls back to `[1, 17]` in code. |
| `CRM_ACCESS_EXEMPT_STAFF_IDS` | Optional. **Omit or leave blank** so `exempt_staff_ids` **equals** the resolved `approver_staff_ids` (stays in sync when you only edit approvers). Set explicitly only when exempt must differ from approvers. If set but parses to empty, falls back to canonical nine ids in `config/crm_access.php`. |
| `CRM_ACCESS_APPROVER_STAFF_IDS` | Comma-separated `staff.id` values; empty/invalid env falls back to canonical nine ids in `config/crm_access.php`. |
| `CRM_ACCESS_QUICK_ONLY_ROLE_IDS` | Quick-only roles (default `14`); empty env falls back to `[14]`. |
| `CRM_ACCESS_STRICT_ALLOCATION` | `true` / `false` — strict allocated-only + grants for non-exempt roles. |
| `CRM_ACCESS_QUICK_GRANT_MINUTES` | Quick grant length (default `15`). |
| `CRM_ACCESS_SUPERVISOR_GRANT_HOURS` | Supervisor grant length after approval (default `24`). |
| `CRM_ACCESS_MAX_PENDING_SUPERVISOR_REQUESTS` | Cap pending supervisor requests per staff (default `5`). |
| `CRM_ACCESS_PENDING_TTL_DAYS` | Days before stale `pending` supervisor requests are auto-expired by `expireStaleGrants()` (default `14`). |

Actual parsing and fallbacks live in `config/crm_access.php` (`$intList` helper for ID lists).

---

## 4. Data model (migrations)

### 4.1 `staff` table

Add one column:

```sql
ALTER TABLE staff ADD COLUMN quick_access_enabled BOOLEAN NOT NULL DEFAULT FALSE;

-- Immediately enable for all existing Calling Team staff (role 14)
UPDATE staff SET quick_access_enabled = TRUE WHERE role = 14;
```

- Only Super Admin may update this flag — enforce in controller/policy.
- New staff created with role 14: set `quick_access_enabled = true` in the staff-creation controller at save time.

### 4.2 `client_access_grants` (new table)

```sql
CREATE TABLE client_access_grants (
    id                    BIGSERIAL PRIMARY KEY,
    staff_id              BIGINT NOT NULL REFERENCES staff(id),
    admin_id              BIGINT NOT NULL REFERENCES admins(id),  -- client or lead row
    record_type           VARCHAR(10) NOT NULL CHECK (record_type IN ('client','lead')),
    grant_type            VARCHAR(20) NOT NULL CHECK (grant_type IN ('quick','supervisor_approved','exempt')),
    access_type           VARCHAR(20) NOT NULL,  -- mirrors grant_type; exists for dashboard grouping
    status                VARCHAR(20) NOT NULL DEFAULT 'pending'
                          CHECK (status IN ('pending','active','expired','revoked','rejected')),
    quick_reason_code     VARCHAR(50),           -- required when grant_type = 'quick'
    requester_note        TEXT,                  -- optional note on supervisor request
    office_id             BIGINT REFERENCES branches(id),
    office_label_snapshot VARCHAR(255),          -- branches.office_name at time of grant
    team_id               INTEGER REFERENCES teams(id),
    team_label_snapshot   VARCHAR(255),          -- teams.name at time of grant (colour optional)
    requested_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    approved_at           TIMESTAMPTZ,
    approved_by_staff_id  BIGINT REFERENCES staff(id),
    starts_at             TIMESTAMPTZ,
    ends_at               TIMESTAMPTZ,
    revoked_at            TIMESTAMPTZ,
    revoke_reason         TEXT,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Performance indexes
CREATE INDEX idx_cag_staff_admin_status  ON client_access_grants (staff_id, admin_id, status);
CREATE INDEX idx_cag_ends_at             ON client_access_grants (ends_at) WHERE status = 'active';
CREATE INDEX idx_cag_status_requested    ON client_access_grants (status, requested_at);
CREATE INDEX idx_cag_approver_queue      ON client_access_grants (status, approved_by_staff_id);
```

> `exempt` rows: write one row per record open (or per session if volume is a concern — decide in Phase D). Fields `approved_by_staff_id`, `quick_reason_code` are NULL for exempt rows.

### 4.3 No separate `purposes` table (v1)

Reason options live in config. Add a migration later if you want a DB-managed list.

---

## 5. `staff.team` field — confirmed structure

**Confirmed:** `staff.team` is an integer FK into the existing `teams` table (`id`, `name`, `color`, `created_at`, `updated_at`). No new table needed.

**16 teams in the DB:**

| `teams.id` | Name | Staff count |
|-----------|------|-------------|
| 1 | JRP | 3 |
| 2 | PR | 6 |
| 3 | SKILL ASSESSMENT | 3 |
| 4 | VISITOR | 2 |
| 5 | TR | 3 |
| 6 | HEAD OFFICE | 17 |
| 7 | Accounts | 8 |
| 8 | Tourist visa | 6 |
| 9 | ART | 4 |
| 10 | Student Visa | 15 |
| 11 | Nomination | 5 |
| 12 | 408 Celebrity Visa | 2 |
| 13 | Misc Migration | 3 |
| **14** | **Calling Team Melbourne** | **8** |
| **15** | **Calling Team Adelaide** | **7** |
| 16 | Education | 4 |

> **Important for Calling Team rule:** "Calling Team" is split into two `teams` records (id 14 = Melbourne, id 15 = Adelaide). Both contain staff with `user_role = 14`. The `quick_access_only` rule applies to **`user_roles.id = 14`**, not `teams.id` — so both city sub-teams are correctly covered.

**Grant modal "Team" dropdown:** query `SELECT id, name, color FROM teams ORDER BY name`. Pre-fill from `staff.team` of the logged-in user. If `staff.team` is null, leave blank (optional field). Fall back to showing "Team {id}" only if `teams` has no matching row (per your rule).

---

## 6. Domain service: `App\Services\CrmAccess\CrmAccessService`

Single class, injected via DI or called statically. Centralises all rules.

### Method contracts

```
isExemptRole(Staff $user): bool
    → $user->id in config exempt_staff_ids  (checked first)
    → OR intval($user->role) in config exempt_role_ids

isApprover(Staff $user): bool
    → isExemptRole (role 1) OR $user->id in approver_staff_ids

canManageStaffQuickAccess(Staff $actor): bool
    → role === 1 (Super Admin) OR isApprover($actor)
    → Used by StaffController (create + update) and staff edit/create UI
      to show/hide the "Quick access enabled" checkbox

hasActiveGrant(Staff $user, int $adminId): bool
    → client_access_grants WHERE staff_id=$user->id
        AND admin_id=$adminId
        AND status='active'
        AND ends_at > UTC_NOW()
    → false if user deactivated (status != 1) regardless of row

getApproverStaffIds(): array
    → configured approver `staff.id` values that still exist with status = active,
      merged with all active Super Admin (role 1) staff ids

**Access decision (implemented on `StaffClientVisibility`, not on `CrmAccessService`):**

`StaffClientVisibility::canAccessClientOrLead(int $adminId, ?Authenticatable $user): bool`

1. Resolve `admins` row for `client` / `lead`; apply super-admin-only locked client file rules where configured.
2. **Exempt role or exempt staff ID** → `true` and **exempt audit row** once per UTC calendar day per staff + record (`logExemptAccessIfNeeded` on `Staff` instances).
3. **Active time-bound grant** for that staff + `admin_id` → `true` (`CrmAccessService::hasActiveGrant`).
4. Else **allocation / lead rules** via `userMaySeeByAllocation` (strict vs non-strict; PR / lead full-access only when **not** strict — see `StaffClientVisibility`).

Controllers use `EnsuresCrmRecordAccess` (`ensureCrmRecordAccess`, `ensureCrmRecordAccessStrict`, `ensureCrmRecordAccessFromRequest`, `ensureCrmRecordAccessForOptionalClientId`) to centralise HTTP abort behaviour.

requestQuickGrant(Staff $user, int $adminId, string $recordType,
                  int $officeId, ?int $teamId, string $reasonCode): Grant
    - assert $user->quick_access_enabled === true
    - assert $reasonCode in config quick_reason_options keys
    - assert no active quick grant for same staff+admin already running
    - create row: status=active, starts_at=now, ends_at=now+15min

requestSupervisorGrant(Staff $user, int $adminId, string $recordType,
                       int $officeId, ?int $teamId, string $note = ''): Grant
    - REJECT if user role is in quick_access_only_role_ids (role 14 = Calling Team)
      → throw AccessDeniedException('Your role only supports quick access.')
    - create row: status=pending
    - fire notification to all approvers (see §7)

approveGrant(Staff $approver, int $grantId): Grant
    - assert isApprover($approver)
    - set status=active, approved_by_staff_id, approved_at=now,
      starts_at=now, ends_at=now+24h

rejectGrant(Staff $approver, int $grantId, string $reason): Grant
    - assert isApprover($approver)
    - set status=rejected

revokeGrantsForStaff(int $staffId, string $reason): int
    - UPDATE status=revoked WHERE staff_id=$staffId AND status IN ('active','pending')
    - call when: quick_access_enabled toggled off, staff deactivated

expireStaleGrants(): int  (called by scheduled job, safety net only)
    - UPDATE status=expired WHERE status=active AND ends_at < now()
    - Also expire very old status=pending rows (not actioned within pending_ttl_days)
      with revoke_reason set (stale supervisor queue hygiene)
```

### Integration with `StaffClientVisibility`

Illustrative helpers (as-built reads **arrays** from `config('crm_access.*')`, not raw `explode` on every call):

```php
// StaffClientVisibility.php (illustrative)

public static function isExemptFromAllocation(\App\Models\Staff $user): bool
{
    // Exempt by specific staff ID (takes priority — role-independent)
    if (in_array((int) $user->id, config('crm_access.exempt_staff_ids', []), true)) {
        return true;
    }
    // Exempt by role
    return in_array((int) $user->role, config('crm_access.exempt_role_ids', [1, 17]), true);
}

public static function isQuickAccessOnly(\App\Models\Staff $user): bool
{
    $roleIds = array_map('intval',
        explode(',', config('crm_access.quick_access_only_role_ids', '14')));
    return in_array((int) $user->role, $roleIds, true);
}
```

**As-built:** `canAccessClientOrLead` honours grants and strict/non-strict allocation; list helpers include `restrictAdminEloquentQuery`, `restrictLeadListQuery`, `restrictMatterListToAllocatedClients`, `restrictDocumentEloquentQuery`, `restrictBookingAppointmentEloquentQuery`, `enrichGlobalSearchItem`, `crossAccessUiFlags`.

---

## 7. HTTP / API surface

Registered in `routes/clients.php` under prefix **`/crm/access/`** with `auth:admin` (same group as other CRM routes). Named routes use the `crm.access.*` prefix.

| Method | URI | Who | Response / notes |
|--------|-----|-----|-------------------|
| `GET` | `/crm/access/meta` | Authenticated staff | JSON: branches, teams, quick reasons, staff office/team, UI flags (`show_quick` / `show_supervisor`). |
| `POST` | `/crm/access/quick` | Authenticated staff | JSON. Body: `admin_id`, `record_type`, `office_id`, `team_id?`, `reason_code`. **Throttle:** 30/min. |
| `POST` | `/crm/access/supervisor` | Authenticated staff | JSON. Body: `admin_id`, `record_type`, `office_id`, `team_id?`, `note?`. **Throttle:** 10/min. |
| `GET` | `/crm/access/queue` | Approver / Super Admin | **HTML** queue page. |
| `GET` | `/crm/access/queue/data` | Approver / Super Admin | JSON pending supervisor requests (up to 200). |
| `GET` | `/crm/access/queue/mini` | Approver / Super Admin | JSON pending supervisor requests (up to 15) for header dropdown. |
| `POST` | `/crm/access/{grant}/approve` | Approver / Super Admin | JSON (`{grant}` numeric). |
| `POST` | `/crm/access/{grant}/reject` | Approver / Super Admin | JSON; body `reason?`. |
| `GET` | `/crm/access/my-grants` | Authenticated staff | **HTML** “my grants” page. |
| `GET` | `/crm/access/my-grants/data` | Authenticated staff | JSON. |
| `GET` | `/crm/access/dashboard` | Approver / Super Admin | **HTML** grants dashboard (filters, aggregates, table, CSV link). |
| `GET` | `/crm/access/dashboard/data` | Approver / Super Admin | JSON (filtered rows + summary; use for integrations — **replaces** old “JSON-only `/dashboard`” behaviour). |
| `GET` | `/crm/access/dashboard/export` | Approver / Super Admin | **CSV download**; same query-string filters as `/dashboard/data`. |

State-changing routes use CSRF via standard web middleware. JSON endpoints expect `Accept: application/json` / XHR where clients rely on JSON error bodies.

---

## 8. Notification system (leverage existing)

The app already has `Notification` model (`sender_id`, `receiver_id`, `module_id`, `url`, `message`) and `BroadcastNotificationCreated` / `NotificationCountUpdated` events for real-time pushes.

**Use the existing system:**

On `requestSupervisorGrant` (as-built in `CrmAccessService::notifyApproversOfPendingGrant`):

- Resolve targets with **`getApproverStaffIds()`** (configured approvers + active role-1 staff), excluding the requester.
- Create `Notification` rows with **`receiver_status = 0`** (unread), `url` pointing at `/crm/access/queue`, `notification_type = access_request`.
- **`NotificationCountUpdated`** is broadcast per approver with updated unread count.
- **`BroadcastNotificationCreated`** is also used for a shared real-time toast payload (batch UUID).

Pseudocode shape:

```php
foreach ($crmAccess->getApproverStaffIds() as $approverId) {
    // skip self; create Notification; broadcast NotificationCountUpdated + toast batch
}
```

On approve/reject: notify the requester in the same way.

---

## 9. Enforcement points (call-site inventory — **as-built**)

Line numbers drift quickly; use ripgrep for `EnsuresCrmRecordAccess`, `ensureCrmRecordAccess`, `StaffClientVisibility::`, and `restrictBookingAppointmentEloquentQuery` in `app/Http/Controllers`.

### Core visibility + grants

| Area | Mechanism |
|------|-----------|
| Single-record client/lead access | `StaffClientVisibility::canAccessClientOrLead` (grants + allocation + exempt logging). |
| HTTP guard | `EnsuresCrmRecordAccess` trait on CRM controllers. |
| Client/lead listings | `restrictAdminEloquentQuery`, `restrictLeadListQuery`, `restrictMatterListToAllocatedClients`, `personAssistingStaffIdOrNull` / search enrichment. |
| Documents | `Document::scopeVisible` → `StaffClientVisibility::restrictDocumentEloquentQuery`. |
| Booking appointments | `StaffClientVisibility::restrictBookingAppointmentEloquentQuery` on list/export/stats queries; per-row checks via `ensureCrmRecordAccess` when `client_id` is set. |
| Email tied to client | `ensureCrmRecordAccessForOptionalClientId` when `email_logs.client_id` is set (`EmailUploadController`, `EmailLabelController` apply/remove, `EmailLogAttachmentController`). |

### Controllers touched for cross-access (non-exhaustive; verify with grep)

| File | Status |
|------|--------|
| `ClientsController`, `LeadController`, `ClientDocumentsController`, `LeadConversionController` | Existing checks extended with grant-aware visibility / lists (Phase B–C). |
| `ClientPersonalDetailsController` | Multiple endpoints gated; `searchPartner` and mutating routes use visibility + access checks (no full enumeration). |
| `DocumentController` | `EnsuresCrmRecordAccess`; document query scoping via `restrictDocumentEloquentQuery`. |
| `ClientAccountsController`, `EmailUploadController` | Gated on client/record context. |
| `OfficeVisitController`, `CRMUtilityController` (e.g. `sendmail`) | Gated where `client_id` / `lead_id` apply. |
| `BookingAppointmentsController` | List/calendar/export/stats restricted; show/update/bulk actions gated on linked `client_id`. |
| `EmailLabelController`, `EmailLogAttachmentController` | Gated via email log’s `client_id` when present. |
| `SignatureDashboardController` | Uses `Document::visible($staff)` → **`restrictDocumentEloquentQuery`** (confirm any “global” copy in comments vs actual query). |
| `AuditLogController` | **N/A for client cross-access** — indexes `StaffLoginLog` only (no per-client log API in that controller). |
| `BroadcastNotificationAjaxController` | **N/A** for client record scoping — broadcasts by staff ids / scope, not `client_id`. |

> **Ongoing hygiene:** When adding endpoints that accept `client_id`, `admin_id`, or `lead_id`, use the trait or an explicit `canAccessClientOrLead` check.

### Search (`getallclients` in `ClientsController`)

Today PA uses `personAssistingStaffIdOrNull` to filter search results. When strict allocation is on, **non-exempt, non-allocated** clients/leads should appear in search results as **locked** (show name only, show "Request Access" button) — **not** hidden. This is the entry point for the cross-access flow.

---

## 10. UI work

### 10.1 Search results (header/global search)

For records the user cannot open:
- Show client/lead name, file number — **locked visual** (greyed row + lock icon).
- **Calling Team (role 14):** show **"Request Quick Access"** only.
- **Other staff with `quick_access_enabled = true`:** show **"Request Quick Access"** and **"Request Supervisor Access"**.
- **Other staff with `quick_access_enabled = false`:** show **"Request Supervisor Access"** only.
- Clicking either opens the **access request modal**.

### 10.2 Access request modal

Fields:
1. **Record** (pre-filled from search — client/lead name + ID, read-only).
2. **Office** — dropdown from `branches` (pre-fill from `staff.office_id` but editable).
3. **Team** — dropdown from `teams` table (`id`, `name`; show colour swatch if useful). Pre-fill from `staff.team` of the logged-in user. Show "Team {id}" only if no matching `teams` row exists.
4. **Reason** — dropdown from `config quick_reason_options` (required for quick; optional/note for supervisor). **Calling Team (role 14):** only sees quick reasons; supervisor note field is hidden.
5. **Note** — optional free text (supervisor requests only; hidden for Calling Team).
6. **Submit button(s):** adapted per role — "Quick Access (15 min)" for Calling Team; dual buttons for others with `quick_access_enabled`.

### 10.3 Approver popup / notification

- **As-built:** Approvers get a **dropdown on the notification bell** (`resources/views/Elements/CRM/header_client_detail.blade.php`): secondary badge for pending supervisor count; body loads `/crm/access/queue/mini` with inline **Approve / Reject**; links to full queue, grants dashboard, and all notifications. Non-approvers keep single-click navigation to `/all-notifications` (see layout scripts).
- Unread notification count still driven by existing `Notification` + `NotificationCountUpdated` / Echo.

### 10.4 Dashboard

- **As-built:** `/crm/access/dashboard` (HTML) with filters (staff id, record `admin_id`, date range, office, team, grant type, status), summary tiles (global pending/active + filtered aggregates and quick/supervisor/exempt split), table (up to 500 rows), and **Export CSV** via `/crm/access/dashboard/export`. JSON: `/crm/access/dashboard/data`.

### 10.5 Superadmin staff settings

- **As-built:** `quick_access_enabled` on staff save in `AdminConsole\StaffController` (Super Admin); toggling **off** revokes grants via `CrmAccessService::revokeGrantsForStaff`.
- New staff with role 14 get `quick_access_enabled = true` on create (same controller).

---

## 11. Phased rollout

### Phase A — Foundation (no visible change to staff yet)

1. Migration: `staff.quick_access_enabled`.
2. Migration: `client_access_grants` table.
3. `config/crm_access.php` with all keys.
4. `CrmAccessService` class + unit tests (time mocking, expiry, flag checks).
5. `StaffClientVisibility::isExemptFromAllocation` helper.
6. Feature flag `CRM_ACCESS_STRICT_ALLOCATION=false` (no tightening yet).

### Phase B — Grant flow + search UX

1. HTTP routes + controllers for request / approve / reject / queue / my-grants.
2. Notification wiring (existing `Notification` model + `BroadcastNotificationCreated`).
3. Extend `canAccessClientOrLead` to honour active grants (Phase B safe — without strict allocation, only newly allocated users are affected).
4. Search results: show locked state + "Request Access" buttons for unallocated records.
5. Access request modal UI.
6. `quick_access_enabled` toggle on staff edit (Super Admin only).
7. Logging: quick/supervisor grant rows in `client_access_grants`.

### Phase C — Allocated-only tightening

1. Extend `restrictAdminEloquentQuery` and `canAccessClientOrLead` in `StaffClientVisibility` to apply allocated-only to **all** non-exempt roles (not just PA role 13).
2. Reconcile leads: today `isRestrictedPersonAssisting` only targets role 13; tighten to all non-exempt roles for leads too.
3. Audit and fix **all** controllers that accept `client_id` / `admin_id` without a gate (§9 list above).
4. Deploy to staging with `CRM_ACCESS_STRICT_ALLOCATION=true`; test each role.
5. Promote to production.

### Phase D — Exempt logging + full dashboard

1. Log exempt access rows in `client_access_grants` — **once per calendar day per staff + admin_id combo** (decided in §2).
2. Build dashboard view with all filters, aggregates, Type column, CSV export.
3. Approver queue popup on notification bell.

### Phase E — Hardening + scheduled jobs

1. **Done:** Scheduled command `access:expire-grants` — registered **hourly** in `app/Console/Kernel.php`.
2. **Done (on deactivation / flag):** `revokeGrantsForStaff` from staff maintenance (e.g. Admin Console staff save when status or quick flag changes) — verify all deactivate paths if new entry points are added.
3. **Done:** Rate limits on `quick` / `supervisor` routes; **max pending supervisor requests** enforced in `CrmAccessService` via config.
4. **Done in service:** Self-approve / self-reject rejected; approver role + staff id checks.
5. **Optional / ops:** Load test approver queue and dashboard at volume; formal penetration test on grant IDs.

**Rollout status vs code:** see **§15** at end of document.

---

## 12. Testing checklist

| Scenario | Expected |
|----------|----------|
| Exempt role opens any client/lead | Access granted; exempt row written |
| Non-exempt, allocated (PA on matter / user_id match) | Access granted; no grant row needed |
| Non-exempt, not allocated — no grant | Access denied (403 / redirect + message) |
| Quick access: `quick_access_enabled = false`, non-calling-team | "Request Supervisor Access" only in modal |
| Quick access: `quick_access_enabled = true`, non-calling-team | Both paths offered in modal |
| **Calling Team (role 14), `quick_access_enabled = true`** | "Quick Access" only — no supervisor button shown |
| **Calling Team (role 14) calls supervisor API directly** | 403 / service exception |
| **Calling Team (role 14), `quick_access_enabled = false`** | Cannot access unallocated at all (no button shown) |
| Quick grant created | 15-min expiry; `status = active`; opens client |
| After 15 min | Next access check → denied; grant row `expired` |
| Supervisor grant: pending | All approvers notified via broadcast; requester sees "Pending" |
| Supervisor grant: approved | 24 h from approval moment; requester notified |
| Supervisor grant: rejected | Requester notified; no access |
| Quick-flagged user requests supervisor 24 h | Works (non-calling-team); logged as `supervisor_approved` |
| Multiple quick grants on different clients simultaneously | Each independent timer; all `active` concurrently |
| Exempt logging: same record opened twice same day | Only **one** exempt row written that day |
| `quick_access_enabled` toggled off (any role) | Active quick grants revoked immediately |
| Staff deactivated | All active + pending grants revoked |
| Non-approver hits `/crm/access/queue` | 403 |
| Deep link to client with no allocation + no grant | 403 / redirect to search |
| Approver tries to approve their own request | Service rejects; 422 error |
| Phase C: PR (role 12) opens unallocated client | Denied when `CRM_ACCESS_STRICT_ALLOCATION=true`; must request access |
| Phase C: PR (role 12) opens unallocated lead | With **strict** on, denied like clients; with **strict** off, PR may still see all leads per `lead_full_access_role_ids` — confirm env before UAT |

---

## 13. Product decisions (locked) ✅

| # | Item | Decision |
|---|------|---------|
| 1 | `staff.team` field | `teams` table already exists with 16 named rows + colours. Use FK. No new table needed. |
| 2 | Search: locked vs hidden | **Show locked + Request Access** |
| 3 | Lead restriction when strict allocation is on | **Everyone non-exempt restricted** (PR role 12 included for leads) |
| 4 | Calling Team grant path | **Quick access only**; supervisor path hard-blocked for `user_roles.id = 14` |
| 5 | Exempt logging granularity | **Once per calendar day** (UTC) per staff + record combo |
| 6 | Notifications | **Existing broadcast system** (no email v1) |

**Follow-ups (non-blocking):** add a committed `.env.example` documenting `CRM_ACCESS_*` if the project adopts one; optional integration/feature tests for dashboard CSV and booking/email gates.

---

## 14. Reference

### Approver `staff.id` values (Postgres-confirmed)

| `staff.id` | Name in DB | Email |
|-----------|-----------|-------|
| 36834 | Ajay Bansal | ajay@bansalimmigration.com.au |
| 36524 | ARUN BANSAL | Immi@bansalimmigration.com.au |
| 36692 | Celesty Parmar | Manager@bansalimmigration.com.au |
| 36483 | Bipan Chander | bansalimmigration123@gmail.com |
| 36484 | KHUSHI Sangroya | khusi.bansal01@gmail.com |
| 36718 | Vipul Goyal | Immi2@bansalimmigration.com.au | **Default privileged staff** (with the other eight) — full client/lead visibility when exempt env is omitted (exempt mirrors approvers) |
| 36523 | Sam (Shubam) | shubambansal.au1@gmail.com |
| 36836 | Yadwinder Pal Singh | migration8899@gmail.com |
| 36830 | Ankit Bansal | admin@bansaleducation.com.au |

> **Default exemption:** If `CRM_ACCESS_EXEMPT_STAFF_IDS` is **omitted or blank**, `exempt_staff_ids` is the **same** as the resolved `approver_staff_ids` (from `CRM_ACCESS_APPROVER_STAFF_IDS` or the canonical nine-id fallback in `config/crm_access.php`).

### `user_roles` (confirmed from DB)

| Role ID | Name |
|---------|------|
| 1 | Super Admin |
| 12 | Person Responsible |
| 13 | Person Assisting |
| 14 | Calling Team |
| 15 | Accountant |
| 16 | Migration Agent |
| 17 | Admin |

### Key files (as-built)

| File | Role |
|------|------|
| `config/crm_access.php` | Feature flags, exempt role IDs, exempt staff IDs, approvers, durations, pending caps, TTL |
| `app/Support/StaffClientVisibility.php` | Access rules, list restrictions, search enrichment, exempt logging, document/booking query scopes |
| `app/Services/CrmAccess/CrmAccessService.php` | Grants, approve/reject, expiry, notifications |
| `app/Http/Controllers/Concerns/EnsuresCrmRecordAccess.php` | Reusable HTTP guards |
| `app/Http/Controllers/CRM/AccessGrantController.php` | Meta, quick/supervisor, queue, dashboard, CSV, mini-queue API |
| `app/Models/ClientAccessGrant.php` | Grant model |
| `routes/clients.php` | `crm/access/*` routes |
| `resources/views/crm/partials/cross-access-modal.blade.php` | Request modal |
| `resources/views/crm/access/*.blade.php` | Queue, my grants, dashboard |
| `resources/views/Elements/CRM/header_client_detail.blade.php` | Approver bell + mini-queue |
| `resources/views/layouts/crm_client_detail.blade.php` (and dashboard variant) | Search locked row handling, bell click guard |
| `app/Console/Commands/ExpireCrmAccessGrants.php` | `access:expire-grants` command |

---

## 15. Implementation status summary (for PM / QA)

| Phase | Scope | Status |
|-------|--------|--------|
| **A** | Migrations, `crm_access` config, `CrmAccessService`, `StaffClientVisibility` helpers, strict flag | **Implemented** |
| **B** | Routes, grant request/approve/reject, notifications, search locked + modal, `quick_access_enabled` UI, unit tests | **Implemented** (iterate on UX as needed) |
| **C** | Strict allocation + grants on lists/leads; controller gates §9 | **Implemented** (keep grep hygiene for new endpoints) |
| **D** | Exempt daily logging, full dashboard + CSV, approver bell mini-queue | **Implemented** |
| **E** | Job + expiry + rate limits + self-approval guard | **Largely implemented**; load/perf testing optional |

**Tests:** `tests/Unit/CrmAccessServiceQuickOnlyTest.php` covers core service rules (expand with feature tests against DB as desired).

**UI assets:** `resources/views/crm/partials/cross-access-modal.blade.php`, `crm/access/queue.blade.php`, `crm/access/my_grants.blade.php`, `crm/access/dashboard.blade.php`.

---

*Document version: 5.3 — product rules in §1–2, §12–13; operational truth for routes/config/controllers in §7, §9, §14–15.*
