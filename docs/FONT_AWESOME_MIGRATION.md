# Font Awesome migration (FA4/5 → FA6)

Prep and rollout guide for BansalLaw CRM. CDN centralization (PR 1) and this prep (PR 0) are done; class renames follow in PR 2–3.

## Single CDN source

Layouts include:

```blade
@include('components.font-awesome')
```

URL and version live in `config/font_awesome.php` (`cdn_url`, `version`). Bump the version there only — not in individual Blade files.

**Layouts using the partial**

- `resources/views/layouts/crm_client_detail.blade.php`
- `resources/views/layouts/crm_client_detail_dashboard.blade.php`
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

### Icon renames (FA4 names still in the codebase)

Configured in `config/font_awesome.php` → `icon_renames`:

| Legacy name | FA6 name |
|-------------|----------|
| `clock-o` | `clock` |
| `pencil-alt` | `pen` |
| `trash-alt` | `trash-can` |
| `file-text` | `file-lines` |
| `arrows-alt` | `up-down-left-right` |
| `ellipsis-v` | `ellipsis-vertical` |
| `thumb-tack` | `thumbtack` |
| `plus-circle` | `circle-plus` |

Apply renames **after** updating the style prefix.

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

### PR 2 batches (Blade)

1. Admin Console list/form views  
2. Assignee module  
3. Client detail tabs and modals (`receipts.blade.php`, document tabs)  
4. Dashboard and shared components  

### PR 3 batches (PHP / JS)

- `app/Helpers/SortableHelper.php`, `config/columnsortable.php`  
- Controllers emitting icon HTML (`ClientAccountsController`, `ClientMatterHubController`, etc.)  
- `public/js/crm/clients/detail-main.js`, `checklist.js`, `custom-form-validation.js`, `email-upload-filename.js`  

## Verification

After PR 2–3, these should return **no matches**:

```bash
rg '\bfa fa-' resources app public/js config
rg '\bfar fa-' resources app public/js
rg '\bfas fa-' resources app public/js
rg '\bfab fa-' resources app public/js
```

### Smoke-test pages

- CRM sidebar and header icons  
- Admin Console row actions (View / Edit)  
- Assignee task types and complete-task modal  
- Matter/personal document menus  
- Receipts modal add/remove lines  
- Sortable column headers  

## Legacy assets (PR 4 — removed)

Deleted after class migration (no code references remained):

```
public/icons/font-awesome/
public/fonts/fontawesome-webfont.svg
public/fonts/fa-*.{svg,eot,ttf,woff,woff2}
public/fonts/webfonts/fa-*.{svg,eot,ttf,woff,woff2}
```

**Keep** other theme fonts: `themify.svg`, `nunito-*`, `ElegantIcons.svg`, etc.

## Known dual-load (future work)

`public/css/app.min.css` still embeds **Font Awesome 5.8.1**. CRM layouts load FA6 CDN afterward. Login layout (`crm-login.blade.php`) has no FA6 CDN and relies on bundled FA5 until the theme bundle is rebuilt.
