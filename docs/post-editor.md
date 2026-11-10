# Post editor

The post editor is the surface authors use to edit a single resource
(post, page, custom CPT). It's mounted via the
[`<x-visual-editor />` Blade component](post-editor/Blade-Component.md) and built on
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
[Global styles](site-editor/Global-Styles.md)). The CSS is fetched from
`GET /visual-editor/api/global-styles/lookup` → `GET /global-styles/{id}`
on boot and injected into the iframe via the React renderer's
`<GlobalStyles>` component.

Block selection, drag-and-drop, and keyboard shortcuts come from
`@wordpress/block-editor` unchanged.

*Since v1.21.0 (#1102).* Clicking the empty area below the content adds an
empty paragraph and puts the caret in it, or moves the caret into an empty
paragraph that's already last, so repeated clicks never stack them. Nothing
is added where the content is template-locked or doesn't allow paragraphs.
Blocks and files dragged onto that area land after the last block.

*Since v1.21.0 (#1109).* Undo and redo step through one edit at a time. A
run of typing is its own step, ended by a structural change (splitting,
inserting, moving or removing a block, or a toolbar or sidebar change) or by
typing in another block, and undo and redo put the caret back where it was.

The topbar's **View with template** switch composes the
resolved template's header and footer around the canvas as a read-only
preview, so authors can judge their content in context without leaving
the editor. Template chrome is edited in the site editor, reached from
the composed canvas's **Edit template ↗** deep link. See
[Composed view](post-editor/Composed-View.md).

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
their `edit.tsx` — see [Custom blocks §5](blocks/Custom-Blocks.md).

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
[Patterns](site-editor/Patterns.md).

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
to opt fields in or out — see [Blade component reference](post-editor/Blade-Component.md).

Document-field changes write back to the model via the same content
endpoint, batched with block changes.

*Since v1.15.3 (#992)*, Post Title, Post Excerpt and Post Content blocks
in the canvas show the entry being edited, for posts, pages and custom
content types. Title and excerpt are editable inline and stay in sync
with the Title and Excerpt fields above; Post Content shows a read-only
preview of the canvas. For custom content types without
`onMetadataChange`, title and excerpt are saved through
`PUT /visual-editor/api/{resource}/{id}/entry` (see
[Content model](content-model.md#2-the-resource-map)).

---

## 7. Autosave + save flow

1. Author edits a block.
2. After ~1s of idle, the editor dispatches `ve:editor:change` on
   `window`.
3. The autosave request fires: `PUT /visual-editor/api/{resource}/{id}/content`.
4. On 200, the editor dispatches `ve:editor:autosave` with `{ resource, id, blocks, updatedAt }`.
5. ⌘S or topbar Save bypasses the debounce and dispatches `ve:editor:save`
   with the same payload.

See [Livewire integration](post-editor/Livewire-Integration.md) for the pattern of bridging these events back
to a server-rendered shell.

---

## 8. Keyboard shortcuts

Standard Gutenberg shortcuts: `⌘B`/`⌘I`/`⌘U`, `⌘K` (link),
`⌘⇧K` (unlink), `⌘Z`/`⌘⇧Z` (undo/redo), `⌘⌥M` (insert media),
`⌘S` (save), `/` (slash inserter), `??` (open shortcut help modal).

The shortcut help modal is restyled with `@artisanpack-ui/react`'s
`Modal` so it picks up the host theme.

### Leaving a block with Enter

*Since v1.21.0 (#1101).*

| Block | Key | Result |
|-------|-----|--------|
| List | Enter on an empty item | A nested item moves out one level. A top-level item ends the list and continues in a paragraph below, splitting the list if the item was in the middle. |
| List | Tab or Space at the start of an item | Nests the item under the one above. The toolbar's **Indent** button does the same. |
| List | Shift+Tab at the start of an item | Moves the item out one level. The toolbar's **Outdent** button does the same. |
| Code, Preformatted, Poetry | Enter at the end of a blank last line | Removes that blank line and continues in a paragraph below. Shift+Enter always adds a newline. |
| Quote | Enter in an empty last paragraph | Continues in a paragraph below the quote. |
| Pullquote | Enter at the end of the text or citation | Continues in a paragraph below. Shift+Enter adds a line break. |

Where a paragraph can't be added after the block, for example inside a
container that doesn't allow one, Enter keeps its usual behavior.

---

## 9. Embedding

The post editor is the same surface whether mounted via Blade, Livewire,
or Inertia. Embedding recipes:

- [Livewire integration](post-editor/Livewire-Integration.md)
- [Inertia integration](post-editor/Inertia-Integration.md)

---

## 10. Pasting content

*Since v1.20.0 (#1045, #1065, #1066).*

**Markdown from anywhere.** Markdown pasted as plain text, or copied from a code editor, a notes app or Google Docs (which wrap it in their own HTML), becomes real blocks: headings, lists, links, tables, Quote blocks for blockquotes, and Code blocks for fences. A fence's language, a `language-*` class or a rendered GitHub code block fills in the Code block's **Language** setting. Detection needs clear Markdown signals, so a stray `*` does nothing. Text that holds raw HTML or a script link outside a code fence is pasted literally, and in Chromium browsers code that VS Code labels as another language (Python, YAML, …) stays as it is. Ordinary text and rich paste are unchanged.

**Images from other sites.** When a paste creates Image blocks whose URLs point at another site, a snackbar says how many there are and offers **Import to media library**. Nothing downloads until the author picks it. Each image is then fetched by the server and added to the media library, and the block switches to the local copy, keeping its alt text. The editor announces when the import starts and when it finishes, and an image that can't be imported keeps its external URL.

The offer only appears when importing is possible:

- `artisanpack-ui/media-library` is installed, and
- the author passes its `create` policy for media.

The server refuses anything but HTTP(S) on the default ports, URLs with credentials, and hosts with an internal or otherwise non-public address, checked again at every redirect (three at most). It downloads only raster image types up to `media_import.max_size_kb`, typed from the file's bytes, never asks for a compressed response, and allows 30 imports a minute per user. See [`media_import`](Configuration.md#media_import).

**Notices.** Snackbars such as the import offer sit in a labelled *Editor notifications* region after the editor. A keyboard user who pastes in the canvas reaches the **Import to media library** action by tabbing forward from the inspector.

---

## See also

- [Blade component reference](post-editor/Blade-Component.md)
- [Composed view](post-editor/Composed-View.md) — edit content inside its resolved template
- [Custom blocks](blocks/Custom-Blocks.md)
- [Renderers](renderers.md) — get saved content back onto the public site
- [Theming](post-editor/Theming.md) — restyle editor chrome
