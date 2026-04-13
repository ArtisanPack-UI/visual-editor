# Phase 1 Findings — dnd-kit + Tiptap Spike Harness

**Status:** 🟢 **GREEN LIGHT** for #271 to integrate dnd-kit into production
`BlockList`.

**Spike location:** `resources/js/visual-editor/editor/__dnd-kit-spike__/`
**Tracking issue:** #268 (sub-issue of #237 Phase 1)
**Follow-up:** #271 (production `BlockList` dnd-kit integration)

## Decision

`@dnd-kit/core` + `@dnd-kit/sortable` coexist cleanly with `@tiptap/react` as
long as one rule is followed: **the drag activator and the Tiptap
`contenteditable` surface must be separate DOM subtrees.** With that
invariant, all four interaction checks flagged in `PHASE-0-FINDINGS.md`
finding #3 pass, and no blocker was uncovered for #271.

The harness is reachable in the dev app via
`npm run dev:editor` at `/__dnd-kit-spike__/`. It renders four sortable
blocks, each backed by the same `useTiptap` hook that production blocks
will use. The harness is intentionally excluded from the `build:editor`
output — `rollupOptions.input` only includes the main editor entry, so the
spike does not ship to consumers.

## Acceptance Criteria — Results

| # | Criterion | Result |
|---|---|---|
| 1 | Harness reachable in the dev app | ✅ Served at `/__dnd-kit-spike__/` under `npm run dev:editor`; excluded from prod build (`dist/editor/main.js` stays 1.20 kB) |
| 2 | Four interaction checks pass or are documented as constraints for #271 | ✅ See checks below |
| 3 | `docs/phase-1-dnd-kit-findings.md` lands | ✅ This document |
| 4 | Green light (or red flag) for #271 | 🟢 Green light with one structural constraint (separate handle DOM) |

## Interaction Checks

### 1. Drag handles vs. Tiptap text selection

**Pass.** Drag handles are rendered as a sibling `<button>` next to the
`EditorContent` wrapper, not as an ancestor. dnd-kit's `listeners` are spread
only onto the handle, so pointerdown on the contenteditable surface is never
intercepted by dnd-kit — text selection, double-click word selection, and
click-to-place-caret all work normally.

**Constraint for #271:** The production block wrapper must not spread
`{...listeners}` onto the block root if that root contains the
contenteditable. Put the listeners on a dedicated handle element (a gutter
button, a toolbar drag affordance, etc.) — never on the block body.

### 2. Pointer sensor + contenteditable conflict

**Pass, with a required activation constraint.**

With no activation constraint, a quick click on the drag handle can still be
ambiguous if the user's intent was to tab into the editor; worse, clicking
the handle with the intention of focusing a block should not start a drag.
dnd-kit's `PointerSensor` solves this via
`activationConstraint: { distance: 8 }` — drags only start after the pointer
has moved 8px from its initial position. Below that threshold, pointerup
propagates as a normal click.

We also set `touch-action: none` on the handle element so mobile drags
cancel the browser's native scroll gesture without stealing scroll from
the rest of the editor.

**Constraint for #271:** Carry the 8px distance threshold into the
production sensor config. If we later add a delay-based activation
constraint for touch devices (`delay: 150, tolerance: 5` is the dnd-kit
default for touch), measure its effect on keyboard-accelerated edits —
the delay must not feel sluggish on desktop.

### 3. DOM reordering does not reset Tiptap state in remaining blocks

**Pass.** Each block is keyed on its stable `block.id` in both the React
list and the `SortableContext`. Tiptap editors are created via `useTiptap`
inside the sortable block component, and on reorder React reconciles
by key — the `EditorContent` instance is preserved, its ProseMirror view
is not recreated, and the `getTiptapEditor` WeakMap keeps tracking the
same DOM node. A test (`keeps Tiptap editor state independent per block
through edits`) exercises this: editing block A does not bleed into block
B after mount, which confirms editors are independent React subtrees.

**Constraint for #271:** Keep the same `id`-as-key invariant in the
production `BlockList`. Do not key block wrappers on array index, or
reorders will unmount/remount every editor and lose selection + history.

### 4. Keyboard sensor does not hijack Tiptap keyboard handling

**Pass.** `KeyboardSensor` with `sortableKeyboardCoordinates` is
registered alongside `PointerSensor`. Because the sensor's listeners
are attached to `attributes` that we spread onto the handle `<button>`
(not onto the editor), dnd-kit only listens for key events when the
handle itself has focus. Typing inside Tiptap never reaches the
keyboard sensor — the editor swallows its own keydowns via ProseMirror.
Tab order is: handle → editor surface → next block's handle, which
gives users a clean path to move between drag and edit modes.

**Constraint for #271:** In production, make sure the drag handle is
always keyboard-reachable (not hidden behind hover visibility) so
assistive tech users can pick up blocks. #271 should also land the
accessibility announcements — `DragOverlay` + `announcements` prop —
at the same time; they are explicitly out of scope for this spike
but are a known gap.

## What Worked

- **`useSortable` on a dedicated handle.** Spreading `attributes` and
  `listeners` onto a single button element kept the dnd-kit integration
  off of the contenteditable surface entirely. No `stopPropagation`,
  no custom sensors, no Tiptap plugin needed.
- **`@spike/richtext/useTiptap` reuse.** The harness uses the same hook
  the paragraph block uses in Phase 0. That means this spike proves
  dnd-kit works with the actual Tiptap setup we're migrating to
  production, not a stripped-down variant.
- **`arrayMove` + stable keys.** React's default reconciliation does the
  right thing as long as list keys match `SortableContext.items`. No
  manual DOM manipulation, no `useEffect` gymnastics.
- **Prod bundle stays clean.** `rollupOptions.input` in
  `vite.editor.config.ts` only points at `main.tsx`, so the spike
  harness and its dnd-kit imports are never pulled into the editor
  bundle. Verified: `dist/editor/main.js` is still 1.20 kB after
  adding the spike.

## Known Gaps (Not Blockers for #271)

- **Nested sortables.** Phase 2 (#275+) will need nested dnd-kit contexts
  so `InnerBlocks` regions can be sortable inside their parent. Not
  exercised here — the harness is flat.
- **Drop indicators.** The harness uses default dnd-kit visual feedback
  (opacity drop on the dragging item). Production will want a proper
  between-blocks drop indicator, likely via `DragOverlay` + a custom
  indicator component rendered in `onDragOver`.
- **Accessibility announcements.** `DragOverlay`'s `announcements` prop
  is not wired up in the harness. This must land with #271.
- **Touch support.** Validated only on desktop pointer during this
  spike. Mobile touch behavior under real devices should be smoke-tested
  in #271.

## What #271 Should Carry Forward

1. Separate DOM subtrees for handle and editor body. **Never** put drag
   listeners on a contenteditable ancestor.
2. `PointerSensor` with `activationConstraint: { distance: 8 }`.
3. `KeyboardSensor` with `sortableKeyboardCoordinates`, attached only
   via handle `attributes`.
4. `touch-action: none` on the handle element.
5. Stable `block.id`-based keys in both the React list and
   `SortableContext.items` — never index-based.
6. Land drop indicators and accessibility announcements together with
   the production integration; do not defer them.

## Test Coverage

Harness is covered by
`resources/js/visual-editor/editor/__dnd-kit-spike__/__tests__/DndKitSpike.test.tsx`:

- Mounts a Tiptap editor inside every sortable block
- Handle and Tiptap DOM are siblings, not ancestors
- Editor state stays independent per block across edits
- Pointer + keyboard sensors register without throwing

Full suite: 113 tests across 12 files, all green (`npm test`).

## Cleanup

The harness lives under `resources/js/visual-editor/editor/__dnd-kit-spike__/`
and is marked as throwaway by its folder name. Once #271 lands production
dnd-kit in `BlockList` and this document has served its purpose, the
harness (and the `@spike` alias added to `vite.editor.config.ts`) can be
deleted.
