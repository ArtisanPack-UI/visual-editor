# @artisanpack-ui/visual-editor-renderer-react

React renderer for the ArtisanPack UI visual editor.

Takes a saved block tree (the JSON shape the editor persists to any
`HasBlockContent` Eloquent model) and renders it to React elements using
per-block components. Dynamic blocks — anything registered with the
visual-editor's `DynamicBlockRegistry` — are fetched from the
`/visual-editor/api/blocks/preview` endpoint on mount and spliced into the
tree, with an unknown-block fallback for anything the server can't render.

Built for Inertia+React apps. Ships zero styling — markup mirrors the server
Blade renderer (`@artisanpack-ui/visual-editor-renderer-blade`) so the same
theme styles apply on both sides.

## Installation

```sh
npm install @artisanpack-ui/visual-editor-renderer-react
```

Peer dependencies: React 18 or 19.

## Usage

```tsx
import { BlockTree } from '@artisanpack-ui/visual-editor-renderer-react';

export default function Post({ post }) {
    return (
        <article className="prose">
            <BlockTree tree={post.content} />
        </article>
    );
}
```

`tree` accepts:

- An array of blocks in the `{ clientId, name, attributes, innerBlocks }`
  shape the visual editor persists.
- A JSON-encoded string of that shape.
- `null` / `undefined` (renders nothing).

## Props

| Prop | Type | Default | Notes |
| --- | --- | --- | --- |
| `tree` | `Block[] \| string \| null` | — | Required. |
| `dynamicBlockEndpoint` | `string` | `/visual-editor/api/blocks/preview` | Override if your app prefix is not `visual-editor`. |
| `fetchOptions` | `RequestInit` | `{ credentials: 'same-origin' }` | Merged on top of the default request. Use for CSRF headers etc. |
| `templateParts` | `TemplatePartRecord[]` | — | `{ slug, theme?, area?, blocks }` records. Inlines `core/template-part` references and resolves a navigation's `overlay` part (needs `area: 'navigation-overlay'`). See [Navigation overlay](#navigation-overlay). |
| `patterns` | `PatternRecord[]` | — | Synced-pattern records; replaces `core/block` references with the pattern's blocks. |
| `defaultTheme` | `string` | — | Theme assumed for template-part references that don't name one. |

## Registering custom renderers

The shared registry maps a block name to a React component. Register your own
component to add support for a new block — or to override a core one:

```tsx
import {
    registerBlockRenderer,
    BlockTree,
} from '@artisanpack-ui/visual-editor-renderer-react';

registerBlockRenderer('acme/my-block', ({ attributes }) => (
    <div className="my-block">{String(attributes.title ?? '')}</div>
));
```

A custom renderer receives:

```ts
interface BlockRendererProps {
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks: Block[];
    children?: React.ReactNode; // pre-rendered inner-block React elements
    slots?: Record<string, React.ReactNode>; // named slots (since 1.13.0)
}
```

Render `{children}` wherever the inner blocks should appear — BlockTree
rendered them before invoking your component so you don't have to walk the
tree yourself. `slots` carries pre-rendered content routed to a named slot
instead of `children`; today that's only the navigation overlay
(`slots[NAVIGATION_OVERLAY_SLOT]`).

## Navigation overlay

Since 1.13.0, `core/navigation` renders with the same responsive overlay
as the Blade renderer: a menu button plus a responsive container that
shows the menu inline at desktop widths and as an accessible full-screen
drawer below 600px (`overlayMenu: 'mobile'`, the default), or always
behind the button (`'always'`). `'never'` keeps a plain `<nav><ul>`.

> **Upgrade note:** in 1.12 the menu was a direct `<ul>` child of the
> `<nav>`. It now sits three levels deeper, inside
> `.wp-block-navigation__responsive-container`, so update host CSS that
> targets `.wp-block-navigation > ul`.

`BlockTree` emits the overlay CSS once, in a
`<style data-ve-navigation-overlay>` tag, inside
`@layer ve-navigation { … }`. Unlayered host styles always win over it.

To show an overlay template part (the block's `overlay` setting) inside
the open drawer, pass it in `templateParts` with
`area: 'navigation-overlay'`:

```tsx
import { BlockTree, NAVIGATION_OVERLAY_AREA } from '@artisanpack-ui/visual-editor-renderer-react';

<BlockTree
    tree={page.content}
    templateParts={[
        { slug: 'mobile-overlay', area: NAVIGATION_OVERLAY_AREA, blocks: overlayPart.blocks },
    ]}
/>
```

A part in another area, a missing part, or a part whose blocks are all
hidden by visibility rules falls back to the menu. Self-referencing
overlays are skipped.

If you override the `core/navigation` renderer, read the overlay from
`slots[NAVIGATION_OVERLAY_SLOT]`. Both constants are exported:

| Export | Value |
| --- | --- |
| `NAVIGATION_OVERLAY_SLOT` | `'overlay'` |
| `NAVIGATION_OVERLAY_AREA` | `'navigation-overlay'` |

A stored `artisanpack/navigation-overlay-content` block is never rendered;
only the renderer creates that block.

## Dynamic blocks

Any block name with no registered renderer is rendered via `<DynamicBlock>`,
which POSTs `{ name, attributes }` to the preview endpoint and splices the
returned HTML into the page with `dangerouslySetInnerHTML`. The HTML ships
pre-escaped by the Laravel side (the same controller the editor calls for
preview), so the only injection surface is the dynamic block's own `render()`
method.

If your app uses CSRF protection, pass the token through `fetchOptions`:

```tsx
<BlockTree
    tree={post.content}
    fetchOptions={{
        headers: { 'X-CSRF-TOKEN': csrfToken },
    }}
/>
```

## Supported core blocks

All blocks in the frozen V1 allow-list from the visual-editor package's M5
audit ship with a React component:

- Text: paragraph, heading, list, list-item, quote, code, preformatted,
  pullquote, verse
- Media: image, gallery, video, audio, file, embed
- Design: cover, media-text, table, separator, spacer, details, search
- Layout: columns, column, group, row, stack, buttons, button

`core/latest-posts` and any other server-only dynamic block is intentionally
not shipped as a client-side component — those hit the preview endpoint.

## Status

This package ships as part of the ArtisanPack UI visual editor V1 (epic
`#309`, milestone M10). During the V1 cycle it lives inside the
`visual-editor` monorepo under `packages/visual-editor-renderer-react/` and
is distributed to npm via a subtree split — see `PACKAGING.md` at the
monorepo root for the release workflow.
