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
| `GET` | `/visual-editor/api/global-styles/css` | The editor canvas stylesheet: the compiled global styles plus the theme's own stylesheets (see below). |

All endpoints behind the API middleware stack.

### The canvas stylesheet

*Since v1.15.0 (#879).* The site and post editor canvases append the
`/global-styles/css` response to their styles, so the canvas uses the
same cascade as the front end. The body is built in this order:

1. The compiled global styles (cms-framework's `GlobalStylesEmitter`),
   preceded by every spacing preset the editor's pickers offer.
2. The block style variation CSS (see
   [Block style variations](#block-style-variations)).
3. The Font Library's generated `fonts.css`, when fonts are installed.
4. The active theme's `style.css`, then its canvas-only `editor.css`.
5. The stylesheets the theme declares through `Theme::editorStyles()`
   (the list cms-framework's `@themeEditorStyles` directive links), after
   the `ap.themes.editorStyles` filter, in order.

Because the canvas inlines this body as a `<style>`, relative `url()`s
in each theme stylesheet are rewritten against the URL that file is
served from, so `url(assets/bg.jpg)` doesn't resolve against the admin
page. Pattern previews read the theme stylesheets the same way.

`Theme::editorStyles()` entries follow the directive's rules, with one
difference: only entries that resolve to a `.css` file inside the
theme's `assets/` directory are inlined. Absolute URLs, root-relative
paths, CDN links and anything that escapes `assets/` are skipped in the
canvas by design. Also:

- An entry's `media` wraps its CSS in an `@media` block. An entry whose
  `media` isn't a printable media query is skipped, rather than inlined
  for every medium.
- Handles dedupe as the directive does: the first entry with a handle
  claims it, even when that entry is skipped (an absolute or CDN URL),
  so a later entry with the same handle is never inlined.

Without cms-framework the endpoint returns an empty `text/css` body,
and the canvas falls back to the package's default canvas styles.

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

The global styles are compiled from the resolved record (theme.json
merged with the user's customizations) and printed once per request as
`<style data-ve-global-styles>`. On the front end they include the
block style variation CSS, so variations registered in code style the
page even without cms-framework.

### Where the Blade renderer prints them

*Since v1.15.0 (#879).* The global styles belong in `<head>`, **before**
the theme's own stylesheet. That is the order the editor canvas and
WordPress use, so a `style.css` rule beats the theme.json rule it
overrides on both surfaces. Mount `<x-ve-blocks-styles>` in `<head>`
before the `<link>` to your theme stylesheet:

```blade
<head>
    <x-ve-blocks-styles :theme-json="$themeJson" />
    <link rel="stylesheet" href="{{ $themeStylesheetUrl }}">
</head>
```

With the default `global_styles_in_head => true`:

- **The head renders first** (a single-file view): `<x-ve-blocks-styles>`
  claims the emission and prints the global styles itself.
- **The blocks render first** inside an `@section` or a component
  layout's slot (an `@extends` or component layout, where Blade renders
  the content before the layout's `<head>`): `<x-ve-blocks>` /
  `<x-ve-template>` push the `<style>` onto the `ve-global-styles` Blade
  stack, and `<x-ve-blocks-styles>` prints that stack. Don't print
  `@stack('ve-global-styles')` yourself in a layout that already has
  `<x-ve-blocks-styles>`.
- **The blocks render outside any section or slot**, or after
  `<x-ve-blocks-styles>` has already rendered: they print inline in the
  body, because nothing rendered later could place them higher.

Either way they print exactly once per request.

A layout that renders blocks inside a section or slot but has neither
`<x-ve-blocks-styles>` nor `@stack('ve-global-styles')` in its head is
covered by the Blade renderer's `InjectQueuedGlobalStyles` middleware,
which it adds to the `web` middleware group. On an HTML response it
injects the queued `<style>` into `<head>` before the first stylesheet
(or before `</head>`). Output that never becomes an HTTP response, such
as mail or `Blade::render()` into a string, gets no injection: set
`artisanpack.visual-editor.global_styles_in_head` to `false` there, so
the first `<x-ve-blocks>` / `<x-ve-template>` prints them inline in the
body, as in 1.14.

See [Front-end styles](../renderers.md#front-end-styles) for the
`<x-ve-blocks-styles>` flags (`:global-styles`,
`:block-style-variations`) and the full placement rules. The React and
Vue renderers print whatever CSS the host passes to `<GlobalStyles>` (or
`BlockTree`'s `globalStylesCss` prop), so placement there is up to the
host's layout.

### Long-lived workers

*Since v1.15.0.* The site editor's `GlobalStylesResolver` is bound
request-scoped, so on Octane and other long-lived workers a Global
Styles edit or a theme switch shows on the next request (front end,
canvas CSS, block style variations, keyframes) rather than after a
worker restart.

### Generated CSS

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

### Presets and custom properties

*Since v1.15.0 (#872, #878).* When `<x-ve-blocks-styles :theme-json="…" />`
compiles a theme.json, it declares, alongside the palette, font sizes
and spacing:

- **Gradients** from `settings.color.gradients` as
  `--wp--preset--gradient--{slug}`, with `.has-{slug}-gradient-background`
  classes. The legacy singular `color.gradient` key is still read when
  `color.gradients` is absent.
- **Font families** from `settings.typography.fontFamilies` as
  `--wp--preset--font-family--{slug}`, with `.has-{slug}-font-family`
  classes, so a preset font-family pick renders. The package defaults
  (`system`, `serif` and `mono`) and any host font families from config
  are always declared underneath the theme's own list; a theme entry
  wins on a shared slug.
- **Shadows** from `settings.shadow.presets` as
  `--wp--preset--shadow--{slug}`.
- **`settings.custom`**, flattened into `--wp--custom--{path}`
  properties as WordPress and cms-framework do:
  `custom.animation.glowColor` becomes
  `--wp--custom--animation--glow-color`.

### Value safety

*Since v1.15.0 (#872, #885, #890).* Theme values are printed into a
`<style>` block, so any preset value (including font families) or
`styles.*` value that could break out of the generated stylesheet is
dropped rather than printed. That covers `;`, `{`, `}`, `<`, `>`,
comments, escapes, control characters, unbalanced or interleaved
quotes, and unbalanced or crossed `( )` / `[ ]` outside strings (the
`Support\CssValue` check). If a theme value goes missing on the front
end after upgrading, check it for one of these.

### Element and per-block rules from theme.json

*Since v1.14.0 (#852).*

When `<x-ve-blocks-styles :theme-json="…" />` compiles a theme.json, its
`styles.elements.*` and `styles.blocks[*].elements.*` rules are wrapped
in `:root :where(…)` like WordPress's global styles, so they all sit at
a flat (0,1,0) specificity. `.has-*` preset classes and inline block
styles beat them, and a later per-block rule wins by source order.

*Since v1.15.0*, pseudo-state sub-nodes follow WordPress: only the
`link` and `button` elements compile them, in the order `:link`,
`:any-link`, `:visited`, `:hover`, `:focus`, `:focus-visible`,
`:active`. A pseudo-state on any other element (for example a
`:hover` under `heading`) is not emitted:

```css
:root :where(a:where(:not(.wp-element-button))) { color: var(--wp--preset--color--primary); }
:root :where(a:where(:not(.wp-element-button)):hover) { color: var(--wp--preset--color--accent); }
:root :where(.wp-block-group h1, .wp-block-group h2, …) { color: …; }
```

The link selector skips `.wp-element-button`, so a link color never
repaints buttons, which are `<a>` elements too. Host CSS that overrode
theme link or heading colors with a bare element selector such as `a`
(0,0,1) may need more specificity than it did before v1.14.0.

*Since v1.15.0 (#893)*, style nodes also accept `outline`
(`color`, `offset`, `style`, `width`), compiled to `outline-*`
declarations at the root, on elements and their states, on blocks and
in block style variations. Use it for focus indicators:

```json
{
  "styles": {
    "elements": {
      "button": {
        ":focus": {
          "outline": { "color": "var:preset|color|primary", "offset": "2px", "style": "solid", "width": "2px" }
        }
      }
    }
  }
}
```

Per-block element colors (Color → Link and the heading / button element
colors on a single block) are scoped to that block's `wp-elements-{hash}`
class and printed once in a `<style data-ve-elements>` block by
`<x-ve-blocks>` / `<x-ve-template>`. Hosts that call `BlockRenderer`
directly print it with `flushSupportAssets()`; see
[Support CSS for direct renders](../renderers.md#support-css-for-direct-renders).

### Block style variations

*Since v1.15.0 (#876, #889).* Block style variations are the named
styles in the block toolbar's **Styles** picker (a "Card" group, an
"Outline" button). Selecting one adds an `is-style-{slug}` class to the
block. They appear in the native picker in both editors, under both the
`core/` and `artisanpack/` block names.

They come from three sources, lowest precedence first. A later source
replaces an earlier one with the same block and slug:

1. **theme.json** — `styles.blocks.{block}.variations.{slug}`, a style
   node per variation. An optional `title` key becomes the picker label.

   ```json
   {
     "styles": {
       "blocks": {
         "core/group": {
           "variations": {
             "card": {
               "title": "Card",
               "color": { "background": "var:preset|color|secondary" },
               "border": { "radius": "12px" },
               "elements": { "heading": { "color": { "text": "var:preset|color|primary" } } }
             }
           }
         }
       }
     }
   }
   ```

2. **Partials** — WordPress-style `styles/*.json` files that declare
   `blockTypes`, each one variation (`slug`, `title`, `styles`) shared
   by every listed block. The active cms-framework theme's `styles/`
   directory is read automatically; add more directories with the
   `block_styles.directories` config key (see
   [Configuration](../Configuration.md)). A partial without `blockTypes`
   is a theme style variation, not a block style, and is skipped.

   ```json
   {
     "version": 3,
     "slug": "outline",
     "title": "Outline",
     "blockTypes": [ "core/button" ],
     "styles": { "border": { "width": "2px", "style": "solid" } }
   }
   ```

3. **Code** — the `VisualEditor` facade:

   ```php
   use ArtisanPackUI\VisualEditor\Facades\VisualEditor;

   // In a service provider's boot() method.
   VisualEditor::registerBlockStyle(
       'core/group',      // block
       'card',            // slug
       'Card',            // label
       [ 'border' => [ 'radius' => '12px' ] ], // theme.json style node (optional)
       false,             // isDefault (optional)
   );

   VisualEditor::unregisterBlockStyle( 'core/quote', 'plain' );
   ```

   A registration without a style keeps the theme's style for that slug
   and only relabels it. Call both methods during boot (a service
   provider's `boot()`). Registrations live in a process-wide registry,
   so on Octane a call made while handling a request leaks into every
   later request the worker serves.

The merged list then passes through the `ap.visualEditor.blockStyles`
filter, which receives a list of `{ block, slug, label, style,
isDefault }` entries and must return the same shape:

```php
addFilter( 'ap.visualEditor.blockStyles', function ( array $styles ): array {
    return array_values( array_filter(
        $styles,
        fn ( array $style ): bool => 'core/quote' !== $style['block'],
    ) );
} );
```

**Generated CSS.** Each variation compiles to a rule on the block's
front-end class plus `.is-style-{slug}`, wrapped in `:root :where(…)`
like the rest of the global styles, so it sits at (0,1,0): `.has-*`
preset classes and the block's own inline styles still beat it. A
`core/x` block targets `.wp-block-x`; an `artisanpack/x` block targets
both `.wp-block-x` and `.wp-block-artisanpack-x`.

```css
:root :where(.wp-block-group.is-style-card) {
	background-color: var(--wp--preset--color--secondary);
	border-radius: 12px;
}
:root :where(.wp-block-group.is-style-card h1, .wp-block-group.is-style-card h2, …) {
	color: var(--wp--preset--color--primary);
}
```

- Nested `elements` produce descendant rules, with the `link` and
  `button` pseudo-states described above. Nested `blocks` style the
  variation's inner blocks.
- Styles land where core's block.json `selectors` put them, for the
  variation's own block and for nested `blocks`: a button paints
  `.wp-block-button__link`, a table paints `> table`, and an image's (or
  featured image's) border and shadow paint its `img`.
- A variation with both a gradient and a background color keeps the
  color: the `background` shorthand is emitted before
  `background-color`, as WordPress does.
- Values pass the same `Support\CssValue` check as other theme values.

The CSS reaches the editor canvas (through `/global-styles/css`), the
front end (inside the global styles) and pattern previews, and each
`.is-style-*` rule is emitted once. `<x-ve-blocks-styles :theme-json>`
skips compiling the theme's variations into its tokens once the page's
global styles, which already carry them, are emitted.

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
