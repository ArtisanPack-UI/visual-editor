# #808 — Navigation block parity: resolution notes

Branch: `feature/808-navigation-inline-canvas-list-view-parity` (off `release/1.12`)

**Status: FIXED 2026-09-22.**

## The two independent bugs (both real)

The visible symptom cluster — "click Add menu item, focus jumps, list view blanks, chrome flickers" — turned out to be two overlapping bugs from different layers, each real, each needing its own fix. Diagnosing them together (which we did for two sessions) made it look like one intractable problem.

### Bug 1 — Selection-clear cluster

**Root cause.** Upstream `@wordpress/block-library/navigation-link/edit.mjs` hardcodes the literal string `"core/navigation"` in `getBlockParentsByBlockName(clientId, "core/navigation")` lookups for post-insert selection, LinkControl mount/unmount, prioritised inserter blocks, and several rendering effects. Because the Phase I5 fork (#413) renamed the outer block to `artisanpack/navigation`, those lookups returned an empty array — and upstream then called `selectBlock(undefined)`, which cleared the current block-editor selection. Every navigation UX flow that ended in "select this block" broke.

**Fix.** Reverted the fork. `artisanpack/navigation` is now a **parse-only deprecation stub** whose `edit` immediately `replaceBlock`s itself with `core/navigation` on mount, preserving all fork attributes and any inner blocks Gutenberg parsed. `core/navigation` is registered directly (via `editor/register-forked-cores.ts`) and inserter-visible. Removed `broadenNavChildParent`, `installNavigationSelectionFix`, and the whole path A hack tower.

Yesterday's parent-alias patch (path A) fixed cluster 1 too, and would have kept working — but every future Gutenberg version can add a new hardcoded `"core/navigation"` string the alias doesn't anticipate. Reverting removes that maintenance tax.

### Bug 2 — Flicker + focus loss + blank list view

**Root cause.** Our `@wordpress/core-data` shim's `saveEntityRecord` always fired a post-save invalidation pair, regardless of create vs. update:

```
dispatch.receiveEntityRecords(kind, name, [saved])              // wipes bag.queries for this entity
dispatch.invalidateResolutionForStoreSelector('getEntityRecords') // resets resolver state
```

For updates of records already in the store, that combo made `hasFinishedResolution('getEntityRecords', <wp_navigation query>)` briefly return `false` during the save round-trip. Upstream `useNavigationMenu` reads that via `hasResolvedNavigationMenus`, computes `isLoading = true`, and re-renders the nav block into its loading state — which does NOT render `NavigationInnerBlocks`. React unmounts the whole subtree containing `use-block-sync`; ~500ms later the refetch completes, resolution flips back true, and the subtree remounts. The visible symptom: chrome dies, focused input inside a nav item loses focus, list view goes empty.

This bug had nothing to do with the fork name. It would fire identically for `core/navigation` or `artisanpack/navigation` — same shim, same `wp_navigation` entity path.

**Fix.** In `saveEntityRecord`, differentiate CREATE (`existingId === null`) vs UPDATE.

- **CREATE**: keep the aggressive Keystone #57 behaviour (wipe queries + invalidate resolver). The new record's id isn't in any cached list yet, so consumers need a refetch to surface it.
- **UPDATE**: pass `invalidateQueries: false` to `receiveEntityRecords` and skip the resolver invalidation. The record is already in `bag.items`; every cached query that referenced its id still does; `items` is authoritative for record content, so consumers reading `getEntityRecords(...).map(id => items[id])` see the fresh attributes without a refetch and without a resolution transient.

Regression test in `vendor/__tests__/core-data-shim.test.tsx`: "saveEntityRecord on UPDATE preserves cached list queries + resolver state (#808)". The Keystone #57 test still guards the CREATE path.

## Diagnostic path that led to the fix

The two prior sessions accumulated a lot of speculation about React commit timing, `use-block-sync`'s internal cleanup, and shim reader shape-stability. The diagnostic that actually cracked it was:

1. **Wrap `dispatch('core/block-editor').resetBlocks` / `replaceInnerBlocks` on the SCOPED iframe registry** (via an `editor.BlockEdit` HOC so it runs inside the iframe). Log args + stack trace on every call.
2. The unmount stack showed `commitPassiveUnmountInsideDeletedTreeOnFiber` — React was deleting a subtree, not re-running an effect.
3. **Extended the probe to also wrap `dispatch('core').receiveEntityRecords`, `editEntityRecord`, `invalidateResolutionForStoreSelector`, `invalidateResolution`, `startResolution`, `finishResolution`.** Repro'd once more and read the log window around the click.
4. The critical 53ms window told the whole story:
   ```
   16:15:30.337  receiveEntityRecords kind=postType name=wp_navigation count=1
   16:15:30.338  invalidateResolutionForStoreSelector selector=getEntityRecords
                 stack: core-data-shim.ts:975:19
   16:15:30.391  replaceInnerBlocks len=0     (from commitPassiveUnmountInsideDeletedTreeOnFiber)
   ```
   Our own shim, our own line, our own bug.

Takeaway for future sessions: **when a React subtree unmounts on state change, dispatch-level tracing on the scoped iframe registry is the fastest way to find the causing state transition.** The scoped registry is only accessible from inside the iframe, so patch via `useRegistry()` inside a HOC installed by `editor.BlockEdit` — not from the outer document.

## Side benefits shipped

- **`/visual-editor/api/site` 404 eliminated.** Upstream Gutenberg calls `getEntityRecord('root', '__unstableBase')` with no id; the resolver was building `/site` (no id → 404) and the catch clause synthesized a `_missing` placeholder — the state transition, while not the direct trigger of bug 2, was still noise on every nav-block interaction. Fix: coerce a falsy id to `SITE_ENTITY_ID` at the top of `fetchEntityRecord` for the `root/__unstableBase` singleton, so the fetch hits `/site/self` and returns real data.
- **`root/__unstableBase` collection form (plural) also short-circuited** in `fetchEntityRecords`, in case a code path ever asks for it in list form.
- **Shim's `treeShapesAlign` + `decorateReusingClientIds` + `rememberAuthoritativeBlocks`** kept from path A — they're a legitimate cache improvement for every entity block going through `useEntityBlockEditor`, not just navigation.

## Renderer compatibility (verified during the review pass)

Existing renderer packages already handle persisted `wp:artisanpack/navigation` markup via delegation, so a page whose DB content was never opened in the editor still renders correctly on the frontend:

- **Blade** (`packages/visual-editor-renderer-blade/`): `BlockRenderer::resolvePartial('artisanpack/navigation')` resolves to `blocks/artisanpack/navigation.blade.php`, which `@include`s `blocks/core/navigation.blade.php`. Identical markup output.
- **Vue** and **React** (`packages/visual-editor-renderer-vue/`, `-react/`): the `registerCoreBlocks` maps register `'artisanpack/navigation'` alongside `'core/navigation'` to the same `NavigationBlock` component.
- `packages/renderer-parity.json` lists both names so parity tests exercise the delegation path.

No renderer-side migration work is needed; the deprecation stub handles the editor path and the existing delegation covers the render path.

## Delete after PR merges

This file (`docs/wip/808-navigation-parity-notes.md`) is a WIP-notes artefact and should be removed once the PR merges.

## Files touched (final list)

```
modified:  resources/js/visual-editor/vendor/core-data-shim.ts
    - saveEntityRecord CREATE vs UPDATE branch (the real fix)
    - fetchEntityRecord: SITE_ENTITY_ID coercion for root/__unstableBase
    - fetchEntityRecords: root/__unstableBase collection short-circuit
    - stripped all [AP #808 …] logs, __AP_SHIM_INSTANCE_ID__, unused imports
    - kept treeShapesAlign / decorateReusingClientIds / rememberAuthoritativeBlocks
modified:  resources/js/visual-editor/vendor/__tests__/core-data-shim.test.tsx
    - added UPDATE regression test alongside existing Keystone #57 CREATE test
modified:  resources/js/visual-editor/blocks/navigation/block.json
modified:  resources/js/visual-editor/blocks/navigation/edit.tsx
modified:  resources/js/visual-editor/blocks/navigation/save.tsx
modified:  resources/js/visual-editor/blocks/navigation/index.ts
    - reduced from full fork to a parse-only deprecation stub
deleted:   resources/js/visual-editor/blocks/navigation/{custom-placeholder,inserter-icon,transforms,upstream-state}.*
modified:  resources/js/visual-editor/editor/forked-block-cutover.ts
    - removed core/navigation from FORKED_CORE_BLOCKS
    - deleted broadenNavChildParent + NAV_CHILD_BLOCKS + NAV_PARENT_BROADEN_FILTER
modified:  resources/js/visual-editor/editor/register-forked-cores.ts
    - docstring update; nav-family init calls preserved
modified:  resources/js/visual-editor/blocks/_shared/forked-entity-edit.tsx
    - deleted installNavigationSelectionFix (parent-alias)
modified:  resources/js/visual-editor/blocks/_shared/forked-entity-save.tsx
    - docstring update
modified:  resources/js/visual-editor/blocks/index.ts
    - removed probe + H1 identity probe wiring
modified:  resources/js/visual-editor/blocks/__tests__/i5-entity-cluster.test.ts
    - removed navigation row from the parametrized suite
deleted:   resources/js/visual-editor/editor/__808-selection-probe.ts
deleted:   resources/js/visual-editor/editor/__h1-shim-identity-probe.ts
deleted:   resources/js/visual-editor/editor/__reset-blocks-probe.ts
```
