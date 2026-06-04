# Navigation

Navigation menus are editable through the site editor's Navigation
section, persisted by cms-framework's `Menu` model, exposed via REST,
and rendered on the public site through the `core/navigation` block.

This page covers menu locations, the fallback chain, the `core/navigation`
block, REST endpoints, and the nav editor UI.

---

## 1. Concepts

- **Menu** — a named, ordered tree of links. Persisted in cms-framework's
  `menus` table. Can be assigned to one or more **menu locations**.
- **Menu item** — a single link inside a menu. Persisted in `menu_items`
  (id, menu_id, parent_id, title, url, type, object, position).
- **Menu location** — a theme-declared slot (e.g. `primary`, `footer`,
  `mobile`). The theme decides what locations exist; the site editor
  assigns menus to them.
- **`core/navigation` block** — renders a menu by id or by location with
  a fallback chain.

---

## 2. Menu locations

Locations are declared in config:

```php
// config/artisanpack/visual-editor.php
'site-editor' => [
    'navigation' => [
        'locations' => [
            'primary' => ['title' => 'Primary Menu'],
            'footer'  => ['title' => 'Footer Menu'],
            'mobile'  => ['title' => 'Mobile Menu'],
        ],
    ],
],
```

The site editor lists declared locations in the Navigation section and
lets authors assign any menu to any location.

Locations are read-only via REST — `GET /visual-editor/api/menu-locations`
returns the declared list; menu → location assignment is written via the
menu's `location` field on `PUT /menus/{id}`.

---

## 3. Fallback chain

When a `core/navigation` block renders, the resolver walks this chain:

1. **Explicit menu id** on the block attributes — `{ menuId: 7 }`.
2. **Menu assigned to the requested location** — `{ location: 'primary' }`
   resolves to whatever menu currently holds `location = 'primary'`.
3. **Fallback menu** — declared in config as the location's `fallback`
   key:

   ```php
   'locations' => [
       'primary' => [
           'title'    => 'Primary Menu',
           'fallback' => 'auto',  // 'auto' | 'first' | menu-slug | null
       ],
   ],
   ```

   - `'auto'` — auto-generate from top-level pages (the WordPress
     default behaviour).
   - `'first'` — the first menu by created_at.
   - `'menu-slug'` — a specific menu's slug.
   - `null` — render nothing.

4. **Empty render** — emits a wrapping `<nav>` with no items.

---

## 4. `core/navigation` block

Attributes:

| Attribute | Type | Purpose |
|-----------|------|---------|
| `menuId` | number | Explicit menu id; wins over location. |
| `location` | string | Menu-location slug. |
| `ref` | number | Pattern reference id for synced nav patterns. |
| `overlayMenu` | string | `'always'` \| `'mobile'` \| `'never'`. |
| `openSubmenusOnClick` | boolean | Click vs hover for submenus. |
| `showSubmenuIcon` | boolean | Render the chevron icon. |
| `textColor` / `backgroundColor` | string | Color presets. |

The block stores no inner blocks — the items come from the resolved
menu at render time. Editing the menu in the site editor updates every
page using this block.

---

## 5. The nav editor UI

The Navigation section of the site editor has two view modes:

- **List view** — flat list of all menus with their locations. Click a
  menu to edit.
- **Tree view** — drag-and-drop reorderable tree of items inside a menu.
  Add items via the inserter (page, post, custom URL, taxonomy term).

The link-control picker (used when adding items) hits
`GET /visual-editor/api/search` to find pages, posts, and template parts
across the resource map.

Submenus are nested by indenting items under a parent. Drag an item one
level right to make it a child of the item above it.

---

## 6. REST API

### Menus

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/visual-editor/api/menus` | List menus (optionally filter by `?location=...`). |
| `POST` | `/visual-editor/api/menus` | Create a menu. |
| `GET` | `/visual-editor/api/menus/{id}` | Fetch a menu with its items. |
| `PUT` | `/visual-editor/api/menus/{id}` | Update menu name, location. |
| `DELETE` | `/visual-editor/api/menus/{id}` | Delete a menu (cascades to items). |

### Menu items

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/visual-editor/api/menu-items?menu_id={id}` | List items in a menu. |
| `POST` | `/visual-editor/api/menu-items` | Create an item (requires `menu_id`). |
| `GET` | `/visual-editor/api/menu-items/{id}` | Fetch an item. |
| `PUT` | `/visual-editor/api/menu-items/{id}` | Update title, url, position, parent. |
| `DELETE` | `/visual-editor/api/menu-items/{id}` | Delete an item (cascades to children). |

### Locations

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/visual-editor/api/menu-locations` | List declared locations (read-only). |

The endpoint shape mirrors `wp_navigation` from the WordPress REST API
so the Gutenberg navigation block resolves against it unchanged.

---

## 7. Rendering on the public site

### Blade

```blade
<x-ve-blocks :tree="[
    [
        'name' => 'core/navigation',
        'attributes' => ['location' => 'primary'],
        'innerBlocks' => [],
    ],
]" />
```

Or inside a template — the navigation block is just one node in the
tree. `<x-ve-template :slug="$slug" />` resolves nav blocks
transparently.

### React / Vue

Both client renderers ship a `<Navigation>` component that fetches and
renders the resolved menu:

```tsx
import { Navigation } from '@artisanpack-ui/visual-editor-renderer-react';

<Navigation location="primary" />
```

---

## 8. Performance

The resolver caches resolved menus per request — a template that
includes the same nav block twice (e.g. desktop + mobile variants) only
hits the database once.

For long-term caching across requests, decorate the resolver in a
service provider or wrap the rendered output in your own Laravel cache.
The package doesn't ship a built-in cache because nav data is rarely
the bottleneck.

---

## See also

- [Site editor](site-editor.md) — the surface that edits menus
- [Templates](templates.md) — templates that include `core/navigation`
- [Renderers](renderers.md) — `<x-ve-blocks>` and `<Navigation>` components
