# Font Awesome migration (FA4/5 → FA6)

Status: **complete** for app code. CDN is centralized at FA 6.7.2. Legacy prefixes and critical FA5 icon names are migrated. Remaining dual-load of FA5 inside `app.min.css` is deferred (documented below).

## Single CDN source

Layouts include:

```blade
@include('components.font-awesome')
```

URL and version live in `config/font_awesome.php` (`cdn_url`, `version`). Bump the version there only — not in individual Blade files.

**Layouts using the partial**

- `resources/views/layouts/crm_client_detail.blade.php`
- `resources/views/layouts/crm_client_detail_dashboard.blade.php`
- `resources/views/layouts/crm-login.blade.php`
- `resources/views/exception.blade.php`

**Static exception:** `public/colour5.html` is plain HTML; keep its `<link>` URL in sync with `config('font_awesome.cdn_url')` manually.

## Class migration rules

| Legacy (FA4/5) | FA6 target |
|----------------|------------|
| `fa fa-{icon}` | `fa-solid fa-{icon}` |
| `fas fa-{icon}` | `fa-solid fa-{icon}` |
| `far fa-{icon}` | `fa-regular fa-{icon}` |
| `fab fa-{icon}` | `fa-brands fa-{icon}` |

Keep utility classes on the same `<i>`: `fa-spin`, `fa-fw`, `fa-lg`, etc.

### Icon renames

Configured in `config/font_awesome.php` → `icon_renames`. Applied in markup and via `FontAwesomeHelper::migrateClasses()`.

| Legacy name | FA6 name |
|-------------|----------|
| `clock-o` | `clock` |
| `pencil-alt` | `pen` |
| `trash-alt` | `trash-can` |
| `file-text` / `file-alt` | `file-lines` |
| `arrows-alt` | `up-down-left-right` |
| `ellipsis-v` | `ellipsis-vertical` |
| `thumb-tack` | `thumbtack` |
| `plus-circle` | `circle-plus` |
| `external-link-alt` | `up-right-from-square` |
| `calendar-alt` | `calendar-days` |
| `map-marker-alt` | `location-dot` |
| `check-circle` | `circle-check` |
| `cloud-upload-alt` | `cloud-arrow-up` |
| `edit` / `save` / `redo` | `pen-to-square` / `floppy-disk` / `arrow-rotate-right` |

Additional FA5→FA6 renames are listed in `config/font_awesome.php` for helper/DB use.

## Helpers and components

### Blade component (new markup)

```blade
<x-fa icon="arrow-left" />
<x-fa icon="eye" style="regular" />
<x-fa icon="github" style="brands" />
<x-fa icon="spinner" spin="true" />
```

### PHP helper (controllers / HTML strings)

```php
use App\Helpers\FontAwesomeHelper;

FontAwesomeHelper::iconClass('solid', 'arrow-left');
FontAwesomeHelper::migrateClasses('fa fa-arrow-left');
FontAwesomeHelper::iconName('clock-o'); // 'clock'
```

## PR rollout

| PR | Scope | Status |
|----|--------|--------|
| 0 | Config, helper, `<x-fa>`, this doc | Done |
| 1 | Central CDN partial @ 6.7.2 | Done |
| 2 | Blade: `fa fa-*`, `far fa-*`, `fas fa-*` | Done (183 files) |
| 3 | PHP + JS string HTML + icon renames | Done (35 files + 18 `fas` prefix fixes) |
| 4 | Delete legacy files under `public/fonts/` (FA only) | Done |
| 5 | Login CDN, DB icon tooling, critical FA5 name fixes, cleanup | Done |

## Database icon strings

`email_labels.icon` stores FA class strings. The seed migration already uses FA6 (`fa-solid fa-inbox`, etc.).

For environments seeded earlier with `fas fa-*`:

```bash
php artisan fontawesome:migrate-db-icons --dry-run
php artisan fontawesome:migrate-db-icons --force
```

Or run the data migration:

```bash
php artisan migrate
# database/migrations/2026_07_09_140000_migrate_email_label_icons_to_fa6.php
```

Both are idempotent. Local dry-run on this workspace reported **0 rows** needing updates.

## Verification

These should return **no matches** in app sources:

```bash
rg '\bfa fa-' resources app public/js config
rg '\bfar fa-' resources app public/js
rg '\bfas fa-' resources app public/js
rg '\bfab fa-' resources app public/js
rg 'fa-external-link-alt|fa-calendar-alt|fa-file-alt|fa-map-marker-alt' resources app public/js
```

After Blade changes: `php artisan view:cache`

### Smoke-test pages

- CRM sidebar and header icons  
- Admin Console row actions (View / Edit)  
- Assignee task types and complete-task modal  
- Matter/personal document menus  
- Receipts modal add/remove lines  
- Sortable column headers  
- Email upload result modal (`public/js/email-upload-filename.js`)  
- Login page (`layouts/crm-login` + FA6 CDN)

## Legacy assets (PR 4 — removed)

Deleted after class migration (no code references remained):

```
public/icons/font-awesome/
public/fonts/fontawesome-webfont.svg
public/fonts/fa-*.{svg,eot,ttf,woff,woff2}
public/fonts/webfonts/fa-*.{svg,eot,ttf,woff,woff2}
```

**Keep** other theme fonts: `themify.svg`, `nunito-*`, `ElegantIcons.svg`, etc.

## Dual-load: `app.min.css` (deferred)

`public/css/app.min.css` still embeds **Font Awesome 5.8.1** (theme bundle). CRM layouts and login now load FA6 CDN **after** that file, so FA6 wins for `fa-solid` / `fa-regular` / `fa-brands` classes.

**Approach (do not strip by hand):**

1. Keep FA6 CDN as the single source of truth for icon glyphs.
2. Leave FA5 CSS inside `app.min.css` until the Stisla/theme SCSS/webpack bundle is rebuilt without Font Awesome.
3. Do **not** surgically delete FA rules from the minified file — high risk of breaking unrelated theme CSS.

When the theme is next rebuilt, exclude Font Awesome from the bundle and drop the embedded FA5 block.

## Dev-only migration scripts

One-shot rewrite scripts live under `scripts/dev/` (not needed at runtime):

- `scripts/dev/migrate-fa-fas-prefix.php`
- `scripts/dev/migrate-fa-icon-renames.php`
- `scripts/dev/migrate-colour5.php`
