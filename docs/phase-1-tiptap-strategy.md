# Phase 1 — Tiptap Extension Audit & Paragraph Node Strategy

**Status:** Decision (no production code)
**Tracking issue:** #267
**Consumed by:** #272 (paragraph + heading + toolbar)
**Supersedes:** Phase 0 finding #4 in `PHASE-0-FINDINGS.md`

## Purpose

Phase 0 shipped the editor spike with `@tiptap/react` + `@tiptap/starter-kit` + `@tiptap/extension-link` because it was the fastest path to a working paragraph. `starter-kit` is a meta-package that drags in ~15 extensions, most of which conflict with our block-aware architecture: headings, lists, code blocks, and blockquotes are **blocks** in our system, not ProseMirror nodes living inside a paragraph. Shipping `starter-kit` unchanged means every paragraph's editor can produce document structure our block store cannot represent.

This document decides (1) which extensions each block-level Tiptap editor actually needs, and (2) whether paragraph's Tiptap instance uses Tiptap's stock `Paragraph` node or a custom `BlockParagraph` node that talks to the Zustand store when the user splits or merges.

## 1. Starter-Kit Extension Audit

`@tiptap/starter-kit` bundles the following extensions (v3.22.x). Each row is decided against our architecture: **one Tiptap editor per block instance, containing only inline content for that single block**. Block-level structure (new paragraphs, headings, lists, quotes, code) is owned by the Zustand block store, not by ProseMirror.

| # | Extension | Kind | Decision | Reasoning |
|---|---|---|---|---|
| 1 | `Document` | node | **Keep** | Required root. Every ProseMirror doc needs it. |
| 2 | `Paragraph` | node | **Replace** (see §2) | Stock node's Enter/Backspace behavior operates on the PM doc; we need it to operate on the block store. Replaced with `BlockParagraph`. |
| 3 | `Text` | node | **Keep** | Required for any text content. |
| 4 | `Heading` | node | **Drop** | Headings are their own block type in our system. A paragraph's editor must never be able to produce an `<h1>`. Heading block uses its own schema (§3). |
| 5 | `BulletList` | node | **Drop** | Lists are a Phase 2 block type. A paragraph editor producing `<ul>` would create structure the store cannot round-trip. |
| 6 | `OrderedList` | node | **Drop** | Same as `BulletList`. |
| 7 | `ListItem` | node | **Drop** | Only meaningful inside a list node we are not shipping here. |
| 8 | `Blockquote` | node | **Drop** | Blockquote is a Phase 2 block type. |
| 9 | `CodeBlock` | node | **Drop** | Code is a Phase 2 block type with its own editor surface (likely not Tiptap at all — monospace textarea or CodeMirror). |
| 10 | `HardBreak` | node | **Keep** | `Shift+Enter` inside a paragraph or heading is a legitimate inline line break. Does not create a new block. |
| 11 | `HorizontalRule` | node | **Drop** | HR is a standalone block type in our system. |
| 12 | `Bold` | mark | **Keep** | Inline formatting. Consumed by the shared toolbar in #272. |
| 13 | `Italic` | mark | **Keep** | Same as Bold. |
| 14 | `Strike` | mark | **Defer** | Not in the #272 toolbar spec. Drop for Phase 1; re-add in Phase 2 when we expand typography controls. Keeping it out now avoids shipping a shortcut (`Mod-Shift-X`) the toolbar doesn't expose. |
| 15 | `Code` | mark | **Defer** | Inline `code` mark is not in #272 scope. Same reasoning as `Strike`. |
| 16 | `Dropcursor` | utility | **Defer** | Signals a drop target inside PM. Our drag-and-drop operates at the block level via dnd-kit (#271), not inside a paragraph's PM doc. Revisit only if we add in-paragraph media drops. |
| 17 | `Gapcursor` | utility | **Drop** | Its job is letting the caret sit between block-level PM nodes (tables, figures, HR). Our paragraph editor contains no such nodes, so gapcursor has nothing to do. |
| 18 | `History` | utility | **Replace with app-level undo** | Tiptap's `History` records undo per-editor. Because every block has its own editor instance, per-editor history produces user-confusing undo (typing in block B then `Ctrl+Z` only undoes block B; it cannot undo the block A edit that came before). Phase 1 needs **one** undo stack at the block-store level. Action: disable `History` in every Tiptap instance (`history: false`); store-level undo/redo is tracked separately under **#266** (Phase 1.4) and must land before Phase 1 closes. |
| 19 | `Link` (via `extension-link`) | mark | **Keep (direct dep)** | Already a direct dep from Phase 0. Not part of `starter-kit`; call it out explicitly because the toolbar in #272 needs it. Configure with `openOnClick: false`, `autolink: true` (matches spike). |

**Summary:** out of ~18 pieces in `starter-kit`, Phase 1 keeps **5 nodes/marks** (`Document`, `Text`, `HardBreak`, `Bold`, `Italic`), **replaces 1** (`Paragraph` → `BlockParagraph`), **drops 11** (heading, lists, blockquote, code block, HR, gapcursor, strike, code, dropcursor, history, and the list-item helper), and adds `Link` on top as a direct dep. The heading block (§3) uses the same base set plus its own node.

### Why not just `StarterKit.configure({ ... : false })`?

The spike already does this (`starterKit.configure({ heading: false, bulletList: false, ... })`). That works, but it still pulls every extension into the bundle — `configure` only disables them at runtime. Phase 1 should stop importing `starter-kit` altogether and import the individual packages we keep. Rationale:

1. **Bundle size.** We ship ~11 unused extensions otherwise. They're small individually, but once we add inspector/sidebar/dnd the total matters.
2. **Explicit contract.** Importing extension-by-extension makes the whitelist auditable in code review. A future contributor cannot accidentally re-enable `heading` by deleting `heading: false`.
3. **Version pinning.** Individual Tiptap packages let us upgrade marks/nodes independently when one of them has a bug fix we need.

## 2. Paragraph Node Decision

**Decision: Option B — build a custom `BlockParagraph` node.**

### Context

Option A uses Tiptap's stock `Paragraph` node inside each block's editor. Option B replaces it with a custom `BlockParagraph` node that owns Enter/Backspace handling and dispatches to the Zustand store instead of mutating the PM doc.

### Why Option B

In our architecture, pressing **Enter** at the end of a paragraph must create **a new block in the store**, not a second `<p>` inside the same editor. Pressing **Backspace** at the start of a block must merge this block's content into the previous block. Both actions operate on the **block tree**, not on a single editor's PM doc.

Option A makes this awkward: we'd have to listen for `onUpdate`, diff the resulting HTML, detect "oh, a second paragraph appeared, let me split this into two blocks," and then forcibly revert the PM doc back to a single paragraph. That is fragile — the PM doc transiently contains structure the block store never agreed to, and reverting it fights Tiptap's transaction pipeline. Selection placement after the split becomes a second fight.

Option B inverts it. `BlockParagraph` installs keymap handlers for `Enter` and `Backspace` that:

- **`Enter` at block boundary:** prevent PM's default, split the current text at the caret, dispatch `insertBlock({ type: 'paragraph', content: <right half> })` to the store with the current block reduced to the left half, and move focus/caret to the new block's editor. Shift+Enter still inserts a `HardBreak` (kept in §1).
- **`Backspace` at block start (empty selection):** prevent PM's default, dispatch `removeBlock` on the current block and merge its remaining content into the previous block's editor, positioning the caret at the join point.

Everywhere else (typing, deleting mid-block, arrow keys, marks), `BlockParagraph` behaves exactly like Tiptap's stock `Paragraph`. It extends the stock node, it does not reimplement it.

This matches how Gutenberg handles the same problem in `RichText` — key handlers at the editor level talk to the block store, and each block's editor is constrained to inline content only.

### Cost

- **~80–150 lines** of code for `BlockParagraph.ts`, concentrated in two keymap handlers plus a small helper for "split HTML at caret."
- **Vitest coverage** for split (end of block, middle of block, empty block) and merge (backspace at start, backspace into previous heading, backspace into previous empty paragraph). Already required by #272's acceptance criteria, so no additional test surface from this decision.
- **Heading block reuses the same node.** The heading block in #272 wraps its content in a different schema (with the `level` attribute), but its Enter/Backspace behavior is identical: Enter at end of an H2 creates a **paragraph** below (not another H2); Backspace at start merges into the previous block. We factor the keymap into a shared `createBlockAwareKeymap({ onSplit, onMerge })` helper and both `BlockParagraph` and `BlockHeading` install it.

### Why not Option A

Option A was on the table because it is "simpler on day one." It is not simpler across the Phase 1 + Phase 2 lifetime:

- Every new inline-text block (heading, list item text, quote, caption) rediscovers the same split/merge problem.
- The `onUpdate` → diff → revert loop runs on every keystroke in every editor. It is wasted work on the 99% of keystrokes that don't split a block.
- Selection restoration after a revert is a known PM pain point.
- Option A makes it very easy to accidentally ship "you can Enter inside a block to create a second paragraph," which is the exact model we are deliberately avoiding.

The Option B cost is paid once, in one file, and every block that embeds a Tiptap editor inherits correct behavior.

## 3. Heading Node Strategy (informational — implemented in #272)

Out of scope for decision, but called out so #272 does not relitigate it:

- Heading is a **separate block type**. It is not produced by paragraph's editor.
- Its Tiptap editor uses the same extension whitelist from §1, plus a custom `BlockHeading` node that extends Tiptap's `Heading` node with the `createBlockAwareKeymap` helper from §2.
- The `level` (1–6) lives as a **block attribute** in the store, not as a node attribute inside PM. The editor renders the appropriate tag (`<h1>`–`<h6>`) based on the store attribute, and the toolbar's level switcher dispatches a store update, not a PM transaction.
- This keeps heading level in one place (the store) and avoids the "is the source of truth PM or the store?" ambiguity.

## 4. Final Dependency List for #272

Direct dependencies to add / keep in `package.json`:

```json
{
  "dependencies": {
    "@tiptap/react": "^3.22.3",
    "@tiptap/pm": "^3.22.3",
    "@tiptap/core": "^3.22.3",
    "@tiptap/extension-document": "^3.22.3",
    "@tiptap/extension-text": "^3.22.3",
    "@tiptap/extension-paragraph": "^3.22.3",
    "@tiptap/extension-heading": "^3.22.3",
    "@tiptap/extension-hard-break": "^3.22.3",
    "@tiptap/extension-bold": "^3.22.3",
    "@tiptap/extension-italic": "^3.22.3",
    "@tiptap/extension-link": "^3.22.3"
  }
}
```

Dependencies to **remove**:

- `@tiptap/starter-kit` — replaced by the explicit list above.

Notes:

- `@tiptap/core` and `@tiptap/pm` are transitive today but become direct because `BlockParagraph` imports from them (`Node.create`, `Plugin`, `TextSelection`). Declaring them directly avoids relying on hoisting.
- `@tiptap/extension-paragraph` and `@tiptap/extension-heading` are the **base** nodes that `BlockParagraph` / `BlockHeading` extend. They are not registered as-is in the editor — our custom nodes are registered in their place.
- `history` is intentionally absent. Store-level undo/redo replaces it.
- `strike`, `code`, `bullet-list`, `ordered-list`, `list-item`, `blockquote`, `code-block`, `horizontal-rule`, `dropcursor`, `gapcursor` are intentionally absent. They come back if and only if they return as explicit block types in a later phase.

## 5. Open Follow-ups (not part of #267)

- **Store-level undo/redo** — replaces disabled Tiptap `History`. Tracked under **#266** (Phase 1.4: Undo/redo history layer on editor store) in the #237 breakdown. Must be in place by the end of Phase 1 or users will notice the regression from the spike.
- **Dropcursor revisit** — if we later allow dropping images or other media inside a paragraph (rather than as sibling blocks), dropcursor comes back.
- **Inline `code` and `strike` marks** — Phase 2 typography expansion.
- **Collaborative editing extensions** (`@tiptap/extension-collaboration`) — not in scope anywhere in Phase 1; flag for Phase 5+.

## Acceptance (against #267)

1. ✅ `docs/phase-1-tiptap-strategy.md` covers the starter-kit audit (§1), the paragraph node decision (§2), and the final dependency list for #272 (§4).
2. ✅ No production code changes — this commit touches only documentation.
