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

### H1 — Two shim instances at runtime (CONFIRMED 2026-09-20)

Diagnostic in `vendor/core-data-shim.ts` (a random `__AP_SHIM_INSTANCE_ID__` + module-init `console.log` + `window.__apShimInstances` array) plus a first-party identity probe at `editor/__h1-shim-identity-probe.ts`, wired into `blocks/index.ts`. After clearing `node_modules/.vite` and restarting the dev app, the console showed:

- Two `[AP #808 H1] core-data-shim evaluated` lines with **different** instance IDs:
  1. `08kmfzvv` served from `@fs/…/visual-editor/resources/js/visual-editor/vendor/core-data-shim.ts` (the alias-resolved source, the one first-party code sees).
  2. `vtf6whmt` served from `node_modules/.vite/deps/chunk-N2N46R4O.js` — a pre-bundled Vite dep chunk. Vite's esbuild pre-bundler followed the `@wordpress/core-data` alias INSIDE the pre-bundle pass, inlining the shim source verbatim into whatever `@wordpress/block-editor`/etc. pre-bundle chunk consumed it.
- **Both `register(store)` outcomes were `fresh`.** With a shared `@wordpress/data` singleton, the second would have thrown "already registered" and hit the shim's `duplicate` guard. Both being `fresh` proves **`@wordpress/data` is ALSO duplicated** — each shim instance registers into its own private wp-data registry. Every store (`core`, `core/block-editor`, `core/blocks`, notices, …) is doubled.

The first-party identity probe (`import * as ViaAlias from '@wordpress/core-data'` vs `import * as ViaRelative from '../vendor/core-data-shim'`) shows both first-party imports resolve to instance `08kmfzvv`. So upstream Gutenberg's `useEntityBlockEditor` runs against instance B's registry (`vtf6whmt`), while first-party fork code and the inspector sidebar's List View run against instance A's (`08kmfzvv`). Reads and writes never cross.

Note: the original `Store "core" is already registered` console error users saw must be from a **different** store (probably one registered by `@wordpress/blocks` or similar) landing in a shared `window.wp.data` bridge — not from our shim's `core` registration, since with duplicated `@wordpress/data` each shim's core registration succeeds in its own private registry.

### H1a — Fix attempts

Tried `optimizeDeps.exclude: ['@wordpress/data', '@wordpress/core-data', … full WP family]` — broke the app: Vite's source pipeline flooded with hundreds of on-demand transform requests, network aborted (`is-plain-object.mjs` / `formatLong.js` failed with "network connection was lost"). Reverted.

Narrowed to `optimizeDeps.exclude: ['@wordpress/data', '@wordpress/core-data']` — broke differently: pre-bundled consumers `import { create }` from `@wordpress/data` failed with `Importing binding name 'create' is not found`. Vite's source-transform interop shape for the CJS-ish wp-data build didn't match the shape esbuild's pre-bundle expected. Reverted.

Both attempts documented for pattern-match value; do NOT retry either without additional shims in place.

### H1-next — Path forward (see H4 for the real fix)

The clean fix is to make the shim resolve as a real `node_modules` package so both the pre-bundler and source pipeline pick the same on-disk file through node module resolution, not through the Vite alias (which the pre-bundler doesn't apply consistently). See H4.

Before doing that, cheaper hypotheses (H2, H3) are worth confirming — the split-brain is structurally proven but we haven't proven it's the DIRECT cause of the dead click. Log `areInnerBlocksControlled(navClientId)` and `useBlockEditingMode()` inside the fork's edit render first.

### H2 — `useInnerBlocksProps` not treating the parent as controlled
`use-block-sync.mjs` calls `setHasControlledInnerBlocks(clientId, true)` in `setControlledBlocks()`. That only runs if the `useEffect` at line 146 fires with a non-outgoing `controlledBlocks` reference. If the initial value is empty on first render AND our reader later swaps to a decorated array, the sync might mis-fire and never mark the parent controlled. `insertBlock` on an uncontrolled parent in a locked-tree context can silently no-op.
- Log `select(blockEditorStore).areInnerBlocksControlled(clientId)` for the nav block's clientId after render. If false, we know that's the fault line.

### H3 — Block-editing-mode
Upstream reads `useBlockEditingMode()` at `edit/index.mjs:291`. If the site-editor's canvas ends up in `contentOnly` or `disabled` mode for a template-part-wrapped nav block, inserts get silently blocked.
- Log the value returned by `useBlockEditingMode()` inside the fork. If it's not `default`, that's the issue.

### H4 — Install the shim as a real `node_modules/@wordpress/core-data` (DONE 2026-09-20)

Implemented as a **two-layer** fix — one layer alone is insufficient:

1. **Node module resolution** (`packages/visual-editor/vendor-shims/core-data/`): a real `package.json` names the shim as `@wordpress/core-data` and points `main` at the shim source. `visual-editor/package.json` now has both a `file:` `dependencies` entry AND an `overrides` entry so every direct + transitive resolution of `@wordpress/core-data` points at one on-disk location. `npm install` in the visual-editor package symlinks `node_modules/@wordpress/core-data → ../../vendor-shims/core-data`.
2. **Vite `resolve.alias` + `optimizeDeps.exclude` combo** (dev app `vite.config.js`): the alias rewrites every runtime `@wordpress/core-data` bare specifier to the shim's source URL from both pre-bundled chunks' transform pass AND first-party imports; the exclude prevents esbuild pre-bundling from inlining a copy of the shim into a pre-bundled chunk. Neither alone works — alias without exclude still inlines a duplicate into the pre-bundle; exclude without alias leaves bare `@wordpress/core-data` specifiers in pre-bundled chunks unresolvable.

Verification: after H4, the H1 probe shows one shim `core-data evaluated` log (one instance ID `9o9q75my`), one `register(store) outcome: fresh`, and the identity probe reports `sameModuleNamespace: true` (was `false` under any partial fix). No `Store "core" is already registered` error.

**Follow-up investigation identified the direct cause** — H1 was necessary for correctness (writes were being staged into a phantom shim instance) but not the direct cause of the dead click. H2 (`areInnerBlocksControlled` flips false → true across renders) and H3 (`blockEditingMode = "default"`) are clean.

Extended H2/H3 probe with `canInsertBlockType` checks + a global click-target logger revealed:
- Clicks DO fire on the appender buttons (canvas `+` = `block-editor-button-block-appender` with `aria-label="Add page"`; list-view `+` = `block-editor-inserter__toggle`).
- `blockListSettings.defaultBlock = core/navigation-link` with `directInsert: true`.
- `canInsertNavLink: false`, `canInsertNavSubmenu: false`, `canInsertPageList: true`.

**Direct cause: `core/navigation-link` and `core/navigation-submenu` have `parent: ['core/navigation-submenu', 'core/navigation']` block-metadata**, but our outer fork is `artisanpack/navigation`. `canInsertBlockType` rejects the insert → the direct-insert click no-ops silently. `core/page-list` has no parent constraint, which is why it's the only one that comes back `true`.

Fix: added `broadenNavChildParent()` in `editor/forked-block-cutover.ts` — a `blocks.registerBlockType` filter that appends `'artisanpack/navigation'` to the `parent` allowlist on `core/navigation-link`, `core/navigation-submenu`, `core/page-list`, `core/home-link`, `core/loginout`. Filter is installed by `registerForkedBlockCutoverFilter()` alongside the existing inserter-suppression filter (so both apply before `initNavigation*` runs).

**Verification 2026-09-20**: after the fix, `canInsertNavLink` / `canInsertNavSubmenu` both return `true`, and clicking the canvas `+` inserts a `core/navigation-link` visibly.

## Remaining follow-ups (NOT #808, split into separate issues)

The core `#808` insert bug is resolved. These remaining UX issues need their own investigation:

1. **Sluggish inserts + focus loss + list-view blank** — investigated 2026-09-20, PARTIAL FIX ATTEMPTED, NOT YET VERIFIED. See "Save-cycle reset investigation" below.
2. **`GET /visual-editor/api/site` 404** — some upstream call fetches the singleton `root/__unstableBase` entity in collection form (no id). The route only accepts `/site/{id}`. Cosmetic here, doesn't block the insert. Fix: mount a `/site` route that returns the singleton, or short-circuit the collection URL in the shim's fetcher for this specific entity.
3. **Diagnostic cleanup for the PR**: remove `[AP #808 …]` console.logs from `edit.tsx` + `core-data-shim.ts`, delete `editor/__h1-shim-identity-probe.ts`, remove the H1 probe wiring in `blocks/index.ts`, keep the H4 config + `broadenNavChildParent` filter + tests.
4. **Add tests** for `broadenNavChildParent` (mirror the shape of `suppressForkedBlockInserter`'s tests).

Files touched by H4:
```
added:    packages/visual-editor/vendor-shims/core-data/package.json
added:    packages/visual-editor/vendor-shims/core-data/index.ts
modified: packages/visual-editor/package.json       (file: dep + overrides)
modified: packages/visual-editor/package-lock.json  (via `npm install`)
modified: packages/visual-editor/vite.config.ts     (removed coreDataShim alias)
modified: artisanpack-ui-dev/vite.config.js         (alias + optimizeDeps.exclude combo)
```
`packages/visual-editor/vitest.config.ts` left alone — the alias there is redundant with the file: install but not harmful.

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

## Save-cycle reset investigation (2026-09-20, still open)

Traced the tree-reset-on-insert via a Boost `browser-logs` dump with `nav-set`, `nav-read (path=X)`, and `nav-diag` probes. Sequence around one insert:

```
22:38:30.546  nav-set incomingLength=5     ← setter fires
22:38:30.547  nav-read path=edited.blocks length=5
22:38:31.555  nav-read path=edited.blocks length=5
22:38:32.438  nav-read path=edited.blocks length=5
22:38:32.442  nav-read path=edited.blocks length=5
22:38:32.447  nav-read path=edited.blocks length=5
22:38:32.448  nav-read path=edited.blocks length=5
22:38:32.448  nav-read path=edited.blocks length=5
22:38:32.551  nav-diag innerBlockCount=0 areInnerBlocksControlled=false selectedBlockClientId=null   ← RESET
22:38:32.866  nav-read path=server-blocks length=5
22:38:32.873  nav-read path=server-blocks length=5
```

**Key observation**: the reset happens at ~22:38:32.551 — BEFORE the reader ever hits `path=server-blocks` (first server-blocks read at 22:38:32.866). During the 100ms window between the last `edited.blocks` read and the reset, no `nav-read` fires and no `nav-set` fires. So the reset isn't caused by the reader returning a new reference. My earlier "reader flips to `raw-parsed` and returns fresh array" theory is DEAD (reader never hits `raw-parsed` — it flips to `server-blocks`, and that happens AFTER the reset).

What most likely triggers the reset in that 100ms window: the debounced `saveEditedEntityRecord` completing, `receiveEntityRecords` running to update the base record + clear edits, and something inside `use-block-sync.mjs` reacting to that transition and calling `resetBlocks` proactively — *without* re-running our reader first. Possibly because `use-block-sync` subscribes to something else (maybe `getEditedEntityRecord` transitions from `hasEdits` to `noEdits`, or the shim's `saveEditedEntityRecord` implementation dispatches a resolver-invalidation that upstream is watching).

### Fix attempted (NOT VERIFIED — HMR wouldn't pick it up)

Added a `lastAuthoritativeBlocks` map + `blocksAreStructurallyEqual` in the shim, and a `rememberAuthoritativeBlocks` call inside the setter. `getDecoratedBlocks` was extended to consult that map on cache-miss and return the setter's array when structural equality holds.

But after multiple hard reloads with a warm dev server, **`[AP #808 nav-decorate]` and `[AP #808 nav-authoritative]` never fired** — the running bundle in the browser is still the version WITHOUT the new logs. Vite dev is not invalidating the shim source file on save. Same failure mode we saw earlier in the session (the notes' H1a mentions the pre-bundle inlining, but with `optimizeDeps.exclude: ['@wordpress/core-data']` set, the shim source itself should be served on demand — evidently it's still coming from a stale served copy somewhere).

### Pickup steps for the next session

1. **Force a completely fresh dev-server state before continuing** — Ctrl-C composer/vite, `rm -rf /Users/jacobmartella/Herd/artisanpack-ui-dev/node_modules/.vite` AND `rm -rf packages/visual-editor/node_modules/.vite`, restart. Then hard-reload and verify `[AP #808 nav-decorate]` fires at boot before touching anything else.
2. **If nav-decorate still doesn't fire**, the shim source served by Vite may be cached at a higher level. Inspect the Network tab for the shim's actual URL and confirm the served bytes contain the new `nav-decorate` string.
3. **Once nav-decorate fires**, run the insert cycle. Because the reset happens before the reader re-runs on server-blocks, the fix in `getDecoratedBlocks` may be too late in the pipeline. If tree still resets, the next probes to add are:
   - Wrap `saveEditedEntityRecord` in the shim to log entry/exit + payload.
   - Log every `receiveEntityRecords` call the shim makes (grep for `receiveEntityRecords` — it's called by our shim after PUT lands).
   - Attempt to see whether upstream `use-block-sync.mjs` calls `resetBlocks` in that 100ms window. That'd require a monkey-patch of `dispatch(blockEditorStore).resetBlocks` at boot to log its callers.
4. **Working hypothesis for tomorrow**: after `saveEditedEntityRecord` clears the edits, `getEditedEntityRecord().blocks` becomes undefined; `use-block-sync` (subscribing via its own `useSelect`) sees the value it previously held (setter's array) become undefined, then a moment later become the newly-decorated server-blocks array. The transient `undefined` step might be what triggers `resetBlocks`. If confirmed: solution is to keep the `blocks` edit alive after save (re-stage it in the save's completion handler), or return the authoritative array from the `edited.blocks` path even when the edits bag has been cleared.

### Files touched during today's session (in addition to what was in the earlier WIP commit)

Uncommitted at end-of-day:

```
modified:   resources/js/visual-editor/vendor/core-data-shim.ts  (rememberAuthoritativeBlocks + blocksAreStructurallyEqual + reader path logs + nav-authoritative + nav-decorate logs; the fix is NOT verified)
```

The changes are worth keeping — even if the fix ends up being wrong or misdirected, the structural-equality memoization is a legitimate improvement to the cache and the diagnostics are what next session will need.
