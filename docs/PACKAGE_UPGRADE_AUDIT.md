# Package Upgrade Audit — Breaking & Outdated Only

**Audit date:** 2026-09-14  
**Sources:** `npm outdated`, `composer outdated --direct`, `npm audit`, `composer audit`  
**Scope:** Direct dependencies only (`package.json` / `composer.json`)  
**Security:** No known advisories (`npm audit` / `composer audit` clean)

This document lists **only** packages that are behind current releases. Packages already current are omitted.

---

## Legend

| Status | Meaning |
|--------|---------|
| **BREAKING** | Latest is a **new major**. Requires code/API migration; do not bump casually. |
| **UPDATE** | Newer version within the same major (or declared range). Usually safe via `npm update` / `composer update`. |

| Layer | Tool |
|-------|------|
| Frontend | npm (`package.json`) |
| Backend | Composer (`composer.json`) |

---

## 1. Frontend — Breaking (major upgrades)

Upgrade these only with a migration plan and regression testing (calendar, tables, jQuery-dependent Blade/JS).

| Package | Installed | Latest | Jump | Notes |
|---------|-----------|--------|------|-------|
| `@fullcalendar/core` | 6.1.21 | **7.1.0** | 6 → 7 | Upgrade **all** `@fullcalendar/*` packages together. |
| `@fullcalendar/daygrid` | 6.1.21 | **7.x** | 6 → 7 | Must match `@fullcalendar/core`. |
| `@fullcalendar/timegrid` | 6.1.21 | **7.x** | 6 → 7 | Must match `@fullcalendar/core`. |
| `@fullcalendar/list` | 6.1.21 | **7.x** | 6 → 7 | Must match `@fullcalendar/core`. |
| `@fullcalendar/interaction` | 6.1.21 | **7.x** | 6 → 7 | Must match `@fullcalendar/core`. |
| `datatables.net` | 2.3.8 | **3.0.4** | 2 → 3 | Breaking API/CSS; update `datatables.net-bs5` in lockstep. |
| `datatables.net-bs5` | 2.3.8 | **3.0.4** | 2 → 3 | Bootstrap 5 styling package for DataTables 3. |
| `jquery` | 3.7.1 | **4.0.0** | 3 → 4 | High risk for legacy Blade / DataTables glue; pin at 3.x until audited. |

**Recommended action:** Stay on current majors until a dedicated migration sprint. Prefer patch/minor updates in section 2 first.

---

## 2. Frontend — Need update (same major / in-range)

Safe to apply with `npm update` (then `npm run postinstall` so `public/` copies stay in sync).

| Package | Installed | Update to | Type |
|---------|-----------|-----------|------|
| `@fortawesome/fontawesome-free` | 7.3.0 | 7.3.1 | Patch |
| `@tailwindcss/vite` | 4.3.2 | 4.3.3 | Patch |
| `tailwindcss` | 4.3.2 | 4.3.3 | Patch |
| `alpinejs` | 3.15.12 | 3.17.2 | Minor |
| `axios` | 1.18.1 | 1.20.0 | Minor |
| `intl-tel-input` | 29.1.2 | 29.2.3 | Minor |
| `laravel-vite-plugin` | 3.1.3 | 3.2.0 | Minor |
| `signature_pad` | 5.1.3 | 5.1.4 | Patch |
| `tinymce` | 8.7.0 | 8.9.1 | Minor |
| `vite` | 8.1.4 | 8.3.0 | Minor |

**Quick command:**

```bash
npm update
npm run postinstall
```

---

## 3. Backend — Breaking (major upgrades)

| Package | Installed | Latest | Jump | Notes |
|---------|-----------|--------|------|-------|
| `guzzlehttp/guzzle` | 7.15.3 | **8.2.0** | 7 → 8 | Composer marks `update-possible` (major). Laravel 13 may still expect Guzzle 7 — verify framework constraints before upgrading. |

**Recommended action:** Do **not** force Guzzle 8 until Laravel / dependent packages declare support.

---

## 4. Backend — Need update (semver-safe)

Safe within current major via `composer update <package>`.

| Package | Installed | Update to | Layer |
|---------|-----------|-----------|-------|
| `laravel/framework` | 13.26.1 | 13.31.0 | Runtime |
| `aws/aws-sdk-php` | 3.393.3 | 3.395.1 | Runtime |
| `league/flysystem-aws-s3-v3` | 3.35.2 | 3.35.3 | Runtime |
| `stripe/stripe-php` | 21.2.1 | 21.3.2 | Runtime |
| `spatie/laravel-query-builder` | 7.3.3 | 7.3.5 | Runtime |
| `yajra/laravel-datatables-oracle` | 13.2.0 | 13.3.0 | Runtime |
| `laravel/pint` | 1.30.5 | 1.32.1 | Dev |
| `phpunit/phpunit` | 12.5.33 | 12.5.35 | Dev |

**Quick command (example):**

```bash
composer update laravel/framework aws/aws-sdk-php league/flysystem-aws-s3-v3 stripe/stripe-php spatie/laravel-query-builder yajra/laravel-datatables-oracle laravel/pint phpunit/phpunit
```

---

## 5. Priority summary

| Priority | Side | Packages | Action |
|----------|------|----------|--------|
| **P0 — Safe now** | Frontend | Section 2 (10 packages) | `npm update` + `postinstall` |
| **P0 — Safe now** | Backend | Section 4 (8 packages) | `composer update` listed packages |
| **P1 — Plan first** | Frontend | FullCalendar 7, DataTables 3, jQuery 4 | Dedicated migration + UI regression |
| **P1 — Plan first** | Backend | Guzzle 8 | Wait for Laravel / ecosystem readiness |

---

## 6. Re-check later

```bash
npm outdated
composer outdated --direct
npm audit
composer audit
```

After upgrades, refresh README “Technology Stack & Package Versions” if locked versions change.
