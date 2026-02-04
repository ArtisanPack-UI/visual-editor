# Using Block Variations

Block variations allow you to create multiple visual and functional variations of the same block type. This is inspired by WordPress Gutenberg's block variations system.

## Overview

A block variation is a predefined configuration of a block with specific default settings. For example, the `group` block has four variations:
- **Group** - Standard vertical container
- **Row** - Horizontal flex layout
- **Stack** - Vertical flex layout with equal spacing
- **Grid** - Wrapping flex layout

## Registering Block Variations

Use the `BlockRegistry::registerVariation()` method to register variations for your block:

```php
use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

// In your service provider's boot() method
$registry = app( BlockRegistry::class );

$registry->registerVariation( 'my-block', 'default', [
    'title'       => __( 'Default' ),
    'description' => __( 'Standard configuration' ),
    'icon'        => 'fas.cube',
    'isDefault'   => true,
    'attributes'  => [
        'settings' => [
            'layout'     => 'vertical',
            'spacing'    => 'medium',
            'background' => 'transparent',
        ],
    ],
] );

$registry->registerVariation( 'my-block', 'card', [
    'title'       => __( 'Card' ),
    'description' => __( 'Card style with shadow' ),
    'icon'        => 'fas.square',
    'isDefault'   => false,
    'attributes'  => [
        'settings' => [
            'layout'     => 'vertical',
            'spacing'    => 'large',
            'background' => 'white',
        ],
    ],
] );
```

### Variation Configuration

Each variation accepts these properties:

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `title` | string | Yes | Display name for the variation |
| `description` | string | No | Short description (1-2 words) |
| `icon` | string | Yes | Icon name (e.g., 'fas.cube') |
| `isDefault` | boolean | No | Whether this is the default variation |
| `attributes` | array | Yes | Default block attributes |
| `attributes.settings` | array | Yes | Default settings to apply |

## Using the Variation Picker Component

The visual editor provides a reusable variation picker component that you can include in your block's renderer:

### Step 1: Check if Block Has Variations

In your block's case statement in `block-renderer.blade.php`:

```php
@case ( 'my-block' )
    @php
        $innerBlocks           = $block['content']['inner_blocks'] ?? [];
        $currentVariation      = $block['settings']['_variation'] ?? 'default';
        $hasVariations         = veBlocks()->hasVariations( 'my-block' );
        $variations            = $hasVariations ? veBlocks()->getVariations( 'my-block' ) : [];
        $variationNotSelected  = ! isset( $block['settings']['_variation'] );
        $showVariationPicker   = empty( $innerBlocks ) && $hasVariations && $variationNotSelected;
    @endphp

    <div class="my-block-container">
        @if ( $showVariationPicker )
            {{-- Show variation picker when block is empty --}}
            @include( 'visual-editor::livewire.partials.variation-picker', [
                'blockType'        => 'my-block',
                'blockId'          => $blockId,
                'variations'       => $variations,
                'currentVariation' => $currentVariation,
            ] )
        @elseif ( !empty( $innerBlocks ) )
            {{-- Render inner blocks --}}
            @foreach ( $innerBlocks as $innerIndex => $innerBlock )
                @include( 'visual-editor::livewire.partials.block-renderer', [
                    'block'          => $innerBlock,
                    'blockIndex'     => $innerIndex,
                    'totalBlocks'    => count( $innerBlocks ),
                    'activeBlockId'  => $activeBlockId,
                    'editingBlockId' => $editingBlockId,
                    'depth'          => $depth + 1,
                    'parentBlockId'  => $blockId,
                ] )
            @endforeach
        @endif
    </div>
    @break
```

### Step 2: Variation Picker Parameters

The `variation-picker` partial accepts these parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `blockType` | string | The block type (e.g., 'my-block') |
| `blockId` | string | The block ID |
| `variations` | array | Array of variations from BlockRegistry |
| `currentVariation` | string | Currently selected variation name |

### Step 3: Add Variation Selector to Settings Panel

The variation selector will automatically appear in the Settings tab when:
1. The block type has registered variations
2. The block is selected in the canvas

No additional code is needed - the editor component handles this automatically.

## How Variations Work

### 1. Block Creation

When a user inserts a block with variations:
- If inserted via the sidebar variation picker, the selected variation is applied immediately
- If inserted via slash command or other methods, the default variation is used

### 2. Variation Selection

When a user selects a variation:
1. The variation name is stored in `settings._variation`
2. All variation attributes are merged into the block's settings
3. The variation picker disappears
4. The block updates with the new settings

### 3. Variation Persistence

The selected variation is stored in the block's settings:
```json
{
  "id": "ve-block-123",
  "type": "my-block",
  "settings": {
    "_variation": "card",
    "layout": "vertical",
    "spacing": "large",
    "background": "white"
  }
}
```

## Variation Picker Behavior

The variation picker only displays when:
1. The block has registered variations
2. The block is empty (no inner blocks)
3. No variation has been explicitly selected yet

Once a variation is selected, the picker disappears and the user can:
- Add inner blocks to the container
- Change settings via the Settings panel
- Switch to a different variation using the variation selector in Settings

## Best Practices

### Naming Variations

- Use short, descriptive names (1-2 words)
- Use lowercase slugs for variation keys (e.g., 'card', 'hero', 'stack')
- Use Title Case for display titles (e.g., 'Card', 'Hero', 'Stack')

### Default Variation

- Always mark one variation as `isDefault => true`
- The default variation is used when the block is inserted without explicit variation selection

### Settings Inheritance

- Variations only set initial defaults
- Users can override any setting via the Settings panel
- Don't include settings in variations that shouldn't be changed

### Icon Selection

- Use descriptive icons that visually represent the variation
- Prefer Font Awesome Solid icons (e.g., 'fas.cube')
- Ensure icons are distinct enough to differentiate variations

## Example: Custom Block with Variations

Here's a complete example of a custom block with variations:

```php
// In your package's service provider
use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

public function boot(): void
{
    $registry = app( BlockRegistry::class );

    // Register the block
    $registry->register( 'testimonial', [
        'name'            => __( 'Testimonial' ),
        'description'     => __( 'Display customer testimonials' ),
        'icon'            => 'fas.quote-left',
        'category'        => 'text',
        'inner_blocks'    => true,
        'keywords'        => [ 'testimonial', 'quote', 'review' ],
        'content_schema'  => [
            'inner_blocks' => [ 'type' => 'repeater', 'label' => __( 'Content' ) ],
        ],
        'settings_schema' => [
            'layout'     => [
                'type'    => 'select',
                'label'   => __( 'Layout' ),
                'options' => [ 'vertical', 'horizontal' ],
                'default' => 'vertical',
            ],
            'show_avatar' => [
                'type'    => 'toggle',
                'label'   => __( 'Show Avatar' ),
                'default' => true,
            ],
            'alignment' => [
                'type'    => 'select',
                'label'   => __( 'Alignment' ),
                'options' => [ 'left', 'center', 'right' ],
                'default' => 'left',
            ],
        ],
        'supports' => [ 'colors', 'borders' ],
    ] );

    // Register variations
    $registry->registerVariation( 'testimonial', 'standard', [
        'title'       => __( 'Standard' ),
        'description' => __( 'Vertical layout' ),
        'icon'        => 'fas.quote-left',
        'isDefault'   => true,
        'attributes'  => [
            'settings' => [
                'layout'      => 'vertical',
                'show_avatar' => true,
                'alignment'   => 'left',
            ],
        ],
    ] );

    $registry->registerVariation( 'testimonial', 'compact', [
        'title'       => __( 'Compact' ),
        'description' => __( 'Horizontal layout' ),
        'icon'        => 'fas.grip-lines',
        'isDefault'   => false,
        'attributes'  => [
            'settings' => [
                'layout'      => 'horizontal',
                'show_avatar' => true,
                'alignment'   => 'left',
            ],
        ],
    ] );

    $registry->registerVariation( 'testimonial', 'centered', [
        'title'       => __( 'Centered' ),
        'description' => __( 'Center aligned' ),
        'icon'        => 'fas.align-center',
        'isDefault'   => false,
        'attributes'  => [
            'settings' => [
                'layout'      => 'vertical',
                'show_avatar' => false,
                'alignment'   => 'center',
            ],
        ],
    ] );
}
```

## Querying Variations

### Check if Block Has Variations

```php
$hasVariations = veBlocks()->hasVariations( 'my-block' );
```

### Get All Variations for a Block

```php
$variations = veBlocks()->getVariations( 'my-block' );
// Returns: ['default' => [...], 'card' => [...]]
```

### Get a Specific Variation

```php
$variation = veBlocks()->getVariation( 'my-block', 'card' );
// Returns: ['title' => 'Card', 'icon' => 'fas.square', ...]
```

## Troubleshooting

### Variation Picker Not Showing

Check that:
1. Variations are registered before the component renders
2. The block type matches the registered block
3. The block is empty (no inner blocks)
4. No variation has been selected yet (`_variation` is not set)

### Variation Not Applying

Check that:
1. The `applyBlockVariation` method exists on Canvas or Editor component
2. Variation attributes are properly formatted
3. Block settings schema includes the settings you're trying to apply

### Variation Persists After Adding Content

This is expected behavior. Once a variation is selected:
1. The picker disappears
2. Settings are applied
3. User can add inner blocks
4. Settings remain until manually changed

To reset, the user can select a different variation in the Settings panel.
