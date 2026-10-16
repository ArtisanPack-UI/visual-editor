# Flex Layout (#595)

The Flex Layout inspector panel exposes the full CSS flexbox property
surface on `artisanpack/group`, `artisanpack/columns`,
`artisanpack/column`, and `artisanpack/grid-item`. It is per-breakpoint
via the existing `<ViewportSwitcher />` so every value cascades the
same way `spacing` / `width` do.

## Where it appears

A "Flex Layout" panel renders in the block inspector for all four
blocks above. On `artisanpack/group` it **replaces** WordPress core's
Flex layout variation (the core flex choice is hidden via a
`blocks.registerBlockType` filter). On `artisanpack/columns` it
**layers on** core's column-distribution: our values win only when
explicitly set.

A sibling "Flex Item" panel appears on `group`, `column`, and
`grid-item` whenever the block is the direct child of a flex
container. When the parent is not flex, the panel renders disabled
with a tooltip — the values are not silently deleted.

## Container controls

| Control | CSS property | Values |
| --- | --- | --- |
| **Enable Flex** | `display: flex` | toggle |
| **Direction** | `flex-direction` | `row`, `column`, `row-reverse` (advanced), `column-reverse` (advanced) |
| **Wrap** | `flex-wrap` | `nowrap`, `wrap`, `wrap-reverse` (advanced) |
| **Justify Content** | `justify-content` | `flex-start`, `center`, `flex-end`, `space-between`; advanced: `space-around`, `space-evenly`, `start`, `end`, `left`, `right` |
| **Align Items** | `align-items` | `stretch`, `flex-start`, `center`, `flex-end`, `baseline`; advanced: `start`, `end`, `self-start`, `self-end`, `first baseline`, `last baseline` |
| **Align Content** | `align-content` | `stretch`, `flex-start`, `center`, `flex-end`, `space-between`; advanced: `space-around`, `space-evenly`, `start`, `end`, `baseline`. Disabled unless Wrap is `wrap` / `wrap-reverse`. |
| **Place Content** | `place-content` (shorthand) | advanced only; greys per-axis controls when set, with a "Reset shorthand" link. |
| **Row Gap** | `row-gap` | any CSS length |
| **Column Gap** | `column-gap` | any CSS length |

## Item controls

| Control | CSS property | Values |
| --- | --- | --- |
| **Align Self** | `align-self` | `auto`, `flex-start`, `center`, `flex-end`, `stretch`, `baseline`; advanced: `start`, `end`, `self-start`, `self-end` |
| **Grow** | `flex-grow` | numeric (0–999) |
| **Shrink** | `flex-shrink` | numeric (0–999) |
| **Basis** | `flex-basis` | `auto`, `0`, `full`, `fit-content`, `max-content`, `min-content`, or any CSS length |
| **Order** | `order` | numeric (-999 to 999) |

## Generated classes

The serializer emits utility classes on the wrapper (no inline styles,
no CSS custom properties). Shape mirrors Tailwind:

```
ap-flex
ap-flex-{row|col|row-reverse|col-reverse}
ap-flex-{nowrap|wrap|wrap-reverse}
ap-justify-{start|center|end|between|around|evenly|left|right|stretch|baseline}
ap-items-{start|center|end|stretch|baseline|self-start|self-end|first-baseline|last-baseline}
ap-content-{start|center|end|between|around|evenly|stretch|baseline}
ap-place-content-[value]
ap-gap-x-[value]   ap-gap-y-[value]

ap-self-{auto|start|center|end|stretch|baseline|self-start|self-end}
ap-grow-{n}     ap-grow-[n]
ap-shrink-{n}   ap-shrink-[n]
ap-basis-{auto|0|full|...}   ap-basis-[value]
ap-order-{n}    ap-order-[n]
```

Responsive prefixes: `tablet:` and `mobile:` — the desktop-first
device keys registered in `BreakpointRegistry` (since 1.12.1). A
`tablet:` class applies at 1023px and below and a `mobile:` class at
767px and below; `mobile:` rules come last in the stylesheet so they
beat `tablet:` on phones. The legacy mobile-first prefixes (`sm:`,
`md:`, `lg:`, `xl:`, `2xl:`) are still shipped so content saved before
1.12.1 renders unchanged.

The static `flex-layout.css` stylesheet ships the rules for the default
breakpoint widths. If you move the breakpoints in config or
`theme.json`, the Blade renderer's arbitrary-value rules follow the new
widths (they're built from the registry). Since 1.15.0 so do the React
and Vue flex serializers, once the host passes the server's breakpoints
to `setBreakpoints()`
(see [Responsive Design Tools](Responsive-Design-Tools.md#25-server-side-rendering)).

*Since v1.15.1 (#895)*, the token classes (`tablet:ap-flex-col`,
`md:ap-flex`, …) follow too:

- **Blade.** `<x-ve-blocks-styles>` prints the token rules at the
  registry's widths as `<style data-ve-flex-layout>`, in place of the
  `flex-layout.css` link.
- **React / Vue.** `<LayoutBaseline />` prints the same
  `<style data-ve-flex-layout>` once `setBreakpoints()` has moved the
  breakpoints. If your host links the published `flex-layout.css`, stop
  linking it when you customize breakpoints. Its default-width rules
  can't be overridden and would still fire.
- **Editor canvas.** The canvas swaps the static sheet for the same
  rules, so a device preview switches where the front end does.

With the default breakpoints nothing changes: the static stylesheet is
linked as before. Saved editor markup always uses the package's
default class names, so blocks don't go invalid when a host changes
its breakpoints.

Arbitrary values (e.g. `ap-gap-x-[3.5rem]`, `ap-basis-[200px]`) come
with a per-page `<style>` snippet emitted by the renderer — they don't
balloon the static stylesheet.

Each renderer has its own emission path:

- **Blade** — `FlexSupport::wrapperForBlock()` pushes the rule body
  into the shared `ResponsiveCssAccumulator` (keyed by a hash of the
  CSS so distinct values across blocks don't dedupe each other), which
  flushes the aggregated CSS through `<x-ve-blocks-styles />`.
- **React** — `BlockTree` walks the block tree once on render, calls
  `serializeFlex()` per block to collect `arbitraryRules`, and emits a
  single `<style data-ve-flex-arbitrary>` element above the rendered
  blocks via `buildArbitraryStyles()`.
- **Vue** — same approach as React inside the Vue `BlockTree`.

The `<style data-ve-flex-arbitrary>` selector is the cross-renderer
contract; the CSS payload is byte-identical across Blade/React/Vue
because all three call into the same `buildArbitraryStyles` logic.

### Block spacing and the default gap

Separately from the Flex Layout panel's Row Gap / Column Gap, every
layout container (`is-layout-flex`, `is-layout-grid`) gets a `gap`
from the layout baseline (`<style data-ve-layout-baseline>` in Blade,
`<LayoutBaseline />` in React and Vue). Since 1.15.0 (#883) the gap
comes from, in order:

1. the container's own **Block spacing** (`style.spacing.blockGap`);
2. the theme's `styles.spacing.blockGap` (or a per-block
   `styles.blocks.*.spacing.blockGap`);
3. `0.5em`, WordPress's flex and grid fallback.

The baseline rules have zero specificity, so blocks that size their
own gap (Columns, Gallery, Post Template, the Flex panel's gap
classes) and any theme rule still win.

Block spacing is **per container** (since 1.15.0). A Row, Stack, Grid
or Group nested inside a container that has its own Block spacing no
longer picks that spacing up. The baseline adds three zero-specificity
rules for this:

```css
:where(body) { --ap-root-block-gap: var(--wp--style--block-gap); }
:where(.is-layout-flow, .is-layout-constrained, .is-layout-flex, .is-layout-grid) { --wp--style--block-gap: var(--ap-root-block-gap); }
:where(.wp-block-columns) { --ap-columns-gap: var(--wp--style--block-gap); }
```

`--ap-root-block-gap` captures the root block gap (the theme's
`styles.spacing.blockGap`, else the package's 24px default), and every
layout container resets `--wp--style--block-gap` to it. A container's
own Block spacing then reaches its children through its per-instance
`wp-container-{hash}` rule (see [Per-instance layout](#per-instance-layout)),
the way WordPress's `wp_get_layout_style()` does it:

- flex and grid containers get `gap: <value>`. A per-axis value
  becomes `gap: <row> <column>` (one value when both sides match),
  with `0.5em` for a side that isn't set;
- flow and constrained containers get
  `> * + * { margin-block-start: <value>; margin-block-end: 0 }`, from
  the top side of a per-axis value.

The inline `--wp--style--block-gap` a block writes on its wrapper is
unchanged, so block stylesheets that read it (columns, navigation,
post-template, the buttons width calc, grid, …) keep working.
`--ap-columns-gap` keeps the Columns gap readable inside each Column,
whose percentage width now reads
`calc(X% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * f)`.
Where the baseline isn't loaded, this falls back to the old expression.

Two host-visible consequences:

- A host that set `--wp--style--block-gap` on a wrapper element
  around `<x-ve-blocks>` (rather than on `:root` / `body`) no longer
  reaches top-level layout containers. Set it on `body` / `:root`, or
  through theme.json `styles.spacing.blockGap`.
- A Tailwind utility (layered CSS) that sets `--wp--style--block-gap`
  on a layout container loses to the unlayered zero-specificity reset.
  Use the block's Block spacing control or an unlayered rule instead.

The editor canvas is unaffected: Gutenberg never writes `blockGap`
inline in the canvas, so it never had the leak.

## Per-instance layout

Since 1.15.0 (#871) every block whose `block.json` declares
`supports.layout` renders WordPress's layout classes in Blade, React
and Vue:

- `is-layout-{type}` and `wp-block-{slug}-is-layout-{type}`;
- a hashed `wp-container-{hash}` class when the block's layout
  settings (content and wide size, justification, orientation, wrap,
  vertical alignment, grid column count and minimum width) or its
  Block spacing need a rule of their own;
- the modifier classes `is-horizontal` / `is-vertical`,
  `is-content-justification-{left|center|right|space-between|stretch}`
  and `is-nowrap`.

The scope is hashed from the rule itself, so identical layouts share
one rule, and the hash is the same in all three renderers. Blade
collects the rules into the page's `<style data-ve-responsive>`; React
and Vue print them in one `<style data-ve-layout>` per `BlockTree`.
Because the gap is part of the hashed rule, a container with its own
Block spacing now gets a `wp-container-*` class, or a different hash
than before 1.15.0.

Grids follow WordPress:

- a column count gives `repeat(N, minmax(0, 1fr))`;
- a minimum column width gives an auto-fill track list;
- both together cap an auto-fill grid that still collapses on narrow
  containers;
- neither gives the default
  `repeat(auto-fill, minmax(min(12rem, 100%), 1fr))`.

The column count must be a whole number from 1 to 64; anything else is
ignored.

Covered blocks:

| Block | Layout |
| --- | --- |
| Group (including Row and Stack), Post Content | As set in the Layout panel. Since 1.15.0 they also get the modifier classes. |
| Buttons | Always flex. Vertical orientation, no-wrap, justification and vertical alignment render. Keeps its own `is-content-justification-*` class (default `left`). |
| Column | Flow by default, with a constrained content size; the Flex panel switches it to flex, as on Group. |
| Cover | The layout applies to `.wp-block-cover__inner-container` (`is-layout-flow wp-block-cover-is-layout-flow` by default; a constrained content size works). The outer `is-layout-flex wp-block-cover-is-layout-flex` is unchanged. |
| Navigation | Always flex: `is-layout-flex wp-block-navigation-is-layout-flex` on the `<nav>`, which is now a flex container, plus a scope for orientation, justification, wrap and gap. Legacy top-level `orientation` / `itemsJustification` fill in for missing `layout.*` values. |
| Query, Quote, Details | Flow. |
| Comments Pagination | Always flex. |

A legacy `layout.inherit: true` with no `type` renders as constrained,
as it does in WordPress and the editor. Columns, Quote, Details and
Query children now get flow spacing (`margin-block-start: <block gap>`)
from the baseline, matching WordPress and the canvas. The React and
Vue renderers also emit the inline Block spacing
(`--wp--style--block-gap`, `row-gap`, `column-gap`) that Blade emits on
these blocks.

Not covered, because they lay themselves out:

- **Columns** (gap and no-wrap through its own CSS);
- **Gallery**;
- **Post Template** (list, grid and masonry);
- **Query Pagination**, which the package stylesheet lays out as a
  `1fr auto 1fr` grid that the flex baseline would override.

### Block spacing values are sanitized

Since 1.15.0, `style.spacing.blockGap` values, as a string or per
axis, are validated before they reach the inline style or the
per-instance rule in all three renderers. A value that could add
declarations (`1rem;position:fixed;inset:0`), break out of the rule,
leave a parenthesis unbalanced, or contain a comment is dropped, as
WordPress's own gap filter does. Preset references
(`var:preset|spacing|40`) still expand, and a string value is trimmed
before it's printed.

## Theme.json defaults

Themes can opt out or set defaults via `settings.artisanpack.flex` in
their theme.json:

```php
'settings' => [
    'artisanpack' => [
        'flex' => [
            'enable'                => true,
            'defaultDirection'      => 'row',
            'defaultJustifyContent' => null,
            'defaultAlignItems'     => null,
            'defaultGap'            => [ 'row' => null, 'column' => null ],
        ],
    ],
],
```

Setting `enable` to `false` hides both panels in the inspector but
does **not** change the rendering of already-saved content — wrapper
classes still emit through the renderer.

## Renderer parity

The single source of truth is `resources/js/visual-editor/blocks/
_shared/flex-controls/serializer.ts`. The Blade `FlexSupport`, the
React renderer's `support/flex-serializer.ts`, and the Vue renderer's
`support/flex-serializer.ts` all mirror that exactly. Parity is
guarded by a shared `fixtures.json` consumed by:

- the editor Jest suite (`serializer.test.ts`)
- the Blade Pest suite (`FlexSupportTest.php`)
- the React vitest suite (`flex-serializer.test.ts`)
- the Vue vitest suite (`flex-serializer.test.ts`)

Adding a new behavior means adding a fixture; every renderer's CI run
fails until it produces the expected output.

## Migration from `layout.type === 'flex'`

Group blocks with the legacy `layout.type === 'flex'` shape migrate
to `artisanpackFlex.container` on first edit via the block
deprecation chain. Unedited content keeps rendering through its
existing markup. See [[migration/From Crosswinds Blocks]] for the
crosswinds-blocks → ArtisanPack UI mapping if you're coming from that
plugin.
