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

### Core query block names

Theme templates written for WordPress use the `core/query-*` family. The
renderers render these names with the matching `artisanpack/*` fork output,
so a theme template renders on the front end without first being saved in
the site editor:

| Core name | Renders as |
|-----------|------------|
| `core/query-pagination` | `artisanpack/query-pagination` |
| `core/query-pagination-previous` | `artisanpack/query-pagination-previous` |
| `core/query-pagination-numbers` | `artisanpack/query-pagination-numbers` |
| `core/query-pagination-next` | `artisanpack/query-pagination-next` |
| `core/query-no-results` | `artisanpack/query-no-results` |
| `core/query-title` | `artisanpack/query-title` |

*Since v1.14.0 (#856).* Earlier versions rendered these names through the
unknown-block wrapper. The Blade, React and Vue renderers all follow this
mapping.

### Tree shapes

`<x-ve-blocks :tree>` accepts both the editor shape (`name` /
`attributes`) and WordPress's `parse_blocks()` shape (`blockName` /
`attrs`). Attributes are read from whichever key the block carries, so
headings in a `parse_blocks()` tree keep their content and get table of
contents anchors, and breadcrumbs and business-info blocks resolve in
either shape.

*Since v1.14.0 (#863).* Earlier versions rendered every block in an
`attrs`-shape tree with empty attributes.

### Front-end styles

Mount `<x-ve-blocks-styles>` once in your layout's `<head>`:

```blade
<head>
    <x-ve-blocks-styles :theme-json="$themeJson" />
</head>
```

It links the bundled block-library CSS and the per-block front-end
stylesheets, and inlines the `--wp--preset--*` tokens compiled from the
`theme.json` you pass, the layout baseline, the defaults below and, since
v1.15.0, the compiled global styles. It takes these attributes:

| Attribute | Default | Effect |
|-----------|---------|--------|
| `:theme-json` | `null` | The `theme.json` array to compile into `<style data-ve-theme-tokens>`. |
| `:bundle` | `true` | `false` skips the block-library `<link>`s. |
| `:interactive` | `true` | `false` skips the accordion / tabs assets. |
| `asset-base` | `/vendor/visual-editor-renderer-blade` | Serve the CSS and scripts from another base URL, such as a CDN. |
| `:global-styles` | `true` | *Since v1.15.0.* `false` means this component never prints the global styles itself. See [Global styles placement](#global-styles-placement). |
| `:block-style-variations` | `true` | *Since v1.15.0.* `false` keeps the theme's `styles.blocks.*.variations` out of the theme tokens. |

**Order.** Mount `<x-ve-blocks-styles>` *before* the `<link>` to your
theme's own stylesheet. The global styles print last inside the
component, so the page follows the editor canvas (and WordPress) order:
block library, then global styles, then the theme's `style.css`. A
`style.css` rule then beats the theme.json rule it overrides on both
surfaces.

```blade
<head>
    <x-ve-blocks-styles :theme-json="$themeJson" />
    <link rel="stylesheet" href="{{ $themeStylesheetUrl }}">
</head>
```

**Block style variations are emitted once.** The global styles already
carry every block style variation's `.is-style-*` rules. Once the global
styles are on the page (printed by this component, queued for it, or
printed earlier by a block renderer), the component doesn't compile the
theme's variations into the tokens again. There's one case it can't
detect. With `:global-styles="false"` on a head that renders *before* the
blocks, the variations are still compiled into the tokens, because the
component can't know the blocks will follow and print them too. Pair it
with `:block-style-variations="false"` on pages that render blocks.

#### Global styles placement

*Since v1.15.0 (#879).* The compiled global styles print once per request
in a `<style data-ve-global-styles>` tag. With
[`global_styles_in_head`](Configuration.md#global_styles_in_head) at its
default of `true`, where they end up depends on what renders first:

1. **The head renders first** (a single-file view with
   `<x-ve-blocks-styles>` above the blocks). The component prints the
   global styles inline in `<head>`.
2. **The blocks render first, inside an `@section` or a component
   slot** (an `@extends` layout, or an anonymous or class component
   layout). `<x-ve-blocks>` / `<x-ve-template>` push the tag onto the
   `ve-global-styles` Blade stack, and `<x-ve-blocks-styles>` prints that
   stack in `<head>` when the layout renders.
3. **The blocks render first, outside any section or slot**, or after a
   `<x-ve-blocks-styles>` has already rendered. Nothing later can move
   them higher in the document, so they print inline in the body, as in
   1.14.

In every case they print exactly once, and a second
`<x-ve-blocks-styles>` doesn't print the stack again. If the layout has
`<x-ve-blocks-styles>`, don't add your own `@stack('ve-global-styles')`,
because the component already prints it. A layout without the component
can print the stack itself:

```blade
<head>
    @stack('ve-global-styles')
    <link rel="stylesheet" href="{{ $themeStylesheetUrl }}">
</head>
```

`:global-styles="false"` means the component never claims the emission
itself. In a single-file view, the first `<x-ve-blocks>` then prints the
global styles in the body. In a layout where the blocks render first,
they have already queued the styles for the head, so the component still
prints the queued stack rather than lose them. To get body placement
there, set `global_styles_in_head` to `false`.

**Safety net for layouts that never print the stack.** The Blade
renderer adds the `InjectQueuedGlobalStyles` middleware
(`ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\InjectQueuedGlobalStyles`)
to the `web` middleware group. It acts only when blocks queued the
global styles (case 2) and neither `<x-ve-blocks-styles>` nor a host
`@stack('ve-global-styles')` printed them. It then inserts the tag:

- inside `<head>`, before the first stylesheet (`<link rel="stylesheet">`
  or `<style>`; commented-out ones are ignored), so the theme's own CSS
  still wins;
- otherwise just before `</head>`;
- with no `</head>`, right after the opening `<body>` tag;
- otherwise at the end of the response.

It never prints the styles twice. It does nothing once the stack has been
printed, or when the response already contains the queued tag. It leaves
streamed, binary-file, JSON and redirect responses alone, along with any
response that has a `Content-Encoding` header or a content type other
than `text/html`. When it injects, it updates `Content-Length`. Its state
lives on the request-scoped `GlobalStylesPlacement` service, so
long-lived workers such as Octane start every request clean.

> **Limitation.** Output that never becomes an HTTP response, such as mail
> or `Blade::render()` into a string, gets no injection. If those views
> render blocks inside a section or slot without printing the stack, set
> `global_styles_in_head` to `false` so the styles print inline in the
> body.

Set `global_styles_in_head` to `false` to restore the 1.14 placement:
`<x-ve-blocks-styles>` never prints the global styles, and the first
`<x-ve-blocks>` / `<x-ve-template>` prints them inline in the body.

#### Tags the Blade renderer prints

Besides its `<link>`s, `<x-ve-blocks-styles>` prints
`<style data-ve-theme-tokens>`, `<style data-ve-spacing-presets>`,
`<style data-ve-default-tokens>` and `<style data-ve-default-styles>`
when each has content, then `<style data-ve-responsive-stacking>`,
`<style data-ve-layout-baseline>` and `<style data-ve-global-styles>`.

*Since v1.15.0*, the layout baseline also resets the inherited block gap
on every layout container at zero specificity, so a nested Row, Stack,
Grid or Group no longer picks up its parent's Block spacing. Each
container's own spacing reaches its children through its
`wp-container-{hash}` rule instead, as in WordPress:

```css
:where(body) { --ap-root-block-gap: var(--wp--style--block-gap); }
:where(.is-layout-flow, .is-layout-constrained, .is-layout-flex, .is-layout-grid) { --wp--style--block-gap: var(--ap-root-block-gap); }
:where(.wp-block-columns) { --ap-columns-gap: var(--wp--style--block-gap); }
```

Set a site-wide `--wp--style--block-gap` on `body` or `:root` (or through
theme.json `styles.spacing.blockGap`), not on a wrapper element around
`<x-ve-blocks>`. See [Flex layout](blocks/Flex-Layout.md) for the
per-instance rules.

`<x-ve-blocks>` and `<x-ve-template>` print the support CSS collected
while their tree renders. *Since v1.15.0* that includes duotone: one
`<style data-ve-duotone>` with the `wp-duotone-*` rules and one visually
hidden `<svg data-ve-duotone>` holding the `<filter>` definitions. Each
filter id is printed once per request, even when a page renders several
trees. Hosts that call `render()` / `renderMarkup()` directly get both
from [`flushSupportAssets()`](#support-css-for-direct-renders).

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

  *Since v1.14.1.* A palette slug named `text`, `link` or `border`
  produces a `.has-{slug}-color` class that is also a block-support
  marker (`has-text-color`, `has-link-color`, `has-border-color`). A
  block that picks one of these slugs as its text color also gets an
  exact `has-{slug}-color-preset` class with its own rule. The
  `.has-{slug}-color` rule remains as a zero-specificity fallback for
  markup the renderer doesn't build: it skips elements with an inline
  `color:`, requires `has-text-color` for `link` / `border`, and the
  `text` fallback is emitted last, so in editor-canvas or saved Gutenberg
  markup a `link` / `border` text pick resolves to `text`. See
  `PresetRegistry::colorUtilityRules()`.

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

#### Support CSS for direct renders

*Since v1.14.0.*

Block supports don't print inline styles. Responsive overrides, states,
animations, gradient borders, box shadows, position, Elements API
colors (Color → Link, heading and button element colors) and, since
v1.15.0, duotone filters are collected
while the tree renders, and `<x-ve-blocks>` / `<x-ve-template>` print
them around their output. When you call `render()` or `renderMarkup()`
yourself, drain them with `flushSupportAssets()`. Print `css` before the
HTML and `scripts` after it:

```php
$renderer = app(BlockRenderer::class);
$html     = $renderer->renderMarkup($markup);
$assets   = $renderer->flushSupportAssets();

echo $assets['css'] . $html . $assets['scripts'];
```

Without it, blocks keep classes such as `wp-elements-{hash}` with no rules
behind them. That includes Navigation link colors, which printed their own
inline `<style>` before v1.14.0. Draining also clears the collected rules,
so a later `<x-ve-blocks>` in the same request doesn't print them again.

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

### Styles `<BlockTree>` prints

Like Blade, the React and Vue renderers don't write support CSS into
inline styles. `<BlockTree>` collects it while it walks the tree and
prints one tag per kind inside the rendered fragment:

| Tag | Contents |
|-----|----------|
| `<style data-ve-layout>` | Per-instance layout rules for `wp-container-{hash}` classes. |
| `<style data-ve-responsive>` | Per-breakpoint overrides for `ve-r-{hash}` classes. |
| `<style data-ve-effects>` | Effects and Custom CSS for `ve-fx-{hash}` classes. |
| `<style data-ve-duotone>` + `<svg data-ve-duotone>` | *Since v1.15.0.* Duotone rules for `wp-duotone-*` classes, plus one visually hidden `<svg>` with the `<filter>` definitions. |

*Since v1.15.0*, the React and Vue renderers also render:

- **Background images** on Group, Quote, Pullquote, Verse and Post
  Content (`style.background.backgroundImage` as an object or a string,
  plus size, position, repeat and attachment), byte for byte with Blade.
  URLs go through `safeUrl()` and are percent-encoded inside `url('…')`.
- **Duotone** on Image, Featured Image, Cover, Avatar, Site Logo and Post
  Author (and their `artisanpack/*` forks). Custom stops (hex, `rgb()`,
  `hsl()` or named colors), `unset` and theme presets all work.
- **Per-instance layout** on every block whose `block.json` declares
  `supports.layout`, with the same inline Block spacing
  (`--wp--style--block-gap`, `row-gap`, `column-gap`) Blade prints.

Two `<BlockTree>` props control the parts that depend on server config:

| Prop | Type | Default | Notes |
|------|------|---------|-------|
| `duotonePresets` | `DuotonePresets` | — | The theme's `settings.color.duotone`, as a flat list or keyed by origin (`{ theme, custom, default }`). Without it, `var:preset\|duotone\|…` picks render no filter; custom stops still work. Also accepted by `<Template>`. |
| `customCssAllowUrls` | `boolean` | `true` | Pass the host's [`effects.custom_css_allow_urls`](Configuration.md#effects). When `false`, Custom CSS that loads a resource (`url(`, `image-set(`, `image(`, `src(`) is dropped, as Blade drops it. `<BlockTree>` only; `<Template>` doesn't forward it, so trees it renders always allow URLs. |

Resolve both on the server and pass them in the page props:

```php
use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;

return Inertia::render('Posts/Show', [
    'post'               => $post,
    'duotonePresets'     => PresetRegistry::activeThemeSettings()['color']['duotone'] ?? null,
    'customCssAllowUrls' => (bool) config('artisanpack.visual-editor.effects.custom_css_allow_urls', true),
]);
```

```tsx
<BlockTree
    tree={post.content}
    duotonePresets={duotonePresets}
    customCssAllowUrls={customCssAllowUrls}
/>
```

### Breakpoints

The responsive (`ve-r-*`), column-width (`ve-w-*`) and Flex Layout rules
use the device breakpoints the renderer is configured with. The defaults
match the server's: Tablet up to 1023px and Mobile up to 767px. *Since
v1.15.0*, hosts that customize breakpoints (config `breakpoints` or
`theme.json`) can pass the server's list to `setBreakpoints()` once at
bootstrap, so the JS output matches Blade:

```php
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

// In the Inertia shared props:
'breakpoints' => app(BreakpointRegistry::class)->toArray(),
```

```ts
import { setBreakpoints, getDeviceBreakpoints } from '@artisanpack-ui/visual-editor-renderer-react';

setBreakpoints(page.props.breakpoints);
```

`setBreakpoints()` takes two forms:

- **An array**, such as `BreakpointRegistry::toArray()`. Entries with
  `minWidthPx` replace the legacy (mobile-first) list. Entries with
  `maxWidthPx` and `previewWidthPx` replace the device list. An array
  with no device entries keeps the default devices, so existing
  `setBreakpoints([{ key, minWidthPx }])` calls behave as before.
- **A map**, shaped like `config('artisanpack.visual-editor.breakpoints')`
  and layered over the defaults the way the server layers the config. A
  number or `'Npx'` string keeps the family of the entry it overrides, an
  object naming one width switches family, and `null` removes the entry:

  ```ts
  setBreakpoints({ mobile: { maxWidthPx: 599, previewWidthPx: 375 } });
  ```

Entries the server would reject are dropped: the reserved `base` key,
keys that aren't a letter or digit followed by letters, digits, `_` or `-`, non-positive or duplicate widths, and
entries with both or neither width. A device preview width wider than its
max-width is clamped to it. `getDeviceBreakpoints()` returns the active
device list, largest first. The types `BreakpointInput`, `BreakpointMap`
and `DeviceBreakpointDefinition` are exported too.

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

The [styles `<BlockTree>` prints](#styles-blocktree-prints) and the
[breakpoint setup](#breakpoints) are the same too. In templates, pass the
1.15 props in kebab case, and import `setBreakpoints` from the Vue
package:

```vue
<BlockTree
    :tree="post.content"
    :duotone-presets="duotonePresets"
    :custom-css-allow-urls="customCssAllowUrls"
/>
```

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

Things to know when you mix renderers, or render more than one tree on a
page:

- **Heading anchors are unique per `<BlockTree>`.** The React and Vue
  renderers dedupe auto anchors within one tree. Two trees on the same
  page (for example a header tree and a content tree) can repeat an
  `id`. Render them as one tree, or set author anchors on the headings
  that could collide. Duotone filter ids work the same way: each
  `<BlockTree>` prints its own `<svg data-ve-duotone>`, so ids can repeat
  across trees. (Blade prints each filter id once per request.)
- **How auto anchors are built.** *Since v1.15.0*, every renderer, and the
  editor's anchor autogeneration, reduces a heading's text to the
  characters an `id` keeps (`[A-Za-z0-9_:.-]`, runs of `-` collapsed)
  before it checks for uniqueness. Text whose letters all drop out (such
  as `日本語`) falls back to `heading`, then `heading-1`, `heading-2` and
  so on. Text with no letters or numbers at all (`???`) gets no anchor.
  Author-set anchors are kept as they are, including numeric ones such as
  `42`. The JS renderers decode only the HTML 4 named entities (plus
  `&apos;`) when they slug headings, so an HTML5-only entity such as
  `&bigstar;` slugs differently from Blade.
- **Post-content headings.** *Since v1.15.0*, the React and Vue renderers
  treat headings inside a post-content block's `_resolvedContent` as Blade
  does: an existing `id` is claimed, and a heading without one gets a
  unique auto anchor. The Table of Contents block's `_resolvedItems` are
  still resolved on the server only.
- **Breakpoints.** Hosts with custom breakpoints must pass
  `app(BreakpointRegistry::class)->toArray()` to
  [`setBreakpoints()`](#breakpoints), or the JS renderers use the default
  widths and their media queries won't match Blade's.
- **`className` on every block.** *Since v1.15.0*, Code, Preformatted,
  Verse, Embed and Accordion title/body carry their `className` in React
  and Vue too, so `ve-r-*` and `ve-fx-*` styling applies to them. On
  those blocks the JS renderers apply `className`, preset classes and
  dimensions, not every block support Blade applies.
- **Dynamic blocks rendered through `/blocks/preview`** get only what the
  block's own `render()` outputs. The Blade renderer merges block supports
  into a dynamic block's wrapper (see
  [`appliesOwnSupports()`](blocks/Custom-Blocks.md#54-dynamic-blocks-and-renderers)); the preview
  endpoint the React and Vue `<DynamicBlock>` fallback calls doesn't.

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
- [Configuration](Configuration.md) — `default_styles`, `global_styles_in_head`, `effects` and `presets`
- [Global styles](site-editor/Global-Styles.md) — CSS emission contract
- [Inertia](post-editor/Inertia-Integration.md) — embedding the renderers inside Inertia apps
