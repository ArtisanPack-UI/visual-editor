# Global styles

The visual editor's global styles are the theme.json-shaped record the
site editor reads and writes to drive site-wide typography, color,
layout, and per-block style overrides. One record per theme, accessed as
a singleton, validated against a pinned schema version, emitted to CSS at
render time.

This page covers the schema pin, record shape, REST surface, validation,
the site-editor styles panels, CSS emission, and how a future schema bump
is handled.

---

## 1. Pinned schema version

The package pins to **theme.json schema version 3**. The value lives in
config at `artisanpack.visual-editor.global_styles.schema_version` (not
as a hard-coded constant) so host apps can override if they know what
they're doing — the default is what the package is tested against.

The pin tracks the `@wordpress/*` packages vendored into the editor
bundle (`@wordpress/block-editor`, `@wordpress/blocks`,
`@wordpress/components`). Those packages target a specific WordPress
core release; the corresponding theme.json schema version is what the
package treats as compatible. Updating the pinned `@wordpress/*` versions
without a conscious theme.json review is the silent drift the V1 plan
explicitly calls out — the pin is here to force the review.

See [Troubleshooting §3](../troubleshooting.md#3-wordpress-package-upgrades)
for the schema-bump procedure.

---

## 2. Record shape

The record stores a theme.json-shaped blob:

```json
{
  "id": 7,
  "version": 3,
  "theme": "artisanpack-base",
  "settings": {
    "color":      { "palette": [ { "slug": "primary", "name": "Primary", "color": "#3b82f6" } ] },
    "typography": {
      "fontFamilies": [ { "slug": "sans", "name": "Sans", "fontFamily": "Inter, system-ui, sans-serif" } ],
      "fontSizes":    [ { "slug": "base", "name": "Base", "size": "1rem" } ]
    },
    "layout":     { "contentSize": "720px", "wideSize": "1120px" },
    "spacing":    { "spacingScale": { "operator": "*", "increment": 1.5, "steps": 7 } }
  },
  "styles": {
    "color":      { "background": "...", "text": "..." },
    "typography": { "fontFamily": "...", "fontSize": "...", "lineHeight": "..." },
    "elements":   { "link": { /* ... */ }, "heading": { /* ... */ } },
    "blocks":     { "artisanpack/button": { /* ... */ } }
  }
}
```

The packaged defaults live at `resources/theme-json/default-base.php` and
are returned by `GET /visual-editor/api/global-styles/base`. Host apps
override defaults by setting
`artisanpack.visual-editor.global_styles.base_path` to a custom PHP file
that returns the same shape.

---

## 3. REST API

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/visual-editor/api/global-styles/lookup` | Resolve the active theme's singleton id (creates the record on first access). |
| `GET` | `/visual-editor/api/global-styles/base` | Theme defaults — the site editor diffs the user record against this to show what's been customized. |
| `GET` | `/visual-editor/api/global-styles/{id}` | Fetch the user record. |
| `PUT` | `/visual-editor/api/global-styles/{id}` | Update the user record. Validated against the pinned schema version. |
| `GET` | `/visual-editor/api/global-styles/css` | Emit the user record as CSS for front-end injection. |

All endpoints behind the API middleware stack.

---

## 4. Validation

`PUT` requests flow through
`ArtisanPackUI\VisualEditor\Http\Requests\UpdateGlobalStylesRequest`,
which enforces:

- `version` must equal the pinned `schema_version` — a schema bump is
  explicit work, not client-driven drift.
- `settings` and `styles` are required arrays.
- `settings.color.palette` entries each carry `slug`, `name`, `color`;
  slugs must be unique across the palette (duplicate slugs collapse to a
  single CSS variable, masking a real bug).
- `settings.typography.fontFamilies` and `settings.typography.fontSizes`
  likewise require unique slugs.
- Per-block styles under `styles.blocks.{block}` must reference a
  registered block name.

A 422 response with `version`, `settings`, or `settings.color.palette`
validation errors is the signal that the payload doesn't match the
pinned schema.

---

## 5. Multi-theme scoping

The record carries a `theme` column so each installed theme gets its own
singleton. The active theme is configured at
`artisanpack.visual-editor.global_styles.theme` (default
`artisanpack-base`). Switching themes loads a fresh singleton rather
than inheriting the previous theme's customizations.

---

## 6. Site-editor styles panels

The site editor's Styles section exposes four nested panels:

- **Typography** — font families, font sizes, base type styles
  (`styles.typography`), element overrides (heading, link).
- **Colors** — palette, background, text, link.
- **Layout** — content width, wide width, root padding, spacing scale.
- **Blocks** — per-block style overrides (`styles.blocks.{block}`),
  organized alphabetically by block name.

Each panel is a controlled form over a slice of the record; saves are
debounced and write the full record via PUT.

A "Reset to theme default" button at the panel level (and per-field
chevrons) deletes the user value for that key, restoring whatever
`/global-styles/base` returns.

---

## 7. CSS emission

`GET /visual-editor/api/global-styles/css` returns a stylesheet derived
from the user record. The Blade renderer's `<x-ve-blocks>` and React/Vue
`<GlobalStyles>` components inject it once at the root.

Generated CSS shape:

```css
:root {
    --wp--preset--color--primary: #3b82f6;
    --wp--preset--font-size--base: 1rem;
    --wp--preset--font-family--sans: Inter, system-ui, sans-serif;
}

body {
    color: var(--wp--preset--color--text);
    background-color: var(--wp--preset--color--background);
    font-family: var(--wp--preset--font-family--sans);
}

.is-style-h1, h1.wp-block-heading {
    font-size: var(--wp--preset--font-size--xxx-large);
}

.wp-block-button {
    /* per-block styles emitted from styles.blocks['artisanpack/button'] */
}
```

CSS variables follow the `--wp--preset--{category}--{slug}` convention
so existing Gutenberg block markup using `var(--wp--preset--*)` resolves
without modification.

---

## 8. Handling a future schema bump

When the `@wordpress/*` pins are upgraded and the upstream theme.json
schema changes:

1. Review the upstream changelog for schema-version bumps (theme.json
   `version` field, new top-level keys, removed keys).
2. If the bump is material, update
   `artisanpack.visual-editor.global_styles.schema_version` to the new
   version in the package's default config.
3. Update `resources/theme-json/default-base.php` so the defaults match
   the new schema.
4. Ship a migration that either rewrites existing user records into the
   new schema shape or documents an upgrade path for host apps that have
   customized their global styles.
5. Update this doc with the new pinned version and the WP core release
   it tracks.

The point of the pin is that each of these steps is a conscious decision
— not a silent change that happens the next time `composer update` or
`npm update` runs.

---

## See also

- [Site editor](../site-editor.md) — the surface that edits global styles
- [Templates](Templates.md) — templates that the styles apply to
- [Renderers](../renderers.md) — CSS injection on the public site
- [Troubleshooting](../troubleshooting.md) — schema-bump procedure
