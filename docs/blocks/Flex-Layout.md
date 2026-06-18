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

Responsive prefixes: `sm:`, `md:`, `lg:`, `xl:`, `2xl:` — matching the
keys registered in `BreakpointRegistry`.

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
