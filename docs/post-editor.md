# Post editor

The post editor is the surface authors use to edit a single resource
(post, page, custom CPT). It's mounted via the
[`<x-visual-editor />` Blade component](blade-component.md) and built on
top of `@wordpress/block-editor`, with chrome restyled to DaisyUI through
[`@artisanpack-ui/react`](https://www.npmjs.com/package/@artisanpack-ui/react).

This page is a tour of the surface: what's where, how to extend each
region, what API endpoints back it.

---

## 1. Layout

```text
┌───────────────────────────────────────────────────────────────────────┐
│  Topbar: Title · Status · Preview · Save · Inserter toggle           │
├──────────────┬──────────────────────────────────────┬─────────────────┤
│              │                                      │                 │
│   Block      │            Canvas                    │   Inspector     │
│   library    │     (iframe-wrapped editor)          │   sidebar       │
│   sidebar    │                                      │                 │
│              │                                      │                 │
├──────────────┴──────────────────────────────────────┴─────────────────┤
│  Footer: breadcrumbs, autosave indicator, block count                │
└───────────────────────────────────────────────────────────────────────┘
```

- **Topbar** — document chrome (title, status, preview, save, inserter
  toggle).
- **Block library sidebar** (left, collapsible) — browsable list of all
  registered blocks, grouped by category. Also surfaces patterns.
- **Canvas** (center) — iframe-wrapped block editor. Isolated styles so
  the front-end theme renders inside without leaking out.
- **Inspector sidebar** (right) — block-level controls (alignment, color,
  typography, spacing, advanced).
- **Footer** — breadcrumbs through the block tree, autosave status,
  block count.

---

## 2. Canvas

The canvas is an iframe-wrapped `BlockEditorProvider` from
`@wordpress/block-editor`. Iframe isolation gives blocks access to the
theme's typography and color CSS without leaking editor chrome styles
into the content.

Theme CSS injection is driven by the active global-styles record (see
[Global styles](global-styles.md)). The CSS is fetched from
`GET /visual-editor/api/global-styles/lookup` → `GET /global-styles/{id}`
on boot and injected into the iframe via the React renderer's
`<GlobalStyles>` component.

Block selection, drag-and-drop, undo/redo, and keyboard shortcuts come
from `@wordpress/block-editor` unchanged.

---

## 3. Inspector sidebar

The right sidebar renders `InspectorControls` for the currently selected
block. Every forked `artisanpack/*` block ships a complete control set:

- Alignment + dimensions
- Color (text, background, link, gradient)
- Typography (font family, size, line height, letter spacing)
- Spacing (margin, padding, block gap)
- Border (radius, style, width, color)
- Advanced (HTML anchor, additional CSS class)

Block authors add their own controls inside `<InspectorControls>` from
their `edit.tsx` — see [Custom blocks §5](custom-blocks.md).

The sidebar is also where the document-settings panel renders (status,
slug, excerpt, featured image, author, comments). Visibility is controlled
by the `supports` attribute on `<x-visual-editor />` — fields you don't
want shown can be turned off without code changes.

---

## 4. Block library sidebar

Opens with the topbar inserter button. Lists every block returned by
`GET /visual-editor/api/blocks`, grouped by `category` (from `block.json`)
and filtered by the enabled-block allow/deny lists.

The same panel also surfaces **patterns** — both synced (referenced from
the pattern store) and unsynced (template snippets to drop inline). See
[Patterns](patterns.md).

Slash command (typed inline in any rich-text block) routes through the
same registry — `/heading`, `/image`, `/pattern hero`, etc.

---

## 5. Media library integration

Image, cover, video, audio, file, and post-featured-image blocks all
route media picking through Gutenberg's `editor.MediaUpload` hook.

The package ships two implementations:

- **Stub** (`media-bridge/media-upload-stub.tsx`) — registered by
  default. Shows a placeholder picker that does nothing. Useful in the
  dev sandbox and in apps that don't have a media library yet.
- **Real bridge** — host apps that ship `artisanpack-ui/media-library`
  (or any compatible store) call
  `registerArtisanpackMediaBridge(MediaModal, uploadMedia)` from
  `@artisanpack-ui/visual-editor` before mounting the editor. The bridge
  swaps the stub for a `MediaModal` that opens the host's picker UI.

```ts
import { registerArtisanpackMediaBridge } from '@artisanpack-ui/visual-editor';
import { MediaModal, uploadMedia } from '@artisanpack-ui/media-library';

registerArtisanpackMediaBridge(MediaModal, uploadMedia);
```

Selected media is fetched server-side from
`GET /visual-editor/api/attachments/{id}` and returned in the
WordPress-shape `Attachment` object Gutenberg blocks expect (`id`,
`source_url`, `alt_text`, `media_details`).

The active bridge slug is recorded in
`config('artisanpack.visual-editor.media.bridge')` (default
`'artisanpack-ui/media-library'`); the server-side adapter that
converts host media records into WP-shape attachments is set in
`config('artisanpack.visual-editor.media.adapter')` (default:
`GutenbergAttachmentAdapter`). Swap the adapter to integrate with a
different media store.

---

## 6. Document settings panel

Inside the inspector sidebar, the document panel exposes:

| Field | Source | `supports` key |
|-------|--------|----------------|
| Title | `:initialTitle` | always shown |
| Slug | `:initialSlug` | always shown |
| Status | `:initialStatus` | always shown |
| Excerpt | `:initialExcerpt` | `excerpt` |
| Featured image | `:initialFeaturedImage` | `featuredImage` |
| Author | `:initialAuthorId` + `:authorOptions` | always shown |
| Comments | `:initialCommentsOpen` | `comments` |

Pass `supports = ['excerpt' => true, 'featuredImage' => false, 'comments' => true]`
to opt fields in or out — see [Blade component reference](blade-component.md).

Document-field changes write back to the model via the same content
endpoint, batched with block changes.

---

## 7. Autosave + save flow

1. Author edits a block.
2. After ~1s of idle, the editor dispatches `ve:editor:change` on
   `window`.
3. The autosave request fires: `PUT /visual-editor/api/{resource}/{id}/content`.
4. On 200, the editor dispatches `ve:editor:autosave` with `{ resource, id, blocks, updatedAt }`.
5. ⌘S or topbar Save bypasses the debounce and dispatches `ve:editor:save`
   with the same payload.

See [Livewire](livewire.md) for the pattern of bridging these events back
to a server-rendered shell.

---

## 8. Keyboard shortcuts

Standard Gutenberg shortcuts: `⌘B`/`⌘I`/`⌘U`, `⌘K` (link),
`⌘⇧K` (unlink), `⌘Z`/`⌘⇧Z` (undo/redo), `⌘⌥M` (insert media),
`⌘S` (save), `/` (slash inserter), `??` (open shortcut help modal).

The shortcut help modal is restyled with `@artisanpack-ui/react`'s
`Modal` so it picks up the host theme.

---

## 9. Embedding

The post editor is the same surface whether mounted via Blade, Livewire,
or Inertia. Embedding recipes:

- [Livewire](livewire.md)
- [Inertia](inertia.md)

---

## See also

- [Blade component reference](blade-component.md)
- [Custom blocks](custom-blocks.md)
- [Renderers](renderers.md) — get saved content back onto the public site
- [Theming](theming.md) — restyle editor chrome
