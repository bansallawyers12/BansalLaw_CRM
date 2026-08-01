# Conflict Check — Phase 4A: Party Upsert (Preserve Data on Re-save)

**Goal:** Saving other parties updates what the UI sends without destroying richer conflict-party data already stored on the row (aliases, DOB, extra emails/phones, ABN, address, etc.).

**Depends on:** [Phase 1](./CONFLICT_CHECK_PHASE1.md), [Phase 2](./CONFLICT_CHECK_PHASE2.md), [Phase 3](./CONFLICT_CHECK_PHASE3.md), [Phase 4B](./CONFLICT_CHECK_PHASE4B.md)

---

## Problem (before 4A)

`MatterOtherPartiesHelper::syncConflictPartiesForMatter()` deleted **all** `client_conflict_parties` rows for the matter and recreated them on every save. Child rows in `conflict_party_emails` / `conflict_party_contacts` were lost, along with fields the UI does not send (aliases, DOB, address, manually enriched ABN, etc.).

`client_matter_opposing_parties` remains delete/recreate via `OpposingPartyHelper::syncForMatter()` — that table is the lightweight matter-picker source only.

---

## Upsert match keys

Within scope `(client_id, client_matter_id)` or `(client_id, null)` for client-level:

| Priority | Match key | Notes |
|----------|-----------|-------|
| 1 | `opposing_lead_id` > 0 | Primary identity for linked other parties |
| 2 | Normalized `name` + `party_role` | For name-only rows, or when linking a name-only row to a lead (fallback if no row with that lead id yet) |

Name normalization: trim, collapse whitespace, lowercase. Company rows use `company_name`; individuals use `first_name` + `last_name`.

Rows in the payload that do not match an existing row are **created**. Existing rows not matched by any payload party are **deleted** (with child emails/phones).

---

## Preserve vs overwrite matrix

| Field | On update |
|-------|-----------|
| `party_role`, `rep_*`, `sort_order`, name fields (`first_name`, `last_name`, `company_name`, `party_type`) | Always from payload |
| `abn`, `acn` | Payload if present → else keep existing → else copy from linked lead company |
| `aliases`, `dob`, `trading_name`, `address`, `suburb`, `state`, `postcode` | Preserve existing unless explicitly in payload |
| `conflict_party_emails` / `conflict_party_contacts` | Preserve unless payload includes `emails` or `phones` arrays (UI does not today) |
| Lead phone/email on create | Still copied from linked lead on **new** rows only |

`updated_at` is bumped only when model attributes actually change (`isDirty()`), which keeps Phase 4B staleness accurate.

---

## Implementation

`app/Support/MatterOtherPartiesHelper.php`

| Method | Role |
|--------|------|
| `syncConflictPartiesForScope()` | Shared upsert for matter + client-level |
| `findExistingConflictPartyRow()` | Match by lead id or name+role |
| `updateConflictPartyRow()` | Preserve rich fields, touch only when dirty |
| `buildConflictPartyAttributes()` | Shared create/update attribute builder |
| `syncConflictPartyChildRecords()` | Create lead contacts on new rows; optional explicit replace |

Both matter and client-level paths run inside `DB::transaction()`.

### Unique index (recommended)

Migration `2026_08_01_110000_add_conflict_party_upsert_unique_indexes.php`:

- PostgreSQL partial unique: `(client_matter_id, opposing_lead_id)` where both NOT NULL
- PostgreSQL partial unique: `(client_id, opposing_lead_id)` for client-level rows
- MySQL: best-effort non-partial index (app-level upsert remains authoritative)

---

## Interaction with Phase 4B staleness

- Staleness uses `max(updated_at)` on `client_matter_opposing_parties` (fallback: `client_conflict_parties`).
- Upsert only bumps `client_conflict_parties.updated_at` when row data changes.
- Re-saving an identical party list should **not** falsely mark a Clear/Waived check stale.
- `client_matter_opposing_parties` still updates on every save (delete/recreate) — that timestamp remains the primary staleness signal for party edits.

---

## Tests

```bash
vendor/bin/phpunit tests/Feature/ConflictCheckPhase4aTest.php
```

Full regression:

```bash
vendor/bin/phpunit tests/Feature/ConflictCheckPhase0Test.php tests/Feature/ConflictCheckPhase1Test.php tests/Feature/ConflictCheckPhase2Test.php tests/Feature/ConflictCheckPhase3Test.php tests/Feature/ConflictCheckPhase4aTest.php tests/Feature/ConflictCheckPhase4bTest.php
```

| Test | Asserts |
|------|---------|
| `resave_parties_preserves_dob_and_aliases` | Enriched fields survive UI re-save |
| `resave_parties_preserves_conflict_party_emails` | Child email row survives |
| `resave_parties_preserves_abn_when_not_in_payload` | ABN kept on role-only update |
| `removed_party_row_is_deleted` | Dropped party + children removed |
| `new_party_added_without_deleting_others` | Second party added; first party extras kept |
| `resave_parties_preserves_data_when_linking_lead` | Name-only row upgraded to linked lead without data loss |
| `client_level_save_uses_transaction` | Mid-save failure rolls back entirely |

---

## QA checklist

- [ ] Add linked other party → save → re-save same list → ABN/emails still in DB
- [ ] Change party role only → aliases/DOB unchanged
- [ ] Remove one party from list → only that row deleted
- [ ] Save parties → staleness hint (4B) → run check → save Clear → OK
- [ ] Matter modal save opposing parties → conflict parties stay in sync without data loss

---

## See also

- [Phase 4B staleness](./CONFLICT_CHECK_PHASE4B.md)

**Next:** Phase 5 UX polish (`force_clear` UI, access-gated match links, performance indexes, history UX).
