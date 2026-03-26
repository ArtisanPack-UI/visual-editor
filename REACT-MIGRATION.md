# Visual Editor: React Migration

## What's Happening

The visual editor's canvas (the WYSIWYG block editing surface) is being migrated from Alpine.js to React. The PHP backend, block definitions, helpers, services, and server-side rendering all stay as-is. Only the client-side editor UI is changing.

## Why

The query loop block system (issue #177) exposed a fundamental architectural limitation in the Alpine.js approach. The query loop needs to:

1. Render the **same block template** (post-title, post-excerpt, featured-image, etc.) **for each post** in a query, with different data per post
2. Let users **click any post** to make it the active/editable one
3. Show **full block editing** (toolbars, inserters, drag/drop, Enter key) on the active post
4. Show **read-only previews** on inactive posts
5. **Editing blocks in any post updates the shared template** — changes apply to all posts

WordPress/Gutenberg achieves this with three React-specific capabilities:

- **`BlockContextProvider`** — React Context that injects `{postId, postType}` into every descendant block. A `post-title` block automatically shows the right title because it reads `postId` from context. Alpine.js has no equivalent to scoped context injection.
- **`useBlockPreview`** — Creates a complete but non-interactive rendering of blocks in an isolated editor provider. Used for the inactive post previews.
- **`useInnerBlocksProps`** — Renders block definitions as fully editable inner blocks with toolbars, inserters, and all editing features.

We tried multiple approaches with Alpine.js (custom JS renderers, `renderChild` callbacks, `renderInnerBlocks` with custom wrappers, event delegation for selection) but each fix created new problems: slow performance, blocks adding to wrong parents, missing toolbars, broken Enter key behavior, and "Start writing..." placeholders instead of post data. The string-concatenation approach in JS renderers fundamentally can't replicate React's component model.

## Reference Material

### Gutenberg Source (downloaded)
- `/Users/jacobmartella/Downloads/gutenberg-trunk/packages/block-library/src/post-template/edit.js` — the key file showing how WordPress handles query loop editing
- `/Users/jacobmartella/Downloads/gutenberg-trunk/packages/block-library/src/query/edit/query-content.js` — the query block editor
- `/Users/jacobmartella/Downloads/gutenberg-trunk/packages/block-editor/src/components/block-context/index.js` — BlockContextProvider implementation
- `/Users/jacobmartella/Downloads/gutenberg-trunk/packages/block-editor/src/components/block-preview/index.js` — useBlockPreview hook
- `/Users/jacobmartella/Downloads/gutenberg-trunk/packages/block-editor/src/components/inner-blocks/index.js` — useInnerBlocksProps hook

### Existing Alpine.js Work (reference branch)
- Branch `enhancement/177-build-query-loop-blocks` off `add/phase-seven` — contains the query loop block definitions, tests, helpers, and Alpine.js canvas renderers
- Branch `add/phase-seven` — the phase 7 work (templates, global styles, theming)

### Key Patterns from Gutenberg's Query Loop

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
// post-title/edit.js
function PostTitleEdit({ context: { postType, postId } }) {
  const [title] = useEntityProp('postType', postType, 'title', postId);
  return <TagName>{title}</TagName>;
}
```

The block declares what context it needs, and the system injects it automatically. The same block definition works in a query loop, on a single post page, or in any other context.

## What Stays the Same

Everything on the PHP side carries over:

- **Block classes** (`src/Blocks/`) — `BaseBlock`, `DynamicBlock`, all block definitions
- **block.json files** — attributes, supports, metadata
- **Server-side rendering** (`views/save.blade.php`) — frontend output
- **ContentResolver** — filter hook-based data resolution
- **Helper functions** (`src/helpers.php`) — `veGetQueryResults`, `veGetQueryTitle`, etc.
- **BlockRegistry, BlockDiscoveryService** — block registration and discovery
- **Tests** — all unit tests for block classes
- **Translation strings** — `resources/lang/`

## What Changes

The client-side editor canvas currently built with:
- Alpine.js stores (`editor`, `selection`, `blockRenderers`, `shortcuts`, `announcer`)
- Blade templates with `x-data`, `x-for`, `x-html` directives
- JS renderers registered via `br.register()` that return HTML strings
- Event delegation for selection, insertion, drag/drop

Gets replaced with:
- React components for the canvas, block list, block wrappers
- React Context for block context (post data injection)
- React hooks for inner blocks (`useInnerBlocksProps`), previews (`useBlockPreview`), selection
- A proper component tree where blocks are React components, not HTML strings

## Scope and Approach

This is a canvas-only migration. The inspector sidebar, the Livewire integration for saving/loading, and the PHP block system all remain. The React canvas would:

1. Receive the block tree from the existing Alpine/Livewire state
2. Render blocks as React components
3. Dispatch changes back to Alpine/Livewire for persistence

This hybrid approach (React canvas + Livewire backend) lets us migrate incrementally rather than rewriting everything at once. The React canvas could communicate with Livewire via custom events, similar to how the current Alpine stores dispatch events.

Alternatively, if the scope grows, the entire editor shell (sidebar, toolbar, canvas) could move to React with the backend remaining Laravel/Livewire for data persistence.

## Open Questions

1. **Build tooling** — Vite is already in use. Need to add React/JSX support (likely `@vitejs/plugin-react`).
2. **State bridge** — How does the React canvas communicate with Livewire? Custom events? A shared store?
3. **Incremental migration** — Can we migrate the canvas first while keeping the sidebar in Alpine/Blade? Or does the tight coupling require migrating both?
4. **Block rendering** — Do we build our own block renderer components, or can we leverage/adapt parts of `@wordpress/block-editor`?
5. **Dependencies** — Which `@wordpress/*` packages (if any) can we use? `@wordpress/block-editor` is tightly coupled to the WP ecosystem but contains patterns worth studying.
