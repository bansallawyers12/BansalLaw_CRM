# Font Awesome migration (FA4/5 → FA6/7)

Status: **complete**. Icons use FA6+ class names (`fa-solid` / `fa-regular` / `fa-brands`). CSS and webfonts are **local** via npm `@fortawesome/fontawesome-free` (currently **7.3.x**).

## Local asset source

Layouts include:

```blade
@include('components.font-awesome')
```

That partial loads `public/css/fontawesome.min.css` (copied from `node_modules` by `npm run copy:fontawesome`, also run from `postinstall`).

**To bump Font Awesome**

1. Update `@fortawesome/fontawesome-free` in `package.json`
2. Run `npm install` (runs `postinstall` → `copy:fontawesome`) or `npm run copy:fontawesome`
3. Commit the refreshed tracked assets: `public/css/fontawesome.min.css` and `public/webfonts/`

**Views using the partial**

- `resources/views/layouts/crm_client_detail.blade.php`
- `resources/views/layouts/crm_client_detail_dashboard.blade.php`
- `resources/views/layouts/crm-login.blade.php`
- `resources/views/exception.blade.php`
- `resources/views/documents/index.blade.php`
- `resources/views/crm/documents/index.blade.php`

**Static HTML:** `public/colour5.html` links `/css/fontawesome.min.css` directly (same local file).

`config/font_awesome.php` holds **only** `style_prefix` and `icon_renames` for `FontAwesomeHelper` and DB migration tooling — not CDN URLs.

## Class migration rules

| Legacy (FA4/5) | FA6/7 target |
|----------------|--------------|
| `fa fa-{icon}` | `fa-solid fa-{icon}` |
| `fas fa-{icon}` | `fa-solid fa-{icon}` |
| `far fa-{icon}` | `fa-regular fa-{icon}` |
| `fab fa-{icon}` | `fa-brands fa-{icon}` |

Keep utility classes on the same `<i>`: `fa-spin`, `fa-fw`, `fa-lg`, etc.

### Icon renames

Configured in `config/font_awesome.php` → `icon_renames`. Applied in markup and via `FontAwesomeHelper::migrateClasses()`.

| Legacy name | Current name |
|-------------|--------------|
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

Additional FA5→FA6/7 renames are listed in `config/font_awesome.php` for helper/DB use.

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

## Database icon strings

`email_labels.icon` stores FA class strings. The seed migration already uses FA6+ (`fa-solid fa-inbox`, etc.).

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

Both are idempotent.

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
- Login page (`layouts/crm-login`)

## Theme bundle note

`public/css/app.min.css` no longer embeds Font Awesome 5 (stripped; comment points at the local FA7 file). Icons come only from `fontawesome.min.css`.

## Dev-only migration scripts

One-shot rewrite scripts live under `scripts/dev/` (not needed at runtime):

- `scripts/dev/migrate-fa-fas-prefix.php`
- `scripts/dev/migrate-fa-icon-renames.php`
- `scripts/dev/migrate-colour5.php`
