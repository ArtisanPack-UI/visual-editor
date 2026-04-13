# Visual Editor: React Migration

> **Status:** Plan locked 2026-04-12. Phase 0 landed — gate passed. Phase 1 next. Tracking issues #236–#242.

## What's Happening

The visual editor's entire client-side editor (canvas **and** shell) is being migrated from Alpine.js to React + TypeScript. The PHP backend, block definitions, helpers, services, and server-side rendering all stay as-is. Only the editor JS is changing.

## Why

The query loop block (#177) exposed a fundamental architectural limitation in the Alpine approach. The query loop needs to:

1. Render the **same block template** (post-title, post-excerpt, featured-image, etc.) **for each post** in a query, with different data per post
2. Let users **click any post** to make it the active/editable one
3. Show **full block editing** (toolbars, inserters, drag/drop, Enter key) on the active post
4. Show **read-only previews** on inactive posts
5. **Editing blocks in any post updates the shared template** — changes apply to all posts

WordPress/Gutenberg achieves this with three React-specific capabilities:

- **`BlockContextProvider`** — React Context that injects `{postId, postType}` into every descendant block. A `post-title` block automatically shows the right title because it reads `postId` from context. Alpine has no equivalent to scoped context injection.
- **`useBlockPreview`** — Creates a complete but non-interactive rendering of blocks in an isolated editor provider. Used for the inactive post previews.
- **`useInnerBlocksProps`** — Renders block definitions as fully editable inner blocks with toolbars, inserters, and all editing features.

We tried multiple approaches with Alpine (custom JS renderers, `renderChild` callbacks, `renderInnerBlocks` with custom wrappers, event delegation for selection) but each fix created new problems: slow performance, blocks adding to wrong parents, missing toolbars, broken Enter key behavior, and "Start writing..." placeholders instead of post data. The string-concatenation approach in JS renderers fundamentally can't replicate React's component model.

## Locked Decisions

| Area | Decision |
|---|---|
| Editor framework | **React 19 + TypeScript**, always |
| Block editor primitives | Built **in-house, patterned on Gutenberg, ZERO `@wordpress/*` deps** |
| Rich text | **Tiptap** (ProseMirror under the hood) |
| Drag and drop | **dnd-kit** |
| State | **Zustand** + React Context for per-post block context |
| Persistence | **REST API** at `/visual-editor/api/*` — framework-agnostic |
| Block definitions | `block.json` is single source of truth; JS `edit.tsx` imports it via Vite |
| Editor inspector UI | Built from `artisanpack-ui/react` |
| Admin host | **One** React editor; Livewire/Vue/React wrappers are thin mount-points |
| Front-end runtime | Static blocks → server-rendered HTML. Interactive blocks → 3 sibling wrapper packages |
| Distribution | Pre-built `dist/` bundle **and** source |
| Testing | Vitest (unit) + Playwright (e2e) + Pest (PHP) |

## What Stays From the Alpine Era

The PHP layer is intact:

- **Block classes** (`src/Blocks/`) — `BaseBlock`, `DynamicBlock`, all block definitions
- **`block.json` files** — attributes, supports, metadata (the JS↔PHP bridge)
- **Server-side rendering** (`views/save.blade.php`) — frontend output
- **`ContentResolver`** — filter hook-based data resolution
- **Helper functions** (`src/helpers.php`) — `veGetQueryResults`, `veGetQueryTitle`, etc.
- **`BlockRegistry`, `BlockDiscoveryService`** — block registration and discovery
- **All Pest tests**
- **Translation strings** — `resources/lang/`
- **All phase 7 PHP work**: templates, global styles, query loop helpers, content/post/comment blocks, post-meta blocks, post-navigation, theme.json, style import/export

## What Gets Deleted

All Alpine canvas / store / shell files:

- `resources/views/components/_editor-canvas*`
- `resources/views/components/_block-toolbar-controls.blade.php`
- `resources/views/packages/visual-editor/editor-shell.blade.php`
- `resources/js/visual-editor/stores/{editor,selection,blockRenderers,shortcuts,announcer}.js`
- All `br.register()` JS renderers
- The `enhancement/177-build-query-loop-blocks` Alpine canvas renderers (PHP from that branch is salvaged)

The two open Alpine bugs (drag-and-drop reordering, button block focus/placeholder) become resolved-by-deletion.

## New Sibling Packages (Phase 5)

Three new packages launched simultaneously at `~/Desktop/ArtisanPack UI Packages/`:

1. **`artisanpack-ui/visual-editor-livewire`** — Volt admin wrapper + Livewire front-end runtimes for interactive blocks
2. **`artisanpack-ui/visual-editor-vue`** — Vue 3 admin wrapper + Vue front-end runtimes
3. **`artisanpack-ui/visual-editor-react`** — React admin wrapper + React front-end runtimes

Each wrapper ships front-end implementations of the same interactive blocks (carousel, accordion, tabs, modal, popover, query-loop-with-filters), all reading the same JSON config. Consumers install only the wrapper for their host framework.

**Custom block authoring rule:** developers ship a React `edit.tsx` for the editor (always React) and pick **one** front-end framework matching their host app.

## Phase Plan

| Phase | Goal | Issue | Estimate |
|---|---|---|---|
| **0** | Architecture spike — prove React query loop works | [#236](https://github.com/ArtisanPack-UI/visual-editor/issues/236) | ~1 week |
| **1** | Editor foundation: Vite, Zustand, Tiptap, REST, paragraphs/headings | [#237](https://github.com/ArtisanPack-UI/visual-editor/issues/237) | ~3-4 weeks |
| **2** | Block parity — port all static blocks to React | [#238](https://github.com/ArtisanPack-UI/visual-editor/issues/238) | ~4-6 weeks |
| **3** | Query loop and dynamic blocks (production) | [#239](https://github.com/ArtisanPack-UI/visual-editor/issues/239) | ~3 weeks |
| **4** | Inspector sidebar and global styles UI | [#240](https://github.com/ArtisanPack-UI/visual-editor/issues/240) | ~3 weeks |
| **5** | Wrapper packages and interactive front-end blocks | [#241](https://github.com/ArtisanPack-UI/visual-editor/issues/241) | ~3-4 weeks |
| **6** | Polish, tests, accessibility, performance, docs | [#242](https://github.com/ArtisanPack-UI/visual-editor/issues/242) | ~2-3 weeks |

**Total:** ~5-6 months for one developer. Phase 2 parallelizes well.

### Phase 0 is a Hard Decision Gate

If the spike can't prove the query loop works in React with mock data, the migration **stops** to rethink the architecture. Don't sink Phase 1+ work into a flawed approach.

## Reference Material

### Gutenberg source (downloaded, study but do not import)
- `~/Downloads/gutenberg-trunk/packages/block-library/src/post-template/edit.js` — query loop editing
- `~/Downloads/gutenberg-trunk/packages/block-library/src/query/edit/query-content.js` — query block editor
- `~/Downloads/gutenberg-trunk/packages/block-editor/src/components/block-context/index.js` — `BlockContextProvider`
- `~/Downloads/gutenberg-trunk/packages/block-editor/src/components/block-preview/index.js` — `useBlockPreview`
- `~/Downloads/gutenberg-trunk/packages/block-editor/src/components/inner-blocks/index.js` — `useInnerBlocksProps`

### Key patterns from Gutenberg's query loop

**Active/inactive post switching:**

```jsx
// For each post in query results:
<BlockContextProvider value={{ postId: post.id, postType: post.type }}>
  {isActive ? <PostTemplateInnerBlocks /> : null}
  <MemoizedPostTemplateBlockPreview
    blocks={blocks}
    isHidden={isActive}
    onClick={() => setActivePostId(post.id)}
  />
</BlockContextProvider>
```

Both an editable version and a preview are rendered for each post. The active post shows the editable version (hidden preview), inactive posts show the preview (no editable version rendered). Clicking swaps them instantly because both are pre-rendered.

**Context consumption by blocks:**

```json
// post-title/block.json
{ "usesContext": ["postId", "postType", "queryId"] }
```

```jsx
// post-title/edit.tsx
function PostTitleEdit({ context: { postType, postId } }) {
  const [title] = useEntityProp('postType', postType, 'title', postId);
  return <TagName>{title}</TagName>;
}
```

The block declares what context it needs, and the system injects it automatically. The same block definition works in a query loop, on a single post page, or in any other context.

## Open Risks

1. **Tiptap + dnd-kit interaction** — drag handles vs contenteditable text selection has known footguns. Prove in Phase 0.
2. **Pre-built bundle React version conflicts** — peer-dep React 19, externals when host provides it.
3. **Wrapper package maintenance cost** — every interactive block × 3 frameworks. Acceptable cost for framework-agnosticism.
4. **`block.json` import from JS** — Vite handles it natively, but creates a JS↔PHP file coupling. Fall back to a manifest build step if it gets ugly.
