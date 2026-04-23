# Global styles — theme.json schema pin

The visual editor's `globalStyles` singleton is the theme.json-shaped
record the site editor reads and writes. C3 (#359) introduces the
backend: a `VisualEditorGlobalStyles` model, migration, REST endpoints,
validated write path, and policy. This document records the **schema
version we pin the package to**, the constraints that follow from that
decision, and how a future version bump is handled.

## Pinned version

The package pins to **theme.json schema version 3**. The value lives in
config at `artisanpack.visual-editor.global_styles.schema_version` (not
as a hard-coded constant in code) so host apps can override if they
know what they are doing; the default is what the package is tested
against.

The pin tracks the `@wordpress/*` packages vendored into the editor
bundle (see `package.json` — `@wordpress/block-editor`,
`@wordpress/block-library`, `@wordpress/components`, etc.). These
packages target a specific WordPress core release series; the
corresponding theme.json schema version is the one the package treats
as "compatible". Updating the pinned `@wordpress/*` versions without a
conscious theme.json review is what §4.2 of the V1 plan calls out as
silent drift — the pin is here to force the review.

## Record shape

The record stores a theme.json-shaped blob:

```json
{
  "id": 7,
  "version": 3,
  "settings": {
    "color":      { "palette": [ /* { slug, name, color } */ ] },
    "typography": {
      "fontFamilies": [ /* { slug, name, fontFamily } */ ],
      "fontSizes":    [ /* { slug, name, size } */ ]
    },
    "layout":     { "contentSize": "720px", "wideSize": "1120px" }
  },
  "styles": {
    "color":      { "background": "...", "text": "..." },
    "typography": { "fontFamily": "...", "fontSize": "...", "lineHeight": "..." },
    "elements":   { "link": { /* ... */ }, "heading": { /* ... */ } },
    "blocks":     { "core/button": { /* ... */ } }
  }
}
```

The full reference payload lives at
`resources/theme-json/default-base.php` and is returned by
`GET /visual-editor/api/global-styles/base`.

## Endpoints

See `docs/core-data-shim.md` §Global styles for the REST contract the
core-data shim addresses. C3 ships four endpoints:

- `GET /visual-editor/api/global-styles/lookup` — resolves the active
  theme's singleton id (creating the row on first access).
- `GET /visual-editor/api/global-styles/{id}` — user record.
- `PUT /visual-editor/api/global-styles/{id}` — write user record;
  validated against the pinned schema version.
- `GET /visual-editor/api/global-styles/base` — theme defaults; the
  site-editor diffs the user record against this to show what has been
  customized.

## Validation

`PUT` requests flow through
`ArtisanPackUI\VisualEditor\Http\Requests\UpdateGlobalStylesRequest` which
enforces:

- `version` must equal the pinned `schema_version` — a schema bump is
  explicit work, not a client-driven drift.
- `settings` and `styles` are required arrays.
- `settings.color.palette` entries must each carry `slug`, `name`,
  `color`; slugs must be unique across the palette (duplicate slugs
  collapse to a single CSS variable, masking a real bug).
- `settings.typography.fontFamilies` and `settings.typography.fontSizes`
  likewise require unique slugs.

A 422 response with a `version`, `settings`, or
`settings.color.palette` validation error is the signal that the
payload does not match the pinned schema.

## Multi-theme scoping

The record carries a `theme` column so each installed theme gets its
own singleton. The active theme is configured at
`artisanpack.visual-editor.global_styles.theme` (default
`artisanpack-base`). Switching themes gets a fresh singleton rather
than inheriting the previous theme's customizations.

## Handling a future schema bump

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
   new schema shape or documents an upgrade path for host apps that
   have customized their global styles.
5. Update this doc with the new pinned version and the WP core release
   it tracks.

The point of the pin is that each of these steps is a conscious
decision — not a silent change that happens the next time `composer
update` or `npm update` runs.

## Out of scope for C3

- CSS emission / frontend rendering — **E1** (front-end global-styles
  CSS emission).
- Site-editor global-styles UI (typography, colors, layout, blocks,
  variations) — **D3**.
- Style variations (theme-level presets) — deferred; §8 of the plan doc
  tracks this as an open question for 1.1.
- Revision history of global-styles edits.
