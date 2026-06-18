# Box Shadows — Solid + Gradient with State & Breakpoint Cascade

Status: **v1.2.0** — Issue [#607](https://github.com/ArtisanPack-UI/visual-editor/issues/607).

Blocks can be painted with a box shadow (drop shadow) that supports
solid color, gradient fill, theme presets, the inset variant, and the
full state + breakpoint cascade — all routed through the same
inspector chips that the border, color, typography, and spacing
panels already use.

The feature mirrors the architecture of border gradients (#490) one-
to-one: same scope-class strategy, same `_…ScopeId` persistence, same
state/responsive routing piggyback. The genuinely novel piece is the
CSS emission strategy for gradient-filled (and inset-gradient-filled)
shadows, which uses a `::before` / `::after` pseudo-element with
`filter: blur()` rather than the native `box-shadow` property (which
only accepts solid colors).

## Authoring

In the editor, open any block that supports borders, scroll to the
**Styles** group in the inspector, and open the **Shadow** panel.
You'll see:

- A row of preset chips (one per `shadow.presets` entry defined in
  `theme.json`).
- Four numeric inputs: **X offset**, **Y offset**, **Blur**, **Spread**.
- A **Color** picker with Color | Gradient tabs (the same UX used
  for backgrounds and border gradients).
- An **Inset** toggle.

Picking a preset short-circuits the custom fields — the shadow
renders as `var(--wp--preset--shadow--{slug})`. Clicking the active
chip clears it and reveals the custom controls again.

To set a different shadow for hover (or focus, md+, etc.), switch
the state or breakpoint chip in the inspector and re-pick. Writes
land in `attributes.states['style.shadow']` /
`attributes.responsive['style.shadow']` automatically — no per-
block.json changes are needed because the supports-extension filter
auto-injects `style.shadow` into the routing lists for every block
with any `__experimentalBorder` support.

## Opting a block in

There is **no `supports.shadow` flag**. Per the issue's deliberate
design call, the panel auto-enables for every block that already
declares any `__experimentalBorder` (or `border`) support. So a
block like:

```json
{
  "supports": {
    "__experimentalBorder": {
      "color":  true,
      "radius": true,
      "style":  true,
      "width":  true
    }
  }
}
```

automatically picks up the Shadow panel + cascade routing with no
changes. That's ~94 core ArtisanPack blocks at the time of writing.

To opt OUT of the state/responsive routing (rare — for blocks where
state-shadow doesn't make sense), set `supports.artisanpackStates:
false` or `supports.artisanpackResponsive: false` explicitly in
block.json. The supports-extension filter preserves explicit
`false` declarations.

## Theme schema

`theme.json` (and your published theme settings) gain a
`settings.shadow.presets` array:

```php
'shadow' => [
    'presets' => [
        [ 'slug' => 'shadow-sm',       'name' => 'Small',    'shadow' => '0 1px 2px 0 rgba(0,0,0,0.05)' ],
        [ 'slug' => 'shadow-md',       'name' => 'Medium',   'shadow' => '0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)' ],
        [ 'slug' => 'shadow-lg',       'name' => 'Large',    'shadow' => '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -4px rgba(0,0,0,0.1)' ],
        [ 'slug' => 'shadow-elevated', 'name' => 'Elevated', 'shadow' => '0 25px 50px -12px rgba(0,0,0,0.25)' ],
    ],
],
```

The `shadow` value is the raw CSS `box-shadow` declaration WITHOUT
the `inset` keyword — the resolver appends ` inset` when the layer's
`inset` flag is true.

Themes that ship custom shadow tokens see their entries surface as
preset chips automatically. Removing a preset that an existing block
still references surfaces a yellow "Shadow preset(s) no longer
available: …" notice above the panel until the slug is restored or
the reference is cleared.

## Attribute shape

The inspector writes a structured subtree at `attributes.style.shadow`:

```json
{
  "style": {
    "shadow": {
      "offsetX":   "2px",
      "offsetY":   "4px",
      "blur":      "8px",
      "spread":    "0",
      "color":     "rgba(0,0,0,0.25)",
      "gradient":  null,
      "inset":     false,
      "preset":    null,
      "_shadowScopeId": "k1f3z9a2p"
    }
  }
}
```

A state or breakpoint override is the same structured subtree under
`attributes.states['style.shadow'][stateKey]` or
`attributes.responsive['style.shadow'][breakpointKey]`. Writes of
`null` (rather than a subtree) clear the override and let the layer
fall back to idle.

## CSS emission

Three emission modes, dispatched per resolved layer:

1. **Preset** — `box-shadow: var(--wp--preset--shadow--{slug})`.
2. **Solid** — stock `box-shadow: [inset] X Y blur spread color`.
3. **Gradient** — `::before` (outer) or `::after` (inset) pseudo-
   element painting the gradient, blurred via `filter: blur(<blur>)`,
   translated by `transform: translate(<X>, <Y>)`. The inset variant
   additionally applies a `mask-composite: exclude` ring mask so the
   gradient fills only the inside edge of the wrapper.

All three modes share the same scoped `<style>` block (one code path
across solid and gradient — no inline `style="box-shadow:…"` on the
wrapper). The scope class is `ve-bs-<id>` where `<id>` is the
persisted `_shadowScopeId`.

## Server-side render

The PHP-side `BoxShadowResolver` + `BoxShadowEmitter` produce
identical CSS to the TS pair, so blocks render the same way whether
they're hydrated by the editor canvas, served as static save markup,
or compiled through the Blade renderer.

The resolver and emitter live at:

- `src/BoxShadow/BoxShadowResolver.php`
- `src/BoxShadow/BoxShadowEmitter.php`

`BoxShadowEmitter` is bound in `VisualEditorServiceProvider` as a
scoped singleton, resolvable via `app(BoxShadowEmitter::class)`.

## Blade renderer integration

The `visual-editor-renderer-blade` package picks up box shadows
automatically. `BlockSupports::compile()` reads `attributes.style.shadow`
on every block, calls `BoxShadowResolver` + `BoxShadowEmitter`,
stamps the `ve-bs-<id>` scope class onto the wrapper, and pushes the
emitted CSS into a per-request `BoxShadowCssAccumulator`. The
`<x-ve-blocks>` and `<x-ve-template>` components drain the
accumulator once per render and emit a single
`<style data-ve-box-shadows>` block at the top of the rendered page.

No per-block-template changes are required — any block that already
goes through `BlockSupports::compile()` (which is every block with
the standard supports wrapper) gets box-shadow rendering for free.

## Known limitation: outer gradient shadow on `overflow: hidden` blocks

CSS box-shadow paints in a layer outside the element box and is not
clipped by the element's own `overflow: hidden`. Our **gradient**
shadow path uses a `::before` (or `::after`) pseudo-element instead,
because the native `box-shadow` property does not accept gradient
values — and a pseudo-element IS clipped by the wrapper's
`overflow: hidden`.

The practical impact: on blocks that clip their content to rounded
corners (Cover is the prominent example), an **outer gradient shadow**
will render as a blurred fill that gets clipped at the wrapper edge
— so the visible "shadow ring" outside the block is missing.

**Workarounds for authors:**

- Use a **solid color shadow** (which paints via stock `box-shadow`
  and is not clipped) when working with `overflow: hidden` blocks.
- Use a **shadow preset** (also `box-shadow`) instead of a gradient.
- Apply gradient shadows on a parent Group block that wraps the
  clipped block, so the shadow renders on the parent's edge.

Inset gradient shadows are unaffected — they paint inside the wrapper
where `overflow: hidden` is exactly what we want.
