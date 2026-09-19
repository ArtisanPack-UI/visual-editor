# #808 — Navigation block parity: WIP notes

Branch: `feature/808-navigation-inline-canvas-list-view-parity` (off `release/1.12`)

**Status: NOT READY FOR PR.** Committed to preserve progress; the write path still doesn't work end-to-end.

## What this branch actually does (works)

1. **`editor/register-forked-cores.ts`** (new)
   Registers `core/navigation` and its parent-locked inner-block family (`navigation-link`, `navigation-submenu`, `home-link`, `page-list`, `page-list-item`, `loginout`) at boot. Wired into `blocks/index.ts` before `discoverAndRegisterCustomBlocks()`. Also installs the `registerForkedBlockCutoverFilter()` so the core blocks land with `inserter: false`.

   **Why**: I7 (#415) removed `registerCoreBlocks()`. The fork architecture (`_shared/forked-entity-edit.tsx`) still assumed those core blocks were registered so it could delegate to their edit components. `getBlockType('core/navigation')` returned `undefined`, so the fork fell back to an empty `<div>` wrapper — the actual reason the nav block canvas was blank in the site editor.

2. **`editor/inspector-sidebar.tsx`**
   New third tab `List View`, dynamically shown when the selected block fills the `InspectorControlsListView` slot (`useSlotFills('InspectorControlsListView')`). Renders `<InspectorControls.Slot group="list" />`. Auto-falls back to Block tab when fills disappear. Arrow-key nav walks all visible tabs.

3. **`vendor/core-data-shim.ts`** — several small fixes
   - `useEntityBlockEditor` now reads via `getEditedEntityRecord` and returns real setters that stage `{blocks, content: {blocks}}` top-level and schedule a debounced `saveEditedEntityRecord` (500ms trailing).
   - `decorateBlock` preserves existing `clientId` when set so setter-supplied blocks don't churn identity across reads (upstream `use-block-sync.mjs` needs reference equality on `pendingChangesRef.outgoing.includes(controlledBlocks)`).
   - `getNavigationFallbackId` returns the first cached published `wp_navigation` id (was `undefined`).
   - `try/catch` guard around `register(store)` so a duplicate registration (from the split-brain scenario below) doesn't leave the second instance half-initialized.
   - `__flushPendingEntityRecordSaves()` test hook exported.

4. **`vitest.config.ts`**
   Narrowed `**/vendor/**` exclude to `packages/**/vendor/**`. The old glob was silently excluding `resources/js/visual-editor/vendor/__tests__/core-data-shim.test.tsx` — 76 shim tests that appeared to pass but never actually ran. All now run and pass.

5. **Tests**
   - `vendor/__tests__/core-data-shim.test.tsx` — 3 new tests: setter stages edits + reflects in reader, debounced PUT hits `/menus/{id}`, `getNavigationFallbackId` resolves.
   - `editor/__tests__/inspector-sidebar.test.tsx` — 2 new tests for the List View tab + updated `@wordpress/*` mocks.
   - `site-editor/__tests__/entity-editor.test.tsx` — added `__experimentalUseSlotFills` to the `@wordpress/components` mock.

All 350 test files / 3276+ tests pass.

## What DOESN'T work yet

**Clicking "Add menu item"** (either in the canvas or the List View sidebar) does absolutely nothing. No inserter popover opens. No console error. No block gets inserted. The path through upstream's `NavigationInnerBlocks` → `useInnerBlocksProps` → `ButtonBlockAppender` → `Inserter` never completes.

## The likely root cause chain

Nothing conclusive — this is where the investigation stopped.

1. **`@wordpress/core-data` is registered twice.** The `Store "core" is already registered` error fires on page load and persists no matter what I tried. Two module instances of the shim exist:
   - One loaded via the `@wordpress/core-data` Vite alias (used by upstream `@wordpress/*` peer packages)
   - One loaded via the relative path `../../vendor/core-data-shim` (used by first-party code)
   - Each calls `register(store)`; the second throws.

2. **Attempted fixes for the dedup that did NOT work:**
   - `optimizeDeps.exclude: ['@wordpress/core-data']`
   - Custom `optimizeDeps.esbuildOptions.plugins` esbuild resolve plugin that rewrites the module ID to the shim's path
   - Both were reverted from the dev app's `vite.config.js` at end of session; the dev app config is clean.

3. **Site-editor iframe boundary.** `BlockCanvas` renders in an iframe. My `document.addEventListener('click', …)` diagnostics attached to the top document never saw canvas clicks — they don't cross the iframe boundary. This means whatever's happening on click, we can't see it from the outer document. (A useful diagnostic in a future session: attach the listener from *inside* the iframe via a `blocks.registerBlockType` filter that wraps children, or use `document.querySelector('iframe').contentDocument`.)

## Hypotheses worth testing tomorrow

Each of these could independently be the reason "Add menu item" is a dead click. Pick the cheapest first.

### H1 — Wrong store bound to onChange
Upstream's `useEntityBlockEditor` (real `@wordpress/core-data`, loaded via Vite pre-bundle) binds `onChange` to `dispatch('core').editEntityRecord`. Because there's one `wp-data` singleton but two modules with different action creator identities, upstream might be dispatching an action shape that the registered store's reducer doesn't recognize. Confirm by:
- Log `wp.data.select('core')` at boot from BOTH the shim's ambient scope AND from a first-party component that imports the shim directly — are they the same reference? If not, we have two `wp-data` core stores registered under different keys somehow.

### H2 — `useInnerBlocksProps` not treating the parent as controlled
`use-block-sync.mjs` calls `setHasControlledInnerBlocks(clientId, true)` in `setControlledBlocks()`. That only runs if the `useEffect` at line 146 fires with a non-outgoing `controlledBlocks` reference. If the initial value is empty on first render AND our reader later swaps to a decorated array, the sync might mis-fire and never mark the parent controlled. `insertBlock` on an uncontrolled parent in a locked-tree context can silently no-op.
- Log `select(blockEditorStore).areInnerBlocksControlled(clientId)` for the nav block's clientId after render. If false, we know that's the fault line.

### H3 — Block-editing-mode
Upstream reads `useBlockEditingMode()` at `edit/index.mjs:291`. If the site-editor's canvas ends up in `contentOnly` or `disabled` mode for a template-part-wrapped nav block, inserts get silently blocked.
- Log the value returned by `useBlockEditingMode()` inside the fork. If it's not `default`, that's the issue.

### H4 — The dedup fix requires publishing the shim as a real `node_modules` package
The cleanest way to guarantee one module instance in dev is to install the shim under `node_modules/@wordpress/core-data` (via a package.json `override` or a fake package). Vite would then dedupe naturally with no alias needed.

### H5 — First-party rewrite that survives block validation
My earlier first-party edit (rewritten `blocks/navigation/edit.tsx` bypassing upstream delegation) worked mechanically but produced `Block contains unexpected or invalid content` errors on the existing saved content. Root cause never diagnosed — likely because `save()` returns `undefined` when `ref` is set (server-rendered), and my `<nav>` wrapper didn't match Gutenberg's parse expectations for that block-comment shape. To make this path viable: either accept that the fork keeps upstream's chrome and only override the writes at the shim layer (H1), OR fully bypass upstream and adjust `save.tsx` accordingly.

## Files touched in this branch

```
modified:   resources/js/visual-editor/blocks/index.ts
modified:   resources/js/visual-editor/editor/__tests__/inspector-sidebar.test.tsx
modified:   resources/js/visual-editor/editor/inspector-sidebar.tsx
modified:   resources/js/visual-editor/site-editor/__tests__/entity-editor.test.tsx
modified:   resources/js/visual-editor/vendor/__tests__/core-data-shim.test.tsx
modified:   resources/js/visual-editor/vendor/core-data-shim.ts
modified:   vitest.config.ts
added:      resources/js/visual-editor/editor/register-forked-cores.ts
added:      docs/wip/808-navigation-parity-notes.md   (this file)
```

The `blocks/navigation/edit.tsx` and `blocks/_shared/forked-entity-edit.tsx` were touched during exploration but reverted to their pre-branch state before commit. Same for the dev app's `vite.config.js`.
