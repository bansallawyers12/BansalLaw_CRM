# Conflict Check — Phase 3: Search Coverage (False Negatives)

**Goal:** Automated search finds real conflicts reliably, so a server-side Clear (Phase 2) actually means something.

**Depends on:** [Phase 1](./CONFLICT_CHECK_PHASE1.md), [Phase 2](./CONFLICT_CHECK_PHASE2.md)

---

## Changes summary

| Step | Area | Fix |
|------|------|-----|
| 3.1 | Phone matching | `normalizePhoneDigits()` (AU-aware full digits); digit-stripped SQL via `REGEXP_REPLACE`; PHP `phonesMatch()` post-filter |
| 3.2 | `client_contacts` | `searchClientContactPhones()` — source `client_contact_phone` |
| 3.3 | Conflict party email/phone | `searchConflictPartyEmailsOnOtherClients()`, `searchConflictPartyPhonesOnOtherClients()`, `rep_email` / `rep_phone` on party row |
| 3.4 | Party ABN/ACN | `MatterOtherPartiesHelper::createConflictPartyRow()` + `opposingPartyToConflictModel()` copy from linked company |
| 3.5 | Company client names | `describeAdminMatch()` / `isKnownPartyIdentityMatchForAdmin()` compare `first_name`/`last_name` before display name |
| 3.6 | DOB | Exact match on `admins.dob` and `client_conflict_parties.dob` — `matched_on: dob:YYYY-MM-DD`, score 85 |
| 3.7 | LIKE wildcards | `escapeLike()` with `!` escape char on all name/company `LIKE`/`ILIKE` patterns |

---

## Coverage matrix

| Field | `searchAdmins` | `client_emails` | `client_contacts` | `conflict_party` row | `conflict_party_emails` | `conflict_party_contacts` | `companies` |
|-------|----------------|-----------------|-------------------|----------------------|-------------------------|---------------------------|-------------|
| Name | ✓ | — | — | ✓ | — | — | — |
| Email | ✓ | ✓ | — | ✓ (`rep_email`) | ✓ | — | — |
| Phone | ✓ (digit norm) | — | ✓ | ✓ (`rep_phone`) | — | ✓ | — |
| Company name | ✓ | — | — | ✓ | — | — | ✓ |
| ABN / ACN | — | — | — | ✓ | — | — | ✓ |
| DOB | ✓ | — | — | ✓ | — | — | — |

**Severity:** All new hard-match sources use existing `splitAndFinalizeMatches()` — known parties still suppressed (Phase 1); shared `opposing_lead_id` still informational.

---

## Phone normalization

```php
// ConflictCheckService::normalizePhoneDigits()
'0412 345 678'  → '61412345678'
'412345678'     → '61412345678'
```

Comparison uses full normalized digits with last-9 suffix fallback in `phonesMatch()`.

SQL uses `RIGHT(REGEXP_REPLACE(column, '[^0-9]', '', 'g'), 9) = ?` on PostgreSQL/MySQL.

---

## Tests

```bash
vendor/bin/phpunit tests/Feature/ConflictCheckPhase3Test.php
```

Regression (run after any search change):

```bash
vendor/bin/phpunit tests/Feature/ConflictCheckPhase0Test.php tests/Feature/ConflictCheckPhase1Test.php tests/Feature/ConflictCheckPhase2Test.php tests/Feature/ConflictCheckPhase3Test.php
```

| Test | Asserts |
|------|---------|
| `formatted_phone_on_other_client_matches_normalized_search_term` | `0412 345 678` ↔ `412345678` |
| `phone_only_on_client_contacts_produces_hard_match` | Phone only on `client_contacts` |
| `party_email_on_other_client_conflict_party_matches` | `rep_email` on other client's party |
| `conflict_party_emails_table_is_searched` | Child `conflict_party_emails` row |
| `company_opposing_party_abn_matches_other_client_company` | ABN from linked company party |
| `company_client_person_names_match_individual_party_name` | `first_name`/`last_name` on company client |
| `exact_dob_on_other_client_produces_hard_match` | DOB exact match |
| `like_wildcard_in_party_name_does_not_match_unrelated_records` | `100% Legal` does not hit unrelated names |
| `normalize_phone_digits_handles_au_formats` | Unit check on normalizer |

Fixtures: `tests/Support/ConflictCheckPhase3Fixtures.php` (extends Phase 0 base).

---

## Optional next: Phase 4B — Staleness enforcement

Phase 2 already stores `parties_snapshot_at` and `search_hash`. A follow-up can:

1. On outcome save / pipeline gate, compare current party snapshot + hash to saved row.
2. Block Clear / engaged without re-run when parties changed since last check.
3. UI banner: “Parties changed since last check — re-run search.”

Estimated effort: 1–2 days; can run in parallel with Phase 3 (now complete).

---

## See also

- [Phase 0 baseline](./CONFLICT_CHECK_PHASE0.md)
- [Phase 1 false-positive fixes](./CONFLICT_CHECK_PHASE1.md)
- [Phase 2 audit integrity](./CONFLICT_CHECK_PHASE2.md)
