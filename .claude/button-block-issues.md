# Button Block: Focus & Placeholder Issues After Insert

## Status: PARTIALLY FIXED — needs more investigation

## What Was Done

### Completed fixes (working):
1. **Buttons block `getContentSchema`** — Moved justification, orientation, flexWrap from Styles tab to Settings tab (`getStyleSchema()` → `getContentSchema()`). Updated `block.json` attribute sources from `"style"` to `"content"`.
2. **Default inner blocks** — When a Buttons block is inserted, it now auto-creates one Button child. Fixed in both `addBlock()` and `replaceBlock()` in `editor-state.blade.php` via `defaultInnerBlocksMap` lookup.
3. **Placeholder CSS** — Added generic `[data-placeholder].ve-is-empty:not(:focus)::before` CSS to the package's `editor.blade.php` style block. Previously this CSS only existed in the dev app's `app.css`.

### Still broken:
1. **Focus** — After inserting a Buttons block (via sidebar inserter → `replaceBlock`), the inner Button child does NOT get focus on its contenteditable span. Current attempt uses `$nextTick` + `setTimeout(50)` in `editor-canvas.blade.php` line 396-407 but user says it's still not working.
2. **Placeholder visibility** — User reports placeholder text ("Add text…") still not visible/hard to see, making the button hard to select on the canvas.

## Key Architecture Details

### Block insertion path from sidebar inserter:
1. User clicks block in sidebar inserter while empty paragraph is selected
2. Sidebar inserter dispatches `ve-block-inserter-select` event
3. Slash command dropdown (if open) catches it, OR the empty paragraph's slash command handler fires
4. **Actual path**: `ve-slash-command-select` event → `editor-canvas.blade.php` line 391-405 → calls `replaceBlock()`
5. `replaceBlock()` looks up `defaultInnerBlocksMap` for the block type and auto-creates inner blocks
6. Returns `newBlock` object with `innerBlocks` array populated

### JS Renderer chain:
- `buttons` renderer (`editor.blade.php:959`) renders the flex container + calls `br.getHtml()` for each inner block
- `button` renderer (`editor.blade.php:1004`) renders `<div data-block-id='innerBlockId'><span class='ve-button-text' contenteditable='true' data-placeholder='...'>text</span></div>`
- All rendered via Alpine `x-html` directive in the canvas `x-for` loop

### Placeholder system:
- `markEmpty()` function (`_editor-canvas-content.blade.php:651`) toggles `ve-is-empty` class on empty `[contenteditable][data-placeholder]` elements
- `markAllEmpty()` runs on `$nextTick` after `$store.editor.blocks` changes
- CSS rule `[data-placeholder].ve-is-empty:not(:focus)::before` shows placeholder when unfocused
- **Potential timing issue**: `markAllEmpty` may fire before `x-html` has rendered the button's inner HTML

## Investigation Ideas

### Focus issue:
- The `setTimeout(50)` approach may not be enough. Try larger delay or use `requestAnimationFrame`
- Check if `document.querySelector('[data-block-id="' + newBlock.id + '"]')` actually finds the element — the outer `x-for` wrapper div has `data-block-id`, but the buttons renderer also outputs inner divs with their own `data-block-id`
- Consider using `MutationObserver` to wait for the contenteditable to appear in the DOM
- The inner button's `data-block-id` is on a div INSIDE the x-html content, not on the x-for wrapper — verify the CSS selector chain works

### Placeholder issue:
- Verify `markAllEmpty()` is actually running AFTER the button's x-html renders
- Add temporary `console.log` in `markAllEmpty` to check if it finds the button's `[contenteditable][data-placeholder]` element
- Check if the button span has `ve-is-empty` class in DevTools after insertion
- The CSS rule uses `:not(:focus)` — make sure the span isn't accidentally receiving focus
- Check if the button's contenteditable span is truly `:empty` or has a text node (even empty string `''` creates a text node in some cases)

### Alternative approach for both issues:
- Add a `focusInnerBlock(blockId)` helper to the editor store or canvas component that:
  1. Waits for DOM via `MutationObserver` or repeated `requestAnimationFrame`
  2. Finds the first `[contenteditable]` inside the block
  3. Calls `markEmpty()` on it explicitly
  4. Then `focus()` it

## Files Modified
- `src/Blocks/Interactive/Buttons/ButtonsBlock.php` — content/style schema swap
- `src/Blocks/Interactive/Buttons/block.json` — attribute source changes
- `src/Blocks/Interactive/Buttons/views/edit.blade.php` — reads from `$content`
- `src/Blocks/Interactive/Buttons/views/save.blade.php` — reads from `$content`
- `src/View/Components/Editor.php` — builds `$defaultInnerBlocksMap`
- `src/View/Components/EditorState.php` — accepts `$defaultInnerBlocksMap`
- `resources/views/components/editor.blade.php` — passes map + placeholder CSS
- `resources/views/components/editor-state.blade.php` — `addBlock`/`replaceBlock` use map
- `resources/views/components/editor-canvas.blade.php` — focus logic after replaceBlock
- `resources/views/components/_editor-canvas-content.blade.php` — `insertFromPopover` uses map
- `resources/views/components/block-inserter.blade.php` — drag handler passes defaultInnerBlocks
- `tests/Unit/Blocks/Interactive/ButtonsBlockTest.php` — updated for content schema
- `tests/Unit/Blocks/BlockDiscoveryServiceTest.php` — block count 18→19
- `tests/Feature/Commands/BlockCacheCommandTest.php` — block count 18→19
