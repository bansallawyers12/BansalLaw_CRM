# Conflict Check — Phase 0: Reproduce & Lock Behaviour

**Goal:** Define “correct” before changing code.  
**Status:** Baseline locked via PHPUnit (`tests/Feature/ConflictCheckPhase0Test.php`). **Phase 1 behaviour** — see [CONFLICT_CHECK_PHASE1.md](./CONFLICT_CHECK_PHASE1.md).

All fixture records use the **`CCP0`** prefix for easy identification in QA databases.

---

## Test matrix (current behaviour)

| # | Scenario | Fixture setup | Expected `match_count` | Expected outcome | Notes |
|---|----------|---------------|------------------------|------------------|-------|
| 1 | Individual linked opposing party (`opposing_lead_id` set) on **this** matter | Subject matter links `CCP0 Charlie Opponent` other-party record | **0** | `clear` | Linked `admins.id` excluded in `searchAdmins`; own-matter rows excluded in `searchMatterOpposingParties` / same-client rows in `searchConflictPartiesOnOtherClients`. |
| 2 | Company linked opposing party | Subject matter links `CCP0 Delta Holdings Pty Ltd` (`is_company` + `companies` row) | **1** (current) | `pending` | **Gap vs intended:** `searchCompanies` matches the `companies` row on the linked other-party admin (`admin_id` not excluded). Individual linked parties correctly return 0. Product may want 0 after Phase 1 fix. |
| 3 | Name-only party row | Subject: name-only `CCP0 Eve Nameonly`; Other client: same name on their matter | **≥ 1** | `pending` | Real cross-client identity hit via `conflict_party` and/or `matter_opposing_party`. |
| 4 | Same linked other-party record on two clients’ matters | Both matters link the same `CCP0 Charlie Opponent` admin row | **2** (current) | `pending` | Client B surfaces twice today: once from synced `client_conflict_parties`, once from `client_matter_opposing_parties`. The shared `admins` other-party row itself is **not** matched. |
| 5 | Subject-only (no parties) | Subject client + active matter, zero opposing parties | **0** | `clear` | Search runs on subject name/email/phone only; warning: *“No opposing parties are saved for this matter…”* |

Run automated lock:

```bash
vendor/bin/phpunit tests/Feature/ConflictCheckPhase0Test.php
```

Run manual matrix (uses app DB; rolls back by default):

```bash
php scripts/dev/conflict-check-phase0-matrix.php
php scripts/dev/conflict-check-phase0-matrix.php --keep   # leave CCP0 rows for UI QA
```

On a populated dev database, `match_count` may exceed the PHPUnit baselines in the table above because of ambient CRM data. The script also prints `fixture_scoped` (matches whose `client_id` is the fixture’s **other client** — most useful for scenarios 3–4).

Each scenario runs in its own transaction so results are not polluted by prior scenarios in the same script run.

---

## Product decision: cross-client other-party hits (same `opposing_lead_id`)

### Question

When Client A’s matter already links other-party record **X**, and Client B’s active matter also links **the same** other-party admin row, should Client B appear in Client A’s conflict results?

### Current behaviour (locked by tests)

| Match source | Shown? | Severity today |
|--------------|--------|----------------|
| Linked other-party admin (`admins.id = opposing_lead_id`) on **this** matter | **No** | Suppressed (exclude list) |
| Same `opposing_lead_id` on **another client’s** matter (`client_matter_opposing_parties`) | **Yes** | Hard match (`pending`, score 75) |
| Synced copy on **another client** (`client_conflict_parties`) | **Yes** | Hard match (`pending`, score 70–98 depending on field) |
| Same identity as **real client/lead** (not only other-party) elsewhere | **Yes** | Hard match (by design — see `ConflictCheckService::searchAdmins` comment) |

### Options for practice sign-off

| Option | UX | When to use |
|--------|-----|-------------|
| **A — Hard match (status quo)** | Counts toward `match_count`; solicitor must Clear/Waive | Treat shared other-party linkage across clients as a real conflict signal (e.g. firm acting both sides). |
| **B — Soft / informational** | Show in results panel with badge *“Same other-party record elsewhere”*; **does not** change `suggested_outcome` to `pending` | Useful when other-party records are reused as a contact index, not a conflict indicator. |
| **C — Suppressed** | Hidden when `opposing_lead_id` matches a row already on the subject matter | Simplest UX; risks missing genuine cross-client conflicts if the shared record is meaningful. |

### Recommendation (pending practice confirmation)

**Option B — soft informational** for matches where:

- `source` is `matter_opposing_party` or `conflict_party`, **and**
- the matched row’s `opposing_lead_id` (if any) is already on the subject matter’s party list.

Keep **hard matches** for:

- Email / phone / ABN / ACN hits on a **real client or lead** (`source = admin`, `client_email`, `company`).
- Name-only rows with **no** shared `opposing_lead_id` (scenario 3).

**Practice sign-off:** ☐ Option A &nbsp; ☐ Option B (recommended) &nbsp; ☐ Option C  
**Signed / date:** ___________________

### Known gap (scenario 2)

Linked **company** other parties currently produce **1 hard match** via `searchCompanies` even when the party is already on the subject matter. Linked **individual** other parties correctly return 0. Confirm whether Phase 1 should align company behaviour with individual (exclude `companies.admin_id` when it equals a matter party’s `opposing_lead_id`).

---

## Implementation references

- Service: `app/Services/ConflictCheckService.php`
- Party loading: `app/Support/MatterOtherPartiesHelper::loadForConflictSearch()`
- Fixtures: `tests/Support/ConflictCheckPhase0Fixtures.php`
- Exclusion of linked leads: `ConflictCheckService::searchAdmins()` (`$excludeIds`)
- Cross-client party search: `searchConflictPartiesOnOtherClients()`, `searchMatterOpposingParties()`

---

## Next phases (out of scope for Phase 0)

1. Implement product decision on cross-client linked other-party hits.
2. Deduplicate `conflict_party` vs `matter_opposing_party` when they describe the same Client B matter row.
3. Matter-status filter (active vs closed) for cross-client hits — confirm with practice separately.
