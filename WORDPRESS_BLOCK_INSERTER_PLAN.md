# WordPress-Style Block Inserter Implementation Plan

## Architecture Overview

The current Visual Editor uses a bottom-positioned typing area with slash command detection. The plan transforms this into a WordPress-style inline block inserter where:

1. **Empty paragraph blocks** will display a "+" button positioned to the right (similar to WordPress)
2. **Slash commands** will work in empty paragraph blocks after backspacing to the beginning
3. **Visual consistency** - the "+" button and block appearance matches WordPress UX patterns

The implementation will leverage existing Alpine components (`slashCommandInput`) and the inner block appender pattern, adapting them for root-level block insertion.

---

## Visual Design Changes

### Current State
- Typing area: Separate contenteditable div at bottom of canvas
- Styling: Dashed border, distinct from regular blocks
- Position: Fixed at bottom after all blocks

### Target State (WordPress-style)
- **Empty paragraph block appearance**: Looks like a regular paragraph block with placeholder text
- **"+" Button placement**: Positioned to the right side of empty blocks, appears on hover
- **Dropdown menu**: Opens on "+" click, same menu as slash commands
- **Visual hierarchy**: Seamless integration with existing blocks

### Implementation Approach

**Step 1: Transform typing area visual styling**

The typing area (lines 1562-1573 in canvas.blade.php) will be restyled to mimic an empty paragraph block:

```blade
{{-- Current styling (REMOVE) --}}
class="min-h-[2.5rem] rounded border border-dashed border-gray-300 bg-white px-3 py-2..."

{{-- New styling (APPLY) --}}
class="ve-canvas-block group relative rounded px-4 py-2 transition-colors min-h-[2.5rem]"
```

**Step 2: Add "+" button to the right side**

Create a floating "+" button container positioned absolutely to the right:

```blade
{{-- Add this structure inside the typing area wrapper (after line 1520) --}}
<div class="relative">
    {{-- "+" Button (appears on hover) --}}
    <div class="absolute right-0 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 transition-opacity">
        <button
            type="button"
            @click="toggleMenu()"
            class="flex h-6 w-6 items-center justify-center rounded border border-gray-300 bg-white text-gray-600 hover:border-blue-500 hover:bg-blue-50 hover:text-blue-600 shadow-sm"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>

    {{-- Editable input continues here... --}}
</div>
```

**Step 3: Update placeholder text**

Change placeholder from "Type to add a block, or type / for commands..." to match WordPress:

```blade
data-placeholder="{{ __( 'Type / to choose a block' ) }}"
```

---

## Slash Command Integration in Empty Blocks

### Current Limitation
Slash commands only work in the separate typing area at the bottom. They do NOT work inside contenteditable blocks during inline editing.

### Target Behavior
When a user backspaces to the beginning of an empty paragraph block, enable slash command detection in that block.

### Implementation Strategy

**Challenge**: The `slashCommandInput` Alpine component is currently tightly coupled to the typing area. We need to make slash detection work in ANY contenteditable element.

**Solution**: Create a reusable Alpine component for slash detection that can be attached to any contenteditable element.

**Step 1: Extract slash command logic into reusable component**

Create a new Alpine data component `inlineSlashCommands` (add after line 1882):

```javascript
Alpine.data( 'inlineSlashCommands', ( { blocks, blockId } ) => ( {
    allBlocks: blocks,
    menuOpen: false,
    slashQuery: '',
    activeIndex: 0,
    flatItems: [],

    // Reuse the same filteredBlocks computed from slashCommandInput
    get filteredBlocks() {
        // ... same implementation as slashCommandInput
    },

    handleInput( event, editorEl ) {
        let text = editorEl.textContent;

        // Detect slash at beginning
        if ( text.startsWith( '/' ) && text.length > 0 ) {
            this.slashQuery = text.substring( 1 );
            if ( !this.menuOpen ) {
                this.menuOpen = true;
                this.activeIndex = 0;
            }
        } else if ( this.menuOpen && !text.startsWith( '/' ) ) {
            this.closeMenu();
        }
    },

    handleKeydown( event, editorEl ) {
        if ( !this.menuOpen ) return;

        if ( 'ArrowDown' === event.key ) {
            event.preventDefault();
            this.activeIndex = Math.min( this.activeIndex + 1, this.flatItems.length - 1 );
        } else if ( 'ArrowUp' === event.key ) {
            event.preventDefault();
            this.activeIndex = Math.max( this.activeIndex - 1, 0 );
        } else if ( 'Enter' === event.key ) {
            event.preventDefault();
            if ( this.flatItems.length > 0 ) {
                this.selectBlock( this.flatItems[ this.activeIndex ], editorEl );
            }
        } else if ( 'Escape' === event.key ) {
            this.closeMenu();
        }
    },

    selectBlock( block, editorEl ) {
        // Delete current block and insert selected block type
        $wire.replaceBlockWithType( blockId, block.type, block.variation );
        this.closeMenu();
    },

    closeMenu() {
        this.menuOpen = false;
        this.slashQuery = '';
        this.activeIndex = 0;
    },

    // Additional methods for positioning menu, etc.
} ) );
```

**Step 2: Add slash detection to empty paragraph blocks**

Modify block-renderer.blade.php (lines 104-131) to add slash command detection when editing empty text blocks:

```blade
@if ( $isEditing && $isRichText && 'text' === $blockType )
    {{-- Wrap in container with slash command support --}}
    <div
        x-data="inlineSlashCommands( { blocks: @js( $this->slashMenuBlocks ), blockId: '{{ $blockId }}' } )"
        class="relative"
    >
        {{-- Slash menu (positioned above) --}}
        <div
            x-show="menuOpen"
            x-cloak
            class="absolute bottom-full left-0 z-50 mb-1 max-h-72 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
        >
            {{-- Same menu structure as typing area --}}
        </div>

        <div
            x-ref="editor"
            contenteditable="true"
            @input="handleInput( $event, $el )"
            @keydown="handleKeydown( $event, $el )"
            {{-- Existing attributes continue... --}}
        >
            {!! kses( $editContent ) !!}
        </div>
    </div>
@endif
```

**Step 3: Add Livewire method to replace block**

Add a new method in canvas.blade.php (after line 980):

```php
/**
 * Replace a block with a new block type.
 *
 * Deletes the current block and inserts a new one in its place.
 *
 * @since 2.1.0
 *
 * @param string      $blockId   The ID of the block to replace.
 * @param string      $newType   The new block type.
 * @param string|null $variation Optional variation name.
 *
 * @return void
 */
public function replaceBlockWithType( string $blockId, string $newType, ?string $variation = null ): void
{
    $location = $this->getBlockLocation( $blockId );

    if ( null === $location ) {
        return;
    }

    // Create new block
    $newBlock = [
        'id'       => str_replace( '.', '-', uniqid( 've-block-', true ) ),
        'type'     => $newType,
        'content'  => [],
        'settings' => [],
    ];

    // Apply variation if provided
    if ( null !== $variation ) {
        $registry        = veBlocks();
        $variationConfig = $registry->getVariation( $newType, $variation );

        if ( null !== $variationConfig ) {
            $newBlock['settings']['_variation'] = $variation;

            if ( isset( $variationConfig['inner_blocks'] ) ) {
                $newBlock['content']['inner_blocks'] = $variationConfig['inner_blocks'];
            }
        }
    }

    // Replace the block in the same position
    $blocks = $this->blocks;
    $parentPath = $location['parent_path'];

    if ( null !== $parentPath ) {
        $siblings = data_get( $blocks, $parentPath );
    } else {
        $siblings = $blocks;
    }

    $siblings[ $location['index'] ] = $newBlock;

    if ( null !== $parentPath ) {
        data_set( $blocks, $parentPath, $siblings );
    } else {
        $blocks = $siblings;
    }

    $this->blocks = $blocks;

    // Start editing the new block
    $this->editingBlockId = $newBlock['id'];
    $this->activeBlockId  = $newBlock['id'];
    $this->focusingNewBlock = true;

    $this->notifyBlocksUpdated();
    $this->dispatch( 'focus-block', blockId: $newBlock['id'] );
}
```

---

## File Modifications

### 1. `/Users/jacobmartella/Desktop/ArtisanPack UI Packages/visual-editor/resources/views/livewire/canvas.blade.php`

**Lines to Modify:**

**A. Lines 1515-1575 - Transform typing area appearance**

```blade
{{-- BEFORE: Distinct typing area styling --}}
<div
    x-data="slashCommandInput( { blocks: @js( $this->slashMenuBlocks ) } )"
    wire:ignore
    class="ve-typing-area relative mt-2"
>
    {{-- ... slash menu ... --}}

    <div
        x-ref="typingInput"
        contenteditable="true"
        @input="handleInput( $event )"
        @keydown="handleKeydown( $event )"
        class="min-h-[2.5rem] rounded border border-dashed border-gray-300 bg-white px-3 py-2 text-sm text-gray-600 outline-none transition-colors focus:border-blue-400 focus:ring-1 focus:ring-blue-200"
        data-placeholder="{{ __( 'Type to add a block, or type / for commands...' ) }}"
    ></div>
</div>

{{-- AFTER: WordPress-style block appearance with + button --}}
<div
    x-data="slashCommandInput( { blocks: @js( $this->slashMenuBlocks ) } )"
    wire:ignore
    class="ve-typing-area ve-canvas-block group relative rounded px-4 py-2 transition-colors mt-2 hover:ring-2 hover:ring-blue-200"
>
    {{-- Slash menu (unchanged) --}}
    <div
        x-show="menuOpen"
        {{-- ... existing menu markup ... --}}
    ></div>

    {{-- Content container with + button --}}
    <div class="relative flex items-start gap-2">
        {{-- Editable input --}}
        <div
            x-ref="typingInput"
            contenteditable="true"
            @input="handleInput( $event )"
            @keydown="handleKeydown( $event )"
            @keydown.escape.prevent="closeMenu()"
            @blur="handleBlur()"
            @focus="handleFocus()"
            class="flex-1 min-h-[2.5rem] outline-none"
            data-placeholder="{{ __( 'Type / to choose a block' ) }}"
        ></div>

        {{-- + Button (appears on hover) --}}
        <button
            type="button"
            @click.stop="toggleMenuFromButton()"
            class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity flex h-6 w-6 items-center justify-center rounded border border-gray-300 bg-white text-gray-600 hover:border-blue-500 hover:bg-blue-50 hover:text-blue-600 shadow-sm"
            title="{{ __( 'Add block' ) }}"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>
</div>
```

**B. Lines 1754-1882 - Update slashCommandInput component**

Add a `toggleMenuFromButton` method:

```javascript
Alpine.data( 'slashCommandInput', ( { blocks } ) => ( {
    // ... existing properties ...

    toggleMenuFromButton() {
        if ( this.menuOpen ) {
            this.closeMenu();
        } else {
            this.menuOpen = true;
            this.activeIndex = 0;
            this.$nextTick( () => {
                if ( this.$refs.typingInput ) {
                    this.$refs.typingInput.focus();
                }
            } );
        }
    },

    // ... rest of existing methods ...
} ) );
```

**C. After line 882 - Add replaceBlockWithType method**

```php
/**
 * Replace a block with a new block type.
 *
 * @since 2.1.0
 *
 * @param string      $blockId   Block ID to replace.
 * @param string      $newType   New block type.
 * @param string|null $variation Optional variation.
 *
 * @return void
 */
public function replaceBlockWithType( string $blockId, string $newType, ?string $variation = null ): void
{
    // Implementation shown above in Step 3
}
```

### 2. `/Users/jacobmartella/Desktop/ArtisanPack UI Packages/visual-editor/resources/views/livewire/partials/block-renderer.blade.php`

**Lines to Modify:**

**A. Lines 182-194 - Add slash command support to empty text blocks**

```blade
@case ( 'text' )
    @php
        $dropCap        = (bool) ( $block['settings']['drop_cap'] ?? false );
        $dropCapClasses = $dropCap ? 'first-letter:float-left first-letter:mr-2 first-letter:text-5xl first-letter:font-bold first-letter:leading-none' : '';
        $isEmpty        = '' === ( $block['content']['text'] ?? '' );
    @endphp

    @if ( $isActive && $isEmpty && !$isEditing )
        {{-- Empty paragraph block with slash command support --}}
        <div
            x-data="{ showInserter: false }"
            @mouseenter="showInserter = true"
            @mouseleave="showInserter = false"
            class="relative flex items-start gap-2 min-h-[2.5rem]"
        >
            <div class="flex-1 prose prose-sm max-w-none {{ $dropCapClasses }}">
                <p class="italic text-gray-400">{{ __( 'Type / to choose a block' ) }}</p>
            </div>

            {{-- + Button --}}
            <button
                type="button"
                x-show="showInserter"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                @click.stop="$wire.startInlineEdit( '{{ $blockId }}' ); $nextTick( () => { document.querySelector( '[x-ref=editor]' )?.focus() } )"
                class="flex-shrink-0 flex h-6 w-6 items-center justify-center rounded border border-gray-300 bg-white text-gray-600 hover:border-blue-500 hover:bg-blue-50 hover:text-blue-600 shadow-sm"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </button>
        </div>
    @else
        {{-- Normal display mode --}}
        <div class="prose prose-sm max-w-none {{ $dropCapClasses }}">
            @if ( '' !== ( $block['content']['text'] ?? '' ) )
                {!! kses( $block['content']['text'] ) !!}
            @else
                <p class="italic text-gray-400">{{ __( 'Type text...' ) }}</p>
            @endif
        </div>
    @endif
    @break
```

**B. Lines 73-131 - Add inline slash detection when editing empty text blocks**

```blade
@if ( $isEditing )
    @if ( $isRichText )
        @php
            // ... existing PHP setup ...
            $isTextBlock = 'text' === $blockType;
            $isEmpty = '' === trim( $editContent );
        @endphp

        @if ( $isTextBlock && $isEmpty )
            {{-- Text block with slash command support --}}
            <div
                x-data="inlineSlashCommands( {
                    blocks: @js( $this->slashMenuBlocks ),
                    blockId: '{{ $blockId }}'
                } )"
                class="relative"
            >
                {{-- Slash menu --}}
                <div
                    x-show="menuOpen"
                    x-transition
                    @click.outside="closeMenu()"
                    x-cloak
                    class="absolute bottom-full left-0 z-50 mb-1 max-h-72 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                >
                    {{-- Reuse slash menu markup from typing area --}}
                    <template x-for="( category, catIdx ) in filteredBlocks" :key="category.key">
                        <div>
                            <div class="sticky top-0 bg-gray-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500" x-text="category.name"></div>
                            <template x-for="( block, blockIdx ) in category.blocks" :key="`${category.key}-${blockIdx}`">
                                <button
                                    type="button"
                                    @click="selectBlock( block, $refs.editor )"
                                    :class="{ 'bg-blue-50 text-blue-700': activeIndex === getFlatIndex( catIdx, blockIdx ) }"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50"
                                >
                                    <span x-text="block.name"></span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Editable element --}}
                <{{ $editTag }}
                    x-ref="editor"
                    contenteditable="true"
                    @input="handleInput( $event, $el )"
                    @keydown="handleKeydown( $event, $el )"
                    {{-- ... existing inline editing attributes ... --}}
                    class="{{ $richTextClasses }}"
                >{!! kses( $editContent ) !!}</{{ $editTag }}>
            </div>
        @else
            {{-- Non-empty text block or other rich text blocks (existing implementation) --}}
            <{{ $editTag }}
                x-ref="editor"
                {{-- ... existing attributes ... --}}
            >{!! kses( $editContent ) !!}</{{ $editTag }}>
        @endif
    @else
        {{-- Plain text edit mode (unchanged) --}}
    @endif
@endif
```

**C. After line 1882 in canvas.blade.php - Add inlineSlashCommands Alpine component**

```javascript
Alpine.data( 'inlineSlashCommands', ( { blocks, blockId } ) => ( {
    allBlocks: blocks,
    menuOpen: false,
    slashQuery: '',
    activeIndex: 0,
    flatItems: [],

    get filteredBlocks() {
        let categories = this.allBlocks;

        if ( '' !== this.slashQuery ) {
            let query = this.slashQuery.toLowerCase();

            categories = this.allBlocks
                .map( ( cat ) => ( {
                    ...cat,
                    blocks: cat.blocks.filter( ( b ) => {
                        let nameMatch = b.name.toLowerCase().includes( query );
                        let kwMatch = ( b.keywords || [] ).some(
                            ( kw ) => kw.toLowerCase().includes( query )
                        );
                        return nameMatch || kwMatch;
                    } ),
                } ) )
                .filter( ( cat ) => cat.blocks.length > 0 );
        }

        this.flatItems = [];
        categories.forEach( ( cat, catIdx ) => {
            cat.blocks.forEach( ( block, blockIdx ) => {
                this.flatItems.push( {
                    catIdx,
                    blockIdx,
                    type: block.type,
                    variation: block.variation || null,
                    name: block.name,
                } );
            } );
        } );

        return categories;
    },

    getFlatIndex( catIdx, blockIdx ) {
        return this.flatItems.findIndex(
            ( item ) => item.catIdx === catIdx && item.blockIdx === blockIdx
        );
    },

    handleInput( event, editorEl ) {
        let text = editorEl.textContent;

        if ( text.startsWith( '/' ) ) {
            this.slashQuery = text.substring( 1 );
            if ( !this.menuOpen ) {
                this.menuOpen = true;
                this.activeIndex = 0;
            }
        } else if ( this.menuOpen ) {
            this.closeMenu();
        }
    },

    handleKeydown( event, editorEl ) {
        if ( !this.menuOpen ) return;

        if ( 'ArrowDown' === event.key ) {
            event.preventDefault();
            this.activeIndex = Math.min( this.activeIndex + 1, this.flatItems.length - 1 );
        } else if ( 'ArrowUp' === event.key ) {
            event.preventDefault();
            this.activeIndex = Math.max( this.activeIndex - 1, 0 );
        } else if ( 'Enter' === event.key ) {
            event.preventDefault();
            if ( this.flatItems.length > 0 ) {
                this.selectBlock( this.flatItems[ this.activeIndex ], editorEl );
            }
        } else if ( 'Escape' === event.key ) {
            this.closeMenu();
        }
    },

    selectBlock( block, editorEl ) {
        window.veNavigating = true;
        $wire.replaceBlockWithType( blockId, block.type, block.variation );
        this.closeMenu();
    },

    closeMenu() {
        this.menuOpen = false;
        this.slashQuery = '';
        this.activeIndex = 0;
    },
} ) );
```

---

## Implementation Steps

### Step 1: Transform Typing Area Visual Design (30 min)

1. **File**: `canvas.blade.php` lines 1515-1575
2. **Changes**:
   - Replace dashed border styling with block-like appearance
   - Add flex container with "+" button
   - Update placeholder text
   - Add hover effect for "+" button visibility
3. **Test**: Verify typing area looks like empty paragraph block
4. **Verification**:
   - Load editor page
   - Check if typing area has block-like appearance
   - Hover over area to see "+" button appear

### Step 2: Add Toggle Method to Slash Component (15 min)

1. **File**: `canvas.blade.php` lines 1754-1882
2. **Changes**:
   - Add `toggleMenuFromButton()` method to `slashCommandInput`
   - Method opens menu and focuses input
3. **Test**: Click "+" button opens slash menu
4. **Verification**:
   - Click "+" button
   - Menu should open
   - Input should be focused

### Step 3: Create Inline Slash Commands Component (45 min)

1. **File**: `canvas.blade.php` after line 1882
2. **Changes**:
   - Add complete `inlineSlashCommands` Alpine data component
   - Implement all methods: handleInput, handleKeydown, selectBlock
3. **Test**: Component compiles without errors
4. **Verification**:
   - Check browser console for JavaScript errors
   - Alpine should register the component

### Step 4: Add replaceBlockWithType Livewire Method (30 min)

1. **File**: `canvas.blade.php` after line 882
2. **Changes**:
   - Add complete method implementation
   - Handle location finding, block creation, variation application
   - Set editing state and dispatch focus event
3. **Test**: Method exists and doesn't break Livewire compilation
4. **Verification**:
   - Check Livewire doesn't throw errors
   - Method should be callable from Alpine

### Step 5: Add Slash Detection to Empty Paragraph Display (20 min)

1. **File**: `block-renderer.blade.php` lines 182-194
2. **Changes**:
   - Detect empty state
   - Show "+" button on hover when empty and active
   - Update placeholder text
3. **Test**: Empty paragraph blocks show "+" button
4. **Verification**:
   - Create empty paragraph block
   - Select it
   - Hover - "+" button should appear

### Step 6: Add Inline Slash Detection to Editing Mode (45 min)

1. **File**: `block-renderer.blade.php` lines 73-131
2. **Changes**:
   - Wrap empty text block editing with `inlineSlashCommands` component
   - Add slash menu markup
   - Wire up input and keydown handlers
3. **Test**: Typing "/" in empty block opens menu
4. **Verification**:
   - Double-click empty paragraph
   - Type "/"
   - Menu should appear above block

### Step 7: Test Slash Command Selection (30 min)

1. **Actions**:
   - Type "/" in empty block
   - Navigate menu with arrows
   - Press Enter to select
2. **Expected**: Block is replaced with selected type
3. **Verification**:
   - Block changes type correctly
   - New block enters edit mode
   - Focus is set correctly

### Step 8: Test Complete Flow (30 min)

1. **Create new content**:
   - Add paragraph → type "/" → select heading
   - Press Enter → new paragraph created
   - Backspace to empty → type "/" → select list
2. **Verify**:
   - No JavaScript errors
   - Focus preservation works
   - Livewire morphing doesn't break state
3. **Edge cases**:
   - Escape key closes menu
   - Click outside closes menu
   - Menu positioning correct

### Step 9: Polish and Refinement (30 min)

1. **Adjust "+" button positioning** if needed
2. **Fine-tune menu positioning** (ensure doesn't overflow)
3. **Test accessibility** (keyboard navigation, ARIA labels)
4. **Browser testing** (Chrome, Firefox, Safari)

---

## Testing Strategy

### Unit Testing

**Test 1: Typing Area Visual Transformation**
- Load editor
- Verify typing area has block-like styling
- Verify "+" button appears on hover
- Verify placeholder text matches WordPress pattern

**Test 2: "+" Button Functionality**
- Click "+" button
- Verify slash menu opens
- Verify input is focused
- Verify menu shows all available blocks

**Test 3: Slash Detection in Empty Blocks**
- Create empty paragraph block
- Start editing (double-click)
- Type "/"
- Verify menu appears above block

**Test 4: Menu Navigation**
- Open menu with "/"
- Use Arrow Down/Up keys
- Verify active item highlights
- Press Enter
- Verify correct block type inserted

**Test 5: Block Replacement**
- Start with empty paragraph
- Type "/heading"
- Select "Heading" from menu
- Verify paragraph is replaced with heading block
- Verify new block enters edit mode

### Integration Testing

**Test 6: Complete Workflow**
1. Create new content
2. Type content in typing area → paragraph created
3. Press Enter → new paragraph
4. Backspace to empty
5. Type "/" → menu appears
6. Select list → paragraph becomes list
7. Type list items
8. Press Enter → new list item
9. Backspace to empty
10. Type "/" → select quote
11. Verify quote block created

**Test 7: Focus Preservation**
- Insert block via slash command
- Verify focus stays in editor
- Verify no blur to typing area
- Verify `window.veFocusingBlock` flag works correctly

**Test 8: Livewire Morphing Compatibility**
- Make several block changes
- Verify state preserved through morphs
- Verify menu doesn't reopen unexpectedly
- Verify wire:key prevents focus loss

### Edge Case Testing

**Test 9: Menu Positioning**
- Create block near top of canvas
- Open slash menu
- Verify menu doesn't overflow viewport
- Verify menu is visible and accessible

**Test 10: Escape Key**
- Open slash menu (via "/" or "+")
- Press Escape
- Verify menu closes
- Verify focus returns to editor

**Test 11: Click Outside**
- Open slash menu
- Click outside menu area
- Verify menu closes
- Verify no state corruption

**Test 12: Empty Content Edge Cases**
- Type "/" then backspace
- Verify menu closes
- Type "/test" then delete all
- Verify menu closes when "/" removed

---

## Potential Challenges & Mitigation

### Challenge 1: Alpine Component Scope Conflicts

**Issue**: Both `slashCommandInput` and `inlineSlashCommands` manage similar state. Could lead to conflicts if both are active simultaneously.

**Mitigation**:
- Only one component is active at a time (typing area vs inline block)
- Use separate x-data scopes
- Ensure menu close handlers don't interfere
- Test: Open typing area menu, then start editing block - verify only one menu visible

### Challenge 2: Focus Management During Block Replacement

**Issue**: When replacing a block, focus needs to transfer from old block to new block seamlessly. Livewire morphing can interfere.

**Mitigation**:
- Use existing `window.veFocusingBlock` flag
- Set flag before calling `replaceBlockWithType`
- Dispatch `focus-block` event with new block ID
- Existing focus polling system handles the rest
- Test: Verify focus moves to new block after replacement

### Challenge 3: Menu Positioning in Scrolled Containers

**Issue**: Slash menu uses absolute positioning. If canvas is scrolled, menu might appear off-screen.

**Mitigation**:
- Use `bottom-full` positioning (menu appears above)
- Add `scrollIntoView` call when menu opens
- Ensure z-index hierarchy is correct (z-50)
- Test: Scroll canvas, open menu, verify visibility

### Challenge 4: Wire:key and DOM Preservation

**Issue**: The typing area has `wire:ignore` to prevent Livewire from morphing it. Need to ensure `wire:key` works correctly for blocks with inline slash.

**Mitigation**:
- Each block already has unique `wire:key="block-{{ $blockId }}"`
- Don't add `wire:ignore` to inline slash components
- Let Livewire morph the content naturally
- Test: Create/delete blocks, verify no duplicate keys

### Challenge 5: Slash Query State Persistence

**Issue**: When slash menu is open and Livewire morphs the DOM, the query state could be lost.

**Mitigation**:
- Alpine's reactivity should preserve state within x-data scope
- If issues arise, use Alpine.store for shared state
- Test: Type "/hea", verify filtering works, verify state persists

### Challenge 6: Empty Block Detection Accuracy

**Issue**: Need to accurately detect when a block is "empty" for showing "+" button and enabling slash commands.

**Mitigation**:
- Check `'' === trim( $block['content']['text'] ?? '' )`
- In edit mode, check `editorEl.textContent` directly
- Don't rely on HTML presence (might have `<br>` tags)
- Test: Create block, add space, delete - verify empty detection

### Challenge 7: Keyboard Navigation Conflicts

**Issue**: Arrow keys are used for both menu navigation and cursor movement in contenteditable.

**Mitigation**:
- Only intercept arrows when `menuOpen === true`
- Let browser handle arrows when menu closed
- Use `event.preventDefault()` only in menu context
- Test: Type with arrows, open menu, navigate menu, close, type again

### Challenge 8: Multiple Blocks Editing Simultaneously

**Issue**: If user somehow triggers editing in multiple blocks, state could conflict.

**Mitigation**:
- Canvas already manages `editingBlockId` (single source of truth)
- Starting edit on new block closes previous edit
- Inline slash component bound to specific blockId
- Test: Try to edit two blocks at once - verify only one active

---

## Performance Considerations

### Optimization 1: Computed Property Caching

Both `slashCommandInput` and `inlineSlashCommands` use `filteredBlocks` computed property. Alpine caches these automatically, so filtering only runs when `slashQuery` changes.

**No action needed** - Alpine handles this efficiently.

### Optimization 2: Menu Rendering

The slash menu contains potentially 50+ block types. Rendering all of them can be expensive.

**Current approach**: Use `x-show` instead of `x-if` for categories. This keeps DOM but hides it.

**Consideration**: If performance becomes an issue, switch to `x-if` to completely remove hidden categories from DOM.

### Optimization 3: Event Listener Management

Each inline slash component adds event listeners to the editor element.

**Current approach**: Alpine automatically manages cleanup when component is destroyed.

**Monitor**: Watch for memory leaks if many blocks created/destroyed rapidly.

---

## Rollback Plan

If critical issues arise during implementation:

### Rollback Step 1: Revert Visual Changes Only

1. Restore typing area original styling (lines 1515-1575)
2. Remove "+" button markup
3. Keep slash command functionality intact

**Result**: Editor works as before, just without visual polish.

### Rollback Step 2: Disable Inline Slash Commands

1. Remove `inlineSlashCommands` component from canvas.blade.php
2. Remove inline slash detection from block-renderer.blade.php
3. Keep typing area slash commands working

**Result**: Slash commands only work in typing area (current behavior).

### Rollback Step 3: Complete Revert

1. Git checkout original canvas.blade.php
2. Git checkout original block-renderer.blade.php
3. Remove `replaceBlockWithType` method

**Result**: Complete revert to working state.

---

## Summary

**Total Implementation Time**: ~4 hours (3 hours implementation + 1 hour testing)

**Files Modified**: 2
- `canvas.blade.php` - Typing area, Alpine components, Livewire methods
- `block-renderer.blade.php` - Empty block display, inline slash detection

**New Features**:
1. WordPress-style typing area with "+" button
2. Slash commands in empty paragraph blocks
3. Block type replacement with focus preservation

**Critical Dependencies**:
- Existing `wire:key="editor-canvas"` for state preservation
- Existing `window.veFocusingBlock` flag for focus management
- Existing slash menu structure and block registry

**Success Criteria**:
- "+" button appears on hover in typing area and empty blocks
- "/" opens slash menu in typing area and empty paragraphs
- Block replacement works smoothly with focus preserved
- All existing tests pass
- No performance degradation
