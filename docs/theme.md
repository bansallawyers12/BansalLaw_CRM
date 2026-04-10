# Bansal Law CRM — Colour Theme Reference

**Theme Name:** Powder Blue & Soft Gold  
**Style:** Light, professional law firm — clean, trustworthy, approachable  
**Preview file:** `public/colour5.html`

---

## Colour Palette

| Token | Hex | Usage |
|-------|-----|-------|
| `--sidebar-bg` | `#DDEAF8` | Sidebar / left navigation background |
| `--sidebar-hover` | `#C8DCEF` | Sidebar nav item hover state |
| `--sidebar-active` | `#3A6FA8` | Sidebar active/selected nav item background |
| `--navy` | `#1E3D60` | Primary brand colour — headings, logo text, icons |
| `--accent-gold` | `#C8992A` | Accent — badges, highlights, active borders, icons |
| `--accent-light` | `#FEFAE8` | Gold tint background — hover, tooltips |
| `--page-bg` | `#F0F6FF` | Main content area background |
| `--card-bg` | `#FFFFFF` | Card / panel background |
| `--text-dark` | `#1A2C40` | Primary body text |
| `--text-muted` | `#5E7A90` | Secondary / placeholder text, labels |
| `--border` | `#C8DCEF` | Borders, dividers, table lines |
| `--success` | `#1E7A52` | Active status, positive indicators |
| `--danger` | `#A83020` | Overdue, alerts, error states |
| `--header-bg` | `#FFFFFF` | Top bar / header background |

---

## CSS Variables (copy-paste ready)

```css
:root {
  --sidebar-bg:    #DDEAF8;
  --sidebar-hover: #C8DCEF;
  --sidebar-active:#3A6FA8;
  --navy:          #1E3D60;
  --accent-gold:   #C8992A;
  --accent-light:  #FEFAE8;
  --page-bg:       #F0F6FF;
  --card-bg:       #FFFFFF;
  --text-dark:     #1A2C40;
  --text-muted:    #5E7A90;
  --border:        #C8DCEF;
  --success:       #1E7A52;
  --danger:        #A83020;
  --header-bg:     #FFFFFF;
}
```

---

## Typography

| Property | Value |
|----------|-------|
| **Primary font** | `'Segoe UI', sans-serif` |
| **Base size** | `14px` |
| **Headings** | `font-weight: 700`, colour `--navy` |
| **Body** | `font-weight: 400`, colour `--text-dark` |
| **Labels / caps** | `font-weight: 600`, `text-transform: uppercase`, `letter-spacing: 1px`, colour `--text-muted` |

---

## Component Rules

### Sidebar
- Background: `--sidebar-bg` (`#DDEAF8`)
- Right border: `1px solid --border`
- Nav text colour: `--navy`
- Active item background: `--sidebar-active`, left border `3px solid --accent-gold`, text `#fff`
- Hover: `--sidebar-hover`
- Section labels: `--text-muted`, `10px`, `700 weight`, `uppercase`

### Top Bar
- Background: `--header-bg` (`#FFFFFF`)
- Bottom border: `1px solid --border`
- Page title: `--navy`, `18px`, `700 weight`
- Breadcrumb active: `--sidebar-active`
- **CRM implementation:** `public/css/crm-theme.css` (linked **last** in `<head>` from `layouts/crm_client_detail.blade.php` and `layouts/crm_client_detail_dashboard.blade.php`, after `@yield('styles')` / `@stack('styles')`). Dashboard widgets also use matching tokens in `public/css/dashboard.css` `:root`.

### Buttons
- **Primary button:** background `--navy`, text `#fff`
- **Gold button:** background `--accent-gold`, text `#fff`
- **Outline button:** border `1px solid --border`, text `--navy`, hover bg `--sidebar-bg`

### KPI Cards
- Background: `--card-bg`, border `1px solid --border`
- Box shadow: `0 1px 4px rgba(30,61,96,0.06)`
- Border radius: `10px`
- Value: `28px`, `700 weight`, `--text-dark`
- Label: `11.5px`, `600 weight`, `uppercase`, `--text-muted`

### Tables
- Header row bg: `--page-bg`
- Row hover bg: `#EBF3FF`
- Border: `1px solid --border`

### Status Badges
| Status | Background | Text |
|--------|-----------|------|
| Active | `rgba(30,122,82,0.12)` | `--success` `#1E7A52` |
| Pending | `rgba(200,153,42,0.15)` | `#7A5800` |
| Closed | `rgba(94,122,144,0.12)` | `--text-muted` |
| In Review | `rgba(30,61,96,0.1)` | `--navy` |

### Icon Dot Colours (activity feed, KPI icons)
| Purpose | Background | Icon colour |
|---------|-----------|-------------|
| Gold / case | `rgba(200,153,42,0.12)` | `--accent-gold` |
| Green / hearing | `rgba(30,122,82,0.12)` | `--success` |
| Navy / client | `rgba(30,61,96,0.1)` | `--navy` |
| Red / danger | `rgba(168,48,32,0.1)` | `--danger` |
| Gray / closed | `rgba(94,122,144,0.1)` | `--text-muted` |

---

## Swatch Preview

```
Sidebar BG    #DDEAF8  ████████  Powder Blue
Navy          #1E3D60  ████████  Deep Navy
Active        #3A6FA8  ████████  Medium Blue
Gold          #C8992A  ████████  Soft Gold
Page BG       #F0F6FF  ████████  Ice Blue
Card BG       #FFFFFF  ████████  White
Text Dark     #1A2C40  ████████  Near Black
Text Muted    #5E7A90  ████████  Steel Gray
Border        #C8DCEF  ████████  Light Blue Border
Success       #1E7A52  ████████  Forest Green
Danger        #A83020  ████████  Burnt Red
```

---

> **Rule:** All new pages, components, and features built for this CRM must reference this file for colour decisions. Do not introduce new colours without adding them here first.
