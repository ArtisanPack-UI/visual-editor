# Block Transformations Implementation Plan

## Overview

Implement WordPress-style block transformations that allow users to convert one block type to another. Users click the block icon in the toolbar to reveal a dropdown of available transformations.

## User Experience

1. **Trigger**: Click the block icon (left side of toolbar)
2. **Dropdown**: Shows list of blocks the current block can transform into
3. **Selection**: Click a block type to transform
4. **Result**: Block content is intelligently mapped to the new block type

## Architecture

### 1. Block Configuration Schema

Add `transforms` configuration to block registration:

```php
$this->register('text', [
    'name' => __('Text'),
    'icon' => 'fas.file-lines',
    'category' => 'text',
    // ... existing config ...
    'transforms' => [
        'to' => ['heading', 'list', 'quote'],
        'from' => ['heading', 'list', 'quote'],
    ],
]);
```

**Configuration Structure:**
- `to`: Array of block types this block can transform TO
- `from`: Array of block types this block can transform FROM

### 2. Content Mapping Rules

Define intelligent mapping rules between block types:

#### Text-based Transformations
| From → To | Content Mapping | Settings Mapping |
|-----------|-----------------|------------------|
| `text` → `heading` | `text` → `text`, add `level: 'h2'` | Typography, colors preserved |
| `text` → `list` | `text` → `text`, add `style: 'bullet'` | Typography, colors preserved |
| `text` → `quote` | `text` → `text`, clear `citation` | Typography, colors, borders preserved |
| `heading` → `text` | `text` → `text`, remove `level` | Typography, colors preserved |
| `heading` → `list` | `text` → `text`, add `style: 'bullet'`, remove `level` | Typography, colors preserved |
| `list` → `text` | `text` → `text`, remove `style` | Typography, colors preserved |
| `list` → `heading` | `text` → `text`, add `level: 'h2'`, remove `style` | Typography, colors preserved |

#### Interactive Transformations
| From → To | Content Mapping |
|-----------|-----------------|
| `button` → `button_group` | Create single button in group |
| `button_group` → `button` | Use first button from group |

#### Layout Transformations
| From → To | Content Mapping |
|-----------|-----------------|
| `group` → `columns` | Convert to 100% width column |
| `columns` → `group` | Merge all column content |
| `grid` → `columns` | Convert grid items to columns |

### 3. Registry Enhancement

**BlockRegistry.php** additions:

```php
/**
 * Get available transformations for a block type.
 *
 * @param string $blockType The source block type
 * @return array Array of block types this block can transform to
 */
public function getTransforms(string $blockType): array
{
    $config = $this->get($blockType);
    return $config['transforms']['to'] ?? [];
}

/**
 * Check if a block can transform to another type.
 *
 * @param string $fromType Source block type
 * @param string $toType Target block type
 * @return bool
 */
public function canTransformTo(string $fromType, string $toType): bool
{
    $transforms = $this->getTransforms($fromType);
    return in_array($toType, $transforms, true);
}
```

### 4. Transform Service

Create `src/Services/BlockTransformService.php`:

```php
<?php

declare(strict_types=1);

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

class BlockTransformService
{
    public function __construct(
        protected BlockRegistry $registry
    ) {}

    /**
     * Transform a block from one type to another.
     *
     * @param array $block The source block data
     * @param string $toType The target block type
     * @return array The transformed block data
     */
    public function transform(array $block, string $toType): array
    {
        $fromType = $block['type'];

        if (!$this->registry->canTransformTo($fromType, $toType)) {
            throw new \InvalidArgumentException(
                "Cannot transform {$fromType} to {$toType}"
            );
        }

        $transformed = [
            'id' => $block['id'],
            'type' => $toType,
            'content' => $this->mapContent($block, $toType),
            'settings' => $this->mapSettings($block, $toType),
        ];

        // Preserve inner blocks if both support them
        if (isset($block['inner_blocks'])) {
            $toConfig = $this->registry->get($toType);
            if ($toConfig['inner_blocks'] ?? false) {
                $transformed['inner_blocks'] = $block['inner_blocks'];
            }
        }

        return $transformed;
    }

    /**
     * Map content fields between block types.
     */
    protected function mapContent(array $block, string $toType): array
    {
        $fromType = $block['type'];
        $fromContent = $block['content'] ?? [];
        $toConfig = $this->registry->get($toType);
        $content = [];

        // Get content schema for target block
        $toSchema = $toConfig['content_schema'] ?? [];

        // Text-based transformations
        if ($this->isTextBasedBlock($fromType) && $this->isTextBasedBlock($toType)) {
            // Preserve text content
            if (isset($fromContent['text'])) {
                $content['text'] = $fromContent['text'];
            }

            // Add type-specific fields with defaults
            foreach ($toSchema as $field => $config) {
                if (!isset($content[$field])) {
                    $content[$field] = $config['default'] ?? $this->getDefaultForField($field, $toType);
                }
            }
        }

        // Media transformations (image → video, etc.)
        if ($fromType === 'image' && $toType === 'video') {
            $content['url'] = $fromContent['media_id'] ?? '';
            $content['autoplay'] = false;
            $content['loop'] = false;
        }

        return $content;
    }

    /**
     * Map settings between block types.
     */
    protected function mapSettings(array $block, string $toType): array
    {
        $fromType = $block['type'];
        $fromSettings = $block['settings'] ?? [];
        $toConfig = $this->registry->get($toType);
        $settings = [];

        // Get settings schema for target block
        $toSchema = $toConfig['settings_schema'] ?? [];
        $fromConfig = $this->registry->get($fromType);
        $fromSupports = $fromConfig['supports'] ?? [];
        $toSupports = $toConfig['supports'] ?? [];

        // Preserve supported settings that both blocks share
        $commonSupports = array_intersect($fromSupports, $toSupports);

        foreach ($fromSettings as $key => $value) {
            // Check if this setting is relevant to common supports
            if ($this->isRelevantSetting($key, $commonSupports)) {
                $settings[$key] = $value;
            }
        }

        // Add missing required settings with defaults
        foreach ($toSchema as $field => $config) {
            if (!isset($settings[$field]) && isset($config['default'])) {
                $settings[$field] = $config['default'];
            }
        }

        return $settings;
    }

    /**
     * Check if a block type is text-based.
     */
    protected function isTextBasedBlock(string $type): bool
    {
        return in_array($type, ['text', 'heading', 'list', 'quote'], true);
    }

    /**
     * Get default value for a content field based on block type.
     */
    protected function getDefaultForField(string $field, string $blockType): mixed
    {
        $defaults = [
            'heading' => ['level' => 'h2'],
            'list' => ['style' => 'bullet'],
            'quote' => ['citation' => ''],
            'button' => ['target' => '_self'],
        ];

        return $defaults[$blockType][$field] ?? null;
    }

    /**
     * Check if a setting is relevant to the given supports.
     */
    protected function isRelevantSetting(string $key, array $supports): bool
    {
        $settingSupportsMap = [
            'text_color' => ['typography', 'colors'],
            'background_color' => ['colors'],
            'font_size' => ['typography'],
            'font_weight' => ['typography'],
            'border_width' => ['borders'],
            'border_color' => ['borders'],
            'border_radius' => ['borders'],
        ];

        if (!isset($settingSupportsMap[$key])) {
            return true; // Unknown settings are preserved
        }

        foreach ($settingSupportsMap[$key] as $support) {
            if (in_array($support, $supports, true)) {
                return true;
            }
        }

        return false;
    }
}
```

### 5. Livewire Editor Method

Add to `editor.blade.php` Livewire component:

```php
/**
 * Transform a block to a different type.
 *
 * @param string $blockId The block ID to transform
 * @param string $toType The target block type
 * @return void
 */
public function transformBlock(string $blockId, string $toType): void
{
    $transformService = app(BlockTransformService::class);

    // Find the block
    $blockIndex = $this->findBlockIndex($blockId);
    if ($blockIndex === null) {
        return;
    }

    $block = $this->blocks[$blockIndex];

    try {
        // Transform the block
        $transformed = $transformService->transform($block, $toType);

        // Replace in blocks array
        $this->blocks[$blockIndex] = $transformed;

        // Mark as dirty
        $this->isDirty = true;

        // Push to history
        $this->pushHistory($this->blocks);

        // Dispatch success event
        $this->dispatch('block-transformed', [
            'blockId' => $blockId,
            'fromType' => $block['type'],
            'toType' => $toType,
        ]);

    } catch (\InvalidArgumentException $e) {
        $this->dispatch('transform-error', [
            'message' => $e->getMessage(),
        ]);
    }
}

/**
 * Find block index by ID.
 */
protected function findBlockIndex(string $blockId): ?int
{
    foreach ($this->blocks as $index => $block) {
        if ($block['id'] === $blockId) {
            return $index;
        }
    }
    return null;
}
```

### 6. UI Implementation

**Update `block-toolbar.blade.php`:**

```blade
@php
    $availableTransforms = app(\ArtisanPackUI\VisualEditor\Registries\BlockRegistry::class)
        ->getTransforms($blockType);
    $hasTransforms = !empty($availableTransforms);
@endphp

{{-- Group 1: Universal Tools --}}
{{-- Block Type Indicator with Transform Dropdown --}}
@if ($hasTransforms)
    <div class="relative" x-data="{ showTransforms: false }">
        <button
            type="button"
            @click="showTransforms = !showTransforms"
            class="flex items-center gap-1 rounded px-1.5 py-1 text-sm font-medium text-gray-500 hover:bg-gray-100"
            title="{{ __('Transform to...') }}"
        >
            <x-artisanpack-icon name="{{ $blockConfig['icon'] ?? 'fas.cube' }}" class="h-4.5 w-4.5" />
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        {{-- Transform Dropdown --}}
        <div
            x-show="showTransforms"
            @click.outside="showTransforms = false"
            x-transition
            class="absolute left-0 top-full z-30 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
        >
            <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase">
                {{ __('Transform to') }}
            </div>
            @foreach ($availableTransforms as $transformType)
                @php
                    $transformConfig = app(\ArtisanPackUI\VisualEditor\Registries\BlockRegistry::class)
                        ->get($transformType);
                @endphp
                <button
                    type="button"
                    wire:click="transformBlock('{{ $blockId }}', '{{ $transformType }}')"
                    @click="showTransforms = false"
                    class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm text-gray-700 hover:bg-blue-50"
                >
                    <x-artisanpack-icon
                        name="{{ $transformConfig['icon'] ?? 'fas.cube' }}"
                        class="h-4 w-4 text-gray-400"
                    />
                    <span>{{ $transformConfig['name'] ?? ucfirst($transformType) }}</span>
                </button>
            @endforeach
        </div>
    </div>
@else
    {{-- No transforms available - static icon --}}
    <span
        class="flex items-center gap-1 rounded px-1.5 py-1 text-sm font-medium text-gray-500"
        title="{{ $blockName }}"
    >
        <x-artisanpack-icon name="{{ $blockConfig['icon'] ?? 'fas.cube' }}" class="h-4.5 w-4.5" />
    </span>
@endif
```

### 7. Block Definitions with Transforms

Update `BlockRegistry.php` `registerDefaults()` to include transforms:

```php
// Text blocks with cross-transforms
$this->register('heading', [
    'name' => __('Heading'),
    'icon' => 'fas.heading',
    'category' => 'text',
    // ... existing config ...
    'transforms' => [
        'to' => ['text', 'list', 'quote'],
        'from' => ['text', 'list', 'quote'],
    ],
]);

$this->register('text', [
    'name' => __('Text'),
    'icon' => 'fas.file-lines',
    'category' => 'text',
    // ... existing config ...
    'transforms' => [
        'to' => ['heading', 'list', 'quote'],
        'from' => ['heading', 'list', 'quote'],
    ],
]);

$this->register('list', [
    'name' => __('List'),
    'icon' => 'fas.list',
    'category' => 'text',
    // ... existing config ...
    'transforms' => [
        'to' => ['text', 'heading', 'quote'],
        'from' => ['text', 'heading', 'quote'],
    ],
]);

$this->register('quote', [
    'name' => __('Quote'),
    'icon' => 'fas.quote-left',
    'category' => 'text',
    // ... existing config ...
    'transforms' => [
        'to' => ['text', 'heading', 'list'],
        'from' => ['text', 'heading', 'list'],
    ],
]);

// Layout blocks
$this->register('group', [
    // ... existing config ...
    'transforms' => [
        'to' => ['columns'],
        'from' => ['columns'],
    ],
]);

$this->register('columns', [
    // ... existing config ...
    'transforms' => [
        'to' => ['group', 'grid'],
        'from' => ['group', 'grid'],
    ],
]);

$this->register('grid', [
    // ... existing config ...
    'transforms' => [
        'to' => ['columns'],
        'from' => ['columns'],
    ],
]);
```

## Implementation Steps

### Phase 1: Registry & Service (Backend)
1. ✅ Add `transforms` configuration to BlockRegistry defaults
2. ✅ Add `getTransforms()` and `canTransformTo()` methods to BlockRegistry
3. ✅ Create `BlockTransformService` with content/settings mapping logic
4. ✅ Register service in `VisualEditorServiceProvider`

### Phase 2: Livewire Integration
1. ✅ Add `transformBlock()` method to editor.blade.php
2. ✅ Add `findBlockIndex()` helper method
3. ✅ Add event dispatching for success/error feedback

### Phase 3: UI Implementation
1. ✅ Update block-toolbar.blade.php with dropdown
2. ✅ Add Alpine.js state management for dropdown
3. ✅ Style dropdown to match existing UI patterns

### Phase 4: Testing
1. ✅ Write unit tests for BlockTransformService
2. ✅ Write feature tests for transform workflow
3. ✅ Test all defined transform combinations
4. ✅ Test error handling for invalid transforms

### Phase 5: Documentation
1. ✅ Update package documentation
2. ✅ Add examples of custom transform configurations
3. ✅ Document content mapping rules

## Testing Strategy

### Unit Tests

```php
// tests/Unit/Services/BlockTransformServiceTest.php

test('transforms text to heading', function () {
    $service = app(BlockTransformService::class);

    $block = [
        'id' => 'block-1',
        'type' => 'text',
        'content' => ['text' => 'Hello World'],
        'settings' => ['text_color' => '#000000'],
    ];

    $transformed = $service->transform($block, 'heading');

    expect($transformed['type'])->toBe('heading')
        ->and($transformed['content']['text'])->toBe('Hello World')
        ->and($transformed['content']['level'])->toBe('h2')
        ->and($transformed['settings']['text_color'])->toBe('#000000');
});

test('preserves typography settings when transforming', function () {
    $service = app(BlockTransformService::class);

    $block = [
        'id' => 'block-1',
        'type' => 'heading',
        'content' => ['text' => 'Title', 'level' => 'h1'],
        'settings' => [
            'text_color' => '#333333',
            'font_size' => '32px',
            'font_weight' => 'bold',
        ],
    ];

    $transformed = $service->transform($block, 'text');

    expect($transformed['settings']['text_color'])->toBe('#333333')
        ->and($transformed['settings']['font_size'])->toBe('32px')
        ->and($transformed['settings']['font_weight'])->toBe('bold');
});

test('throws exception for invalid transforms', function () {
    $service = app(BlockTransformService::class);

    $block = [
        'id' => 'block-1',
        'type' => 'image',
        'content' => ['media_id' => 123],
        'settings' => [],
    ];

    $service->transform($block, 'heading');
})->throws(\InvalidArgumentException::class);
```

### Feature Tests

```php
// tests/Feature/Livewire/BlockTransformTest.php

test('user can transform text block to heading', function () {
    $content = Content::factory()->create([
        'blocks' => [
            [
                'id' => 'block-1',
                'type' => 'text',
                'content' => ['text' => 'Hello'],
                'settings' => [],
            ],
        ],
    ]);

    Livewire::test(Editor::class, ['content' => $content])
        ->call('transformBlock', 'block-1', 'heading')
        ->assertSet('blocks.0.type', 'heading')
        ->assertSet('blocks.0.content.text', 'Hello')
        ->assertSet('blocks.0.content.level', 'h2')
        ->assertDispatched('block-transformed');
});
```

## Future Enhancements

1. **Multi-block Transforms**: Transform multiple selected blocks at once
2. **Custom Transform Handlers**: Allow packages to register custom transform logic
3. **Transform Patterns**: Save common transform sequences as patterns
4. **Transform Preview**: Show preview before applying transformation
5. **Undo-specific Transforms**: Allow undoing just the transform without full undo

## Related Features

- Block Variations (already implemented)
- Block Patterns
- Block Styles
- Copy/Paste Block Style

## Resources

- WordPress Block Transforms: https://developer.wordpress.org/block-editor/reference-guides/block-api/block-transforms/
- Gutenberg Transform Types: https://github.com/WordPress/gutenberg/tree/trunk/packages/blocks/src/api/transforms
