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

### Editor fallback for a newly inserted block

*Since v1.13.0 (#841).* When an author inserts a `core/navigation` block
that has no menu yet, the editor binds it to a fallback menu, resolved by
`GET /visual-editor/api/menus/fallback`:

1. the menu assigned to the active theme's `primary` location, then
2. the active theme's most recently updated menu (with no active theme,
   the most recently updated menu overall).

When there is no menu at all, the endpoint answers `204 No Content` and
the block shows its menu picker and **Create** button. Unlike WordPress,
no menu is created on the author's behalf. Before 1.13 the editor took
the first menu in its cache, which wasn't predictable. The editor
re-resolves the fallback after a menu is created and when the fallback
menu is deleted.

---

## 4. `core/navigation` block

Attributes:

| Attribute | Type | Purpose |
|-----------|------|---------|
| `menuId` | number | Explicit menu id; wins over location. |
| `location` | string | Menu-location slug. |
| `ref` | number | Pattern reference id for synced nav patterns. |
| `overlayMenu` | string | `'always'` \| `'mobile'` \| `'never'`. |
| `overlay` | string | Slug of a `navigation-overlay` template part rendered inside the open overlay. Empty = default overlay. |
| `submenuVisibility` | string | `'hover'` \| `'click'` \| `'always'` (vertical only). |
| `showSubmenuIcon` | boolean | Render the chevron icon. |
| `hasIcon` / `icon` | boolean / string | Hamburger icon vs. "Menu" text, and which icon (`'handle'` \| `'menu'`). |
| `textColor` / `backgroundColor` | string | Color presets. |

The block stores no inner blocks — the items come from the resolved
menu at render time. Editing the menu in the site editor updates every
page using this block.

### Overlay controls

The block's Settings tab carries Gutenberg's own **Overlay** panel:

- **Overlay Visibility** — Off / Mobile / Always (`overlayMenu`).
- **Menu / Close preview** — expands the "Show icon button" toggle and
  the icon choice (`hasIcon`, `icon`).
- **Overlay template** — picks a template part in the
  `navigation-overlay` area (`overlay`). **Edit** opens that part in the
  site editor's template-part editor; the **+** button (or **Create
  overlay** when none exist) creates a new "Navigation Overlay" part
  through `POST /visual-editor/api/template-parts` and opens it. New
  overlays start from the `core/navigation-overlay` pattern, a vertical
  navigation block. Before jumping to the overlay, the site editor saves
  the template part you're in so the nav block keeps its new `overlay`
  setting.

When a menu has submenus, the **Display** panel adds **Submenu
Visibility** (Hover / Click) and **Show arrow**.

The **⋮** menu in the block's List View switches between menus and
offers **Create new Menu**. The Menus list only appears when there is
another menu to switch to.

Overlay parts also appear under the **Navigation Overlay** filter in the
site editor's Template Parts section, and can be created there directly
by choosing that area. While you edit one, the site editor tells
Gutenberg (through a minimal `core/editor` store) that the canvas is an
overlay, so a nav block inside it hides its own Overlay panel.

On the front end, all three renderers render the chosen part's blocks
inside the open overlay in place of the menu copy (Blade since 1.12, React
and Vue since 1.13; see [Renderers](../renderers.md#navigation-overlay)
for passing the part to `BlockTree`). If the part is missing, in another
area, or renders nothing (for example every block in it is hidden by
visibility rules), the overlay falls back to the menu. An overlay part
that contains a navigation pointing back at itself, directly or through
another part, is skipped instead of recursing. The toggle button always
uses the default two-line icon — `hasIcon` and `icon` only affect the
editor for now.

The overlay is accessible in every renderer: at desktop widths the menu
stays in the accessibility tree (the container never carries
`aria-hidden`), and the dialog role and `aria-modal` are only applied
while the drawer is open. The open drawer traps Tab focus, closes on
Escape, a backdrop click or a link click, returns focus to the menu
button, and the button reports `aria-expanded`. The overlay colors
(`overlayBackgroundColor` / `overlayTextColor` presets and the custom
`customOverlayBackgroundColor` / `customOverlayTextColor` values) are
sanitized: preset slugs are reduced to `[a-z0-9-]` and custom values must
pass the CSS-value whitelist. In Blade, the block's own
`customTextColor` / `customBackgroundColor` go through the same
whitelist.

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
| `GET` | `/visual-editor/api/menus/fallback` | The menu a new `core/navigation` block binds to: the active theme's `primary` location menu, else its most recently updated menu. `204` when there is no menu. *Since v1.13.0.* |
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

## 8. Per-viewport visibility (mobile-overlay-only content)

The `core/navigation` block stores a single `innerBlocks` tree that
renders in both the desktop bar and the mobile overlay drawer — the
responsive container flips between the two views via CSS. Dropping a
`core/buttons` CTA into the nav so it appears in the mobile overlay
also renders it inline in the desktop bar unless you gate it per
viewport.

Use the existing **screen-size visibility rule** (see
[Block Visibility](../visibility.md)) on any nav child — `core/navigation-link`,
`core/navigation-submenu`, or a nested `core/buttons` — to scope its
render to specific breakpoints. Every block ships opted-in to block
visibility, so the Screen Size subsection is already available in the
Inspector for these blocks.

To render a CTA only inside the mobile overlay:

1. Select the nested `core/buttons` (or a `core/navigation-link`) inside
   the nav.
2. Open the **Visibility** panel in the Inspector.
3. Under **Screen Size**, pick `Show` at these sizes and check only
   `Small (≥640px)`.

The renderer emits a scoped `@media (min-width:...)` `display:none`
rule for every breakpoint you didn't check, so the CTA disappears from
the desktop / tablet bar while staying visible inside the drawer on
mobile. There's no runtime JavaScript for the gate — the CSS is scoped
per block so two blocks hidden at different breakpoints don't share
rules.

The inverse workflow — a "Book a demo" button visible only on desktop
— uses the same rule with `Show at → Large` + larger breakpoints, or
`Hide at → Small` + `Medium`.

The Screen Size options are the legacy mobile-first breakpoint keys
(`sm`, `md`, `lg`, `xl`, `2xl`), not the desktop-first **Tablet** /
**Mobile** overrides the viewport switcher uses for styles since
1.12.1. The two systems are independent: style overrides set at
Tablet or Mobile don't change which screen sizes a block renders at.

Links and submenus live in the menu, not in the page's block tree, so
their visibility settings are saved with the menu item. Menu items
store every block attribute that has no dedicated column (visibility,
animations, bindings, and similar) in `menu_items.block_attributes`,
which requires cms-framework 2.11 or later. Editing the menu from the
site editor's **Navigation** section keeps those settings intact.

> **Follow-up (v1.x):** a dedicated `overlayInnerBlocks` region on the
> nav block (Option B in #798) is tracked separately. Until it ships,
> per-viewport visibility on individual nav children is the supported
> path for mobile-overlay-only content.

---

## 9. Performance

The resolver caches resolved menus per request — a template that
includes the same nav block twice (e.g. desktop + mobile variants) only
hits the database once.

For long-term caching across requests, decorate the resolver in a
service provider or wrap the rendered output in your own Laravel cache.
The package doesn't ship a built-in cache because nav data is rarely
the bottleneck.

---

## See also

- [Site editor](../site-editor.md) — the surface that edits menus
- [Templates](Templates.md) — templates that include `core/navigation`
- [Renderers](../renderers.md) — `<x-ve-blocks>` and `<Navigation>` components
- [Block Visibility](../visibility.md) — the screen-size rule powering
  per-viewport visibility on nav children
