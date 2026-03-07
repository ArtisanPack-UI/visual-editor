# Creating Custom Blocks for the Visual Editor

This guide walks you through everything you need to know to create custom blocks for the ArtisanPack UI Visual Editor. Whether you're building a simple text block or a complex container block with inner blocks, this guide covers the full process from start to finish.

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Block Directory Structure](#block-directory-structure)
3. [Step 1: Create the block.json Metadata File](#step-1-create-the-blockjson-metadata-file)
4. [Step 2: Create the Block PHP Class](#step-2-create-the-block-php-class)
5. [Step 3: Create the Editor View](#step-3-create-the-editor-view-editbladephp)
6. [Step 4: Create the Frontend View](#step-4-create-the-frontend-view-savebladephp)
7. [Step 5: Register Your Block](#step-5-register-your-block)
8. [Block Attributes](#block-attributes)
9. [Content & Style Schemas](#content--style-schemas)
10. [Block Supports](#block-supports)
11. [Toolbar Controls](#toolbar-controls)
12. [Custom Inspector Panels](#custom-inspector-panels)
13. [Block Variations](#block-variations)
14. [Block Transforms](#block-transforms)
15. [Container Blocks (Inner Blocks)](#container-blocks-inner-blocks)
16. [Block Versioning & Migration](#block-versioning--migration)
17. [Disabling & Filtering Blocks](#disabling--filtering-blocks)
18. [Complete Examples](#complete-examples)
19. [Best Practices](#best-practices)
20. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

The Visual Editor block system is built around several core components:

| Component | Location | Purpose |
|-----------|----------|---------|
| `BlockInterface` | `src/Blocks/Contracts/BlockInterface.php` | Contract all blocks must satisfy |
| `BaseBlock` | `src/Blocks/BaseBlock.php` | Abstract class providing default implementations |
| `HasBlockSupports` | `src/Blocks/Concerns/HasBlockSupports.php` | Trait for feature support declarations |
| `BlockRegistry` | `src/Blocks/BlockRegistry.php` | Centralized registry for all block types |
| `BlockDiscoveryService` | `src/Blocks/BlockDiscoveryService.php` | Auto-discovers blocks from the filesystem |

### How It Works

1. **Discovery** -- `BlockDiscoveryService` scans `src/Blocks/{Category}/{BlockName}/` directories for `block.json` files.
2. **Instantiation** -- The discovered class (e.g., `MyBlockBlock`) is instantiated, which triggers `block.json` metadata loading.
3. **Registration** -- The block instance is registered in `BlockRegistry` and becomes available in the editor.
4. **Rendering** -- When a block is used, `renderEditor()` renders the edit view and `render()` renders the frontend save view.

### Block Lifecycle

```
block.json (metadata) --> BaseBlock constructor --> loadMetadata()
                                                --> resolveBlockDirectory()
                                                --> Block is ready for registration
```

---

## Block Directory Structure

Every block lives in its own directory under a category folder. The naming convention is:

```
src/Blocks/{Category}/{BlockName}/
├── block.json              # Required: Metadata and attributes
├── {BlockName}Block.php    # Required: PHP class extending BaseBlock
└── views/
    ├── edit.blade.php      # Required: Editor view
    ├── save.blade.php      # Required: Frontend view
    ├── toolbar.blade.php   # Optional: Custom toolbar controls
    └── inspector.blade.php # Optional: Custom inspector panel
```

### Categories

Blocks are organized into these categories:

| Category | Directory | Purpose |
|----------|-----------|---------|
| `text` | `Text/` | Text content blocks (Paragraph, Heading, List, Quote) |
| `media` | `Media/` | Media blocks (Image, Video, Audio, File, Gallery) |
| `layout` | `Layout/` | Layout/container blocks (Group, Columns, Grid, Spacer) |
| `interactive` | `Interactive/` | Interactive blocks (Button, Code) |

You can use any of these existing categories or define your own category string.

### Naming Conventions

- **Directory name** matches the block class name prefix: `Alert/AlertBlock.php`
- **Class name** follows the pattern `{BlockName}Block` and extends `BaseBlock`
- **Namespace** follows the directory structure: `ArtisanPackUI\VisualEditor\Blocks\{Category}\{BlockName}`

---

## Step 1: Create the block.json Metadata File

The `block.json` file is the single source of truth for your block's identity, attributes, and feature support. It is loaded automatically by `BaseBlock` when the block is instantiated.

### Minimal block.json

```json
{
    "type": "alert",
    "name": "Alert",
    "description": "Display an alert or notice message",
    "icon": "exclamation-triangle",
    "category": "interactive",
    "keywords": ["alert", "notice", "warning", "info"],
    "version": 1,
    "public": true,
    "parent": null,
    "allowedChildren": null,
    "attributes": {
        "message": {
            "type": "rich_text",
            "source": "content",
            "default": ""
        },
        "variant": {
            "type": "string",
            "source": "content",
            "default": "info"
        }
    },
    "supports": {
        "anchor": true,
        "className": true
    }
}
```

### block.json Field Reference

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `type` | `string` | Yes | Unique block type identifier (kebab-case). Used as the registry key. |
| `name` | `string` | Yes | Human-readable block name shown in the inserter. |
| `description` | `string` | Yes | Short description shown in the inserter tooltip. |
| `icon` | `string` | Yes | Icon identifier (Heroicons name, e.g., `photo`, `bars-3-bottom-left`). |
| `category` | `string` | Yes | Block category: `text`, `media`, `layout`, `interactive`, or custom. |
| `keywords` | `array` | No | Searchable keywords for the block inserter. |
| `version` | `int` | No | Schema version for migrations. Defaults to `1`. |
| `public` | `bool` | No | Whether the block appears in the inserter. Defaults to `true`. |
| `parent` | `array\|null` | No | Allowed parent block types. `null` means any parent. |
| `allowedChildren` | `array\|null` | No | Allowed child block types. `null` means any child. |
| `supportsInnerBlocks` | `bool` | No | Whether the block can contain other blocks. Defaults to `false`. |
| `hasJsRenderer` | `bool` | No | Whether the editor uses JavaScript rendering. Defaults to `false`. |
| `innerBlocksOrientation` | `string` | No | Inner blocks layout: `vertical` or `horizontal`. Defaults to `vertical`. |
| `attributes` | `object` | Yes | Block attribute declarations. See [Block Attributes](#block-attributes). |
| `supports` | `object` | No | Feature support flags. See [Block Supports](#block-supports). |
| `variations` | `array` | No | Static variation definitions. See [Block Variations](#block-variations). |

---

## Step 2: Create the Block PHP Class

The PHP class extends `BaseBlock` and provides the content and style schemas that drive the inspector panel UI. Most metadata is read from `block.json`, so the PHP class is focused on schema definitions and behavior.

### Minimal Block Class

```php
<?php

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Alert;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

class AlertBlock extends BaseBlock
{
    /**
     * Get the content field schema.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function getContentSchema(): array
    {
        return [
            'message' => [
                'type'    => 'rich_text',
                'label'   => __( 'Message' ),
                'toolbar' => [ 'bold', 'italic', 'link' ],
                'default' => '',
            ],
            'variant' => [
                'type'    => 'select',
                'label'   => __( 'Alert Type' ),
                'options' => [
                    'info'    => __( 'Info' ),
                    'success' => __( 'Success' ),
                    'warning' => __( 'Warning' ),
                    'error'   => __( 'Error' ),
                ],
                'default' => 'info',
            ],
        ];
    }

    /**
     * Get the style field schema.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStyleSchema(): array
    {
        return [];
    }
}
```

### What BaseBlock Provides For Free

When you extend `BaseBlock`, you inherit default implementations for all `BlockInterface` methods. The constructor automatically:

1. Resolves the block directory path using PHP reflection.
2. Loads and parses `block.json` from that directory.
3. Stores the metadata for use by all getter methods.

This means `getType()`, `getName()`, `getDescription()`, `getIcon()`, `getCategory()`, `getKeywords()`, `getVersion()`, `isPublic()`, `supportsInnerBlocks()`, `getAllowedParents()`, `getAllowedChildren()`, `getAttributes()`, and more all read from `block.json` automatically.

### Methods You Should Override

| Method | When to Override |
|--------|-----------------|
| `getContentSchema()` | **Always** -- defines the inspector panel content fields |
| `getStyleSchema()` | **Always** -- defines the inspector panel style fields (return `[]` if none) |
| `getToolbarControls()` | When adding custom toolbar buttons beyond the default alignment control |
| `getVariations()` | When offering preset configurations (e.g., Group vs Row vs Stack) |
| `getTransforms()` | When allowing conversion to/from other block types |
| `migrate()` | When you bump the schema version and need to migrate old content |

### Methods You Rarely Need to Override

| Method | Default Behavior |
|--------|-----------------|
| `getType()` | Reads `type` from `block.json` |
| `getName()` | Reads `name` from `block.json` (with translation key fallback) |
| `getDescription()` | Reads `description` from `block.json` (with translation key fallback) |
| `render()` | Renders the `save.blade.php` view |
| `renderEditor()` | Renders the `edit.blade.php` view |
| `getAdvancedSchema()` | Auto-generates anchor/className fields based on supports |
| `getDefaultContent()` | Extracts defaults from content schema + attributes |
| `getDefaultStyles()` | Extracts defaults from style schema + attributes |
| `toArray()` | Serializes block metadata for JavaScript |

---

## Step 3: Create the Editor View (edit.blade.php)

The editor view is what users see and interact with inside the visual editor. It receives several variables from the rendering system.

### Available Variables

| Variable | Type | Description |
|----------|------|-------------|
| `$content` | `array` | Block content values (keyed by attribute name) |
| `$styles` | `array` | Block style values (keyed by attribute name) |
| `$context` | `array` | Rendering context (includes `blockId`, parent info, etc.) |
| `$block` | `BaseBlock` | The block instance itself |
| `$innerBlocks` | `array` | Pre-rendered inner block HTML strings (for container blocks) |

### Basic Editor View

```blade
@php
    $message = $content['message'] ?? '';
    $variant = $content['variant'] ?? 'info';

    $variantClasses = [
        'info'    => 'alert-info',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'error'   => 'alert-error',
    ];

    $classes = 've-block ve-block-alert ve-block-editing alert ' . ( $variantClasses[$variant] ?? 'alert-info' );
@endphp

<div class="{{ $classes }}">
    <div
        contenteditable="true"
        data-placeholder="{{ __( 'Type your alert message...' ) }}"
    >{!! $message !!}</div>
</div>
```

### Key Editor View Patterns

#### Inline Editing with `contenteditable`

Use `contenteditable="true"` on elements where users should type directly:

```blade
<p
    contenteditable="true"
    data-placeholder="{{ __( 'Start typing...' ) }}"
>{!! $text !!}</p>
```

#### Placeholder Text

Use `data-placeholder` to show placeholder text when the field is empty. The editor's CSS and JavaScript handle showing/hiding the placeholder.

#### Enter Key Behavior

Add `data-ve-enter-new-block="true"` to contenteditable elements that should create a new block when the user presses Enter:

```blade
<p
    contenteditable="true"
    data-ve-enter-new-block="true"
>{!! $text !!}</p>
```

#### Slash Commands

Add `data-ve-slash-command="true"` to enable the `/` slash command menu for inserting blocks:

```blade
<p
    contenteditable="true"
    data-ve-slash-command="true"
>{!! $text !!}</p>
```

#### CSS Class Conventions

- Always include `ve-block` and `ve-block-{type}` classes on the root element.
- Add `ve-block-editing` to distinguish editor views from save views.
- Use inline styles for user-configured colors, spacing, and borders.

#### Inline Styles for User Settings

Build inline styles from the `$styles` array:

```blade
@php
    $inlineStyles = '';
    if ( $textColor ) {
        $inlineStyles .= "color: {$textColor};";
    }
    if ( $bgColor ) {
        $inlineStyles .= "background-color: {$bgColor};";
    }
@endphp

<div
    class="{{ $classes }}"
    @if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
```

---

## Step 4: Create the Frontend View (save.blade.php)

The save view renders the block for public/frontend display. It receives the same variables as the editor view but should produce clean, semantic HTML without editor-specific attributes.

### Basic Save View

```blade
@php
    $message   = $content['message'] ?? '';
    $variant   = $content['variant'] ?? 'info';
    $anchor    = $content['anchor'] ?? null;
    $htmlId    = $content['htmlId'] ?? null;
    $className = $content['className'] ?? '';

    $elementId = $htmlId ?: $anchor;

    $variantClasses = [
        'info'    => 'alert-info',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'error'   => 'alert-error',
    ];

    $classes = 've-block ve-block-alert alert ' . ( $variantClasses[$variant] ?? 'alert-info' );
    if ( $className ) {
        $classes .= " {$className}";
    }
@endphp

<div
    class="{{ $classes }}"
    @if ( $elementId ) id="{{ $elementId }}" @endif
    role="alert"
>{!! $message !!}</div>
```

### Key Differences from Editor View

| Concern | Editor View | Save View |
|---------|-------------|-----------|
| `contenteditable` | Yes, for inline editing | No |
| `data-placeholder` | Yes | No |
| `data-ve-*` attributes | Yes | No |
| `ve-block-editing` class | Yes | No |
| `anchor`/`htmlId` support | Not needed | Include `id` attribute |
| `className` support | Not needed | Append to class list |
| Accessibility attributes | Optional | Include `role`, `aria-*` |
| Link `rel` attributes | Not applicable | Include `noopener noreferrer` for `_blank` targets |

---

## Step 5: Register Your Block

### Automatic Discovery (Package Blocks)

If your block lives inside the package's `src/Blocks/{Category}/{BlockName}/` directory structure, it is auto-discovered. The `BlockDiscoveryService` scans these category directories:

- `Text/`
- `Media/`
- `Layout/`
- `Interactive/`

The discovery service looks for a `block.json` file in each subdirectory and resolves the class name as:

```
{BaseNamespace}\{Category}\{BlockName}\{BlockName}Block
```

For example:
```
src/Blocks/Interactive/Alert/AlertBlock.php
--> ArtisanPackUI\VisualEditor\Blocks\Interactive\Alert\AlertBlock
```

No manual registration is needed for auto-discovered blocks.

### Manual Registration (Application Blocks)

For blocks defined in your Laravel application (outside the package), register them in a service provider:

```php
<?php

namespace App\Providers;

use App\VisualEditor\Blocks\Interactive\Alert\AlertBlock;
use Illuminate\Support\ServiceProvider;

class VisualEditorServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Using the helper function
        veRegisterBlock( new AlertBlock() );

        // Or using the registry directly
        app( 'visual-editor.blocks' )->register( new AlertBlock() );
    }
}
```

### Helper Functions

The package provides global helper functions for block management:

```php
// Register a block
veRegisterBlock( $block );

// Check if a block type exists
veBlockExists( 'alert' ); // true

// Get a registered block instance
$block = veGetBlock( 'alert' );

// Get the Visual Editor instance
$editor = visualEditor();
```

### Using the Hooks System

The block registry fires an action hook when a block is registered:

```php
addAction( 'ap.visualEditor.block.registered', function ( $block ) {
    // React to block registration
    Log::info( 'Block registered: ' . $block->getType() );
} );
```

You can also filter the full list of registered blocks:

```php
addFilter( 'ap.visualEditor.blocksRegister', function ( array $blocks ) {
    // Remove a block
    unset( $blocks['code'] );

    // Or add a block
    $blocks['custom'] = new CustomBlock();

    return $blocks;
} );
```

---

## Block Attributes

Attributes define the data your block stores. Each attribute is declared in `block.json` under the `attributes` key.

### Attribute Structure

```json
{
    "attributes": {
        "attributeName": {
            "type": "string",
            "source": "content",
            "default": "default value"
        }
    }
}
```

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `type` | `string` | Yes | Data type: `string`, `rich_text`, `url`, `integer`, `boolean`, `object`, `array` |
| `source` | `string` | Yes | Where the value is stored: `content` or `style` |
| `default` | `mixed` | Yes | Default value when the block is first created |

### Content vs. Style Source

- **`content`** -- Data that defines what the block displays (text, URLs, settings). Passed to views as `$content`.
- **`style`** -- Visual presentation values (colors, sizes, spacing, borders). Passed to views as `$styles`.

This separation allows the inspector panel to organize fields into logical tabs.

### Attribute Types

| Type | JSON Type | Use Case |
|------|-----------|----------|
| `string` | `"string"` | Short text, select values, identifiers |
| `rich_text` | `"string"` | HTML-formatted text with rich editing |
| `url` | `"string"` | URL values with validation |
| `integer` | `"number"` | Whole numbers |
| `boolean` | `"boolean"` | Toggle/checkbox values |
| `object` | `"object"` | Complex values like spacing, borders |
| `array` | `"array"` | Lists of values |

### Example: Object Attribute (Border)

```json
{
    "border": {
        "type": "object",
        "source": "style",
        "default": {
            "width": "0",
            "widthUnit": "px",
            "style": "none",
            "color": "#000000",
            "radius": "0",
            "radiusUnit": "px",
            "perSide": false,
            "perCorner": false
        }
    }
}
```

---

## Content & Style Schemas

Schemas define how the inspector panel renders controls for editing block attributes. The `getContentSchema()` and `getStyleSchema()` methods return arrays of field definitions.

### Schema Field Structure

```php
'fieldName' => [
    'type'        => 'text',        // Required: field type
    'label'       => __( 'Label' ), // Required: display label
    'placeholder' => 'Hint...',     // Optional: input placeholder
    'hint'        => 'Help text',   // Optional: help text below field
    'default'     => '',            // Optional: default value
    'options'     => [],            // Required for select fields
    'inspector'   => true,          // Optional: show in inspector (default true)
    'condition'   => [],            // Optional: conditional display
],
```

### Available Field Types

#### Text Input (`text`)

Single-line text input:

```php
'title' => [
    'type'        => 'text',
    'label'       => __( 'Title' ),
    'placeholder' => __( 'Enter title...' ),
    'default'     => '',
],
```

#### Textarea (`textarea`)

Multi-line text input:

```php
'description' => [
    'type'    => 'textarea',
    'label'   => __( 'Description' ),
    'hint'    => __( 'Describe this element for accessibility' ),
    'default' => '',
],
```

#### Rich Text (`rich_text`)

Rich text editor with formatting toolbar:

```php
'content' => [
    'type'    => 'rich_text',
    'label'   => __( 'Content' ),
    'toolbar' => [ 'bold', 'italic', 'link', 'underline', 'strikethrough' ],
    'default' => '',
],
```

Available toolbar options: `bold`, `italic`, `link`, `underline`, `strikethrough`.

#### URL (`url`)

URL input with validation:

```php
'link' => [
    'type'    => 'url',
    'label'   => __( 'Link URL' ),
    'default' => '',
],
```

#### Select (`select`)

Dropdown select:

```php
'variant' => [
    'type'    => 'select',
    'label'   => __( 'Style' ),
    'options' => [
        'primary'   => __( 'Primary' ),
        'secondary' => __( 'Secondary' ),
        'outline'   => __( 'Outline' ),
    ],
    'default' => 'primary',
],
```

#### Toggle (`toggle`)

Boolean toggle switch:

```php
'showIcon' => [
    'type'    => 'toggle',
    'label'   => __( 'Show Icon' ),
    'default' => true,
],
```

#### Checkbox (`checkbox`)

Boolean checkbox:

```php
'openInNewTab' => [
    'type'    => 'checkbox',
    'label'   => __( 'Open in new tab' ),
    'default' => false,
],
```

#### Range (`range`)

Number slider:

```php
'opacity' => [
    'type'    => 'range',
    'label'   => __( 'Opacity' ),
    'min'     => 0,
    'max'     => 100,
    'step'    => 5,
    'default' => 100,
],
```

#### Color (`color`)

Color picker:

```php
'textColor' => [
    'type'    => 'color',
    'label'   => __( 'Text Color' ),
    'default' => null,
],
```

#### Spacing (`spacing`)

Padding/margin control with individual sides:

```php
'padding' => [
    'type'    => 'spacing',
    'label'   => __( 'Padding' ),
    'sides'   => [ 'top', 'right', 'bottom', 'left' ],
    'default' => null,
],
```

#### Border (`border`)

Border width, style, color, and radius control:

```php
'border' => [
    'type'    => 'border',
    'label'   => __( 'Border' ),
    'default' => [
        'width'      => '0',
        'widthUnit'  => 'px',
        'style'      => 'none',
        'color'      => '#000000',
        'radius'     => '0',
        'radiusUnit' => 'px',
        'perSide'    => false,
        'perCorner'  => false,
    ],
],
```

#### Media Picker (`media_picker`)

Media library picker for selecting images/files:

```php
'imageUrl' => [
    'type'    => 'media_picker',
    'label'   => __( 'Image' ),
    'default' => '',
],
```

### Conditional Fields

Show a field only when another field has a specific value:

```php
'linkTarget' => [
    'type'      => 'select',
    'label'     => __( 'Link Target' ),
    'options'   => [
        '_self'  => __( 'Same Window' ),
        '_blank' => __( 'New Window' ),
    ],
    'default'   => '_self',
    'condition' => [ 'link', '!=', '' ],  // Only show when link is not empty
],
```

The `condition` array takes three elements: `[fieldName, operator, value]`.

### Hidden Inspector Fields

Set `'inspector' => false` to exclude a field from the inspector panel. This is useful for fields managed by toolbar controls or variation pickers:

```php
'flexDirection' => [
    'type'      => 'select',
    'label'     => __( 'Direction' ),
    'options'   => [ 'column' => 'Column', 'row' => 'Row' ],
    'default'   => 'column',
    'inspector' => false,  // Managed by variation picker, not inspector
],
```

---

## Block Supports

Supports declare which built-in editor features your block enables. They are defined in the `supports` object of `block.json` and are merged with defaults by the `HasBlockSupports` trait.

### Default Supports

All blocks start with these defaults (most features disabled):

```json
{
    "supports": {
        "align": false,
        "textAlignment": false,
        "textFormatting": false,
        "color": {
            "text": false,
            "background": false
        },
        "typography": {
            "fontSize": false,
            "fontFamily": false
        },
        "spacing": {
            "margin": false,
            "padding": false,
            "blockSpacing": false
        },
        "border": false,
        "shadow": false,
        "dimensions": {
            "aspectRatio": false,
            "minHeight": false
        },
        "background": {
            "backgroundImage": false,
            "backgroundSize": false,
            "backgroundPosition": false,
            "backgroundGradient": false
        },
        "anchor": true,
        "className": true
    }
}
```

### Support Reference

#### Alignment (`align`)

Controls block-level alignment in the toolbar:

```json
// Enable specific alignments
"align": ["left", "center", "right"]

// Enable all alignments (left, center, right, wide, full)
"align": true

// Disable alignment
"align": false
```

#### Text Alignment (`textAlignment`)

Shows text alignment controls (left, center, right) in the block toolbar:

```json
"textAlignment": true
```

#### Text Formatting (`textFormatting`)

Shows text formatting controls (bold, italic, link) in the block toolbar:

```json
"textFormatting": true
```

#### Color (`color`)

Enables text and/or background color controls in the inspector:

```json
"color": {
    "text": true,
    "background": true
}
```

#### Typography (`typography`)

Enables font size and/or font family controls:

```json
"typography": {
    "fontSize": true,
    "fontFamily": true
}
```

Some blocks support additional typography features like `dropCap`:

```json
"typography": {
    "fontSize": true,
    "dropCap": true
}
```

#### Spacing (`spacing`)

Enables margin, padding, and/or block gap controls:

```json
"spacing": {
    "margin": true,
    "padding": true,
    "blockGap": true
}
```

#### Border (`border`)

Enables border width, style, color, and radius controls:

```json
"border": true
```

#### Shadow (`shadow`)

Enables drop shadow control:

```json
"shadow": true
```

#### Dimensions (`dimensions`)

Enables aspect ratio and/or minimum height controls:

```json
"dimensions": {
    "aspectRatio": true,
    "minHeight": true
}
```

#### Background (`background`)

Enables background image, size, position, and gradient controls:

```json
"background": {
    "backgroundImage": true,
    "backgroundSize": true,
    "backgroundPosition": true,
    "backgroundGradient": true
}
```

#### Anchor & HTML ID (`anchor`, `htmlId`)

Enables the HTML anchor/ID field in the advanced settings panel:

```json
"anchor": true,
"htmlId": true
```

#### Additional CSS Classes (`className`)

Enables the additional CSS classes field in the advanced settings panel:

```json
"className": true
```

### Checking Supports in PHP

Use the `supportsFeature()` method with dot-notation for nested features:

```php
$block->supportsFeature( 'color.text' );       // true/false
$block->supportsFeature( 'spacing.margin' );    // true/false
$block->supportsFeature( 'border' );            // true/false
$block->supportsFeature( 'anchor' );            // true/false

// Get supported alignment options
$block->getSupportedAlignments();  // ['left', 'center', 'right']

// Get all active style supports as flat dot-path list
$block->getActiveStyleSupports();  // ['color.text', 'color.background', 'spacing.margin']
```

---

## Toolbar Controls

The toolbar appears above a selected block and provides quick-access controls. By default, blocks with alignment support get an alignment control automatically.

### Declaring Toolbar Controls

Override `getToolbarControls()` to add custom controls:

```php
public function getToolbarControls(): array
{
    // Start with parent controls (includes alignment if supported)
    $controls = parent::getToolbarControls();

    // Add a custom control group
    $controls[] = [
        'group'    => 'alert-actions',
        'controls' => [
            [
                'type'  => 'button',
                'field' => 'dismiss',
                'label' => __( 'Dismissible' ),
                'icon'  => 'x-mark',
            ],
        ],
    ];

    return $controls;
}
```

### Toolbar Control Structure

Each control group has:

| Property | Type | Description |
|----------|------|-------------|
| `group` | `string` | Unique group identifier |
| `controls` | `array` | Array of control definitions |

Each control has:

| Property | Type | Description |
|----------|------|-------------|
| `type` | `string` | Control type: `button`, `block-alignment` |
| `field` | `string` | The attribute field this control manages |
| `label` | `string` | Accessible label for the control |
| `icon` | `string` | Icon identifier |
| `options` | `array` | For alignment controls, the alignment options |

### Custom Toolbar View

For complex toolbar interactions, create a `views/toolbar.blade.php` file. The `hasCustomToolbar()` method automatically returns `true` when this file exists.

```blade
{{-- views/toolbar.blade.php --}}
<template x-if="(() => {
    if (!Alpine.store('selection')?.focused || !Alpine.store('editor')) return false;
    const block = Alpine.store('editor').getBlock(Alpine.store('selection').focused);
    return block?.type === 'alert';
})()">
    <div x-data="{
        get block() {
            const blockId = Alpine.store('selection')?.focused;
            if (!blockId || !Alpine.store('editor')) return null;
            return Alpine.store('editor').getBlock(blockId);
        },

        toggleDismissible() {
            if (!this.block) return;
            const current = this.block.content?.dismissible ?? false;
            $dispatch('ve-field-change', {
                blockId: this.block.id,
                field: 'dismissible',
                value: !current
            });
        },
    }" class="relative flex items-center">
        {{-- Separator --}}
        <div class="w-px h-4 bg-base-300 mx-0.5" aria-hidden="true"></div>

        <button
            type="button"
            class="btn btn-ghost btn-xs btn-square"
            x-on:click="toggleDismissible()"
            :title="block?.content?.dismissible ? 'Remove dismiss' : 'Add dismiss'"
        >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>
```

### Interacting with the Editor from JavaScript

The editor exposes Alpine.js stores for block management:

```javascript
// Get the currently selected block
const blockId = Alpine.store('selection')?.focused;
const block = Alpine.store('editor')?.getBlock(blockId);

// Update a block field
$dispatch('ve-field-change', {
    blockId: blockId,
    field: 'variant',
    value: 'warning'
});

// Update multiple fields at once
Alpine.store('editor').updateBlock(blockId, {
    variant: 'warning',
    dismissible: true
});
```

---

## Custom Inspector Panels

The inspector panel is auto-generated from your content and style schemas. For most blocks, you don't need a custom inspector view. However, for complex blocks requiring specialized UI, you can create a `views/inspector.blade.php` file.

The `hasCustomInspector()` method automatically returns `true` when this file exists.

```blade
{{-- views/inspector.blade.php --}}
<x-ve-inspector-section title="{{ __( 'Alert Settings' ) }}">
    <x-ve-inspector-field>
        <x-slot:label>{{ __( 'Alert Variant' ) }}</x-slot:label>
        {{-- Custom variant picker UI --}}
        <div class="flex gap-2">
            <button class="btn btn-xs btn-info">Info</button>
            <button class="btn btn-xs btn-success">Success</button>
            <button class="btn btn-xs btn-warning">Warning</button>
            <button class="btn btn-xs btn-error">Error</button>
        </div>
    </x-ve-inspector-field>
</x-ve-inspector-section>
```

---

## Block Variations

Variations allow a single block type to offer multiple preset configurations. Users pick a variation when inserting the block or can switch between variations.

### Defining Variations in PHP

Override `getVariations()`:

```php
public function getVariations(): array
{
    return [
        [
            'name'        => 'info',
            'label'       => __( 'Info Alert' ),
            'description' => __( 'An informational alert message' ),
            'icon'        => 'information-circle',
            'attributes'  => [
                'variant' => 'info',
            ],
            'isDefault'   => true,
        ],
        [
            'name'        => 'warning',
            'label'       => __( 'Warning Alert' ),
            'description' => __( 'A warning alert message' ),
            'icon'        => 'exclamation-triangle',
            'attributes'  => [
                'variant' => 'warning',
            ],
            'isDefault'   => false,
        ],
        [
            'name'        => 'error',
            'label'       => __( 'Error Alert' ),
            'description' => __( 'An error alert message' ),
            'icon'        => 'x-circle',
            'attributes'  => [
                'variant' => 'error',
            ],
            'isDefault'   => false,
        ],
    ];
}
```

### Variation Structure

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `name` | `string` | Yes | Unique variation identifier |
| `label` | `string` | Yes | Human-readable label |
| `description` | `string` | No | Short description |
| `icon` | `string` | Yes | Icon identifier |
| `attributes` | `array` | Yes | Attribute overrides applied when this variation is selected |
| `isDefault` | `bool` | Yes | Whether this is the default variation |

### Static Variations in block.json

You can also define variations directly in `block.json`. These are typically used for simpler variations that don't need translated labels:

```json
{
    "variations": [
        {
            "name": "info",
            "icon": "information-circle",
            "isDefault": true,
            "attributes": {
                "variant": "info"
            }
        },
        {
            "name": "warning",
            "icon": "exclamation-triangle",
            "isDefault": false,
            "attributes": {
                "variant": "warning"
            }
        }
    ]
}
```

### Variation Picker in Edit View

For container blocks, you can display a variation picker when the block is empty (see the Group block for a reference implementation):

```blade
@php
    $showVariationPicker = empty( $innerBlocks ) && ! $hasExplicitSettings;
@endphp

@if ( $showVariationPicker )
    <div class="ve-variation-picker flex gap-3">
        <button type="button" data-ve-set-variation="info">Info</button>
        <button type="button" data-ve-set-variation="warning">Warning</button>
    </div>
@else
    {{-- Normal block content --}}
@endif
```

---

## Block Transforms

Transforms allow users to convert a block from one type to another. They define attribute mappings between the source and target block types.

### Defining Transforms

Override `getTransforms()`:

```php
public function getTransforms(): array
{
    return [
        // Target block type => attribute mappings
        'heading' => [
            'text' => 'text',  // source attribute => target attribute
        ],
        'quote' => [
            'text' => 'text',
        ],
    ];
}
```

In this example, a Paragraph block can be transformed into a Heading or Quote block, mapping its `text` attribute to the target block's `text` attribute.

### Transform Rules

- The keys are the target block types you can transform **to**.
- The values map source attribute names to target attribute names.
- Unmapped attributes use default values from the target block.
- Transforms are one-directional -- define transforms on both block types for bidirectional conversion.

---

## Container Blocks (Inner Blocks)

Container blocks can hold other blocks as children. This is how blocks like Group, Columns, and Grid work.

### Enabling Inner Blocks

In `block.json`:

```json
{
    "supportsInnerBlocks": true,
    "hasJsRenderer": true,
    "innerBlocksOrientation": "vertical",
    "allowedChildren": null
}
```

| Property | Description |
|----------|-------------|
| `supportsInnerBlocks` | Set to `true` to enable inner block support |
| `hasJsRenderer` | Set to `true` for container blocks (editor delegates rendering to JavaScript) |
| `innerBlocksOrientation` | `vertical` (stacked) or `horizontal` (side-by-side) |
| `allowedChildren` | `null` for any block type, or an array like `["paragraph", "heading"]` |

### Rendering Inner Blocks in Edit View

Use the `<x-ve-inner-blocks>` component:

```blade
<div class="ve-block ve-block-my-container ve-block-editing">
    <x-ve-inner-blocks
        :inner-blocks="$innerBlocks"
        :parent-id="$context['blockId'] ?? null"
        :placeholder="__( 'Add content here...' )"
        :orientation="'vertical'"
        :editing="true"
    />
</div>
```

### Rendering Inner Blocks in Save View

In the save view, inner blocks are pre-rendered HTML strings:

```blade
<div class="ve-block ve-block-my-container">
    @foreach ( $innerBlocks as $innerBlock )
        {!! $innerBlock !!}
    @endforeach
</div>
```

### Restricting Child Block Types

To only allow specific block types as children:

```json
{
    "allowedChildren": ["paragraph", "heading", "image"]
}
```

### Restricting Parent Block Types

To make a block only insertable inside specific parent blocks:

```json
{
    "parent": ["columns"]
}
```

This is how the `column` block works -- it can only exist inside a `columns` block.

### Hierarchical Block Example

The Columns/Column pattern demonstrates parent-child blocks:

```
columns (container, allowedChildren: ["column"])
├── column (container, parent: ["columns"], allowedChildren: null)
│   ├── paragraph
│   ├── image
│   └── button
├── column
│   └── heading
└── column
    └── paragraph
```

---

## Block Versioning & Migration

When you change a block's attribute structure, bump the version number and implement the `migrate()` method to handle existing content.

### Bumping the Version

In `block.json`:

```json
{
    "version": 2
}
```

### Implementing Migration

Override the `migrate()` method to transform old content to the new format:

```php
public function migrate( array $content, int $fromVersion ): array
{
    if ( $fromVersion < 2 ) {
        // Version 1 had 'type' attribute, version 2 renamed it to 'variant'
        if ( isset( $content['type'] ) ) {
            $content['variant'] = $content['type'];
            unset( $content['type'] );
        }
    }

    return $content;
}
```

The migration is called with the old content and the version it was saved with. Apply incremental migrations for each version gap.

---

## Disabling & Filtering Blocks

### Disabling Core Blocks via Config

In `config/artisanpack/visual-editor.php`:

```php
'blocks' => [
    'core' => [
        'heading'   => true,   // Enabled
        'paragraph' => true,   // Enabled
        'code'      => false,  // Disabled
    ],
    'disabled' => [
        'spacer',              // Also disabled
    ],
],
```

### Unregistering Blocks Programmatically

```php
// Unregister a single block
app( 'visual-editor.blocks' )->unregister( 'code' );

// Unregister multiple blocks
app( 'visual-editor.blocks' )->unregister( [ 'code', 'spacer', 'divider' ] );

// Unregister an entire category
app( 'visual-editor.blocks' )->unregisterCategory( 'media' );
```

### Filtering Blocks with Hooks

```php
addFilter( 'ap.visualEditor.blocksRegister', function ( array $blocks ) {
    // Remove blocks not needed for this context
    unset( $blocks['code'] );

    return $blocks;
} );
```

---

## Complete Examples

### Example 1: Simple Alert Block

A non-container block with a message and variant selector.

**Directory structure:**

```
src/Blocks/Interactive/Alert/
├── block.json
├── AlertBlock.php
└── views/
    ├── edit.blade.php
    └── save.blade.php
```

**block.json:**

```json
{
    "type": "alert",
    "name": "Alert",
    "description": "Display an alert or notice message",
    "icon": "exclamation-triangle",
    "category": "interactive",
    "keywords": ["alert", "notice", "warning", "info", "message"],
    "version": 1,
    "public": true,
    "parent": null,
    "allowedChildren": null,
    "attributes": {
        "message": {
            "type": "rich_text",
            "source": "content",
            "default": ""
        },
        "variant": {
            "type": "string",
            "source": "content",
            "default": "info"
        },
        "dismissible": {
            "type": "boolean",
            "source": "content",
            "default": false
        },
        "textColor": {
            "type": "string",
            "source": "style",
            "default": null
        },
        "padding": {
            "type": "object",
            "source": "style",
            "default": null
        },
        "margin": {
            "type": "object",
            "source": "style",
            "default": null
        }
    },
    "supports": {
        "color": {
            "text": true,
            "background": false
        },
        "spacing": {
            "margin": true,
            "padding": true
        },
        "anchor": true,
        "className": true
    }
}
```

**AlertBlock.php:**

```php
<?php

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Alert;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

class AlertBlock extends BaseBlock
{
    public function getContentSchema(): array
    {
        return [
            'message'     => [
                'type'    => 'rich_text',
                'label'   => __( 'Message' ),
                'toolbar' => [ 'bold', 'italic', 'link' ],
                'default' => '',
            ],
            'variant'     => [
                'type'    => 'select',
                'label'   => __( 'Alert Type' ),
                'options' => [
                    'info'    => __( 'Info' ),
                    'success' => __( 'Success' ),
                    'warning' => __( 'Warning' ),
                    'error'   => __( 'Error' ),
                ],
                'default' => 'info',
            ],
            'dismissible' => [
                'type'    => 'toggle',
                'label'   => __( 'Dismissible' ),
                'hint'    => __( 'Allow users to close this alert' ),
                'default' => false,
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'textColor' => [
                'type'    => 'color',
                'label'   => __( 'Text Color' ),
                'default' => null,
            ],
            'padding'   => [
                'type'    => 'spacing',
                'label'   => __( 'Padding' ),
                'sides'   => [ 'top', 'right', 'bottom', 'left' ],
                'default' => null,
            ],
            'margin'    => [
                'type'    => 'spacing',
                'label'   => __( 'Margin' ),
                'sides'   => [ 'top', 'bottom' ],
                'default' => null,
            ],
        ];
    }

    public function getTransforms(): array
    {
        return [
            'paragraph' => [
                'message' => 'text',
            ],
        ];
    }
}
```

**views/edit.blade.php:**

```blade
@php
    $message     = $content['message'] ?? '';
    $variant     = $content['variant'] ?? 'info';
    $dismissible = $content['dismissible'] ?? false;
    $textColor   = $styles['textColor'] ?? null;
    $padding     = $styles['padding'] ?? null;
    $margin      = $styles['margin'] ?? null;

    $variantClasses = [
        'info'    => 'alert-info',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'error'   => 'alert-error',
    ];

    $inlineStyles = '';
    if ( $textColor ) {
        $inlineStyles .= "color: {$textColor};";
    }
    if ( is_array( $padding ) ) {
        $top    = $padding['top'] ?? '0';
        $right  = $padding['right'] ?? '0';
        $bottom = $padding['bottom'] ?? '0';
        $left   = $padding['left'] ?? '0';
        $inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
    }
    if ( is_array( $margin ) ) {
        $top    = $margin['top'] ?? '0';
        $bottom = $margin['bottom'] ?? '0';
        $inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
    }

    $classes = 've-block ve-block-alert ve-block-editing alert ' . ( $variantClasses[$variant] ?? 'alert-info' );
@endphp

<div
    class="{{ $classes }}"
    @if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
    @if ( $dismissible )
        <button type="button" class="btn btn-sm btn-circle btn-ghost" contenteditable="false">X</button>
    @endif
    <div
        contenteditable="true"
        data-placeholder="{{ __( 'Type your alert message...' ) }}"
    >{!! $message !!}</div>
</div>
```

**views/save.blade.php:**

```blade
@php
    $message     = $content['message'] ?? '';
    $variant     = $content['variant'] ?? 'info';
    $dismissible = $content['dismissible'] ?? false;
    $textColor   = $styles['textColor'] ?? null;
    $padding     = $styles['padding'] ?? null;
    $margin      = $styles['margin'] ?? null;
    $anchor      = $content['anchor'] ?? null;
    $htmlId      = $content['htmlId'] ?? null;
    $className   = $content['className'] ?? '';

    $elementId = $htmlId ?: $anchor;

    $variantClasses = [
        'info'    => 'alert-info',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'error'   => 'alert-error',
    ];

    $inlineStyles = '';
    if ( $textColor ) {
        $inlineStyles .= "color: {$textColor};";
    }
    if ( is_array( $padding ) ) {
        $top    = $padding['top'] ?? '0';
        $right  = $padding['right'] ?? '0';
        $bottom = $padding['bottom'] ?? '0';
        $left   = $padding['left'] ?? '0';
        $inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
    }
    if ( is_array( $margin ) ) {
        $top    = $margin['top'] ?? '0';
        $bottom = $margin['bottom'] ?? '0';
        $inlineStyles .= " margin-top: {$top}; margin-bottom: {$bottom};";
    }

    $classes = 've-block ve-block-alert alert ' . ( $variantClasses[$variant] ?? 'alert-info' );
    if ( $className ) {
        $classes .= " {$className}";
    }
@endphp

<div
    class="{{ $classes }}"
    @if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
    @if ( $elementId ) id="{{ $elementId }}" @endif
    role="alert"
    @if ( $dismissible ) x-data="{ show: true }" x-show="show" @endif
>
    {!! $message !!}
    @if ( $dismissible )
        <button type="button" class="btn btn-sm btn-circle btn-ghost" x-on:click="show = false" aria-label="{{ __( 'Dismiss' ) }}">X</button>
    @endif
</div>
```

---

### Example 2: Container Block (Card)

A container block that wraps child blocks in a card layout.

**block.json:**

```json
{
    "type": "card",
    "name": "Card",
    "description": "A card container with header and footer slots",
    "icon": "square-2-stack",
    "category": "layout",
    "keywords": ["card", "container", "box", "panel"],
    "version": 1,
    "public": true,
    "parent": null,
    "allowedChildren": null,
    "supportsInnerBlocks": true,
    "hasJsRenderer": true,
    "innerBlocksOrientation": "vertical",
    "attributes": {
        "title": {
            "type": "string",
            "source": "content",
            "default": ""
        },
        "backgroundColor": {
            "type": "string",
            "source": "style",
            "default": null
        },
        "shadow": {
            "type": "string",
            "source": "style",
            "default": "md"
        },
        "padding": {
            "type": "object",
            "source": "style",
            "default": null
        },
        "border": {
            "type": "object",
            "source": "style",
            "default": {
                "width": "1",
                "widthUnit": "px",
                "style": "solid",
                "color": "#e5e7eb",
                "radius": "8",
                "radiusUnit": "px",
                "perSide": false,
                "perCorner": false
            }
        }
    },
    "supports": {
        "color": {
            "text": false,
            "background": true
        },
        "spacing": {
            "margin": true,
            "padding": true
        },
        "border": true,
        "shadow": true,
        "anchor": true,
        "className": true
    }
}
```

**CardBlock.php:**

```php
<?php

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout\Card;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

class CardBlock extends BaseBlock
{
    public function getContentSchema(): array
    {
        return [
            'title' => [
                'type'        => 'text',
                'label'       => __( 'Card Title' ),
                'placeholder' => __( 'Optional card title...' ),
                'default'     => '',
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'backgroundColor' => [
                'type'    => 'color',
                'label'   => __( 'Background Color' ),
                'default' => null,
            ],
            'shadow'          => [
                'type'    => 'select',
                'label'   => __( 'Shadow' ),
                'options' => [
                    'none' => __( 'None' ),
                    'sm'   => __( 'Small' ),
                    'md'   => __( 'Medium' ),
                    'lg'   => __( 'Large' ),
                    'xl'   => __( 'Extra Large' ),
                ],
                'default' => 'md',
            ],
            'padding'         => [
                'type'    => 'spacing',
                'label'   => __( 'Padding' ),
                'sides'   => [ 'top', 'right', 'bottom', 'left' ],
                'default' => null,
            ],
            'border'          => [
                'type'    => 'border',
                'label'   => __( 'Border' ),
                'default' => [
                    'width'      => '1',
                    'widthUnit'  => 'px',
                    'style'      => 'solid',
                    'color'      => '#e5e7eb',
                    'radius'     => '8',
                    'radiusUnit' => 'px',
                    'perSide'    => false,
                    'perCorner'  => false,
                ],
            ],
        ];
    }
}
```

**views/edit.blade.php:**

```blade
@php
    $title       = $content['title'] ?? '';
    $bgColor     = $styles['backgroundColor'] ?? null;
    $shadow      = $styles['shadow'] ?? 'md';
    $padding     = $styles['padding'] ?? null;
    $border      = $styles['border'] ?? [];
    $innerBlocks = $innerBlocks ?? [];

    $shadowMap = [
        'none' => '',
        'sm'   => 'shadow-sm',
        'md'   => 'shadow-md',
        'lg'   => 'shadow-lg',
        'xl'   => 'shadow-xl',
    ];

    $inlineStyles = '';
    if ( $bgColor ) {
        $inlineStyles .= "background-color: {$bgColor};";
    }
    if ( is_array( $padding ) ) {
        $top    = $padding['top'] ?? '0';
        $right  = $padding['right'] ?? '0';
        $bottom = $padding['bottom'] ?? '0';
        $left   = $padding['left'] ?? '0';
        $inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
    }
    if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
        $bWidth = ( $border['width'] ?? '0' ) . ( $border['widthUnit'] ?? 'px' );
        $bStyle = $border['style'] ?? 'solid';
        $bColor = $border['color'] ?? 'currentColor';
        $inlineStyles .= " border: {$bWidth} {$bStyle} {$bColor};";

        $bRadius = $border['radius'] ?? '0';
        if ( $bRadius && '0' !== $bRadius ) {
            $bRadiusUnit = $border['radiusUnit'] ?? 'px';
            $inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
        }
    }

    $classes = 've-block ve-block-card ve-block-editing ' . ( $shadowMap[$shadow] ?? 'shadow-md' );
@endphp

<div
    class="{{ $classes }}"
    @if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
>
    @if ( $title )
        <div class="ve-card-header font-bold text-lg mb-2">{{ $title }}</div>
    @endif
    <x-ve-inner-blocks
        :inner-blocks="$innerBlocks"
        :parent-id="$context['blockId'] ?? null"
        :placeholder="__( 'Add card content...' )"
        :orientation="'vertical'"
        :editing="true"
    />
</div>
```

**views/save.blade.php:**

```blade
@php
    $title       = $content['title'] ?? '';
    $bgColor     = $styles['backgroundColor'] ?? null;
    $shadow      = $styles['shadow'] ?? 'md';
    $padding     = $styles['padding'] ?? null;
    $border      = $styles['border'] ?? [];
    $anchor      = $content['anchor'] ?? null;
    $htmlId      = $content['htmlId'] ?? null;
    $className   = $content['className'] ?? '';
    $innerBlocks = $innerBlocks ?? [];

    $elementId = $htmlId ?: $anchor;

    $shadowMap = [
        'none' => '',
        'sm'   => 'shadow-sm',
        'md'   => 'shadow-md',
        'lg'   => 'shadow-lg',
        'xl'   => 'shadow-xl',
    ];

    $inlineStyles = '';
    if ( $bgColor ) {
        $inlineStyles .= "background-color: {$bgColor};";
    }
    if ( is_array( $padding ) ) {
        $top    = $padding['top'] ?? '0';
        $right  = $padding['right'] ?? '0';
        $bottom = $padding['bottom'] ?? '0';
        $left   = $padding['left'] ?? '0';
        $inlineStyles .= " padding: {$top} {$right} {$bottom} {$left};";
    }
    if ( is_array( $border ) && 'none' !== ( $border['style'] ?? 'none' ) ) {
        $bWidth = ( $border['width'] ?? '0' ) . ( $border['widthUnit'] ?? 'px' );
        $bStyle = $border['style'] ?? 'solid';
        $bColor = $border['color'] ?? 'currentColor';
        $inlineStyles .= " border: {$bWidth} {$bStyle} {$bColor};";

        $bRadius = $border['radius'] ?? '0';
        if ( $bRadius && '0' !== $bRadius ) {
            $bRadiusUnit = $border['radiusUnit'] ?? 'px';
            $inlineStyles .= " border-radius: {$bRadius}{$bRadiusUnit};";
        }
    }

    $classes = 've-block ve-block-card ' . ( $shadowMap[$shadow] ?? 'shadow-md' );
    if ( $className ) {
        $classes .= " {$className}";
    }
@endphp

<div
    class="{{ $classes }}"
    @if ( $inlineStyles ) style="{{ $inlineStyles }}" @endif
    @if ( $elementId ) id="{{ $elementId }}" @endif
>
    @if ( $title )
        <div class="ve-card-header font-bold text-lg mb-2">{{ $title }}</div>
    @endif
    @foreach ( $innerBlocks as $innerBlock )
        {!! $innerBlock !!}
    @endforeach
</div>
```

---

## Best Practices

### 1. Always Extend BaseBlock

Never implement `BlockInterface` directly. `BaseBlock` provides metadata loading, view resolution, supports management, and sensible defaults. Extending it saves significant boilerplate.

### 2. Use block.json for Metadata

Keep identity, attributes, and supports declarations in `block.json`. Only use the PHP class for schemas, toolbar controls, variations, and transforms.

### 3. Separate Edit and Save Views

The edit view should include editor-specific attributes (`contenteditable`, `data-placeholder`, `data-ve-*`). The save view should produce clean, semantic HTML for the frontend.

### 4. Always Provide Fallback Defaults

Extract values from `$content` and `$styles` with null coalescing:

```php
$text = $content['text'] ?? '';
$color = $styles['textColor'] ?? null;
```

### 5. Validate Values in Views

For attributes that map to HTML attributes or CSS values, validate against allowed values:

```php
$allowedTags = [ 'div', 'section', 'article' ];
$tag = in_array( $tag, $allowedTags ) ? $tag : 'div';
```

### 6. Wrap User-Facing Strings with `__()`

All labels, placeholders, hints, and option text should be translatable:

```php
'label' => __( 'Button Text' ),
```

For package blocks, use the translation namespace:

```php
'label' => __( 'visual-editor::ve.button_text' ),
```

### 7. Support Anchor and className

Unless you have a specific reason not to, enable `anchor` and `className` supports so users can set HTML IDs and add custom CSS classes.

### 8. Follow CSS Class Conventions

- Root element: `ve-block ve-block-{type}`
- Editor views: add `ve-block-editing`
- Modifier classes: `ve-{type}-{modifier}` (e.g., `ve-button-sm`, `ve-button-filled`)

### 9. Handle Inner Blocks Safely

For container blocks, always check if `$innerBlocks` is empty and provide meaningful placeholder content.

### 10. Add Accessibility Attributes

In save views, include appropriate ARIA attributes:

```blade
<div role="alert" aria-live="polite">
```

### 11. Use `noopener noreferrer` for External Links

When rendering links that open in new tabs:

```php
$relAttr = '_blank' === $linkTarget ? 'noopener noreferrer' : null;
```

---

## Troubleshooting

### Block Not Appearing in the Inserter

1. **Check `public` flag** -- Ensure `"public": true` in `block.json`.
2. **Check the config** -- Make sure the block isn't disabled in `config/artisanpack/visual-editor.php`.
3. **Check the type** -- Ensure `type` in `block.json` is unique and not conflicting with another block.
4. **Check the class name** -- The discovery service expects `{BlockName}Block` class name (e.g., `AlertBlock` for `Alert/`).
5. **Check the namespace** -- Verify the namespace matches the directory structure.

### Block Not Loading Metadata

1. **Check block.json syntax** -- Ensure it's valid JSON (no trailing commas, proper quoting).
2. **Check file location** -- `block.json` must be in the same directory as the PHP class.
3. **Clear caches** -- In production, run `php artisan cache:clear` and remove `bootstrap/cache/visual-editor-blocks.php`.

### View Not Found

The view resolution order is:

1. Published views: `resources/views/vendor/visual-editor/blocks/{type}/`
2. Co-located views: `{blockDir}/views/`
3. Package namespace: `visual-editor::blocks.{type}`

Ensure your views are in `views/edit.blade.php` and `views/save.blade.php` relative to your block class.

### Inspector Fields Not Showing

1. **Check `inspector` flag** -- Fields with `'inspector' => false` won't appear.
2. **Check `condition`** -- Conditional fields only show when their condition is met.
3. **Check schema return** -- Ensure `getContentSchema()` and `getStyleSchema()` return proper arrays.

### Styles Not Applying

1. **Check `source`** -- Ensure the attribute source is `style` (not `content`) in `block.json`.
2. **Check inline styles** -- Build inline styles from `$styles` values in your views.
3. **Check supports** -- Enable the appropriate support flags in `block.json`.

### Production Caching

In production, blocks are cached in `bootstrap/cache/visual-editor-blocks.php`. If you add or modify blocks:

```bash
php artisan cache:clear
# Remove the manifest cache
rm bootstrap/cache/visual-editor-blocks.php
```

---

## Quick Reference

### File Checklist for a New Block

- [ ] `block.json` -- Type, name, description, icon, category, attributes, supports
- [ ] `{BlockName}Block.php` -- Class extending `BaseBlock` with content/style schemas
- [ ] `views/edit.blade.php` -- Editor view with `contenteditable` and `data-` attributes
- [ ] `views/save.blade.php` -- Frontend view with semantic HTML and accessibility
- [ ] `views/toolbar.blade.php` -- (Optional) Custom toolbar controls
- [ ] `views/inspector.blade.php` -- (Optional) Custom inspector panel

### Key Helper Functions

```php
veRegisterBlock( $block );        // Register a block
veBlockExists( 'alert' );         // Check if block exists
veGetBlock( 'alert' );            // Get a block instance
visualEditor();                    // Get the Visual Editor instance
```

### Key Alpine.js Interactions

```javascript
// Get selected block
Alpine.store('selection')?.focused

// Get block data
Alpine.store('editor')?.getBlock(blockId)

// Update a field
$dispatch('ve-field-change', { blockId, field, value })

// Update multiple fields
Alpine.store('editor').updateBlock(blockId, { key: value })
```
