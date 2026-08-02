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
- [Global styles](site-editor/Global-Styles.md) — CSS emission contract
- [Inertia](post-editor/Inertia-Integration.md) — embedding the renderers inside Inertia apps
