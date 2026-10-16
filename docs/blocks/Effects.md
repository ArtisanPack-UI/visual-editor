---
title: Effects
---

# Effects

**Status:** v1.15 · issue #881
**Attribute:** `artisanpackEffects` (added to every block)

The **Effects** panel adds visual effects to any block without custom
classes: opacity, overflow, backdrop blur and saturation, blend mode,
isolation, z-index, clip shapes and gradient text. Users with the Custom
CSS ability can also attach a small stylesheet to one block. Everything
compiles to one hashed `ve-fx-{hash}` class, and the Blade, React and Vue
renderers and the editor canvas print the same CSS for it.

See also:

- [Access gate](../site-editor/Access-Gate.md) — who may write Custom CSS.
- [Configuration](../Configuration.md#effects) — `effects.custom_css_capability` and `effects.custom_css_allow_urls`.
- [Custom blocks](Custom-Blocks.md#2-blockjson-schema) — opting a block out.

---

## 1. For editors

Select a block and open **Styles → Effects**. The panel is a tools panel:
**Opacity** shows by default, and the **⋮** menu adds the other controls.
**Reset all** clears every effect (but keeps Custom CSS you can't see, see
[§3](#3-custom-css)).

| Control | What it does |
|---|---|
| **Opacity** | 0–100%. |
| **Overflow (horizontal)** / **Overflow (vertical)** | `visible`, `hidden`, `clip`, `scroll` or `auto`. |
| **Backdrop blur** | 0–200px. Blurs whatever shows through a translucent background (frosted glass). |
| **Backdrop saturation** | 0–500%. |
| **Blend mode** | Any `mix-blend-mode` keyword (`multiply`, `screen`, `overlay`, …). |
| **Z-index** | Stacks the block above or below its neighbors. A block that isn't positioned gets `position: relative` so the value takes effect. |
| **Isolate** | Starts a new stacking context (`isolation: isolate`), so blend modes inside the block don't mix with what's behind it. |
| **Clip shape** | Circle, Ellipse, Triangle, Diamond, Hexagon or Slanted bottom. |
| **Clip background to text** | Paints the block's background only inside the letters, for gradient text. See [§4](#4-text-clip). |
| **Custom CSS** | Only shown to users with the Custom CSS ability. See [§3](#3-custom-css). |

---

## 2. Attribute shape

The panel writes one object attribute. Every key is optional:

```json
{
    "artisanpackEffects": {
        "opacity": 80,
        "overflowX": "hidden",
        "overflowY": "auto",
        "backdropBlur": 12,
        "backdropSaturate": 180,
        "mixBlendMode": "multiply",
        "isolation": true,
        "zIndex": 2,
        "clipShape": "hexagon",
        "textClip": true,
        "customCss": "& img { border-radius: 8px; }"
    }
}
```

| Key | Type | Accepted values |
|---|---|---|
| `opacity` | integer (percent) | 0–100 |
| `overflowX`, `overflowY` | string | `visible`, `hidden`, `clip`, `scroll`, `auto` |
| `backdropBlur` | integer (px) | 0–200 |
| `backdropSaturate` | integer (%) | 0–500 |
| `mixBlendMode` | string | `normal`, `multiply`, `screen`, `overlay`, `darken`, `lighten`, `color-dodge`, `color-burn`, `hard-light`, `soft-light`, `difference`, `exclusion`, `hue`, `saturation`, `color`, `luminosity` |
| `isolation` | boolean | `true` emits `isolation: isolate` |
| `zIndex` | integer | -99999–99999 |
| `clipShape` | string | `circle`, `ellipse`, `triangle`, `diamond`, `hexagon`, `slant` |
| `textClip` | boolean | `true` clips the background to the text |
| `customCss` | string | See [§3](#3-custom-css) |

Numbers may also be numeric strings. Out-of-range numbers are clamped, and
fractional ones round to the nearest integer. Unknown keys and invalid
values are dropped, so a stored value can never write arbitrary CSS
outside `customCss`.

### Opting a block out

The attribute is added to every block type. A block opts out in its
`block.json`:

```json
{
    "supports": {
        "artisanpackEffects": false
    }
}
```

The block then gets neither the attribute nor the panel.

### Front-end output

A block with effects gets a `ve-fx-{hash}` class and one rule:

```css
.ve-fx-1a2b3c4d5e6f{opacity:0.8;-webkit-backdrop-filter:blur(12px) saturate(180%);backdrop-filter:blur(12px) saturate(180%);z-index:2;& img { border-radius: 8px; }}
:where(.ve-fx-1a2b3c4d5e6f){position:relative}
```

- The hash covers the compiled declarations and the Custom CSS, so
  identical blocks share one rule.
- The zero-specificity `position: relative` is only added with a
  `zIndex`; any theme or Positioning rule wins over it.
- **Blade** prints the rules in the page's `<style data-ve-responsive>`
  block. **React and Vue** print them in one `<style data-ve-effects>` per
  `BlockTree`.
- The editor canvas compiles the same CSS, so the preview matches the
  front end.

---

## 3. Custom CSS

Custom CSS is printed **inside** the block's own rule and relies on native
CSS nesting:

- bare declarations style the block itself: `border: 2px solid; padding: 1rem;`
- nested rules style the block or its descendants: `& img { border-radius: 8px; }`, `&:hover { opacity: 1; }`
- `@media`, `@supports` and `@container` may be nested.

```css
outline: 2px dashed currentColor;

& img {
    border-radius: 8px;
}

@media (max-width: 600px) {
    padding: 0.5rem;
}
```

Comments are removed and whitespace outside strings collapses to single
spaces before the CSS is printed.

### Who can write it

Custom CSS is **denied by default**. The field is shown only to users who
pass the `visual-editor.edit-block-css` gate, which by default requires the
`edit_block_css` capability (config `effects.custom_css_capability`).
Nobody has it until you grant it to a role or redefine the gate. Users
without the ability:

- don't see the field;
- keep the Custom CSS an administrator set when they use **Reset all**;
- can't add or change Custom CSS through any save path. The server keeps
  only the Custom CSS already stored on that record.

See [Access gate](../site-editor/Access-Gate.md) for the default check,
how to override it, and how the guard runs on every block-content model
save.

### Limits

Scoping only holds while the CSS can't close the block's rule early or
leave the `<style>` element, so a value is **rejected outright** (never
partially kept, and not printed at all) when it:

- is longer than 10,000 characters;
- contains `<`, a backslash, or a control character other than tab,
  newline or carriage return;
- closes a brace, parenthesis or bracket it didn't open, closes them out
  of order, or leaves one (or a string or comment) open. Brackets must
  nest correctly: `color: rgb(`, `a[href{}` and `a:( { ) }` are all
  rejected. Brackets inside strings and comments don't count;
- has an **unquoted** `url(…)`. Write `url("…")` or `url('…')`;
- uses an at-rule other than `@media`, `@supports` or `@container`;
- contains `expression(`, `javascript:`, `-moz-binding` or `behavior:`;
- loads a resource while the site disallows it (see below).

Custom CSS is a guard against accidents and broken markup, not a sandbox:
a selector can still reach outside the block through it
(`:root:has(&) body`). That's why writing it needs the gate.

### Disallowing URLs

```php
// config/artisanpack/visual-editor.php
'effects' => [
    'custom_css_capability' => 'edit_block_css',
    'custom_css_allow_urls' => false, // default true
],
```

With `effects.custom_css_allow_urls` set to `false`, Custom CSS that uses
`url(`, `image-set(` (including `-webkit-image-set(`), `image(` or `src(`
outside strings and comments (in any letter case) is dropped entirely, in
every renderer and the editor. This closes the remaining exfiltration
channel, where attribute selectors plus a remote URL can leak input values.
Stored CSS isn't stripped on save; it just isn't printed while the switch
is off.

The Blade renderer and the editor read the config directly. The React and
Vue renderers can't, so pass the same value to `BlockTree`:

```tsx
// React
<BlockTree tree={blocks} customCssAllowUrls={false} />
```

```vue
<!-- Vue -->
<BlockTree :tree="blocks" :custom-css-allow-urls="false" />
```

The prop defaults to `true`. If the server config is `false` and the prop
isn't passed, React and Vue pages print CSS that Blade drops. Vue and
Inertia editor hosts pass `customCssAllowUrls` (and `canEditBlockCss`) in
their mount config; see [Renderers](../renderers.md).

### In the editor

- Invalid CSS is marked with `aria-invalid`, a red border (override the
  color with `--ap-visual-editor-error`) and help text that starts with
  "Error:", so color isn't the only cue. A specific message explains when
  only a URL is the problem.
- Changes between valid and invalid are announced to screen readers once
  the value has held for half a second.
- The field is left-to-right with spell checking off.
- In the site editor's in-tree entity and pattern canvases, the preview
  rule is wrapped in `@scope (.editor-styles-wrapper)`, so a selector such
  as `:root:has(&) .interface-…` can't restyle the editor itself. Iframe
  canvases and the front end are unaffected. `@scope` needs Chrome / Edge
  118+, Safari 17.4+ or Firefox 146+; older browsers skip the preview of
  that block's effects in the in-tree site editor only.

---

## 4. Text clip

**Clip background to text** emits
`background-clip: text; -webkit-text-fill-color: transparent`. Text with a
transparent fill and nothing behind it would be invisible, so the
declarations are emitted **only when the block paints a gradient or a
background image on itself**:

- a `gradient` preset;
- a custom gradient (`style.color.gradient`);
- a background image (`style.background.backgroundImage`).

A gradient that only comes from a block style variation or global styles
doesn't count; add the gradient on the block. The panel shows a notice
when text clip is on without one.

*Since v1.15.2 (#904)*, the clip prints in its own rule, `!important` and at
doubled-class specificity. The block's gradient arrives as a `background`
shorthand, either the `!important` `.has-{slug}-gradient-background` preset
class or an inline style, and the shorthand resets `background-clip`. In the
editor canvas the global styles also load after the effects styles, so
`!important` alone would lose on source order there. Before 1.15.2, text clip
over the block's own gradient painted a solid gradient box.

```css
.ve-fx-1a2b3c4d5e6f{-webkit-text-fill-color:transparent}
.ve-fx-1a2b3c4d5e6f.ve-fx-1a2b3c4d5e6f{-webkit-background-clip:text!important;background-clip:text!important}
```

Every text-clip rule also gets a forced-colors fallback, at the same
specificity, because forced colors mode drops background images and
gradients:

```css
@media (forced-colors:active){.ve-fx-1a2b3c4d5e6f.ve-fx-1a2b3c4d5e6f{-webkit-text-fill-color:currentColor;-webkit-background-clip:border-box!important;background-clip:border-box!important}}
```

---

## 5. Renderer parity

The PHP compiler (`ArtisanPackUI\VisualEditor\Effects\EffectsCompiler` and
`CustomCssSanitizer`) has byte-identical TypeScript twins in the editor
(`resources/js/visual-editor/effects/compiler.ts`) and in the React and Vue
renderers (`support/effects.ts`). Whitespace and letter case follow PHP's
ASCII rules everywhere, so the same attribute produces the same class and
CSS on every surface.

For direct use, the PHP API is `EffectsCompiler::compile( $attributes )`,
which returns `['class' => …, 'css' => …]` or `null`. Its React and Vue
twin, `compileEffects( attrs, { allowCustomCssUrls, hasBackgroundFill } )`
in `support/effects.ts`, returns `{ className, css }` or `null`;
`BlockTree` calls it for you.
