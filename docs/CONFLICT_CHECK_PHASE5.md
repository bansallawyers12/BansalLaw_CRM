# Conflict Check — Phase 5: UX Polish & Hardening

**Goal:** Finish staff-facing UX gaps and tighten access/performance so the conflict check feature is production-ready end-to-end.

**Depends on:** [Phases 0–4A](./CONFLICT_CHECK_PHASE0.md) (via [Phase 4A](./CONFLICT_CHECK_PHASE4A.md), [Phase 4B](./CONFLICT_CHECK_PHASE4B.md))

---

## 5.1 — Force clear override UI

When **Run conflict check** finds hard matches (`match_count > 0`), a collapsible panel appears:

- **Override — clear despite matches**
- Checkbox `#cpForceClear` + note that Notes must be ≥ 20 characters
- Save sends `force_clear=1` (server already validated in `validateOutcomeAgainstResults()`)
- Success toast mentions override when `force_clear_applied` is true

Without override, Clear save remains **422** when matches exist (unchanged).

---

## 5.2 — Access-gated match links

`ConflictCheckService::applyMatchAccessGating()`:

- When staff is logged in and `StaffClientVisibility::canAccessClientOrLead()` is false → `detail_url = null`, `access_locked = true`
- Otherwise sets encoded detail URL as before

UI (`renderMatchRow`):

- Open link only when `detail_url` present
- Lock icon + “No access to this record” when `access_locked`

Stored history detail re-applies gating via `sanitizeStoredMatchesForViewer()`.

---

## 5.3 — History snapshot detail

- History rows are clickable (`data-check-id`)
- Modal loads `GET /clients/conflict-check/{checkId}?client_id=…`
- Shows: checked at, staff name, matter ref, truncated `search_hash`, notes, hard + informational match lists

Partial: `resources/views/crm/clients/partials/conflict-check-history-detail.blade.php`

---

## 5.4 — Pipeline grace tightened

`ClientConflictCheck::scopeForPipelineMatter()` — strict matter id only (no `orWhereNull('client_matter_id')`).

Used in `saveLeadPipelineSection` engaged/retained gate.

**History display** still uses `forActiveMatter()` with legacy null-matter fallback.

---

## 5.5 — Performance indexes

Not added in this phase — profile on production data first; Phase 3 phone digit SQL is the likely bottleneck.

---

## 5.6 — Cleanup

| Item | Status |
|------|--------|
| `searchWasRun` / `lastMatches` | Still used for staleness ack + match display |
| 422 Clear rejection toast | Appends `match_count` when present |
| Lead edit staleness props | Out of scope — conflict card on client detail only |
| Regression script | `scripts/dev/run-conflict-check-tests.sh` |

---

## Tests

```bash
vendor/bin/phpunit tests/Feature/ConflictCheckPhase5Test.php
```

Full regression:

```bash
scripts/dev/run-conflict-check-tests.sh
# or
vendor/bin/phpunit tests/Feature/ConflictCheckPhase0Test.php tests/Feature/ConflictCheckPhase1Test.php tests/Feature/ConflictCheckPhase2Test.php tests/Feature/ConflictCheckPhase3Test.php tests/Feature/ConflictCheckPhase4aTest.php tests/Feature/ConflictCheckPhase4bTest.php tests/Feature/ConflictCheckPhase5Test.php
```

| Test | Asserts |
|------|---------|
| `force_clear_with_sufficient_notes_saves_clear_despite_matches` | Override saves Clear with matches |
| `clear_without_override_still_rejected_when_matches_exist` | 422 unchanged |
| `restricted_staff_sees_match_without_detail_url_for_inaccessible_client` | `access_locked`, no URL |
| `conflict_check_detail_endpoint_returns_stored_matches` | Detail API returns snapshot |
| `pipeline_ignores_legacy_null_matter_clear_for_specific_matter` | Strict pipeline scope |

---

## QA checklist (sign-off)

- [ ] Hard matches present → force clear with 20+ char note → Clear saves
- [ ] Hard matches present → Clear without override → 422
- [ ] Restricted staff sees matches but cannot open inaccessible client detail
- [ ] History row expands to show saved matches
- [ ] Full flow: parties → run → save Clear → pipeline engaged → no warning
- [ ] Change parties → stale hint → run → save Clear → engaged OK
- [ ] Legacy client-wide Clear does **not** satisfy engaged on a specific matter

---

## See also

- [Phase 4B staleness](./CONFLICT_CHECK_PHASE4B.md)
- [Phase 4A party upsert](./CONFLICT_CHECK_PHASE4A.md)
