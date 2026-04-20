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
}
```

Render `{children}` wherever the inner blocks should appear — BlockTree
rendered them before invoking your component so you don't have to walk the
tree yourself.

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
