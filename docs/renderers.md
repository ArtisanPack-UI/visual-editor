---
title: Renderers
---

# Renderers

The visual editor saves a Gutenberg-shaped block tree to your model.
**Renderers** are the packages that take that block tree and turn it back
into HTML for the public site. V1 ships three:

| Package | Type | Where it runs | Use it when |
|---------|------|---------------|-------------|
| `artisanpack-ui/visual-editor-renderer-blade` | Composer (PHP) | Server-side, inside Blade views | Traditional Laravel app, Blade + Livewire, no SPA front-end |
| `@artisanpack-ui/visual-editor-renderer-react` | npm | Client-side, inside React tree | Inertia+React, headless React front-end |
| `@artisanpack-ui/visual-editor-renderer-vue` | npm | Client-side, inside Vue tree | Inertia+Vue, headless Vue front-end |

All three resolve a per-block partial/component by block name and fall
through to a placeholder when nothing's registered. Dynamic blocks
render server-side regardless of the client renderer — the Blade renderer
calls `DynamicBlock::render()` directly, the React and Vue renderers
proxy through `/visual-editor/api/blocks/preview`.

---

## 1. Blade renderer

`composer require artisanpack-ui/visual-editor-renderer-blade`

```blade
<x-ve-blocks :tree="$post->getBlockContent()" />
```

The `<x-ve-blocks>` component walks the block tree and renders each block:

1. If it's a dynamic block, call the registered
   `DynamicBlock::render($attributes)`.
2. Otherwise, render the partial
   `visual-editor-renderer-blade::blocks.{namespace}.{name}` with
   `$attributes` and `$innerBlocksHtml` in scope.
3. If no partial exists, emit an HTML comment placeholder.

Static-block partials live under
`packages/visual-editor-renderer-blade/resources/views/blocks/{namespace}/{block}.blade.php`.
Host apps override individual partials by publishing the view namespace:

```bash
php artisan vendor:publish --tag=visual-editor-blade-views
```

Then edit
`resources/views/vendor/visual-editor-renderer-blade/blocks/artisanpack/callout.blade.php`.

### Rendering a template

For full-template rendering (with template-part resolution and the
`<head>` block emitted by global styles):

```blade
<x-ve-template :slug="$templateSlug" />
```

`<x-ve-template>` looks up the template via `TemplateResolver`, applies
the fallback chain (theme file → user override → custom), and inlines
template parts. See [Templates](site-editor/Templates.md) for hierarchy details.

Template-part and synced-pattern references are recognized under both
their core and forked names (`BlockShape::TEMPLATE_PART_NAMES` /
`BlockShape::PATTERN_REF_NAMES`):

| Reference | Names expanded |
|-----------|----------------|
| Template part | `core/template-part` (theme files), `artisanpack/template-part` (templates saved in the site editor) |
| Synced pattern | `core/block`, `artisanpack/block` (older saved templates) |

*Since v1.12.1 (#822).* Before 1.12.1 only the core names were expanded,
so every part in a template saved from the site editor rendered as an
empty wrapper. The React and Vue inliners follow the same rule. A resolved
template part keeps the block name it was saved with, and its saved
`innerBlocks` snapshot is always replaced by the live part. A resolved
pattern reference is always output as `core/block`.

### Front-end styles

Mount `<x-ve-blocks-styles>` once in your layout's `<head>`:

```blade
<head>
    <x-ve-blocks-styles :theme-json="$themeJson" />
</head>
```

It links the bundled block-library CSS and the per-block front-end
stylesheets, and inlines the `--wp--preset--*` tokens compiled from the
`theme.json` you pass, the layout baseline, and the defaults below. Pass
`:bundle="false"` to skip the block-library `<link>`s, `:interactive="false"`
to skip the accordion / tabs assets, and `asset-base` to serve the CSS from
a CDN.

### Theme-less front-end defaults

*Since v1.12.1 (#821).* A site with no theme now renders close to the
editor canvas. `<x-ve-blocks-styles>` always declares:

- **Font-size and palette presets** — every preset the editor's pickers
  offer, as `--wp--preset--font-size--*` / `--wp--preset--color--*`
  properties, plus the `.has-{slug}-font-size`, `.has-{slug}-color`,
  `.has-{slug}-background-color`, and `.has-{slug}-border-color` classes
  that point at them. When the theme ships no list, the package defaults
  are used (`small` 13px, `regular` 16px, `medium` 20px, `large` 28px,
  `huge` 36px; `base-content`, `base-muted`, `primary`, `secondary`,
  `accent`, `success`, `warning`, `error`), with host
  [`presets`](Configuration.md#presets) layered on top. Before 1.12.1 a
  "Large" pick on a theme-less site saved `has-large-font-size` with
  nothing behind it.
- **Layout tokens at zero specificity** — `:where(:root)` defaults for
  `--wp--style--global--content-size` (720px),
  `--wp--style--global--wide-size` (1080px), `--wp--style--block-gap`
  (24px), and `--wp--style--root--padding-*` (1.5rem), plus the
  constrained-layout rules when the theme sets no layout sizes. Any theme
  `:root` declaration wins whatever the source order.

The package's own CSS also gives every `var(--wp--*)` / `var(--ap-*)` a
fallback value, so a missing token never collapses a declaration.

**Baseline stylesheet.** On top of the tokens, an optional baseline adds
content typography (system font stack, 16px, line-height 1.6), a heading
scale for `.wp-block-heading`, and root padding for constrained post
content (with `.alignfull` children bleeding to the edges). It is scoped
to block output (`.wp-block-post-content`, `.wp-block-heading`,
`.has-global-padding`) rather than `body` or bare `h1`–`h6`, so it can't
restyle a Tailwind host's own chrome, and every rule is wrapped in
`:where()` so any theme rule wins. The
[`default_styles`](Configuration.md#default_styles) config key controls
it:

| Value | Behaviour |
|-------|-----------|
| `'auto'` (default) | Emitted only when no theme is active — no `theme-json` is passed and cms-framework reports no active theme. |
| `true` | Always emitted. |
| `false` | Never emitted. |

The React and Vue renderers declare the same preset and layout token
defaults through `<LayoutBaseline />` (see below). The baseline
stylesheet is Blade-only.

### Rendering raw block markup

*Since v1.5.5 (#688).*

`<x-ve-blocks>` and `<x-ve-template>` both start from a block **tree**. When
what you have is a raw WP block-markup **string** — a block theme's
`templates/*.html` or `parts/*.html`, a `.php` pattern, a persisted
`post_content` column — use `renderMarkup()`:

```php
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

$html = app(BlockRenderer::class)->renderMarkup(
    file_get_contents($theme->path('templates/home.html')),
    defaultTheme: 'artisanpack-ui',
);
```

Markup in, HTML out. The call hydrates the markup into a tree, then inlines
`template-part` and synced-pattern references before walking it — so a
standalone theme template renders its parts as real content rather than
empty wrappers.

The hydration step matters more than it looks. Gutenberg persists most block
text in the **saved HTML**, not in the delimiter JSON, so a naive
`{blockName, attrs}` → `{name, attributes}` key-rename yields a
structurally-correct but completely textless page. Hydration replays each
registered block type's `block.json` attribute definitions back over the
saved HTML to recover paragraph and heading `content`, button text and href,
image `src`/`alt`/`caption`, list values, table cells, and everything else
declared with a `source`. Recovery is registry-driven, so a block that ships
a new sourced attribute is picked up with no extra wiring.

To hydrate without rendering — when you need the tree itself, or want to
pass a `$post` for full-fidelity rendering — use the hydrator directly:

```php
use ArtisanPackUI\VisualEditor\Support\BlockMarkupHydrator;

$tree = app(BlockMarkupHydrator::class)->hydrate($markup);
```

```blade
<x-ve-blocks :tree="$tree" :post="$post" />
```

**Scope.** `renderMarkup()` cannot resolve blocks that need an entity in
scope — `post-*`, `core/query` loops, comments, breadcrumbs — because a
string input carries no post. Hydrate to a tree and hand it to
`<x-ve-blocks :tree="…" :post="…" />` for those.

**Requires cms-framework.** The markup parser lives in
`artisanpack-ui/cms-framework`. Without it, `renderMarkup()` returns an empty
string and `hydrate()` returns an empty tree. Gate on
`BlockMarkupHydrator::canParseMarkup()` if you would rather fail loudly.
`hydrateTree()`, which takes an already-parsed `parse_blocks()`-shape array,
works either way.

> **Security.** `$markup` must already be trusted to render. Block partials
> emit recovered rich-text unescaped — that is what makes a paragraph's
> `<strong>` survive the round trip — so hydration is safe over theme files,
> patterns, and editor-authored content that passed the post editor's
> authorization, and **not** over visitor-submitted markup, which it would
> turn into stored XSS. It is not a sanitizer. This is the same trust
> boundary Gutenberg draws around block markup. Run untrusted markup through
> your own sanitizer (e.g. `kses()` from `artisanpack-ui/security`) first.

### Registering a custom block renderer

Static blocks: add the partial. Dynamic blocks: register the `DynamicBlock`
subclass in your service provider:

```php
VisualEditor::registerDynamicBlock(LatestPostsBlock::class);
```

The renderer picks it up automatically.

---

## 2. React renderer

`npm install @artisanpack-ui/visual-editor-renderer-react`

```tsx
import { BlockTree, registerBlockRenderer } from '@artisanpack-ui/visual-editor-renderer-react';
import { CalloutBlock } from './blocks/callout';

registerBlockRenderer('artisanpack/callout', CalloutBlock);

export function Post({ blocks }) {
    return <BlockTree tree={blocks} />;
}
```

Each renderer component receives `{ attributes, innerBlocks, children }`:

```tsx
export function CalloutBlock({ attributes, children }) {
    const severity = attributes.severity ?? 'info';
    return (
        <div className={`ap-callout ap-callout--${severity}`}>
            <div className="ap-callout__body">{children}</div>
        </div>
    );
}
```

`children` is the pre-rendered innerBlocks tree — pass it straight into
whatever wrapper the block needs. If you'd rather render innerBlocks
manually, use `<BlockTree tree={innerBlocks} />`.

### Dynamic blocks in React

The React renderer ships a `<DynamicBlock>` fallback that fetches the
server-rendered HTML from `POST /visual-editor/api/blocks/preview` and
injects it via `dangerouslySetInnerHTML`. The fallback fires whenever a
block has no client registration but the server has a `DynamicBlock` for
that name.

To skip the round-trip, register a client renderer for the dynamic block
that produces equivalent HTML from the same attributes. This is a
denormalization — keep the two in sync deliberately.

### Rendering templates and global styles

```tsx
import { Template, GlobalStyles } from '@artisanpack-ui/visual-editor-renderer-react';

<>
    <GlobalStyles />
    <Template slug={templateSlug} />
</>
```

`<GlobalStyles>` fetches and emits the CSS from
`/visual-editor/api/global-styles/css`. Mount it once at the root.

Also mount `<LayoutBaseline />` once (React and Vue). Besides the
flow / constrained / flex / grid layout rules, it declares the package
default spacing presets (`--wp--preset--spacing--20` … `--70`) at zero
specificity (`:where(:root)`) and the navigation block's default item gap
(`:where(.wp-block-navigation)`), so `var:preset|spacing|*` picks and
Block spacing resolve even when the theme ships no `spacingSizes`. Since
v1.12.1 (#821) it also declares the default font-size and palette presets
with their `.has-{slug}-*` classes, and the content size, wide size, block
gap and root padding tokens — the same defaults as the Blade renderer's
[theme-less front-end defaults](#theme-less-front-end-defaults). Any
`:root` declaration from `<GlobalStyles>` — theme, style variation, user
Global Styles or host presets — overrides those defaults.

### Navigation overlay

*Since v1.13.0 (#804).* The React and Vue renderers render
`core/navigation` with the same responsive overlay as Blade: a menu
button plus a responsive container that shows the menu inline at desktop
widths and as a full-screen drawer below 600px (with the default
`overlayMenu: 'mobile'`), or always behind the button (`'always'`).
`'never'` keeps the plain `<nav><ul>`. The drawer closes on Escape, a
backdrop click or a link click, traps Tab focus while open and returns
focus to the menu button.

> **Upgrade note.** In 1.12 the React and Vue renderers output a plain
> `<nav><ul>`. With the overlay, the menu `<ul>` sits three levels
> deeper, inside `.wp-block-navigation__responsive-container`. Update
> host CSS that targets `.wp-block-navigation > ul`.

When the tree contains a navigation with an overlay, `<BlockTree>` emits
the overlay CSS once, in a `<style data-ve-navigation-overlay>` tag.
The rules sit in the `ve-navigation` cascade layer
(`@layer ve-navigation { … }`), so any unlayered host or theme rule wins
over them regardless of specificity.

To render a navigation overlay template part (the block's `overlay`
setting) inside the open drawer, pass the part in `templateParts` with
`area: 'navigation-overlay'`. A reference to a part in any other area is
ignored, and the drawer falls back to the menu, as it also does when the
part is missing or visibility rules hide all of its blocks:

```tsx
import { BlockTree, NAVIGATION_OVERLAY_AREA } from '@artisanpack-ui/visual-editor-renderer-react';

<BlockTree
    tree={page.content}
    templateParts={[
        { slug: 'header', area: 'header', blocks: header.blocks },
        // NAVIGATION_OVERLAY_AREA === 'navigation-overlay'
        { slug: 'mobile-overlay', area: NAVIGATION_OVERLAY_AREA, blocks: overlay.blocks },
    ]}
/>
```

The overlay part is resolved wherever the navigation ends up, including
inside template parts, synced patterns (when you pass `patterns`) and
query loops. A part that references itself, directly or through
another part, is skipped instead of recursing.

**Custom navigation renderers.** The renderer resolves the part into an
internal overlay-content block and hands its rendered blocks to the
`core/navigation` renderer as a named slot, not as `children`:

- React: the new `slots` prop on `BlockRendererProps`. The overlay is
  `slots[NAVIGATION_OVERLAY_SLOT]` (`'overlay'`).
- Vue: the named slot `overlay` (`slots.overlay?.()`).

```tsx
import {
    NAVIGATION_OVERLAY_SLOT,
    registerBlockRenderer,
    type BlockRendererProps,
} from '@artisanpack-ui/visual-editor-renderer-react';

function MyNavigation({ attributes, children, slots }: BlockRendererProps) {
    const overlay = slots?.[NAVIGATION_OVERLAY_SLOT];

    return (
        <nav className="my-nav">
            <ul>{children}</ul>
            {overlay !== undefined && <div className="my-nav__drawer">{overlay}</div>}
        </nav>
    );
}

registerBlockRenderer('core/navigation', MyNavigation);
```

Both packages export `NAVIGATION_OVERLAY_SLOT` and
`NAVIGATION_OVERLAY_AREA`, so you don't have to hard-code either string.
Only the renderer creates the overlay-content block
(`artisanpack/navigation-overlay-content`); a stored block with that name
is dropped and never rendered.

---

## 3. Vue renderer

`npm install @artisanpack-ui/visual-editor-renderer-vue`

Same registry pattern. Renderer components are Vue SFCs (or `defineComponent`):

```ts
import { defineComponent, h } from 'vue';
import { registerBlockRenderer, BlockTree } from '@artisanpack-ui/visual-editor-renderer-vue';

const CalloutBlock = defineComponent({
    props: ['attributes', 'innerBlocks'],
    setup(props, { slots }) {
        return () => h(
            'div',
            { class: `ap-callout ap-callout--${props.attributes.severity ?? 'info'}` },
            [h('div', { class: 'ap-callout__body' }, slots.default?.())],
        );
    },
});

registerBlockRenderer('artisanpack/callout', CalloutBlock);
```

The `<BlockTree>` / `<Template>` / `<GlobalStyles>` components mirror the
React renderer's API.

That includes the [navigation overlay](#navigation-overlay):
`templateParts` takes the same records (`area: 'navigation-overlay'` for
overlay parts), and a custom `core/navigation` renderer receives the
overlay content in the named `overlay` slot instead of the default slot.

---

## 4. Which renderer for which stack

- **Traditional Laravel (Blade, Livewire, Volt)** — Blade renderer only.
  Dynamic blocks resolve in-process; no extra network round-trips.
- **Inertia + React** — React renderer on the front-end, Blade renderer
  optional for SSR. Most apps don't need SSR.
- **Inertia + Vue** — Vue renderer on the front-end.
- **API-driven SPA (no Inertia)** — fetch the block tree from
  `/visual-editor/api/{resource}/{id}/content` and render with the React
  or Vue package.

See [Inertia recipes](post-editor/Inertia-Integration.md) for end-to-end examples.

---

## 5. Renderer parity

The three renderers must produce equivalent HTML for the same block tree.
The package's `npm run verify:parity` script renders a fixture tree
through all three and diffs the output. Add a fixture entry whenever you
add a custom block that ships partials/components in more than one
renderer.

---

## 6. Distribution

The three renderer packages live under `packages/` in the monorepo and
are split out to:

- `artisanpack-ui/visual-editor-renderer-blade` (Packagist)
- `@artisanpack-ui/visual-editor-renderer-react` (npm)
- `@artisanpack-ui/visual-editor-renderer-vue` (npm)

V1.0.0 publishes the Blade renderer; React and Vue renderers ship from
the dev app via a path/file repository until their first Packagist/npm
publish.

---

## See also

- [Custom blocks](blocks/Custom-Blocks.md) — authoring blocks that need renderers
- [Templates](site-editor/Templates.md) — template fallback chain and `core/template-part`
- [Patterns](site-editor/Patterns.md) — synced-pattern (`core/block`) references
- [Configuration](Configuration.md) — `default_styles` and `presets`
- [Global styles](site-editor/Global-Styles.md) — CSS emission contract
- [Inertia](post-editor/Inertia-Integration.md) — embedding the renderers inside Inertia apps
