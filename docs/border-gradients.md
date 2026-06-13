# Border Gradients & Color-Picker Consistency

Status: **v1.1.0** — Issue [#490](https://github.com/ArtisanPack-UI/visual-editor/issues/490).

Block borders can be painted with a linear, radial, or conic gradient.
The feature composes with the existing State Design Tools and
Responsive Design Tools (per-state and per-breakpoint overrides),
honors registered gradient tokens from `theme.json`, and clips
correctly at any `border-radius`.

The PR also sweeps the rest of the editor so that **every per-block
color picker offers a Color | Gradient tabbed UX**, matching the way
backgrounds work natively in Gutenberg. See "Color-picker consistency"
below for the audit + deltas.

## Authoring

In the editor, open any block that supports borders, scroll to the
**Border** group in the inspector, and find the **Gradient border**
panel. Pick a gradient from the theme palette, or paste a raw CSS
gradient value (`linear-gradient(...)`, `radial-gradient(...)`,
`conic-gradient(...)`).

To set a different gradient for hover (or focus, active, etc.), switch
the state chip in the inspector to that state and re-pick. The
underlying writes go through the standard `withStateAttributes` HOC,
so the cascade and inheritance behavior match every other state-able
property.

Same story for breakpoints — switch the viewport chip to `md` (or any
breakpoint) and re-pick. Writes land in `attributes.responsive`,
respecting the mobile-first cascade.

## Opting a block in

Add `gradient: true` to the block's existing border support:

```json
{
  "supports": {
    "__experimentalBorder": {
      "color":  true,
      "radius": true,
      "style":  true,
      "width":  true,
      "gradient": true
    }
  }
}
```

(Or under the plain `"border"` key for non-experimental blocks.) Every
block in this package that already supported border was opted in
automatically by `scripts/opt-in-gradient-border.mjs`.

The `withGradientBorderControl` HOC and `extend-supports` filter
auto-detect the flag — no per-block UI code is needed.

## Theme tokens

Tokens are picked up from `theme.json`:

```json
{
  "settings": {
    "color": {
      "gradients": [
        {
          "slug":     "primary-glow",
          "name":     "Primary Glow",
          "gradient": "linear-gradient(135deg, #ff0080 0%, #7928ca 100%)"
        }
      ]
    }
  }
}
```

The editor stores the slug (`primary-glow`) on the block. The renderer
expands it to `var(--wp--preset--gradient--primary-glow)` at emit
time, which resolves against the same custom properties WordPress
generates for the background gradient palette.

### Missing token warning

If you rename or delete a referenced gradient slug from `theme.json`,
the editor's inspector panel shows a yellow `Notice` listing the
affected slugs. Until the token is restored (or the block re-picks),
the border renders as `transparent` on that cascade slot.

## How it renders

The CSS strategy is a single mask-pseudo emission — one path that
handles all three gradient kinds × all border-radius values cleanly.
Each block with a gradient border gets a stable, content-derived
scope class (`ve-gb-<hash>`) and the rules below:

```css
.ve-gb-abc123 { position: relative; }
.ve-gb-abc123::before {
    content: '';
    position: absolute;
    inset: 0;
    padding: 2px;                /* the border width */
    border-radius: inherit;      /* matches the wrapper's radius */
    background: linear-gradient(135deg, #ff0080, #7928ca);
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    mask-composite: exclude;
    pointer-events: none;
}
```

`border-image` would have been simpler for linear gradients on a
zero-radius block, but it doesn't follow `border-radius` — the
gradient renders square and fringes at every rounded corner. The
mask-composite trick is a touch heavier (one extra paint layer) but
produces pixel-correct output across the full matrix.

### State composition

A `:hover` override is emitted as an additional `::before` rule
wrapped in `@media (hover: hover)` so touch devices don't sticky-state:

```css
.ve-gb-abc123::before { transition: background 200ms ease, opacity 200ms ease; }
@media (hover: hover) {
    .ve-gb-abc123:hover::before { background: linear-gradient(135deg, #7928ca, #ff0080); }
}
```

Other states (`:focus`, `:active`, `:disabled`, …) get the same
treatment without the `@media` wrap. The state list is the
canonical `StateRegistry::DEFAULT_STATES` set.

### Breakpoint composition

A per-breakpoint override emits an additional `@media (min-width:…)`
rule:

```css
@media (min-width: 768px) {
    .ve-gb-abc123::before { background: linear-gradient(180deg, #ff0080, #7928ca); }
}
```

Mobile-first cascade — same semantics as the rest of the responsive
design tools.

## Output location

All gradient border CSS for a render pass is collected into one
`<style data-ve-gradient-borders>` block prepended to the rendered
HTML, dedupe-keyed by scope class so a block tree with N siblings
sharing the same payload emits the rule once.

## Files

| Concern                              | File                                                                                                  |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------- |
| PHP resolver (cascade → payload)     | `src/GradientBorder/GradientBorderResolver.php`                                                       |
| PHP emitter (payload → CSS)          | `src/GradientBorder/GradientBorderEmitter.php`                                                        |
| PHP per-request accumulator          | `packages/visual-editor-renderer-blade/src/Services/GradientBorderCssAccumulator.php`                 |
| PHP compile integration              | `packages/visual-editor-renderer-blade/src/Support/BlockSupports.php` → `compileGradientBorder()`     |
| JS resolver                          | `resources/js/visual-editor/gradient-borders/resolver.ts`                                             |
| JS emitter                           | `resources/js/visual-editor/gradient-borders/emitter.ts`                                              |
| JS inspector control + token warning | `resources/js/visual-editor/gradient-borders/with-gradient-border-control.tsx`                        |
| JS supports auto-extension           | `resources/js/visual-editor/gradient-borders/extend-supports.ts`                                      |
| JS one-shot registrar                | `resources/js/visual-editor/gradient-borders/register.ts`                                             |
| Bulk block opt-in script             | `scripts/opt-in-gradient-border.mjs`                                                                  |
| Visual regression contract           | `tests/visual/border-gradients.spec.ts`                                                               |

## Color-picker consistency

Each per-block color picker now offers Color | Gradient tabs:

- **Background, text, link** — Gutenberg's auto-injected color panel
  renders `ColorGradientControl` (tabbed) whenever a block declares
  `supports.color.gradients: true`. Seven forked blocks were missing
  that flag and have been updated by
  `scripts/opt-in-color-gradients.mjs`: cover, icon, post-navigation-link,
  term-description, list (core), preformatted (core), quote (core).
- **Border color** — see the "How it renders" section above. The
  native `BorderBoxControl` color popover trigger is hidden via a
  scoped CSS rule in `with-gradient-border-control.tsx`; in its place,
  our own tools-panel item renders `ColorGradientControl` (with all
  four palette keys, so the Gradient tab is always available). A
  dedicated style picker (Solid / Dashed / Dotted) sits alongside,
  replacing what the native popover used to bundle.
- **Cover overlay (placeholder state)** — `blocks/cover/edit/index.tsx`
  used a color-only `ColorPalette` before media was chosen; it now
  uses `ColorGradientControl` for symmetry with the full inspector
  control (`inspector-controls.tsx`), which has always been tabbed.

### Theme.json must define `settings.color.gradients`

Gutenberg's auto-injected Background / Text color panels (the ones that
appear on any block declaring `supports.color.gradients: true`) read
their palette from `useSettings('color.gradients')`. **If the active
theme.json doesn't define `settings.color.gradients`, those panels
silently fall back to color-only** — even though every block in this
package opts into gradients. The Gradient tab disappears with no warning.

Themes consuming this package should set:

```json
{
  "settings": {
    "color": {
      "gradients": [
        { "name": "...", "slug": "...", "gradient": "linear-gradient(...)" }
      ],
      "defaultGradients": true,
      "customGradient": true
    }
  }
}
```

`defaultGradients: true` also pulls in WP's bundled palette. The dev
themes (`themes/dev-sample/theme.json`, `resources/theme.json`) carry
this setting.

### Why `ColorGradientControl` needs all four palette keys

A subtle gotcha that bit this PR repeatedly: Gutenberg's
`__experimentalColorGradientControl` only honors caller-supplied
palettes when ALL FOUR of these props are present:

```ts
colors
gradients
disableCustomColors
disableCustomGradients
```

Pass three of them and it silently falls through to
`ColorGradientControlSelect`, which reads `color.gradients` from
`useSettings()`. In a theme that doesn't define gradients at the
settings level, that returns empty and the Gradient tab disappears.

So every call site in this PR passes all four explicitly — same shape
the editor's own background picker uses.

### Deferred to a follow-up

Two site-editor surfaces still render bare `ColorPalette` and weren't
upgraded in this PR because adding gradient support requires a new
storage model:

- `site-editor/styles/panels/styles-fields.tsx:160` — entity-level
  style values for theme defaults. Stored as palette REFS
  (`paletteRefFromColor(...)`); supporting gradients would require a
  parallel "gradient ref" concept and a site-wide gradient palette.
- `site-editor/styles/panels/colors-panel.tsx:548` — site-wide
  defaults (background / text / link). Same palette-ref shape.
- `site-editor/styles/panels/colors-panel.tsx:414` — the palette
  editor's own bespoke `ColorIndicator + ColorPicker` for adding new
  palette entries. Editing the color-palette itself is a different
  conceptual surface from picking a value; not a candidate for tabs.

These belong to a separate "site-wide gradient palette" feature, not
to the per-block color story.

## What's out of scope (v1.x)

The following from the original issue are deferred to a v2 milestone:

- **Per-side independent gradients** — v1 paints one continuous frame
  on all four sides.
- **Animated / rotating border gradients** — tracked under Block
  Animations.
- **Gradient masks / non-rectangular shapes** — tracked separately.
- **Multiple stacked border gradients** — single layer only in v1.
- **Per-state-per-breakpoint nested cascades** — per-state and
  per-breakpoint each work independently; nesting them (e.g. "hover
  at the `md` breakpoint") is not supported in v1. The existing
  `attributes.states` / `attributes.responsive` bags are siblings,
  not composable, so this is a structural limitation that lands when
  the upstream cascading is reworked.
- **Playwright visual-regression runner** — the matrix lives in
  `tests/visual/border-gradients.spec.ts` as the contract; wiring
  the runner, baselines, and CI job is its own infrastructure issue.
