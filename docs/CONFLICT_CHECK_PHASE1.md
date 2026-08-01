# Conflict Check — Phase 1: Stop False Positives

**Goal:** Known parties on the current matter never inflate conflict counts; cross-client shared other-party hits become informational only.

**Status:** Implemented in `ConflictCheckService`, conflict check API, and conflict parties UI.

---

## Behaviour summary (Phase 1)

| Scenario | `match_count` | `informational_count` | `suggested_outcome` |
|----------|---------------|------------------------|---------------------|
| Linked individual on **this** matter | 0 | 0 | `clear` |
| Linked company on **this** matter | 0 | 0 | `clear` (fixes Phase 0 gap via `searchCompanies` exclusion) |
| Name-only party, mirror on other client | ≥ 1 | 0 | `pending` (hard) |
| Same linked other-party on two clients | 0 | 1 | `clear` (deduped informational) |
| Subject-only, no parties | 0 | 0 | `clear` |

Run tests:

```bash
vendor/bin/phpunit tests/Feature/ConflictCheckPhase1Test.php
```

---

## Service changes (`ConflictCheckService`)

### Known-party context

Built once per `run()` from search terms:

- **`admin_ids`** — subject client id + every matter party `opposing_lead_id > 0`
- **`identity`** — per name-only party (no `opposing_lead_id`): normalized name, emails, phones, ABN, ACN

Helpers:

- `isExcludedAdminId()` — suppresses known admin/company self-matches
- `isKnownPartyIdentityMatch()` — skips other-party admin rows that fuzzy-match a typed name-only party
- `isSharedOtherPartyOnOtherClient()` — cross-client row sharing a known `opposing_lead_id`

### Match metadata

Each match may include:

| Field | Values |
|-------|--------|
| `severity` | `hard` \| `informational` |
| `is_known_party` | bool |
| `is_cross_client` | bool |
| `informational_reason` | string or null |

### API payload

```json
{
  "matches": [],
  "informational_matches": [],
  "match_count": 0,
  "informational_count": 1,
  "suggested_outcome": "clear",
  "client_matter_id": 123
}
```

Only **`match_count` (hard)** drives `suggested_outcome`.

---

## Controller

`ClientPersonalDetailsController::runConflictCheck`:

- Rejects invalid explicit `client_matter_id` / matter ref with **422** (mirrors save parties)
- Returns `informational_matches`, `informational_count`, `client_matter_id`

---

## UI (`conflict-parties-card.blade.php`)

- **Potential conflicts** — hard matches (existing panel)
- **Same other-party elsewhere** — informational matches (muted panel, no Open link)
- Status: `"0 conflicts · 1 informational note"`
- Outcome dropdown sets `pending` only when hard `match_count > 0`
- Clear confirmation only when hard matches exist

---

## Product decision applied

**Option B (soft informational)** for shared `opposing_lead_id` on another client's matter — see [CONFLICT_CHECK_PHASE0.md](./CONFLICT_CHECK_PHASE0.md).

---

## Out of scope (later phases)

- Server-side outcome re-run (Phase 2)
- Phone normalization (Phase 3)
- `client_matter_id` column on saved checks (Phase 2)

---

## Phase 0 archive

Pre-Phase-1 baselines (company false positive, dual hard cross-client hits) are documented in [CONFLICT_CHECK_PHASE0.md](./CONFLICT_CHECK_PHASE0.md). `ConflictCheckPhase0Test` retains unchanged scenarios only.
