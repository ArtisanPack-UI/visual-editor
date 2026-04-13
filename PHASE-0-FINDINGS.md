# Phase 0 Findings — React Editor Architecture Spike

**Status:** ✅ **PROCEED** to Phase 1.

**Spike location:** `resources/js/editor-spike/`
**Tracking issues:** #236 (overview), #243–#251 (sub-issues)
**Test suite:** 38 tests across 8 files — all passing (`npm test`).

## Decision

The Phase 0 spike successfully proves the React + Tiptap architecture can
solve the query-loop problem that blocked the Alpine implementation (#177).
All five decision-gate criteria pass, and nothing encountered during the
spike invalidates the migration plan. Phase 1 is cleared to begin.

## Acceptance Criteria — Results

All five criteria from #250 are exercised by `queryLoop.test.tsx` and verified
in the running spike app.

| # | Criterion | Result | Evidence |
|---|---|---|---|
| 1 | Query loop renders 5 mock posts | ✅ | `renders one post article per mock post returned by the query` |
| 2 | Each `post-title` shows the correct per-post title (not all the same) | ✅ | `renders the correct per-post title via BlockContext` — asserts all 5 distinct titles |
| 3 | First post is active by default; its paragraph is editable in Tiptap | ✅ | `marks the first post as active by default` + `renders a live Tiptap editor inside the active post only` |
| 4 | Clicking an inactive post activates it; the previously-active becomes a preview | ✅ | `activates a previously-inactive post when its Edit button is clicked` |
| 5 | Editing the active post's paragraph propagates to every inactive preview (shared template) | ✅ | `propagates edits in the active post to every inactive preview` |

## What Worked

- **`BlockContextProvider`** — Plain React Context, ~30 lines, no escape
  hatches. The `post-title` block reads `postId`/`postType` directly and
  renders the right title per post. Gutenberg's pattern maps cleanly onto
  React Context with none of the Alpine workarounds we previously needed.
- **`useInnerBlocksProps` + `RenderBlock`** — The recursive
  "render block → edit → inner blocks" loop is straightforward in React
  and does not need any of Gutenberg's selector/store machinery for the
  spike's needs.
- **`useBlockPreview`** — Rendering the same block tree inside a
  `ReadOnlyProvider` + `inert` wrapper gives us non-interactive previews
  for free. No separate renderer, no duplicated block definitions.
- **Tiptap integration** — `@tiptap/react` + `starter-kit` was enough for
  a working paragraph edit experience. `useTiptap` is ~40 lines. The
  `editable` flag toggles cleanly between edit and read-only states so
  the same component works inside `InnerBlocks` and `BlockPreview`.
- **Vite + React 19 + TypeScript scaffold** — Set up in a day, type-safe,
  fast HMR, no friction. The existing package build pipeline did not need
  any structural changes to host the spike.

## Surprises & Things to Flag for Phase 1

### 1. Shared-mutable-state + `__bumpTemplate` for preview sync

Criterion 5 ("edits propagate to inactive previews") currently works via a
deliberately simple, slightly hacky pattern:

- All posts in the query loop share the **same** `block.innerBlocks`
  reference from the mock tree.
- `ParagraphEdit` mutates `block.attributes.content` in place on every
  Tiptap `onUpdate`.
- `QueryLoopEdit` injects a `__bumpTemplate` callback through
  `BlockContextProvider`; `ParagraphEdit` calls it after every edit,
  which bumps a `templateVersion` state on the query loop and forces all
  `BlockPreview`s to remount (`key={templateVersion}`).

This is fine for the spike — it proves the visual/UX outcome — but
**Phase 1 must not ship this**. Real implementations need either:

- A proper store (Zustand/Jotai/Redux Toolkit) holding block state, with
  selectors driving re-renders, **or**
- An immutable-update pattern where the block tree is replaced top-down
  and React reconciles previews normally.

**Action for Phase 1 (#237 breakdown):** Pick the store approach early.
Do not port the `__bumpTemplate` hack into production code.

### 2. `useInnerBlocksProps` is simpler than expected

Gutenberg's real `useInnerBlocksProps` is substantially more complex
(selection, drag handles, merge behavior, append/remove APIs, block lists,
etc.). For the spike we only needed the "render children from the tree"
piece. Phase 1 will need to grow this primitive significantly when we add:

- Block insertion/removal
- Selection management
- Drag-and-drop (dnd-kit lands in Phase 1, not Phase 0)
- Keyboard merge/split across block boundaries

None of this is blocked — just don't assume the spike's 80-line version is
close to the final shape.

### 3. DnD (dnd-kit) was not in the spike

The spike deliberately did not integrate dnd-kit, so we have **no empirical
signal yet** on Tiptap + dnd-kit interactions. Known risks to validate
early in Phase 1:

- Tiptap's ProseMirror DOM ownership vs. dnd-kit's DOM reordering
- Drag handles inside contenteditable regions
- Sensor conflicts (drag-to-select text vs. drag-to-move block)

**Action for Phase 1:** Build the dnd-kit integration behind a
throwaway test block **before** migrating real blocks, so we can isolate
interaction issues without contaminating production block code.

### 4. Tiptap dependency footprint

We shipped `@tiptap/react` + `@tiptap/starter-kit` + `@tiptap/extension-link`
for the spike. `starter-kit` is a meta-package that drags in ~15 extensions
we may not need (or may want to replace with our own for custom behavior —
e.g., our own paragraph node for block-aware splits).

**Action for Phase 1:** Audit `starter-kit` and decide per-extension what
to keep, replace, or drop. Plan for a noticeable bundle-size conversation
once we add inspector/sidebar and real production tooling.

### 5. `BlockPreview` remount cost

Every `__bumpTemplate` call remounts all preview trees (because of
`key={templateVersion}`). With 4 previews × ~2 blocks each this is
imperceptible, but it is a **linear remount-on-every-keystroke**. The
real store-based approach from finding #1 also solves this automatically
(React reconciles instead of remounting), so it's not a separate Phase 1
issue — but it's worth noting that we have no perf budget headroom from
the spike's approach.

### 6. `useBlockPreview` needed `inert` + `pointer-events: none`

`inert` alone was not enough to prevent Tiptap from initializing editors
inside previews during an early iteration. The working solution was to
render paragraph's read-only path as a plain
`dangerouslySetInnerHTML` `<div>` (not a Tiptap instance at all) when
`useReadOnly()` returns `true`. Phase 1 should keep this split — do not
try to "reuse" a disabled Tiptap instance for previews.

### 7. Nothing else surprised us

No unexpected `@wordpress/*` coupling. No Gutenberg pattern that had to be
rewritten from scratch against our types. No forced upgrade of React,
TypeScript, or Vite. No hidden dependency on browser APIs the spike
environment didn't support (jsdom ran all tests).

## Spike Cleanup

Per #251, the spike code stays in place as a reference:

- `resources/js/editor-spike/` — keep
- Spike tests (`__tests__/`) — keep

**Phase 1 will delete the spike directory and the `editor-spike` entry
point as part of landing the real implementation.** Until then it serves
as the canonical reference for the primitives we're porting into
production code.

## Recommended Phase 1 Sequencing

Informed by the findings above, #237 should front-load the risks the spike
did not cover:

1. Pick and wire up a block-tree store (kills finding #1 and #5).
2. Integrate dnd-kit behind a throwaway test block (kills finding #3).
3. Audit Tiptap extensions + decide the paragraph node strategy (finding #4).
4. Grow `useInnerBlocksProps` toward its production shape (finding #2).
5. Only then begin porting real blocks.

The goal is to hit the biggest unknowns before investing in block-level
migration work.
