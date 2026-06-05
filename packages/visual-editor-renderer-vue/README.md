# @artisanpack-ui/visual-editor-renderer-vue

Vue renderer for the ArtisanPack UI visual editor.

Takes a saved block tree (the JSON shape the editor persists to any
`HasBlockContent` Eloquent model) and renders it to Vue VNodes using
per-block components. Dynamic blocks — anything registered with the
visual-editor's `DynamicBlockRegistry` — are fetched from the
`/visual-editor/api/blocks/preview` endpoint on mount and spliced into the
tree, with an unknown-block fallback for anything the server can't render.

Built for Inertia+Vue apps. Ships zero styling — markup mirrors the server
Blade renderer (`@artisanpack-ui/visual-editor-renderer-blade`) and the
React renderer (`@artisanpack-ui/visual-editor-renderer-react`) so the same
theme styles apply across all three.

## Installation

```sh
npm install @artisanpack-ui/visual-editor-renderer-vue
```

Peer dependencies: Vue 3.3 or later.

## Usage

```vue
<script setup lang="ts">
import { BlockTree } from '@artisanpack-ui/visual-editor-renderer-vue';

defineProps<{ post: { content: unknown } }>();
</script>

<template>
    <article class="prose">
        <BlockTree :tree="post.content" />
    </article>
</template>
```

`tree` accepts:

- An array of blocks in the `{ clientId, name, attributes, innerBlocks }`
  shape the visual editor persists.
- A JSON-encoded string of that shape.
- `null` / `undefined` (renders nothing).

## Props

| Prop | Type | Default | Notes |
| --- | --- | --- | --- |
| `tree` | `Block[] \| string \| null` | `null` | Optional. `null`/`undefined` renders nothing. |
| `dynamicBlockEndpoint` | `string` | `/visual-editor/api/blocks/preview` | Override if your app prefix is not `visual-editor`. |
| `fetchOptions` | `RequestInit` | `{ credentials: 'same-origin' }` | Merged on top of the default request. Use for CSRF headers etc. |

## Registering custom renderers

The shared registry maps a block name to a Vue component. Register your own
component to add support for a new block — or to override a core one:

```ts
import { defineComponent, h } from 'vue';
import {
    registerBlockRenderer,
    BlockTree,
} from '@artisanpack-ui/visual-editor-renderer-vue';

const StickerBlock = defineComponent({
    props: {
        name: { type: String, required: true },
        attributes: { type: Object, required: true },
        innerBlocks: { type: Array, required: true },
    },
    setup(props) {
        return () => h('div', { class: 'my-block' }, String(props.attributes.title ?? ''));
    },
});

registerBlockRenderer('acme/my-block', StickerBlock);
```

A custom renderer receives the standard `BlockRendererProps`:

```ts
interface BlockRendererProps {
    name: string;
    attributes: Record<string, unknown>;
    innerBlocks: Block[];
}
```

Inner blocks are passed through the default slot, already rendered as Vue
VNodes — render `<slot />` wherever inner blocks should appear so you don't
have to walk the tree yourself.

## Dynamic blocks

Any block name with no registered renderer is rendered via `<DynamicBlock>`,
which POSTs `{ name, attributes }` to the preview endpoint and splices the
returned HTML into the page via `innerHTML`. The HTML ships pre-escaped by
the Laravel side (the same controller the editor calls for preview), so the
only injection surface is the dynamic block's own `render()` method.

If your app uses CSRF protection, pass the token through `fetchOptions`:

```vue
<BlockTree
    :tree="post.content"
    :fetch-options="{ headers: { 'X-CSRF-TOKEN': csrfToken } }"
/>
```

## Supported core blocks

All blocks in the frozen V1 allow-list from the visual-editor package's M5
audit ship with a Vue component:

- Text: paragraph, heading, list, list-item, quote, code, preformatted,
  pullquote, verse
- Media: image, gallery, video, audio, file, embed
- Design: cover, media-text, table, separator, spacer, details, search
- Layout: columns, column, group, row, stack, buttons, button

`core/latest-posts` and any other server-only dynamic block is intentionally
not shipped as a client-side component — those hit the preview endpoint.

## Parity with the React renderer

The Vue renderer is a straight port of the React renderer's behavior and is
tested against it: every core block has a parity test that renders the same
fixture through both frameworks and asserts byte-identical HTML (after
normalizing framework-level serialization differences). When behavior
diverges, that's a bug in one of the two renderers — fix the renderer, not
the test.

## Status

This package ships as part of the ArtisanPack UI visual editor V1 (epic
`#309`, milestone M11). During the V1 cycle it lives inside the
`visual-editor` monorepo under `packages/visual-editor-renderer-vue/` and
is distributed to npm via a subtree split — see `PACKAGING.md` at the
monorepo root for the release workflow.
