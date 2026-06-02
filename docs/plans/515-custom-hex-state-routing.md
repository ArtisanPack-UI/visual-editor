# #515 — Custom hex picked on a non-idle state: diagnosis and fixes

## Problem

When the inspector's State chip strip is set to a non-idle state (Hover, Focus, etc.), picking a **custom hex** color via the Color > Background picker does not persist the new value into `attributes.states.<path>.<activeState>`. After save+reload the states bag still holds the **previous** hover value.

Palette slug picks on the same non-idle chip work correctly — both `attributes.<slug-key>` and `attributes.states.<slug-key>.<activeState>` update as expected.

## Root cause analysis

Four contributing issues were identified. All four are addressed by the code on this branch.

### 1. HOC did not mirror state-eligible writes to the base attribute

**File:** `resources/js/visual-editor/states/with-state-attributes.tsx`

The `withStateAttributes` HOC intercepts `setAttributes` calls from the color panel. When the active state is non-idle, it routes state-eligible changes into `attributes.states` but did **not** update the base attribute in the data store.

This matters because WordPress's newer block-support panels (color, border, shadow on apiVersion 3 blocks) read attributes via `useSelect('core/block-editor').getBlockAttributes()` — bypassing the HOC's `mergedAttributes` prop entirely. So even though the HOC correctly updated `states.hover`, the inspector panel still read the **old overlaid value** from the data store, making it appear as though the pick had no effect.

**Debug evidence:** Console logs confirmed:
```
[HOC] wrappedSetAttributes called {activeState: "hover", changedPaths: ["style.color.background", "backgroundColor"]}
[HOC] routed to states: "style.color.background" = "#1ae61d"
[HOC] dispatching finalUpdates ["states"]   ← only states, NOT the base
```

**Fix:** The HOC now mirrors state-eligible values to BOTH `states` and the base attribute using `buildTopLevelPatch` for sibling preservation. The `StateInspectorSync` component's pristine snapshot mechanism restores the canonical idle base before save.

### 2. Sibling clobbering in `planCorrection` (interceptor)

**File:** `resources/js/visual-editor/states/state-write-interceptor.tsx`

`planCorrection` built its `updatePayload` via `setPath()`, which creates a minimal nested tree like `{style: {color: {background: '#hex'}}}`. When `updateBlockAttributes` shallow-merges this at the top level, it **replaces the entire `style` subtree**, clobbering siblings like `style.spacing.padding` or `style.border.radius`.

**Fix:** Replaced `setPath` with `buildTopLevelPatch`, which deep-clones the relevant top-level subtree from the current attributes and applies only the changed leaf, preserving all siblings.

### 3. Block-change pristine restore targeted the wrong block

**File:** `resources/js/visual-editor/states/StateInspectorSync.tsx`

When block selection changes, `restorePristine()` was called with `slice.clientId` (the **new** block's ID). The old block's pristine snapshot was never unwound, so:
- If the user clicked away from the block before saving, the old block's data-store attributes leaked the synced overlay into the persisted markup.
- The pristine snapshot was orphaned (never cleared).

**Fix:** The effect now detects block changes and restores the **previous** block's pristine base before processing the new selection.

### 4. Save lifecycle without `core/editor`

**File:** `resources/js/visual-editor/editor/use-persistence.ts`

The visual editor does not register a `core/editor` store, so `isSavingPost()` never fires. The `StateInspectorSync` save-lifecycle restore (which depends on `isSavingPost`) never runs.

**Fix (two-pronged):**

1. **Save-path patching:** `use-persistence.ts` now calls `patchBlocksWithPristine()` right before `saveContent()`. This patches the block tree **in memory** — replacing overlaid base values with their pristine idle snapshots — without modifying the data store. The live editing overlay is unaffected.

2. **Host-agnostic flush API:** A new `flushBeforeSave()` function is exported from `states/index.ts`. Hosts that don't use `core/editor` can call this before serializing block content. It restores all pristine snapshots in the data store.

## Files changed

| File | Change |
|------|--------|
| `responsive/attribute-paths.ts` | Added `deepClone` and `buildTopLevelPatch` (moved from `StateInspectorSync` to shared module) |
| `states/with-state-attributes.tsx` | HOC now mirrors state-eligible writes to the base attribute via `buildTopLevelPatch` |
| `states/state-write-interceptor.tsx` | `planCorrection` uses `buildTopLevelPatch` instead of `setPath` for sibling preservation |
| `states/StateInspectorSync.tsx` | Block-change restore targets the previous block; uses shared `buildTopLevelPatch`/`deepClone`; exports `flushBeforeSave()` |
| `states/state-bridge.ts` | Added `getAllPristineClientIds()` |
| `states/index.ts` | Exports `flushBeforeSave` |
| `editor/use-persistence.ts` | Save path patches blocks with pristine bases before sending to API |
| `responsive/__tests__/attribute-paths.test.ts` | Tests for `deepClone` and `buildTopLevelPatch` |
| `states/__tests__/state-write-interceptor.test.ts` | Tests for sibling preservation and custom hex routing |
| `states/__tests__/StateInspectorSync.test.ts` | Tests for `flushBeforeSave` |
| `states/__tests__/with-state-attributes.test.tsx` | Updated to expect base-mirroring behavior |

## Testing status

- All 1674 JS tests pass (vitest)
- All 805 PHP tests pass (pest)
- **Not yet verified in browser** — the `jmwd-keystone-cms` host app serves visual-editor assets through a Laravel controller (`VisualEditorAssetController`) with a 1-hour `Cache-Control: max-age=3600` header. This made it very difficult to test new builds during this session because browsers kept serving stale chunks even after hard refresh and private windows. The cache header was temporarily changed to `no-cache` for testing but the stale cache persisted.

## How to test on the MacBook Pro

1. `cd ~/Code/ArtisanPack\ UI\ Packages/visual-editor`
2. `git checkout bugfix/515-custom-hex-not-routed-to-state`
3. `npm run build`
4. Clear browser cache completely (Safari: Develop > Empty Caches, or use a fresh private window)
5. In `jmwd-keystone-cms`, temporarily set the asset controller's `Cache-Control` header to `no-cache` if still having stale-chunk issues
6. Open the CMS editor, select a button block
7. Click the **Hover** chip
8. Pick a new custom hex Background color — the swatch and canvas should update immediately
9. Save, reload
10. Click Hover again — the new color should persist in `states['style.color.background'].hover`

## Open questions

- The `VisualEditorAssetController`'s `max-age=3600` cache header makes iterative dev painful. Consider adding a build hash query param or using `ETag`/`Last-Modified` for conditional requests instead of a fixed TTL.
- If the browser issue persists on the MacBook Pro despite cache clearing, the next diagnostic step is to add `console.warn` logs in the save path (`use-persistence.ts`) to confirm the blocks reaching `saveContent()` contain the updated `states` bag. The HOC routing was confirmed working via debug logs during this session.
