# Runtime Inner Blocks Support in Editor Canvas

## Context

The `<x-ve-inner-blocks>` Blade component, Quote block citation toggle, and data-layer inner block methods (`addInnerBlock`, `removeInnerBlock`) are all implemented. However, the editor canvas does not actually support inner blocks at runtime:

- **Enter key** always adds a new block to the top-level canvas (via `addBlockAfter`), not inside a parent block
- **`getBlockHtml()`** renders quote as a single contenteditable div, not as a container with actual child blocks
- **`getBlock()`** only searches top-level blocks, so `updateBlock()` can't find inner blocks
- **`handleInput()`** doesn't handle content syncing for inner blocks
- **Layer panel** shows a flat list, not a tree
- **Add block ("+" buttons)** don't appear inside the inner blocks area
- **Drag and drop** can't move blocks into the inner blocks area

The quote block should behave like WordPress's Quote block: pressing Enter inside it creates a child paragraph block within the quote, and those inner blocks are rendered and editable inside the blockquote.

---

## Step 1: Make `getBlock()` Recursive

**File:** `resources/views/components/editor-state.blade.php` (line 136)

Change `getBlock()` to recursively search inner blocks. This makes `updateBlock()` automatically work for nested blocks.

```javascript
getBlock( blockId ) {
    const findBlock = ( blocks ) => {
        for ( const block of blocks ) {
            if ( block.id === blockId ) return block;
            if ( block.innerBlocks?.length ) {
                const found = findBlock( block.innerBlocks );
                if ( found ) return found;
            }
        }
        return null;
    };
    return findBlock( this.blocks );
},
```

Also add a helper to find the parent block of a given inner block:

```javascript
getParentBlock( blockId ) {
    const findParent = ( blocks, parent ) => {
        for ( const block of blocks ) {
            if ( block.id === blockId ) return parent;
            if ( block.innerBlocks?.length ) {
                const found = findParent( block.innerBlocks, block );
                if ( found ) return found;
            }
        }
        return null;
    };
    return findParent( this.blocks, null );
},
```

And add `addInnerBlockAfter()` for adding inner blocks after a sibling:

```javascript
addInnerBlockAfter( afterBlockId, block = {} ) {
    const parent = this.getParentBlock( afterBlockId );
    if ( ! parent ) return null;

    const index = parent.innerBlocks.findIndex( ( b ) => b.id === afterBlockId );
    if ( -1 === index ) return null;

    return this.addInnerBlock( parent.id, block, index + 1 );
},
```

---

## Step 2: Update Enter Key Handler for Inner Blocks Context

**File:** `resources/views/components/editor-canvas.blade.php` (line 409)

Modify the Enter key handler to detect if the contenteditable is inside a `[data-ve-inner-blocks]` container. If so, use `addInnerBlock()` instead of `addBlockAfter()`.

```javascript
x-on:keydown.enter="
    if ( ! slashCommandOpen && ! $event.shiftKey && $event.target.hasAttribute( 'data-ve-enter-new-block' ) ) {
        $event.preventDefault();
        const innerBlocksContainer = $event.target.closest( '[data-ve-inner-blocks]' );
        const blockEl = $event.target.closest( '[data-block-id]' );
        if ( ! blockEl || ! Alpine.store( 'editor' ) ) return;

        const blockId = blockEl.getAttribute( 'data-block-id' );

        if ( innerBlocksContainer ) {
            // Inside an inner blocks container — add child block to parent
            const parentId = innerBlocksContainer.getAttribute( 'data-parent-id' ) || blockId;
            const innerBlockEl = $event.target.closest( '[data-inner-block-id]' );

            let newBlock;
            if ( innerBlockEl ) {
                // Enter pressed inside an existing inner block — add after it
                const innerBlockId = innerBlockEl.getAttribute( 'data-inner-block-id' );
                newBlock = Alpine.store( 'editor' ).addInnerBlockAfter( innerBlockId );
            } else {
                // Enter pressed in the placeholder — add first inner block
                newBlock = Alpine.store( 'editor' ).addInnerBlock( parentId, { type: 'paragraph' } );
            }

            if ( newBlock ) {
                $nextTick( () => {
                    const newEl = document.querySelector( '[data-inner-block-id=' + newBlock.id + ']' );
                    if ( newEl ) { newEl.focus(); }
                } );
            }
        } else {
            // Top-level block — existing behavior
            const newBlock = Alpine.store( 'editor' ).addBlockAfter( blockId );
            if ( newBlock ) {
                $nextTick( () => {
                    const newEl = document.querySelector( '[data-block-id=' + newBlock.id + '] [contenteditable]' );
                    if ( newEl ) { newEl.focus(); }
                    if ( Alpine.store( 'selection' ) ) {
                        Alpine.store( 'selection' ).select( newBlock.id, false );
                    }
                } );
            }
        }
    }
"
```

---

## Step 3: Render Inner Blocks in `getBlockHtml()` (Quote)

**File:** `artisanpack-ui/resources/views/packages/visual-editor/editor-shell.blade.php`

Update the quote block rendering in `getBlockHtml()` to:
- When `block.innerBlocks` has items, render each inner block as a contenteditable div with `data-inner-block-id`
- When empty, show the InnerBlocks placeholder (current behavior)
- Include `data-parent-id` on the `[data-ve-inner-blocks]` container

Each inner block rendered inside the quote:
```html
<div class="ve-inner-block ve-block ve-block-paragraph"
     data-inner-block-id="block-10-1"
     contenteditable="true"
     data-placeholder="Type / to choose a block"
     data-ve-enter-new-block="true"
>{inner block text}</div>
```

---

## Step 4: Update `handleInput()` for Inner Blocks

**File:** `artisanpack-ui/resources/views/packages/visual-editor/editor-shell.blade.php`

Add inner block detection at the top of `handleInput()`:

```javascript
handleInput( event ) {
    const target = event.target;
    if ( ! target.hasAttribute( 'contenteditable' ) ) return;

    this._markEmpty( target );
    target.querySelectorAll( '[data-placeholder]' ).forEach( this._markEmpty );

    // Inner block content syncing
    if ( target.hasAttribute( 'data-inner-block-id' ) ) {
        const innerBlockId = target.getAttribute( 'data-inner-block-id' );
        Alpine.store( 'editor' ).updateBlock( innerBlockId, { text: target.innerHTML } );
        return;
    }

    // ... existing top-level block handling
}
```

Since `getBlock()` is now recursive (Step 1), `updateBlock(innerBlockId, ...)` will find and update the inner block.

---

## Step 5: Update Demo Data

**File:** `artisanpack-ui/resources/views/packages/visual-editor/editor-shell.blade.php`

Update the initial quote block to include inner blocks:

```php
[
    'id' => 'block-10',
    'type' => 'quote',
    'attributes' => [ 'showCitation' => true, 'citation' => 'ArtisanPack Team' ],
    'innerBlocks' => [
        [
            'id' => 'block-10-1',
            'type' => 'paragraph',
            'attributes' => [ 'text' => 'The visual editor makes content creation intuitive and powerful.' ],
            'innerBlocks' => [],
        ],
    ],
],
```

Note: `text` attribute is moved to an inner paragraph block. The quote's own `text` attribute is no longer used when inner blocks exist.

---

## Step 6: Add Block Inserter Inside Inner Blocks Area

**File:** `artisanpack-ui/resources/views/packages/visual-editor/editor-shell.blade.php`

The "+" add block buttons that appear between top-level blocks (insertion points) don't exist inside the inner blocks area. Add insertion points between inner blocks rendered in `getBlockHtml()`:

- Between each inner block, render a small insertion point (same pattern as the canvas insertion points)
- A "+" button after the last inner block
- Clicking the "+" button opens a mini block inserter that adds to the parent's `innerBlocks` via `addInnerBlock()`

In `getBlockHtml()` for quote, between each inner block div:
```html
<div class="ve-inner-insertion-point relative group/inner-insert py-0.5">
    <div class="flex justify-center">
        <button type="button"
            class="w-5 h-5 rounded-full bg-primary text-primary-content flex items-center justify-center opacity-0 group-hover/inner-insert:opacity-100 transition-opacity"
            data-ve-inner-insert
            data-parent-id="block-10"
            data-insert-index="1"
        >+</button>
    </div>
</div>
```

Handle click on `[data-ve-inner-insert]` buttons in `init()` with a delegated listener:
```javascript
el.addEventListener( 'click', ( e ) => {
    const insertBtn = e.target.closest( '[data-ve-inner-insert]' );
    if ( ! insertBtn ) return;

    const parentId = insertBtn.getAttribute( 'data-parent-id' );
    const index = parseInt( insertBtn.getAttribute( 'data-insert-index' ), 10 );

    const newBlock = Alpine.store( 'editor' ).addInnerBlock( parentId, { type: 'paragraph' }, index );
    if ( newBlock ) {
        this.$nextTick( () => {
            const newEl = document.querySelector( '[data-inner-block-id=' + newBlock.id + ']' );
            if ( newEl ) { newEl.focus(); }
        } );
    }
} );
```

---

## Step 7: Drop Zone for Inner Blocks Container

**File:** `artisanpack-ui/resources/views/packages/visual-editor/editor-shell.blade.php`

Add drag-and-drop event handlers to the InnerBlocks container rendered in `getBlockHtml()`. When a block is dropped on the inner blocks area:

1. Add `dragover` and `drop` handlers to the `[data-ve-inner-blocks]` container in the `getBlockHtml()` output
2. On drop: remove the block from its current position (top-level or inner) and add it as an inner block of the quote
3. Visual feedback: highlight the inner blocks area when dragging over it

This will be handled in the `init()` method with delegated event listeners on the canvas element, since `getBlockHtml()` returns raw HTML strings (no Alpine directives).

```javascript
// In init(), add delegated drop handler for inner blocks containers
el.addEventListener( 'dragover', ( e ) => {
    const innerBlocksEl = e.target.closest( '[data-ve-inner-blocks]' );
    if ( innerBlocksEl ) {
        e.preventDefault();
        e.stopPropagation();
        innerBlocksEl.classList.add( 'ring-2', 'ring-info', 'ring-offset-1' );
    }
} );

el.addEventListener( 'dragleave', ( e ) => {
    const innerBlocksEl = e.target.closest( '[data-ve-inner-blocks]' );
    if ( innerBlocksEl ) {
        innerBlocksEl.classList.remove( 'ring-2', 'ring-info', 'ring-offset-1' );
    }
} );

el.addEventListener( 'drop', ( e ) => {
    const innerBlocksEl = e.target.closest( '[data-ve-inner-blocks]' );
    if ( ! innerBlocksEl || ! this.draggingBlockId ) return;

    e.preventDefault();
    e.stopPropagation();
    innerBlocksEl.classList.remove( 'ring-2', 'ring-info', 'ring-offset-1' );

    const parentId = innerBlocksEl.getAttribute( 'data-parent-id' );
    if ( ! parentId ) return;

    const store = Alpine.store( 'editor' );
    const block = store.getBlock( this.draggingBlockId );
    if ( ! block ) return;

    // Remove from current position (top-level or inner)
    const parent = store.getParentBlock( this.draggingBlockId );
    if ( parent ) {
        store.removeInnerBlock( parent.id, this.draggingBlockId );
    } else {
        store.removeBlock( this.draggingBlockId );
    }

    // Add as inner block of the target parent
    store.addInnerBlock( parentId, {
        type: block.type,
        attributes: { ...block.attributes },
        innerBlocks: [ ...( block.innerBlocks || [] ) ],
    } );

    this.draggingBlockId = null;
} );
```

---

## Step 8: Update Tests

**File:** `tests/Unit/Blocks/Text/QuoteBlockTest.php`

No test changes needed — the existing tests verify Blade rendering and block metadata. The runtime inner blocks behavior is JavaScript-based in the editor shell and not unit-testable with Pest.

---

## Files Changed Summary

| File | Action |
|------|--------|
| `resources/views/components/editor-state.blade.php` | **Edit** — Make `getBlock()` recursive, add `getParentBlock()`, add `addInnerBlockAfter()` |
| `resources/views/components/editor-canvas.blade.php` | **Edit** — Update Enter key handler for inner blocks context |
| `artisanpack-ui/.../editor-shell.blade.php` | **Edit** — Update `getBlockHtml()` for inner blocks rendering, update `handleInput()`, update demo data, add inner block inserter buttons, add drag-drop handlers for inner blocks |

---

## Verification

1. Run existing tests: `./vendor/bin/pest --filter=QuoteBlock`
2. Run InnerBlocks tests: `./vendor/bin/pest --filter=InnerBlocks`
3. Run full test suite: `./vendor/bin/pest`
4. Manual browser testing:
   - Quote block should show with blockquote styling (left border, italic)
   - Initial quote should show "The visual editor..." as an inner paragraph block
   - Pressing Enter inside the quote should add a new paragraph inside the quote (not on the canvas)
   - Citation "ArtisanPack Team" should appear below the inner blocks
   - Citation toggle button in toolbar should show/hide the citation
   - Typing in inner blocks should sync content to the store
   - "+" add block buttons should appear between inner blocks and after the last inner block
   - Clicking "+" inside the quote should add a new paragraph inner block
   - Dragging a block from the canvas and dropping it on the quote's inner blocks area should move it inside the quote
