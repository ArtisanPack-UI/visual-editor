<?php

declare(strict_types=1);

/**
 * Visual Editor - Canvas
 *
 * The main editing surface where content blocks are rendered.
 * Supports drag-and-drop reordering of blocks, inline editing,
 * zoom controls, grid overlay, and keyboard navigation.
 *
 *
 * @since      1.0.0
 */

use ArtisanPackUI\VisualEditor\Models\UserSection;
use ArtisanPackUI\VisualEditor\Registries\SectionRegistry;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    /**
     * The content blocks data.
     *
     * @since 1.0.0
     */
    public array $blocks = [];

    /**
     * The ID of the currently active block.
     *
     * @since 1.0.0
     */
    public ?string $activeBlockId = null;

    /**
     * The current zoom level as a percentage (50-200).
     *
     * @since 1.1.0
     */
    public int $zoomLevel = 100;

    /**
     * Whether the alignment grid overlay is visible.
     *
     * @since 1.1.0
     */
    public bool $showGrid = false;

    /**
     * The ID of the block currently in inline edit mode.
     *
     * @since 1.1.0
     */
    public ?string $editingBlockId = null;

    /**
     * Flag to indicate we're focusing a newly added block.
     * Prevents blur handlers from clearing editingBlockId prematurely.
     *
     * @since 1.6.0
     */
    public bool $focusingNewBlock = false;

    // ──────────────────────────────────────────────────────────
    // Block Selection
    // ──────────────────────────────────────────────────────────

    /**
     * Select a block in the canvas.
     *
     * @since 1.0.0
     *
     * @param  string  $blockId  The block ID to select.
     */
    public function selectBlock(string $blockId): void
    {
        $this->activeBlockId = $blockId;
        $this->editingBlockId = null;
        $this->dispatch('block-selected', blockId: $blockId);
    }

    /**
     * Handle block selection from external sources (e.g. layers tab).
     *
     * @since 1.4.0
     *
     * @param  string  $blockId  The block ID to select.
     */
    #[On('block-selected')]
    public function onBlockSelected(string $blockId): void
    {
        $this->activeBlockId = $blockId;

        // Don't clear editingBlockId if we're focusing a newly added block
        if (! $this->focusingNewBlock && $this->editingBlockId !== $blockId) {
            $this->editingBlockId = null;
        }
    }

    /**
     * Sync blocks from the editor (e.g. after layers reorder).
     *
     * @since 1.4.0
     *
     * @param  array  $blocks  The updated blocks array.
     */
    #[On('canvas-sync-blocks')]
    public function onCanvasSyncBlocks(array $blocks): void
    {
        $this->blocks = $blocks;
    }

    /**
     * Deselect all blocks.
     *
     * @since 1.1.0
     */
    public function deselectAll(): void
    {
        $this->activeBlockId = null;
        $this->editingBlockId = null;
    }

    // ──────────────────────────────────────────────────────────
    // Block Reordering
    // ──────────────────────────────────────────────────────────

    /**
     * Handle drag-and-drop reordering of blocks.
     *
     * Accepts an array of block IDs in the new order,
     * as provided by the x-drag-context drag:end event.
     * When parentBlockId is provided, reorders within that
     * container's inner blocks rather than the top-level array.
     *
     * @since 1.0.0
     *
     * @param  array  $orderedIds  The new block ID order.
     * @param  string|null  $parentBlockId  Optional parent container block ID.
     * @param  int  $slotIndex  Column slot index for columns blocks (-1 for non-columns).
     */
    public function reorderBlocks(array $orderedIds, ?string $parentBlockId = null, int $slotIndex = -1): void
    {
        if ($parentBlockId !== null) {
            $this->reorderInnerBlocks($orderedIds, $parentBlockId, $slotIndex);

            return;
        }

        $indexed = collect($this->blocks)->keyBy('id');
        $reordered = [];
        $seen = [];

        foreach ($orderedIds as $id) {
            if ($indexed->has($id)) {
                $reordered[] = $indexed->get($id);
                $seen[] = $id;
            }
        }

        // Append any blocks not in orderedIds to prevent data loss
        foreach ($this->blocks as $block) {
            if (! in_array($block['id'] ?? '', $seen, true)) {
                $reordered[] = $block;
            }
        }

        $this->blocks = $reordered;
        $this->notifyBlocksUpdated();
    }

    // ──────────────────────────────────────────────────────────
    // Block Insertion
    // ──────────────────────────────────────────────────────────

    /**
     * Handle a block insert event from the sidebar.
     *
     * Appends a new block directly to the flat blocks list. If a variation
     * is specified, applies the variation's default settings to the block.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The block type to insert.
     * @param  string|null  $variation  The variation name to apply (optional).
     */
    #[On('block-insert')]
    public function insertBlock(string $type, ?string $variation = null): void
    {
        $settings = [];

        // Apply variation settings if specified
        if ($variation !== null) {
            $variationConfig = veBlocks()->getVariation($type, $variation);

            if ($variationConfig !== null) {
                // Store the variation name
                $settings['_variation'] = $variation;

                // Apply variation attributes
                if (isset($variationConfig['attributes']['settings']) && is_array($variationConfig['attributes']['settings'])) {
                    $settings = array_merge($settings, $variationConfig['attributes']['settings']);
                }
            }
        }

        $this->blocks[] = [
            'id' => str_replace('.', '-', uniqid('ve-block-', true)),
            'type' => $type,
            'name' => $type,
            'content' => [],
            'settings' => $settings,
        ];

        $this->notifyBlocksUpdated();
    }

    /**
     * Insert a block into a container's inner blocks.
     *
     * Creates a new block and appends it to the specified parent
     * container's inner blocks array. For columns blocks, the
     * slotIndex specifies which column to insert into.
     *
     * @since 2.0.0
     *
     * @param  string  $type  The block type to insert.
     * @param  string  $parentBlockId  The parent container block ID.
     * @param  int  $slotIndex  Column slot index for columns blocks (-1 for non-columns).
     */
    public function insertBlockIntoContainer(string $type, string $parentBlockId, int $slotIndex = -1): void
    {
        $parentPath = $this->findBlockPath($parentBlockId, $this->blocks);

        if ($parentPath === null) {
            return;
        }

        $newBlock = [
            'id' => str_replace('.', '-', uniqid('ve-block-', true)),
            'type' => $type,
            'content' => [],
            'settings' => [],
        ];

        $blocks = $this->blocks;

        if ($slotIndex >= 0) {
            $innerKey = $parentPath.'.content.columns.'.$slotIndex.'.blocks';
            $currentInner = data_get($blocks, $innerKey, []);

            $currentInner[] = $newBlock;
            data_set($blocks, $innerKey, $currentInner);
        } else {
            $innerKey = $parentPath.'.content.inner_blocks';
            $currentInner = data_get($blocks, $innerKey, []);

            $currentInner[] = $newBlock;
            data_set($blocks, $innerKey, $currentInner);
        }

        $this->blocks = $blocks;
        $this->notifyBlocksUpdated();
    }

    /**
     * Handle a section insert event from the sidebar.
     *
     * Inserts the section's default blocks directly into the
     * flat blocks list without a section wrapper.
     *
     * @since 1.1.0
     *
     * @param  string  $type  The section type to insert.
     */
    #[On('section-insert')]
    public function insertSection(string $type): void
    {
        $registry = app(SectionRegistry::class);
        $config = $registry->get($type);

        if ($config !== null) {
            foreach ($config['default_blocks'] ?? [] as $blockDef) {
                $this->blocks[] = [
                    'id' => str_replace('.', '-', uniqid('ve-block-', true)),
                    'type' => $blockDef['type'] ?? 'text',
                    'content' => $blockDef['content'] ?? [],
                    'settings' => [],
                ];
            }
        }

        $this->notifyBlocksUpdated();
    }

    /**
     * Insert a user-created section pattern.
     *
     * Loads blocks from a UserSection record and inserts them
     * flat into the blocks list.
     *
     * @since 1.1.0
     *
     * @param  int  $userSectionId  The UserSection ID to insert.
     */
    #[On('user-section-insert')]
    public function insertUserSection(int $userSectionId): void
    {
        $userSection = UserSection::find($userSectionId);

        if ($userSection === null || empty($userSection->blocks)) {
            return;
        }

        foreach ($userSection->blocks as $blockDef) {
            $this->blocks[] = [
                'id' => str_replace('.', '-', uniqid('ve-block-', true)),
                'type' => $blockDef['type'] ?? 'text',
                'content' => $blockDef['content'] ?? [],
                'settings' => $blockDef['settings'] ?? [],
            ];
        }

        $userSection->increment('use_count');

        $this->notifyBlocksUpdated();
    }

    /**
     * Save current blocks as a user section pattern.
     *
     * Creates a new UserSection record from the current blocks array.
     *
     * @since 1.1.0
     *
     * @param  string  $name  The section name.
     * @param  string|null  $description  Optional section description.
     * @param  string|null  $category  Optional section category.
     */
    #[On('save-blocks-as-section')]
    public function saveBlocksAsSection(string $name, ?string $description = null, ?string $category = null): void
    {
        if (trim($name) === '' || empty($this->blocks)) {
            return;
        }

        abort_unless(auth()->check(), 403);

        UserSection::create([
            'user_id' => auth()->id(),
            'name' => trim($name),
            'description' => $description,
            'category' => $category,
            'blocks' => $this->blocks,
        ]);

        $this->dispatch('section-saved');
    }

    // ──────────────────────────────────────────────────────────
    // Block Move / Delete
    // ──────────────────────────────────────────────────────────

    /**
     * Move a block up by one position within its sibling array.
     *
     * Works for both top-level and nested blocks by using
     * recursive block location.
     *
     * @since 1.7.0
     *
     * @param  string  $blockId  The block ID to move up.
     */
    public function moveBlockUp(string $blockId): void
    {
        $location = $this->getBlockLocation($blockId);

        if ($location === null || $location['index'] === 0) {
            return;
        }

        $siblings = $this->getSiblingsArray($location['parentPath']);
        $index = $location['index'];

        $temp = $siblings[$index - 1];
        $siblings[$index - 1] = $siblings[$index];
        $siblings[$index] = $temp;

        $this->setSiblingsArray($location['parentPath'], $siblings);
        $this->notifyBlocksUpdated();
    }

    /**
     * Move a block down by one position within its sibling array.
     *
     * Works for both top-level and nested blocks by using
     * recursive block location.
     *
     * @since 1.7.0
     *
     * @param  string  $blockId  The block ID to move down.
     */
    public function moveBlockDown(string $blockId): void
    {
        $location = $this->getBlockLocation($blockId);

        if ($location === null) {
            return;
        }

        $siblings = $this->getSiblingsArray($location['parentPath']);
        $index = $location['index'];

        if ($index >= count($siblings) - 1) {
            return;
        }

        $temp = $siblings[$index + 1];
        $siblings[$index + 1] = $siblings[$index];
        $siblings[$index] = $temp;

        $this->setSiblingsArray($location['parentPath'], $siblings);
        $this->notifyBlocksUpdated();
    }

    /**
     * Change the heading level for a heading block.
     *
     * Searches recursively to support nested heading blocks.
     *
     * @since 1.7.0
     *
     * @param  string  $blockId  The block ID to update.
     * @param  string  $level  The new heading level (h1-h6).
     */
    public function changeHeadingLevel(string $blockId, string $level): void
    {
        if (! in_array($level, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            return;
        }

        $path = $this->findBlockPath($blockId, $this->blocks);

        if ($path === null) {
            return;
        }

        $blocks = $this->blocks;
        data_set($blocks, $path.'.content.level', $level);
        $this->blocks = $blocks;

        $this->notifyBlocksUpdated();
    }

    /**
     * Change the list style for a list block.
     *
     * Searches recursively to support nested list blocks.
     *
     * @since 1.10.0
     *
     * @param  string  $blockId  The block ID to update.
     * @param  string  $style  The new list style (bullet or number).
     */
    public function changeListStyle(string $blockId, string $style): void
    {
        if (! in_array($style, ['bullet', 'number'], true)) {
            return;
        }

        $path = $this->findBlockPath($blockId, $this->blocks);

        if ($path === null) {
            return;
        }

        $blocks = $this->blocks;
        data_set($blocks, $path.'.content.style', $style);
        $this->blocks = $blocks;

        $this->notifyBlocksUpdated();
    }

    /**
     * Update a block setting from the toolbar.
     *
     * @since 1.11.0
     *
     * @param  string  $blockId  The block ID to update.
     * @param  string  $key  The setting key to update.
     * @param  mixed  $value  The new value.
     */
    public function updateToolbarBlockSetting(string $blockId, string $key, mixed $value): void
    {
        $path = $this->findBlockPath($blockId, $this->blocks);

        if ($path === null) {
            return;
        }

        $blocks = $this->blocks;
        data_set($blocks, $path.'.settings.'.$key, $value);
        $this->blocks = $blocks;

        // Notify parent editor component that blocks have been updated
        $this->dispatch('toolbar-setting-updated', blockId: $blockId, key: $key, value: $value)->to('visual-editor::editor');
        $this->notifyBlocksUpdated();
    }

    /**
     * Delete a block from the canvas.
     *
     * Searches recursively to support deleting nested blocks
     * from within their parent containers.
     *
     * @since 1.1.0
     *
     * @param  string  $blockId  The block ID to delete.
     */
    public function deleteBlock(string $blockId): void
    {
        $location = $this->getBlockLocation($blockId);

        if ($location === null) {
            return;
        }

        $siblings = $this->getSiblingsArray($location['parentPath']);
        array_splice($siblings, $location['index'], 1);
        $this->setSiblingsArray($location['parentPath'], array_values($siblings));

        if ($this->activeBlockId === $blockId) {
            $this->activeBlockId = null;
            $this->editingBlockId = null;
        }

        $this->notifyBlocksUpdated();
    }

    /**
     * Apply a block variation to the active block.
     *
     * @since 2.0.0
     *
     * @param  string  $blockType  The block type.
     * @param  string  $variationName  The variation name.
     */
    public function applyBlockVariation(string $blockType, string $variationName): void
    {
        if ($this->activeBlockId === null) {
            return;
        }

        $path = $this->findBlockPath($this->activeBlockId, $this->blocks);
        $blocks = $this->blocks;
        $registry = veBlocks();

        if ($path === null || ! $registry->hasVariations($blockType)) {
            return;
        }

        $variation = $registry->getVariation($blockType, $variationName);

        if ($variation === null) {
            return;
        }

        // Store the variation name
        data_set($blocks, $path.'.settings._variation', $variationName);

        // Apply variation attributes
        if (isset($variation['attributes']['settings'])) {
            foreach ($variation['attributes']['settings'] as $key => $value) {
                data_set($blocks, $path.'.settings.'.$key, $value);
            }
        }

        $this->blocks = $blocks;
        $this->notifyBlocksUpdated();
        $this->dispatch('block-selected', blockId: $this->activeBlockId);
    }

    // ──────────────────────────────────────────────────────────
    // Zoom & Grid
    // ──────────────────────────────────────────────────────────

    /**
     * Set the canvas zoom level.
     *
     * @since 1.1.0
     *
     * @param  int  $level  The zoom level as a percentage (50-200).
     */
    public function setZoomLevel(int $level): void
    {
        $this->zoomLevel = max(50, min(200, $level));
    }

    /**
     * Toggle the alignment grid overlay.
     *
     * @since 1.1.0
     */
    public function toggleGrid(): void
    {
        $this->showGrid = ! $this->showGrid;
    }

    // ──────────────────────────────────────────────────────────
    // Inline Editing
    // ──────────────────────────────────────────────────────────

    /**
     * Enter inline edit mode for a text block.
     *
     * @since 1.1.0
     *
     * @param  string  $blockId  The block ID to edit inline.
     */
    public function startInlineEdit(string $blockId): void
    {
        $this->editingBlockId = $blockId;
        $this->activeBlockId = $blockId;
        $this->dispatch('block-selected', blockId: $blockId);
    }

    /**
     * Save inline edit and exit edit mode.
     *
     * Searches recursively to support nested block editing.
     *
     * @since 1.1.0
     *
     * @param  string  $blockId  The block ID being edited.
     * @param  string  $content  The updated text content.
     */
    public function saveInlineEdit(string $blockId, string $content): void
    {
        $path = $this->findBlockPath($blockId, $this->blocks);

        if ($path !== null) {
            $blocks = $this->blocks;
            data_set($blocks, $path.'.content.text', $content);
            $this->blocks = $blocks;
        }

        // Only clear editingBlockId if we're NOT focusing a newly added block
        // This prevents the contenteditable from disappearing during focus operations
        if (! $this->focusingNewBlock) {
            $this->editingBlockId = null;
        }

        $this->notifyBlocksUpdated();
    }

    /**
     * Save inline edit and navigate to an adjacent block.
     *
     * Combines content saving with navigation to prevent data loss
     * when moving between blocks via Tab or Arrow keys. Uses
     * depth-first traversal for navigation order.
     *
     * @since 1.6.0
     *
     * @param  string  $blockId  The block ID being edited.
     * @param  string  $content  The updated text content.
     * @param  string  $direction  The navigation direction ('up' or 'down').
     */
    public function saveAndNavigate(string $blockId, string $content, string $direction): void
    {
        // Save the current block's content
        $path = $this->findBlockPath($blockId, $this->blocks);

        if ($path !== null) {
            $blocks = $this->blocks;
            data_set($blocks, $path.'.content.text', $content);
            $this->blocks = $blocks;
        }

        // Build flat navigation list and find current position
        $navList = $this->buildFlatNavigationList($this->blocks);
        $currentIndex = array_search($blockId, $navList, true);

        if ($currentIndex === false) {
            $this->editingBlockId = null;
            $this->notifyBlocksUpdated();

            return;
        }

        $lastIndex = count($navList) - 1;

        // At the last block going down: exit edit mode and focus typing area.
        if ($direction === 'down' && $currentIndex >= $lastIndex) {
            $this->editingBlockId = null;
            $this->activeBlockId = null;
            $this->notifyBlocksUpdated();
            $this->dispatch('focus-typing-area');

            return;
        }

        // At the first block going up: stay on the same block.
        if ($direction === 'up' && $currentIndex === 0) {
            $this->notifyBlocksUpdated();

            return;
        }

        $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;
        $targetBlockId = $navList[$targetIndex] ?? '';
        $targetBlock = $this->findBlockRecursive($targetBlockId, $this->blocks);

        $this->activeBlockId = $targetBlockId;
        $this->editingBlockId = $targetBlock !== null && $this->isBlockEditable($targetBlock['type'] ?? '')
            ? $targetBlockId
            : null;

        $this->notifyBlocksUpdated();

        if ($this->editingBlockId !== null) {
            $this->dispatch('focus-block', blockId: $this->editingBlockId);
        }
    }

    // ──────────────────────────────────────────────────────────
    // Keyboard Navigation
    // ──────────────────────────────────────────────────────────

    /**
     * Handle keyboard navigation between blocks.
     *
     * Uses depth-first traversal to navigate through all blocks
     * including those nested inside containers.
     *
     * @since 1.1.0
     *
     * @param  string  $direction  The navigation direction ('up' or 'down').
     */
    #[On('canvas-navigate')]
    public function navigateBlocks(string $direction): void
    {
        if (empty($this->blocks)) {
            return;
        }

        $navList = $this->buildFlatNavigationList($this->blocks);
        $currentIndex = null;

        if ($this->activeBlockId !== null) {
            $currentIndex = array_search($this->activeBlockId, $navList, true);

            if ($currentIndex === false) {
                $currentIndex = null;
            }
        }

        if ($currentIndex === null) {
            $targetIndex = $direction === 'up'
                ? count($navList) - 1
                : 0;
        } elseif ($direction === 'up') {
            $targetIndex = max(0, $currentIndex - 1);
        } else {
            $targetIndex = min(count($navList) - 1, $currentIndex + 1);
        }

        $targetBlockId = $navList[$targetIndex] ?? '';
        $targetBlock = $this->findBlockRecursive($targetBlockId, $this->blocks);

        $this->activeBlockId = $targetBlockId;
        $this->editingBlockId = $targetBlock !== null && $this->isBlockEditable($targetBlock['type'] ?? '')
            ? $this->activeBlockId
            : null;

        if ($this->editingBlockId !== null) {
            $this->dispatch('focus-block', blockId: $this->editingBlockId);
        }
    }

    /**
     * Handle keyboard deletion of selected block.
     *
     * @since 1.1.0
     */
    #[On('canvas-delete-selected')]
    public function deleteSelected(): void
    {
        if ($this->activeBlockId !== null) {
            $this->deleteBlock($this->activeBlockId);
        }
    }

    // ──────────────────────────────────────────────────────────
    // Block Creation Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Insert a block with optional initial text content.
     *
     * Used by the typing area to create blocks from typed text
     * or from the slash command menu. Does not dispatch blocks-updated
     * to avoid the editor re-render cascade that would destroy the
     * canvas component and lose editing state. The blocks-updated
     * event is deferred until saveInlineEdit or insertBlockAfter.
     *
     * @since 1.2.0
     *
     * @param  string  $type  The block type to insert.
     * @param  string  $content  Optional initial text content.
     */
    public function insertBlockWithContent(string $type, string $content = '', ?string $variation = null): void
    {
        $newBlock = [
            'id' => str_replace('.', '-', uniqid('ve-block-', true)),
            'type' => $type,
            'content' => [],
            'settings' => [],
        ];

        if ($content !== '') {
            $newBlock['content']['text'] = $content;
        }

        // Apply variation if provided
        if ($variation !== null) {
            $registry = veBlocks();
            $variationConfig = $registry->getVariation($type, $variation);

            if ($variationConfig !== null) {
                $newBlock['settings']['_variation'] = $variation;

                if (isset($variationConfig['attributes']['settings'])) {
                    foreach ($variationConfig['attributes']['settings'] as $key => $value) {
                        $newBlock['settings'][$key] = $value;
                    }
                }
            }
        }

        $this->blocks[] = $newBlock;

        $this->activeBlockId = $newBlock['id'];
        $this->editingBlockId = $newBlock['id'];

        // CRITICAL: Set flag to prevent saveInlineEdit from clearing editingBlockId
        // during morphs and focus operations
        $this->focusingNewBlock = true;

        // Dispatch prepare-focus event
        $this->dispatch('prepare-focus', blockId: $newBlock['id']);

        // Dispatch blocks-updated and editor-sync-state immediately
        // Canvas has wire:key which preserves editingBlockId through editor morphs
        $this->notifyBlocksUpdated();

        // Don't auto-select in layers tab - let natural focus work
        // $this->dispatch( 'block-selected', blockId: $newBlock['id'] );
        $this->dispatch('focus-block', blockId: $newBlock['id']);
    }

    /**
     * Clear the focusing new block flag.
     *
     * Called from JavaScript after focus is successfully established
     * on a newly added block, allowing normal blur behavior to resume.
     *
     * @since 1.6.0
     */
    #[On('clear-focus-flag')]
    public function clearFocusFlag(): void
    {
        $this->focusingNewBlock = false;
        // Editor already synced immediately in insertBlockWithContent
    }

    /**
     * Replace a block with a new block type.
     *
     * Deletes the current block and inserts a new one in its place.
     *
     * @since 2.1.0
     *
     * @param  string  $blockId  The ID of the block to replace.
     * @param  string  $newType  The new block type.
     * @param  string|null  $variation  Optional variation name.
     */
    public function replaceBlockWithType(string $blockId, string $newType, ?string $variation = null): void
    {
        $location = $this->getBlockLocation($blockId);

        if ($location === null) {
            return;
        }

        // Create new block
        $newBlock = [
            'id' => str_replace('.', '-', uniqid('ve-block-', true)),
            'type' => $newType,
            'content' => [],
            'settings' => [],
        ];

        // Apply variation if provided
        if ($variation !== null) {
            $registry = veBlocks();
            $variationConfig = $registry->getVariation($newType, $variation);

            if ($variationConfig !== null) {
                $newBlock['settings']['_variation'] = $variation;

                if (isset($variationConfig['inner_blocks'])) {
                    $newBlock['content']['inner_blocks'] = $variationConfig['inner_blocks'];
                }
            }
        }

        // Replace the block in the same position
        $blocks = $this->blocks;
        $parentPath = $location['parent_path'];

        if ($parentPath !== null) {
            $siblings = data_get($blocks, $parentPath);
        } else {
            $siblings = $blocks;
        }

        $siblings[$location['index']] = $newBlock;

        if ($parentPath !== null) {
            data_set($blocks, $parentPath, $siblings);
        } else {
            $blocks = $siblings;
        }

        $this->blocks = $blocks;

        // Start editing the new block
        $this->editingBlockId = $newBlock['id'];
        $this->activeBlockId = $newBlock['id'];
        $this->focusingNewBlock = true;

        $this->notifyBlocksUpdated();
        $this->dispatch('focus-block', blockId: $newBlock['id']);
    }

    /**
     * Save current block content and insert a new text block after it.
     *
     * Triggered by pressing Enter inside an editable block. Saves the
     * current block's content, creates a new text block immediately
     * after it within the same sibling array, and enters edit mode
     * on the new block. Works for both top-level and nested blocks.
     *
     * @since 1.6.0
     *
     * @param  string  $blockId  The block ID being edited.
     * @param  string  $content  The current block's text content.
     */
    public function insertBlockAfter(string $blockId, string $content): void
    {
        $location = $this->getBlockLocation($blockId);

        if ($location === null) {
            return;
        }

        // Save the current block's content
        $path = $this->findBlockPath($blockId, $this->blocks);
        $blocks = $this->blocks;
        data_set($blocks, $path.'.content.text', $content);
        $this->blocks = $blocks;

        // Create new block and insert after current within same sibling array
        $newBlock = [
            'id' => str_replace('.', '-', uniqid('ve-block-', true)),
            'type' => 'text',
            'content' => [],
            'settings' => [],
        ];

        $siblings = $this->getSiblingsArray($location['parentPath']);
        array_splice($siblings, $location['index'] + 1, 0, [$newBlock]);
        $this->setSiblingsArray($location['parentPath'], $siblings);

        $this->activeBlockId = $newBlock['id'];
        $this->editingBlockId = $newBlock['id'];

        $this->notifyBlocksUpdated();
        $this->dispatch('focus-block', blockId: $newBlock['id']);
    }

    // ──────────────────────────────────────────────────────────
    // Computed Properties & Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Get available blocks grouped by category for the slash command menu.
     *
     * @since 1.2.0
     */
    #[Computed]
    public function slashMenuBlocks(): array
    {
        $registry = veBlocks();

        return $registry->getGroupedByCategory()->map(function ($category, $key) use ($registry) {
            $expandedBlocks = collect();

            foreach ($category['blocks'] as $blockType => $block) {
                // Skip blocks that have parent constraints (only allowed inside specific blocks)
                if (! empty($block['parent'])) {
                    continue;
                }

                if ($registry->hasVariations($blockType)) {
                    // Add each variation as a separate entry
                    $variations = $registry->getVariations($blockType);

                    foreach ($variations as $variationName => $variation) {
                        $expandedBlocks->push([
                            'type' => $blockType,
                            'variation' => $variationName,
                            'name' => $variation['title'] ?? $block['name'],
                            'icon' => $variation['icon'] ?? $block['icon'] ?? 'fas.cube',
                            'keywords' => $block['keywords'] ?? [],
                        ]);
                    }
                } else {
                    // Keep regular blocks as-is
                    $expandedBlocks->push([
                        'type' => $blockType,
                        'variation' => null,
                        'name' => $block['name'],
                        'icon' => $block['icon'] ?? 'fas.cube',
                        'keywords' => $block['keywords'] ?? [],
                    ]);
                }
            }

            return [
                'key' => $key,
                'name' => $category['name'],
                'icon' => $category['icon'],
                'blocks' => $expandedBlocks->toArray(),
            ];
        })->values()->toArray();
    }

    // ──────────────────────────────────────────────────────────
    // Recursive Block Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Recursively find a block by ID anywhere in the block tree.
     *
     * Searches top-level blocks and descends into container blocks
     * (inner_blocks, columns[].blocks, items[].inner_blocks).
     *
     * @since 2.0.0
     *
     * @param  string  $blockId  The block ID to find.
     * @param  array  $blocks  The blocks array to search within.
     * @return array|null The block data, or null if not found.
     */
    private function findBlockRecursive(string $blockId, array $blocks): ?array
    {
        foreach ($blocks as $block) {
            if (($block['id'] ?? '') === $blockId) {
                return $block;
            }

            $found = $this->searchInnerBlocks($blockId, $block);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Search within a single block's inner block containers.
     *
     * Checks content.inner_blocks, content.columns[].blocks,
     * and content.items[].inner_blocks recursively.
     *
     * @since 2.0.0
     *
     * @param  string  $blockId  The block ID to find.
     * @param  array  $block  The parent block to search within.
     * @return array|null The found block data, or null.
     */
    private function searchInnerBlocks(string $blockId, array $block): ?array
    {
        // Check content.inner_blocks (group, column, grid_item)
        if (! empty($block['content']['inner_blocks'])) {
            $found = $this->findBlockRecursive($blockId, $block['content']['inner_blocks']);

            if ($found !== null) {
                return $found;
            }
        }

        // Check content.columns[].blocks (columns block)
        if (! empty($block['content']['columns'])) {
            foreach ($block['content']['columns'] as $column) {
                if (! empty($column['blocks'])) {
                    $found = $this->findBlockRecursive($blockId, $column['blocks']);

                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        // Check content.items[].inner_blocks (grid items within grid)
        if (! empty($block['content']['items'])) {
            foreach ($block['content']['items'] as $item) {
                if (! empty($item['inner_blocks'])) {
                    $found = $this->findBlockRecursive($blockId, $item['inner_blocks']);

                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Recursively find the dot-notation path to a block by ID.
     *
     * Returns a path string like "1" for top-level or
     * "0.content.inner_blocks.2" for a nested block.
     *
     * @since 2.0.0
     *
     * @param  string  $blockId  The block ID to find.
     * @param  array  $blocks  The blocks array to search within.
     * @param  string  $prefix  The current path prefix (for recursion).
     * @return string|null The dot-notation path, or null if not found.
     */
    private function findBlockPath(string $blockId, array $blocks, string $prefix = ''): ?string
    {
        foreach ($blocks as $index => $block) {
            $currentPath = $prefix === '' ? (string) $index : $prefix.'.'.$index;

            if (($block['id'] ?? '') === $blockId) {
                return $currentPath;
            }

            // Check content.inner_blocks (group, column, grid_item)
            if (! empty($block['content']['inner_blocks'])) {
                $innerPath = $this->findBlockPath($blockId, $block['content']['inner_blocks'], $currentPath.'.content.inner_blocks');

                if ($innerPath !== null) {
                    return $innerPath;
                }
            }

            // Check content.columns[].blocks (columns block)
            if (! empty($block['content']['columns'])) {
                foreach ($block['content']['columns'] as $colIndex => $column) {
                    if (! empty($column['blocks'])) {
                        $colPath = $this->findBlockPath($blockId, $column['blocks'], $currentPath.'.content.columns.'.$colIndex.'.blocks');

                        if ($colPath !== null) {
                            return $colPath;
                        }
                    }
                }
            }

            // Check content.items[].inner_blocks (grid items within grid)
            if (! empty($block['content']['items'])) {
                foreach ($block['content']['items'] as $itemIndex => $item) {
                    if (! empty($item['inner_blocks'])) {
                        $itemPath = $this->findBlockPath($blockId, $item['inner_blocks'], $currentPath.'.content.items.'.$itemIndex.'.inner_blocks');

                        if ($itemPath !== null) {
                            return $itemPath;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the parent array path and index for a block.
     *
     * Returns an array with 'parentPath' (dot-notation to the siblings array)
     * and 'index' (the block's index within that array), or null if not found.
     *
     * @since 2.0.0
     *
     * @param  string  $blockId  The block ID to locate.
     * @return array{parentPath: string, index: int}|null
     */
    private function getBlockLocation(string $blockId): ?array
    {
        $path = $this->findBlockPath($blockId, $this->blocks);

        if ($path === null) {
            return null;
        }

        $lastDot = strrpos($path, '.');

        if ($lastDot === false) {
            return [
                'parentPath' => '',
                'index' => (int) $path,
            ];
        }

        return [
            'parentPath' => substr($path, 0, $lastDot),
            'index' => (int) substr($path, $lastDot + 1),
        ];
    }

    /**
     * Get a reference to the siblings array for a block.
     *
     * Returns the array containing the block (top-level or nested).
     *
     * @since 2.0.0
     *
     * @param  string  $parentPath  The dot-notation path to the parent array.
     */
    private function getSiblingsArray(string $parentPath): array
    {
        if ($parentPath === '') {
            return $this->blocks;
        }

        return data_get($this->blocks, $parentPath, []);
    }

    /**
     * Set a siblings array at the given parent path.
     *
     * @since 2.0.0
     *
     * @param  string  $parentPath  The dot-notation path to the parent array.
     * @param  array  $siblings  The new siblings array.
     */
    private function setSiblingsArray(string $parentPath, array $siblings): void
    {
        if ($parentPath === '') {
            $this->blocks = $siblings;

            return;
        }

        $blocks = $this->blocks;
        data_set($blocks, $parentPath, $siblings);
        $this->blocks = $blocks;
    }

    /**
     * Build a depth-first flat navigation list of block IDs.
     *
     * Walks the entire block tree in document order so keyboard
     * navigation can move through all visible blocks including
     * those nested inside containers.
     *
     * @since 2.0.0
     *
     * @param  array  $blocks  The blocks array to walk.
     * @return array<string> Flat list of block IDs in document order.
     */
    private function buildFlatNavigationList(array $blocks): array
    {
        $list = [];

        foreach ($blocks as $block) {
            $list[] = $block['id'] ?? '';

            // Descend into inner_blocks (group, column, grid_item)
            if (! empty($block['content']['inner_blocks'])) {
                $list = array_merge($list, $this->buildFlatNavigationList($block['content']['inner_blocks']));
            }

            // Descend into columns[].blocks (columns block)
            if (! empty($block['content']['columns'])) {
                foreach ($block['content']['columns'] as $column) {
                    if (! empty($column['blocks'])) {
                        $list = array_merge($list, $this->buildFlatNavigationList($column['blocks']));
                    }
                }
            }

            // Descend into items[].inner_blocks (grid items within grid)
            if (! empty($block['content']['items'])) {
                foreach ($block['content']['items'] as $item) {
                    if (! empty($item['inner_blocks'])) {
                        $list = array_merge($list, $this->buildFlatNavigationList($item['inner_blocks']));
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Reorder inner blocks within a container.
     *
     * @since 2.0.0
     *
     * @param  array  $orderedIds  The new block ID order.
     * @param  string  $parentBlockId  The parent container block ID.
     * @param  int  $slotIndex  Column slot index for columns blocks (-1 for non-columns).
     */
    private function reorderInnerBlocks(array $orderedIds, string $parentBlockId, int $slotIndex): void
    {
        $parentPath = $this->findBlockPath($parentBlockId, $this->blocks);

        if ($parentPath === null) {
            return;
        }

        $blocks = $this->blocks;
        $parentBlock = data_get($blocks, $parentPath);

        if ($slotIndex >= 0) {
            $innerKey = $parentPath.'.content.columns.'.$slotIndex.'.blocks';
        } else {
            $innerKey = $parentPath.'.content.inner_blocks';
        }

        $currentInner = data_get($blocks, $innerKey, []);
        $indexed = collect($currentInner)->keyBy('id');
        $reordered = [];
        $seen = [];

        foreach ($orderedIds as $id) {
            if ($indexed->has($id)) {
                $reordered[] = $indexed->get($id);
                $seen[] = $id;
            }
        }

        foreach ($currentInner as $innerBlock) {
            if (! in_array($innerBlock['id'] ?? '', $seen, true)) {
                $reordered[] = $innerBlock;
            }
        }

        data_set($blocks, $innerKey, $reordered);
        $this->blocks = $blocks;
        $this->notifyBlocksUpdated();
    }

    /**
     * Notify editor and sidebar that blocks have been updated.
     *
     * @since 2.0.0
     */
    private function notifyBlocksUpdated(): void
    {
        $this->dispatch('blocks-updated', blocks: $this->blocks);
        $this->dispatch('editor-sync-state', blocks: $this->blocks)->to('visual-editor::editor');
    }

    /**
     * Check whether a block type supports inline text editing.
     *
     * @since 1.5.0
     *
     * @param  string  $blockType  The block type identifier.
     */
    private function isBlockEditable(string $blockType): bool
    {
        $config = veBlocks()->get($blockType);
        $textType = $config['content_schema']['text']['type'] ?? null;

        return in_array($textType, ['text', 'textarea', 'richtext'], true);
    }
}; ?>

<div
	class="ve-canvas flex-1 overflow-auto bg-gray-50 p-6"
	x-data="{
		panX: 0,
		panY: 0,
		isPanning: false,
		panStartX: 0,
		panStartY: 0,
		handlePanStart( e ) {
			if ( 1 === e.button || ( 0 === e.button && e.altKey ) ) {
				this.isPanning  = true;
				this.panStartX  = e.clientX - this.panX;
				this.panStartY  = e.clientY - this.panY;
				e.preventDefault();
			}
		},
		handlePanMove( e ) {
			if ( this.isPanning ) {
				this.panX = e.clientX - this.panStartX;
				this.panY = e.clientY - this.panStartY;
			}
		},
		handlePanEnd() {
			this.isPanning = false;
		},
	}"
	x-effect="if ( $refs.surface ) { $refs.surface.style.transform = 'scale(' + ( {{ $zoomLevel }} / 100 ) + ') translate(' + panX + 'px, ' + panY + 'px)'; $refs.surface.style.transformOrigin = 'top center'; }"
	@mousedown="handlePanStart( $event )"
	@mousemove.window="handlePanMove( $event )"
	@mouseup.window="handlePanEnd()"
	@click.self="$wire.deselectAll()"
>
	{{-- Zoom Controls --}}
	<div class="sticky top-0 z-10 mb-4 flex items-center justify-end gap-2">
		<x-artisanpack-button
			wire:click="setZoomLevel( {{ $zoomLevel - 10 }} )"
			icon="o-minus"
			color="ghost"
			size="sm"
			:disabled="50 >= $zoomLevel"
			:title="__( 'Zoom Out' )"
		/>
		<span class="min-w-[3rem] text-center text-xs text-gray-600">
			{{ $zoomLevel }}%
		</span>
		<x-artisanpack-button
			wire:click="setZoomLevel( {{ $zoomLevel + 10 }} )"
			icon="o-plus"
			color="ghost"
			size="sm"
			:disabled="200 <= $zoomLevel"
			:title="__( 'Zoom In' )"
		/>
		<x-artisanpack-button
			wire:click="setZoomLevel( 100 )"
			:label="__( 'Reset' )"
			color="ghost"
			size="sm"
			:title="__( 'Reset Zoom' )"
		/>
		<x-artisanpack-button
			wire:click="toggleGrid"
			icon="o-squares-2x2"
			:color="$showGrid ? 'primary' : 'ghost'"
			size="sm"
			:title="__( 'Toggle Grid' )"
		/>
	</div>

	{{-- Canvas Surface --}}
	<div
		x-ref="surface"
		class="relative mx-auto max-w-4xl transition-transform"
	>
		{{-- Grid Overlay --}}
		@if ( $showGrid )
			<div
				class="pointer-events-none absolute inset-0 z-0"
				style="background-image: linear-gradient(to right, rgba(209,213,219,0.3) 1px, transparent 1px), linear-gradient(to bottom, rgba(209,213,219,0.3) 1px, transparent 1px); background-size: 20px 20px;"
			></div>
		@endif

		@if ( !empty( $blocks ) )
			{{-- Blocks with drag-and-drop --}}
			<div
				x-drag-context
				@drag:end="$el._recentlyMovedKeys = []; $wire.reorderBlocks( $event.detail.orderedIds )"
				class="space-y-2"
				role="list"
				aria-label="{{ __( 'Page blocks' ) }}"
			>
				@foreach ( $blocks as $blockIndex => $block )
					@include( 'visual-editor::livewire.partials.block-renderer', [
						'block'          => $block,
						'blockIndex'     => $blockIndex,
						'totalBlocks'    => count( $blocks ),
						'activeBlockId'  => $activeBlockId,
						'editingBlockId' => $editingBlockId,
						'depth'          => 0,
						'parentBlockId'  => null,
						'slotIndex'      => null,
					] )
				@endforeach
			</div>
		@endif

		{{-- Typing Area with Slash Command Menu --}}
		<div
			x-data="{
				...slashCommandInput( { blocks: @js( $this->slashMenuBlocks ) } ),
			isVisible: false,
			get shouldBeVisible() {
				const hasBlocks = $wire.blocks && $wire.blocks.length > 0;
				return !hasBlocks || this.isVisible;
			}
			}"
		:class="shouldBeVisible ? 'opacity-100' : 'opacity-0'"
			@focus-typing-area.window="isVisible = true; setTimeout(() => $refs.typingInput?.focus(), 50)"
		
		@click.self="isVisible = true"
			wire:ignore
			class="ve-typing-area ve-canvas-block group relative rounded px-4 py-2 transition-all mt-2 hover:ring-2 hover:ring-blue-200 cursor-text"
		>
			{{-- Slash Command Menu (positioned below by default, above if near bottom) --}}
			<div
				x-show="menuOpen"
				x-transition:enter="transition ease-out duration-150"
				x-transition:enter-start="opacity-0 translate-y-1"
				x-transition:enter-end="opacity-100 translate-y-0"
				x-transition:leave="transition ease-in duration-100"
				x-transition:leave-start="opacity-100 translate-y-0"
				x-transition:leave-end="opacity-0 translate-y-1"
				@click.outside="closeMenu()"
				x-cloak
				:class="menuPositionAbove ? 'absolute bottom-full left-0 z-50 mb-1 max-h-72 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg' : 'absolute top-full left-0 z-50 mt-1 max-h-72 w-72 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg'"
				role="listbox"
				aria-label="{{ __( 'Block types' ) }}"
			>
				<template x-for="( category, catIdx ) in filteredBlocks" :key="category.key">
					<div>
						<div
							class="sticky top-0 bg-gray-50 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500"
							x-text="category.name"
						></div>
						<template x-for="( block, blockIdx ) in category.blocks" :key="`${category.key}-${blockIdx}`">
							<button
								type="button"
								@click="selectBlock( block )"
								@mouseenter="setActiveIndex( getFlatIndex( block ) )"
								:class="{ 'bg-blue-50 text-blue-700': activeIndex === getFlatIndex( block ) }"
								class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50"
								role="option"
								:aria-selected="activeIndex === getFlatIndex( block )"
							>
								<span x-text="block.name"></span>
							</button>
						</template>
					</div>
				</template>
				<div x-show="0 === flatItems.length" class="px-3 py-4 text-center text-sm text-gray-400">
					{{ __( 'No matching blocks found' ) }}
				</div>
			</div>

			{{-- Content container with + button --}}
		<div class="relative flex items-start gap-2">
			{{-- Editable Input --}}
			<div
				x-ref="typingInput"
				contenteditable="true"
				@input="handleInput( $event )"
			@click="isVisible = true; requestAnimationFrame(() => $el.focus())"
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
	</div>
</div>

<style>
	[contenteditable][data-placeholder]:empty::before {
		content: attr( data-placeholder );
		color: #9ca3af;
		pointer-events: none;
	}
</style>

@script
<script>
	window.veNavigating = false
	window.veFocusingBlock = false

	Livewire.hook( 'morphed', () => {
		if ( !window.veFocusingBlock ) {
			window.veNavigating = false
		}
	} )

	const canvasHandler = ( event ) => {
		// Skip when typing area or slash menu is active
		if ( event.target.closest( '.ve-typing-area' ) ) {
			return
		}

		if ( ( 'ArrowUp' === event.key || 'ArrowDown' === event.key ) && !event.target.isContentEditable ) {
			event.preventDefault()
			$wire.dispatch( 'canvas-navigate', { direction: 'ArrowUp' === event.key ? 'up' : 'down' } )
		}

		if ( 'Tab' === event.key && !event.ctrlKey && !event.metaKey && !event.target.isContentEditable ) {
			event.preventDefault()
			$wire.dispatch( 'canvas-navigate', { direction: event.shiftKey ? 'up' : 'down' } )
		}

		if ( 'Escape' === event.key && !event.target.isContentEditable && ![ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( event.target.tagName ) ) {
			$wire.deselectAll()
		}

		if ( ( 'Delete' === event.key || 'Backspace' === event.key ) && !event.target.isContentEditable && ![ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( event.target.tagName ) ) {
			$wire.dispatch( 'canvas-delete-selected' )
		}
	}

	document.addEventListener( 'keydown', canvasHandler )

	let cleanup = () => {
		document.removeEventListener( 'keydown', canvasHandler )
	}

	document.addEventListener( 'livewire:navigating', cleanup, { once: true } )

	window.veAtTopOfElement = function( el ) {
		let sel = window.getSelection()
		if ( !sel || 0 === sel.rangeCount || !sel.isCollapsed ) return false
		let range = sel.getRangeAt( 0 )
		if ( 0 === range.startOffset && ( range.startContainer === el || range.startContainer === el.firstChild ) ) return true
		let elRect = el.getBoundingClientRect()
		let rangeRect = range.getBoundingClientRect()
		return rangeRect.top - elRect.top < ( rangeRect.height || 16 )
	}

	window.veAtBottomOfElement = function( el ) {
		let sel = window.getSelection()
		if ( !sel || 0 === sel.rangeCount || !sel.isCollapsed ) return false
		let range = sel.getRangeAt( 0 )
		let elRect = el.getBoundingClientRect()
		let rangeRect = range.getBoundingClientRect()
		if ( 0 === rangeRect.height ) {
			let tempRange = document.createRange()
			tempRange.selectNodeContents( el )
			tempRange.collapse( false )
			return range.startContainer === tempRange.startContainer && range.startOffset === tempRange.startOffset
		}
		return elRect.bottom - rangeRect.bottom < ( rangeRect.height || 16 )
	}

	Livewire.on( 'focus-typing-area', () => {
		setTimeout( () => {
			window.veNavigating = false
			let typingInput = document.querySelector( '.ve-typing-area input, .ve-typing-area [contenteditable]' )
			if ( typingInput ) {
				typingInput.focus()
			}
		}, 50 )
	} )

	// Set flag immediately before morphs happen
	Livewire.on( 'prepare-focus', function( event ) {
		window.veFocusingBlock = true
		window.veFocusBlockId = event.blockId
	} )

	Livewire.on( 'focus-block', function( event ) {
		let blockId = event.blockId
		window.veFocusingBlock = true
		window.veFocusBlockId = blockId

		// Function to focus the element
		const focusElement = function() {
			let selector = '[wire\\:key="block-' + blockId + '"] [contenteditable="true"]'
			let el = document.querySelector( selector )

			if ( el && document.contains( el ) ) {
				el.focus()
				let sel = window.getSelection()
				let range = document.createRange()
				range.selectNodeContents( el )
				range.collapse( false )
				sel.removeAllRanges()
				sel.addRange( range )
				return true
			}
			return false
		}

		// Initial focus attempt with polling
		let attempts = 0
		let maxAttempts = 30
		let pollInterval = setInterval( function() {
			attempts++

			if ( focusElement() ) {
				clearInterval( pollInterval )

				// Keep flag true for 1 second to maintain focus through morphs
				setTimeout( function() {
					window.veFocusingBlock = false
					window.veFocusBlockId = null
					Livewire.dispatch( 'clear-focus-flag' )
				}, 1000 )
			} else if ( attempts >= maxAttempts ) {
				clearInterval( pollInterval )
				window.veFocusingBlock = false
				window.veFocusBlockId = null
				Livewire.dispatch( 'clear-focus-flag' )
			}
		}, 100 )
	} )

	Alpine.data( 'richTextEditor', ( { htmlContent } ) => ( {
		htmlContent: htmlContent || '',

		format( command ) {
			document.execCommand( command, false, null )
		},

		isActive( command ) {
			return document.queryCommandState( command )
		},

		insertLink() {
			let url = prompt( 'Enter URL:' )
			if ( url ) {
				document.execCommand( 'createLink', false, url )
			}
		},
	} ) )

	Alpine.data( 'globalBlockToolbar', () => ( {
		format( command ) {
			document.execCommand( command, false, null )
		},

		isActive( command ) {
			return document.queryCommandState( command )
		},

		insertLink() {
			let url = prompt( 'Enter URL:' )
			if ( url ) {
				document.execCommand( 'createLink', false, url )
			}
		},
	} ) )

	Alpine.data( 'slashCommandInput', ( { blocks } ) => ( {
		allBlocks: blocks,
		menuOpen: false,
		slashQuery: '',
		activeIndex: 0,
		flatItems: [],
		filteredBlocks: [],
		menuPositionAbove: false,
		init() {
			// Initialize filteredBlocks

		// Prime the typing input for focus on first load
		this.$nextTick(() => {
			if ( this.$refs.typingInput ) {
				this.$refs.typingInput.focus()
				this.$refs.typingInput.blur()
			}
		})
			this.updateFilteredBlocks()
			
			// Watch for slashQuery changes and update filteredBlocks
			this.$watch( 'slashQuery', () => {
				console.log( '🔍 slashQuery changed to:', this.slashQuery )
				this.updateFilteredBlocks()
			} )

		// Watch for isVisible changes and auto-focus
		this.$watch( 'isVisible', (value) => {
			if ( value && this.$refs.typingInput ) {
				setTimeout(() => {
					this.$refs.typingInput.focus()
				}, 150)
			}
		} )
		},
		updateMenuPosition() {
			if ( !this.$refs.typingInput ) return

			const rect = this.$refs.typingInput.getBoundingClientRect()
			const menuHeight = 288 // max-h-72 = 18rem = 288px
			const spaceBelow = window.innerHeight - rect.bottom
			const spaceAbove = rect.top

			// Show above only if not enough space below AND enough space above
			this.menuPositionAbove = spaceBelow < menuHeight && spaceAbove > menuHeight
		},

		updateFilteredBlocks() {
			let categories = this.allBlocks
		console.log( '🔍 filteredBlocks getter called, slashQuery:', this.slashQuery )

			if ( '' !== this.slashQuery ) {
				let query = this.slashQuery.toLowerCase()
			console.log( '🔍 Filtering with query:', query )

				categories = this.allBlocks
					.map( ( cat ) => ( {
						...cat,
						blocks: cat.blocks.filter( ( b ) => {
							let nameMatch = b.name.toLowerCase().includes( query )
							let kwMatch = ( b.keywords || [] ).some(
								( kw ) => kw.toLowerCase().includes( query )
							)
							return nameMatch || kwMatch
						} ),
					} ) )
					.filter( ( cat ) => cat.blocks.length > 0 )
			}

		console.log( '🔍 Filtered categories count:', categories.length )
			// Build flatItems with sequential IDs AND add _flatId to blocks
			this.flatItems = []
			let flatIndex = 0
			categories = categories.map( cat => ( {
				...cat,
				blocks: cat.blocks.map( block => ( {
					...block,
					_flatId: flatIndex++
				} ) )
			} ) )

			// Now build flatItems from the blocks with _flatId
			categories.forEach( cat => {
				cat.blocks.forEach( block => {
					this.flatItems.push( {
						flatId: block._flatId,
						type: block.type,
						variation: block.variation || null
					} )
				} )
			} )

		console.log( '🔍 Final flatItems count:', this.flatItems.length )
			this.filteredBlocks = categories
		},

		getFlatIndex( block ) {
			return block._flatId !== undefined ? block._flatId : 0
		},

		setActiveIndex( idx ) {
			this.activeIndex = idx
		},

		handleInput( event ) {
			let text = this.$refs.typingInput.textContent
		console.log( '⌨️ handleInput called, text:', text )

			if ( text.startsWith( '/' ) ) {
				this.slashQuery = text.substring( 1 )
				if ( !this.menuOpen ) {
			console.log( '⌨️ Setting slashQuery to:', this.slashQuery )
					this.menuOpen = true
					this.activeIndex = 0
					this.$nextTick( () => this.updateMenuPosition() )
				}
			} else if ( this.menuOpen ) {
				this.closeMenu()
			} else if ( '' !== text.trim() ) {
				this.$refs.typingInput.textContent = ''
				window.veNavigating = true
				this.$refs.typingInput.blur()
				$wire.insertBlockWithContent( 'text', text )
			}
		},

		handleKeydown( event ) {
			if ( this.menuOpen ) {
			// Prevent default behavior for navigation keys when menu is open
			if ( ['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes( event.key ) ) {
				event.preventDefault()
				event.stopPropagation()
			}

				if ( 'ArrowDown' === event.key ) {
					event.preventDefault()
				event.stopPropagation()
					this.activeIndex = Math.min(
						this.activeIndex + 1,
						this.flatItems.length - 1
					)
				} else if ( 'ArrowUp' === event.key ) {
					event.preventDefault()
				event.stopPropagation()
					this.activeIndex = Math.max( this.activeIndex - 1, 0 )
				} else if ( 'Enter' === event.key ) {
					event.preventDefault()
				event.stopPropagation()
					if ( this.flatItems.length > 0 ) {
						this.selectBlock( this.flatItems[ this.activeIndex ] )
					}
				} else if ( 'Escape' === event.key ) {
					this.closeMenu()
				}
				return
			}

			if ( 'Enter' === event.key && !event.shiftKey ) {
				event.preventDefault()
			}
		},

		selectBlock( block ) {
			$wire.insertBlockWithContent( block.type, '', block.variation )
			this.closeMenu()
		this.isVisible = false
			if ( this.$refs.typingInput ) {
				this.$refs.typingInput.textContent = ''
			}
			// Don't refocus typing input - let the new block get focus naturally
			// this.$nextTick( () => {
			// 	if ( this.$refs.typingInput ) {
			// 		this.$refs.typingInput.focus()
			// 		this.$refs.typingInput.scrollIntoView( { behavior: 'smooth', block: 'nearest' } )
			// 	}
			// } )
		},

		closeMenu() {
			this.menuOpen = false
			this.slashQuery = ''
			this.activeIndex = 0
		},

		toggleMenuFromButton() {
			if ( this.menuOpen ) {
				this.closeMenu()
			} else {
				this.menuOpen = true
				this.activeIndex = 0
				this.$nextTick( () => {
					if ( this.$refs.typingInput ) {
						this.updateMenuPosition()
						this.$refs.typingInput.focus()
					}
				} )
			}
		},

		handleFocus() {
		this.isVisible = true
	},

		handleBlur() {
			setTimeout( () => {
				if ( !this.$el.contains( document.activeElement ) ) {
					this.closeMenu()
				this.isVisible = false
				}
			}, 150 )
		},
	} ) )

	Alpine.data( 'inlineSlashCommands', ( { blocks, blockId } ) => ( {
		allBlocks: blocks,
		menuOpen: false,
		slashQuery: '',
		activeIndex: 0,
		flatItems: [],
		menuPositionAbove: false,
		filteredBlocks: [],

		init() {
			// Initialize filteredBlocks
			this.updateFilteredBlocks()
			
			// Watch for slashQuery changes and update filteredBlocks
			this.$watch( 'slashQuery', () => {
				console.log( '🔍 [INLINE] slashQuery changed to:', this.slashQuery )
				this.updateFilteredBlocks()
			} )
		},

		updateMenuPosition() {
			if ( !this.$refs.editor ) return

			const rect = this.$refs.editor.getBoundingClientRect()
			const menuHeight = 288 // max-h-72 = 18rem = 288px
			const spaceBelow = window.innerHeight - rect.bottom
			const spaceAbove = rect.top

			// Show above only if not enough space below AND enough space above
			this.menuPositionAbove = spaceBelow < menuHeight && spaceAbove > menuHeight
		},

		updateFilteredBlocks() {
			let categories = this.allBlocks
		console.log( '🔍 [INLINE] filteredBlocks getter called, slashQuery:', this.slashQuery )

			if ( '' !== this.slashQuery ) {
				let query = this.slashQuery.toLowerCase()
			console.log( '🔍 [INLINE] Filtering with query:', query )

				categories = this.allBlocks
					.map( ( cat ) => ( {
						...cat,
						blocks: cat.blocks.filter( ( b ) => {
							let nameMatch = b.name.toLowerCase().includes( query )
							let kwMatch = ( b.keywords || [] ).some(
								( kw ) => kw.toLowerCase().includes( query )
							)
							return nameMatch || kwMatch
						} ),
					} ) )
					.filter( ( cat ) => cat.blocks.length > 0 )
			}

		console.log( '🔍 [INLINE] Filtered categories count:', categories.length )
			// Build flatItems with sequential IDs AND add _flatId to blocks
			this.flatItems = []
			let flatIndex = 0
			categories = categories.map( cat => ( {
				...cat,
				blocks: cat.blocks.map( block => ( {
					...block,
					_flatId: flatIndex++
				} ) )
			} ) )

			// Now build flatItems from the blocks with _flatId
			categories.forEach( cat => {
				cat.blocks.forEach( block => {
					this.flatItems.push( {
						flatId: block._flatId,
						type: block.type,
						variation: block.variation || null
					} )
				} )
			} )

		console.log( '🔍 [INLINE] Final flatItems count:', this.flatItems.length )
			this.filteredBlocks = categories
		},

		getFlatIndex( block ) {
			return block._flatId !== undefined ? block._flatId : 0
		},

		handleInput( event, editorEl ) {
			let text = editorEl.textContent
		console.log( '⌨️ [INLINE] handleInput called, text:', text )

			if ( text.startsWith( '/' ) ) {
				this.slashQuery = text.substring( 1 )
			console.log( '⌨️ [INLINE] Setting slashQuery to:', this.slashQuery )
				if ( !this.menuOpen ) {
					this.menuOpen = true
					this.activeIndex = 0
					this.$nextTick( () => this.updateMenuPosition() )
				}
			} else if ( this.menuOpen ) {
				this.closeMenu()
			}
		},

		handleKeydown( event, editorEl ) {
			if ( !this.menuOpen ) return

		// Prevent default behavior for navigation keys when menu is open
		if ( ['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes( event.key ) ) {
			event.preventDefault()
			event.stopPropagation()
		}


			if ( 'ArrowDown' === event.key ) {
				event.preventDefault()
			event.stopPropagation()
				this.activeIndex = Math.min( this.activeIndex + 1, this.flatItems.length - 1 )
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault()
			event.stopPropagation()
				this.activeIndex = Math.max( this.activeIndex - 1, 0 )
			} else if ( 'Enter' === event.key ) {
				event.preventDefault()
			event.stopPropagation()
				if ( this.flatItems.length > 0 ) {
					this.selectBlock( this.flatItems[ this.activeIndex ], editorEl )
				}
			} else if ( 'Escape' === event.key ) {
				this.closeMenu()
			}
		},

		selectBlock( block, editorEl ) {
			window.veNavigating = true
			$wire.replaceBlockWithType( blockId, block.type, block.variation )
			this.closeMenu()
		},

		closeMenu() {
			this.menuOpen = false
			this.slashQuery = ''
			this.activeIndex = 0
		},
	} ) )
</script>
@endscript
