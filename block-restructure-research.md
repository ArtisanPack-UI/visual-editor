# Option C: Co-located Blocks with `block.json` Metadata

## The Core Idea

Move from the current split structure (PHP classes in one place, blade templates in another, no per-block metadata files) to a Gutenberg-inspired model where **everything about a block lives in one directory**, and declarative metadata lives in a machine-readable `block.json` file rather than as PHP class properties.

---

## Current Structure vs. Proposed Structure

### Current (Heading block as example)

```
src/Blocks/
├── Text/
│   └── HeadingBlock.php          ← class properties + schemas + supports + transforms
├── BaseBlock.php
├── BlockRegistry.php
└── ...

resources/views/blocks/
├── heading.blade.php             ← frontend render (save)
├── heading-editor.blade.php      ← editor render (edit)
├── image.blade.php
├── image-editor.blade.php
└── ... (all 32 block templates in one flat directory)
```

**Problems:**
- To understand the heading block, you jump between 3 locations
- All blade templates are flat in one directory — 32 files and growing
- No per-block tests, styles, or custom controls
- Block metadata (name, icon, description) is embedded in PHP class properties, not machine-readable

### Proposed

```
src/Blocks/
├── Text/
│   ├── Heading/
│   │   ├── block.json                  ← declarative metadata
│   │   ├── HeadingBlock.php            ← schemas, transforms, migrations
│   │   └── views/
│   │       ├── edit.blade.php          ← editor render
│   │       └── save.blade.php          ← frontend render
│   │
│   ├── Paragraph/
│   │   ├── block.json
│   │   ├── ParagraphBlock.php
│   │   └── views/
│   │       ├── edit.blade.php
│   │       └── save.blade.php
│   │
│   ├── List/
│   │   ├── block.json
│   │   ├── ListBlock.php
│   │   └── views/
│   │       ├── edit.blade.php
│   │       └── save.blade.php
│   │
│   └── Quote/
│       ├── block.json
│       ├── QuoteBlock.php
│       └── views/
│           ├── edit.blade.php
│           └── save.blade.php
│
├── Media/
│   ├── Image/
│   │   ├── block.json
│   │   ├── ImageBlock.php
│   │   ├── views/
│   │   │   ├── edit.blade.php
│   │   │   ├── save.blade.php
│   │   │   ├── inspector.blade.php     ← custom inspector (optional)
│   │   │   └── toolbar.blade.php       ← custom toolbar (optional)
│   │   └── tests/
│   │       └── ImageBlockTest.php
│   │
│   ├── Gallery/
│   ├── Video/
│   ├── Audio/
│   └── File/
│
├── Layout/
│   ├── Columns/
│   ├── Column/
│   ├── Group/
│   ├── Spacer/
│   └── Divider/
│
├── Interactive/
│   ├── Button/
│   └── Code/
│
├── BaseBlock.php                       ← abstract base (loads block.json)
├── BlockRegistry.php                   ← auto-discovers blocks via block.json
├── BlockTransformService.php
├── Contracts/
│   └── BlockInterface.php
└── Concerns/
    └── HasBlockSupports.php
```

**Key changes:**
1. Each block gets its own directory under its category
2. Blade views move *into* the block directory (co-located)
3. A `block.json` file holds all declarative metadata
4. Optional files (`inspector.blade.php`, `toolbar.blade.php`, `tests/`) only exist when needed
5. The category grouping (`Text/`, `Media/`, etc.) is preserved

---

## What Goes in `block.json`

The `block.json` file holds everything that's purely *declarative* — data that describes the block but doesn't contain logic. This is the stuff currently stored as PHP class properties.

### Heading block.json

```json
{
    "$schema": "./block-schema.json",
    "apiVersion": 1,
    "type": "heading",
    "name": "Heading",
    "description": "Add a heading to your content",
    "icon": "h1",
    "category": "text",
    "keywords": ["title", "h1", "h2", "h3", "header"],
    "version": 1,
    "public": true,
    "parent": null,
    "allowedChildren": null,
    "supports": {
        "align": ["left", "center", "right", "wide", "full"],
        "color": {
            "text": true,
            "background": true
        },
        "typography": {
            "fontSize": true,
            "fontFamily": false
        },
        "spacing": {
            "margin": false,
            "padding": false
        },
        "border": false,
        "anchor": true,
        "className": true
    }
}
```

### Image block.json

```json
{
    "$schema": "./block-schema.json",
    "apiVersion": 1,
    "type": "image",
    "name": "Image",
    "description": "Add an image to your content",
    "icon": "photo",
    "category": "media",
    "keywords": ["photo", "picture", "img"],
    "version": 1,
    "public": true,
    "parent": null,
    "allowedChildren": null,
    "supports": {
        "align": true,
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
            "padding": false
        },
        "border": false,
        "anchor": true,
        "className": true
    }
}
```

### Column block.json (with parent constraint)

```json
{
    "$schema": "./block-schema.json",
    "apiVersion": 1,
    "type": "column",
    "name": "Column",
    "description": "A single column within a columns block",
    "icon": "columns",
    "category": "layout",
    "keywords": [],
    "version": 1,
    "public": false,
    "parent": ["columns"],
    "allowedChildren": null,
    "supports": {
        "align": false,
        "color": {
            "text": true,
            "background": true
        },
        "typography": {
            "fontSize": false,
            "fontFamily": false
        },
        "spacing": {
            "margin": false,
            "padding": true
        },
        "border": true,
        "anchor": true,
        "className": true
    }
}
```

### What stays OUT of block.json

These things stay in PHP because they contain **logic**, not just data:

- `getContentSchema()` — references `__()` translation helpers, conditional logic
- `getStyleSchema()` — same translation/logic concerns
- `getTransforms()` — field mapping logic
- `getVariations()` — could go in block.json, but may need computed values
- `migrate()` — migration logic
- Any custom rendering overrides

---

## How `BaseBlock` Changes

The `BaseBlock` would auto-load `block.json` from its directory, eliminating the need for subclasses to declare properties. Here's what the refactored base class would look like:

### New BaseBlock.php

```php
abstract class BaseBlock implements BlockInterface
{
    use HasBlockSupports;

    /**
     * Metadata loaded from block.json.
     */
    protected array $metadata = [];

    /**
     * Resolved path to this block's directory.
     */
    protected string $blockDir = '';

    public function __construct()
    {
        $this->blockDir = $this->resolveBlockDirectory();
        $this->metadata = $this->loadMetadata();
    }

    /**
     * Resolve the directory containing this block's files.
     *
     * Uses reflection to find the directory of the concrete class,
     * so HeadingBlock.php in src/Blocks/Text/Heading/ returns that path.
     */
    protected function resolveBlockDirectory(): string
    {
        $reflector = new \ReflectionClass( static::class );
        return dirname( $reflector->getFileName() );
    }

    /**
     * Load and decode block.json from the block directory.
     */
    protected function loadMetadata(): array
    {
        $path = $this->blockDir . '/block.json';

        if ( ! file_exists( $path ) ) {
            return [];
        }

        $json = json_decode( file_get_contents( $path ), true );

        return is_array( $json ) ? $json : [];
    }

    // --- Metadata getters now pull from block.json ---

    public function getType(): string
    {
        return $this->metadata['type'] ?? '';
    }

    public function getName(): string
    {
        return $this->metadata['name'] ?? '';
    }

    public function getDescription(): string
    {
        return $this->metadata['description'] ?? '';
    }

    public function getIcon(): string
    {
        return $this->metadata['icon'] ?? '';
    }

    public function getCategory(): string
    {
        return $this->metadata['category'] ?? 'text';
    }

    public function getKeywords(): array
    {
        return $this->metadata['keywords'] ?? [];
    }

    public function getVersion(): int
    {
        return $this->metadata['version'] ?? 1;
    }

    public function isPublic(): bool
    {
        return $this->metadata['public'] ?? true;
    }

    public function getAllowedParents(): ?array
    {
        return $this->metadata['parent'] ?? null;
    }

    public function getAllowedChildren(): ?array
    {
        return $this->metadata['allowedChildren'] ?? null;
    }

    // --- Supports now loaded from block.json ---

    public function getSupports(): array
    {
        return $this->metadata['supports'] ?? [
            'align'      => false,
            'color'      => ['text' => false, 'background' => false],
            'typography' => ['fontSize' => false, 'fontFamily' => false],
            'spacing'    => ['margin' => false, 'padding' => false],
            'border'     => false,
            'anchor'     => true,
            'htmlId'     => true,
            'className'  => true,
        ];
    }

    // --- Views now resolve from block directory ---

    public function render( array $content, array $styles, array $context = [] ): string
    {
        return view( $this->resolveView( 'save' ), [
            'content' => $content,
            'styles'  => $styles,
            'context' => $context,
            'block'   => $this,
        ] )->render();
    }

    public function renderEditor( array $content, array $styles, array $context = [] ): string
    {
        $editorView = $this->resolveView( 'edit' );

        if ( view()->exists( $editorView ) ) {
            return view( $editorView, [
                'content' => $content,
                'styles'  => $styles,
                'context' => $context,
                'block'   => $this,
            ] )->render();
        }

        return $this->render( $content, $styles, $context );
    }

    /**
     * Check if this block has a custom inspector template.
     */
    public function hasCustomInspector(): bool
    {
        return view()->exists( $this->resolveView( 'inspector' ) );
    }

    /**
     * Render the custom inspector controls (if they exist).
     */
    public function renderInspector( array $content, array $styles, array $context = [] ): ?string
    {
        $view = $this->resolveView( 'inspector' );

        if ( ! view()->exists( $view ) ) {
            return null;
        }

        return view( $view, [
            'content' => $content,
            'styles'  => $styles,
            'context' => $context,
            'block'   => $this,
        ] )->render();
    }

    /**
     * Check if this block has a custom toolbar template.
     */
    public function hasCustomToolbar(): bool
    {
        return view()->exists( $this->resolveView( 'toolbar' ) );
    }

    /**
     * Render the custom toolbar controls (if they exist).
     */
    public function renderToolbar( array $content, array $styles, array $context = [] ): ?string
    {
        $view = $this->resolveView( 'toolbar' );

        if ( ! view()->exists( $view ) ) {
            return null;
        }

        return view( $view, [
            'content' => $content,
            'styles'  => $styles,
            'context' => $context,
            'block'   => $this,
        ] )->render();
    }

    /**
     * Resolve a view name for this block.
     *
     * Looks in the block's co-located views/ directory first,
     * then falls back to the package's views/blocks/ directory
     * for backward compatibility.
     */
    protected function resolveView( string $name ): string
    {
        $type = $this->getType();

        // Co-located view (new structure)
        // Registered as: visual-editor::blocks.{type}.{name}
        $colocated = "visual-editor::blocks.{$type}.{$name}";
        if ( view()->exists( $colocated ) ) {
            return $colocated;
        }

        // Legacy fallback: resources/views/blocks/{type}.blade.php
        // Maps 'save' -> '{type}' and 'edit' -> '{type}-editor'
        $legacySuffix = $name === 'edit' ? '-editor' : '';
        $legacy       = "visual-editor::blocks.{$type}{$legacySuffix}";
        if ( view()->exists( $legacy ) ) {
            return $legacy;
        }

        return $colocated;
    }

    // --- These stay as abstract methods (they contain logic) ---

    abstract public function getContentSchema(): array;

    abstract public function getStyleSchema(): array;

    // --- These have sensible defaults ---

    public function getTransforms(): array
    {
        return [];
    }

    public function getVariations(): array
    {
        return [];
    }

    public function migrate( array $content, int $fromVersion ): array
    {
        return $content;
    }

    // ... (getAdvancedSchema, getDefaultContent, getDefaultStyles stay the same)
}
```

---

## How Block Classes Slim Down

With metadata in `block.json` and views co-located, the PHP class only needs to define the things that require actual code: schemas and transforms.

### Before (current HeadingBlock.php — 207 lines)

```php
class HeadingBlock extends BaseBlock
{
    protected string $type = 'heading';           // → moves to block.json
    protected string $name = 'Heading';           // → moves to block.json
    protected string $description = '...';        // → moves to block.json
    protected string $icon = 'h1';                // → moves to block.json
    protected string $category = 'text';          // → moves to block.json
    protected array $keywords = [...];            // → moves to block.json

    public function getContentSchema(): array { ... }   // stays
    public function getStyleSchema(): array { ... }     // stays
    public function getTransforms(): array { ... }      // stays
    public function getSupports(): array { ... }        // → moves to block.json
}
```

### After (new HeadingBlock.php — ~80 lines)

```php
class HeadingBlock extends BaseBlock
{
    public function getContentSchema(): array
    {
        return [
            'text'  => [
                'type'        => 'rich_text',
                'label'       => __( 'visual-editor::ve.block_heading_placeholder' ),
                'placeholder' => __( 'visual-editor::ve.block_heading_placeholder' ),
                'toolbar'     => [ 'bold', 'italic', 'link' ],
                'default'     => '',
            ],
            'level' => [
                'type'    => 'select',
                'label'   => __( 'visual-editor::ve.heading_level' ),
                'options' => [
                    'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3',
                    'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6',
                ],
                'default' => 'h2',
            ],
        ];
    }

    public function getStyleSchema(): array
    {
        return [
            'alignment'       => [
                'type'    => 'alignment',
                'label'   => __( 'visual-editor::ve.text_alignment' ),
                'options' => [ 'left', 'center', 'right' ],
                'default' => 'left',
            ],
            'textColor'       => [
                'type'    => 'color',
                'label'   => __( 'visual-editor::ve.text_color' ),
                'default' => null,
            ],
            'backgroundColor' => [
                'type'    => 'color',
                'label'   => __( 'visual-editor::ve.background_color' ),
                'default' => null,
            ],
            'fontSize'        => [
                'type'    => 'select',
                'label'   => __( 'visual-editor::ve.font_size' ),
                'options' => [
                    'small' => __( 'visual-editor::ve.small' ),
                    'base'  => __( 'visual-editor::ve.normal' ),
                    'large' => __( 'visual-editor::ve.large' ),
                    'xl'    => __( 'visual-editor::ve.extra_large' ),
                ],
                'default' => null,
            ],
        ];
    }

    public function getTransforms(): array
    {
        return [
            'paragraph' => [ 'text' => 'text' ],
            'quote'     => [ 'text' => 'text' ],
        ];
    }
}
```

The class drops from ~207 lines to ~80 lines, and the remaining code is *purely behavioral* — it defines what the schemas look like and how transforms work. Everything descriptive lives in `block.json`.

---

## How View Registration Changes

The `VisualEditorServiceProvider` needs to register views from each block's `views/` directory. There are two approaches:

### Approach A: Register each block's view directory individually

```php
protected function registerViews(): void
{
    // Package-level views (components, editor shell, etc.)
    $this->loadViewsFrom( __DIR__ . '/../resources/views', 'visual-editor' );

    // Block views — scan block directories for co-located views
    $blocksDir = __DIR__ . '/Blocks';
    $categories = ['Text', 'Media', 'Layout', 'Interactive'];

    foreach ( $categories as $category ) {
        $categoryPath = $blocksDir . '/' . $category;
        if ( ! is_dir( $categoryPath ) ) {
            continue;
        }

        foreach ( new \DirectoryIterator( $categoryPath ) as $item ) {
            if ( ! $item->isDir() || $item->isDot() ) {
                continue;
            }

            $viewsPath = $item->getPathname() . '/views';
            if ( is_dir( $viewsPath ) ) {
                $blockType = strtolower( $item->getFilename() );

                // Register as visual-editor::blocks.heading.save, etc.
                $this->loadViewsFrom( $viewsPath, "visual-editor-block-{$blockType}" );
            }
        }
    }
}
```

### Approach B: Use a single `addNamespace` with nested structure

Keep using `resources/views/blocks/` but reorganize it into subdirectories:

```
resources/views/blocks/
├── heading/
│   ├── save.blade.php
│   └── edit.blade.php
├── image/
│   ├── save.blade.php
│   ├── edit.blade.php
│   ├── inspector.blade.php
│   └── toolbar.blade.php
└── ...
```

This still resolves as `visual-editor::blocks.heading.save` with zero changes to `loadViewsFrom`. The trade-off is views aren't *physically* co-located with the PHP class, but they're at least organized per-block instead of flat.

### Recommended: Approach A (true co-location)

Approach A is the Gutenberg-style answer. Views live next to their PHP class. The `resolveView()` method in BaseBlock handles the lookup, and the service provider scans for `views/` directories.

---

## How Block Registration Changes

Currently, every block is manually listed in the service provider's `$coreBlocks` array. With `block.json`, you can auto-discover blocks:

```php
protected function registerCoreBlocks(): void
{
    $registry   = $this->app->make( 'visual-editor.blocks' );
    $coreConfig = config( 'artisanpack.visual-editor.blocks.core', [] );
    $disabled   = config( 'artisanpack.visual-editor.blocks.disabled', [] );

    $blocksDir  = __DIR__ . '/Blocks';
    $categories = [ 'Text', 'Media', 'Layout', 'Interactive' ];

    foreach ( $categories as $category ) {
        $categoryPath = $blocksDir . '/' . $category;
        if ( ! is_dir( $categoryPath ) ) {
            continue;
        }

        foreach ( new \DirectoryIterator( $categoryPath ) as $item ) {
            if ( ! $item->isDir() || $item->isDot() ) {
                continue;
            }

            $blockJsonPath = $item->getPathname() . '/block.json';
            if ( ! file_exists( $blockJsonPath ) ) {
                continue;
            }

            $metadata = json_decode( file_get_contents( $blockJsonPath ), true );
            if ( ! $metadata || empty( $metadata['type'] ) ) {
                continue;
            }

            $type = $metadata['type'];

            // Check config-based enable/disable
            if ( false === ( $coreConfig[ $type ] ?? true ) ) {
                continue;
            }
            if ( in_array( $type, $disabled, true ) ) {
                continue;
            }

            // Resolve class name from directory structure
            $className = "ArtisanPackUI\\VisualEditor\\Blocks\\{$category}\\"
                       . $item->getFilename() . "\\"
                       . $item->getFilename() . 'Block';

            if ( class_exists( $className ) ) {
                $registry->register( new $className() );
            }
        }
    }

    if ( function_exists( 'doAction' ) ) {
        doAction( 'ap.visualEditor.blocksInit' );
    }
}
```

This means adding a new block is just:
1. Create the directory with `block.json` and the block class
2. It's automatically discovered and registered

No need to touch the service provider.

---

## Optional Files: Inspector and Toolbar

Most blocks can rely on the generic schema-driven inspector (your existing `inspector-field.blade.php` component that reads `getContentSchema()` and `getStyleSchema()`). But some blocks need custom UI — like the Image block needing an upload area, or a Gallery needing a drag-and-drop reorder interface.

For those cases, a block can include optional blade files:

### `views/inspector.blade.php` (custom inspector panel)

This would be rendered *in addition to* or *instead of* the schema-driven fields, depending on how you want to handle it. The recommended approach is to render it **above** the auto-generated fields, so the block can add custom controls while still getting the schema-driven ones for free.

Example for Image block:

```blade
{{-- src/Blocks/Media/Image/views/inspector.blade.php --}}
<div class="ve-block-image-inspector">
    {{-- Custom image upload/replace UI --}}
    <div class="ve-image-upload-area" x-data="imageUploader">
        @if ( $content['url'] )
            <img src="{{ $content['url'] }}" class="ve-image-preview" />
            <button
                class="btn btn-sm btn-outline"
                @click="replaceImage"
            >{{ __('visual-editor::ve.replace_image') }}</button>
        @else
            <div class="ve-upload-placeholder" @click="openUploader">
                <p>{{ __('visual-editor::ve.click_to_upload') }}</p>
            </div>
        @endif
    </div>

    {{-- Focal point picker --}}
    <x-ve-panel-body :title="__('visual-editor::ve.focal_point')" :initialOpen="false">
        {{-- custom focal point UI here --}}
    </x-ve-panel-body>
</div>
{{-- Schema-driven fields (alt, caption, link, etc.) still render automatically --}}
```

### `views/toolbar.blade.php` (custom toolbar controls)

For blocks that need toolbar controls beyond the standard alignment/formatting:

```blade
{{-- src/Blocks/Text/Heading/views/toolbar.blade.php --}}
<x-ve-toolbar-group>
    @php $level = $content['level'] ?? 'h2'; @endphp
    @foreach ( ['h1','h2','h3','h4','h5','h6'] as $h )
        <x-ve-toolbar-button
            :active="$level === $h"
            @click="$dispatch('ve-field-change', {
                blockId: '{{ $context['blockId'] }}',
                field: 'level',
                value: '{{ $h }}'
            })"
        >{{ strtoupper($h) }}</x-ve-toolbar-button>
    @endforeach
</x-ve-toolbar-group>
```

### How the editor shell uses these

In your editor sidebar / block toolbar components, you'd check for custom templates:

```php
// In the editor sidebar Blade component
@php
    $block = $registry->get($blockType);
@endphp

{{-- Render custom inspector if it exists --}}
@if ( $block->hasCustomInspector() )
    {!! $block->renderInspector( $content, $styles, $context ) !!}
@endif

{{-- Always render schema-driven fields --}}
@foreach ( $block->getContentSchema() as $field => $schema )
    <x-ve-inspector-field ... />
@endforeach
```

---

## The `block.json` Schema File

To get IDE autocompletion and validation, you'd create a JSON schema:

```
src/Blocks/block-schema.json
```

```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "title": "ArtisanPack Visual Editor Block",
    "type": "object",
    "required": ["type", "name", "category"],
    "properties": {
        "$schema": { "type": "string" },
        "apiVersion": { "type": "integer", "default": 1 },
        "type": {
            "type": "string",
            "description": "Unique block type identifier (e.g. 'heading', 'image')"
        },
        "name": {
            "type": "string",
            "description": "Human-readable block name"
        },
        "description": {
            "type": "string",
            "description": "Short description shown in the block inserter"
        },
        "icon": {
            "type": "string",
            "description": "FontAwesome icon identifier"
        },
        "category": {
            "type": "string",
            "enum": ["text", "media", "layout", "interactive"],
            "description": "Block category for the inserter"
        },
        "keywords": {
            "type": "array",
            "items": { "type": "string" },
            "description": "Searchable terms for block discovery"
        },
        "version": {
            "type": "integer",
            "default": 1,
            "description": "Schema version for migrations"
        },
        "public": {
            "type": "boolean",
            "default": true,
            "description": "Whether this block appears in the inserter"
        },
        "parent": {
            "oneOf": [
                { "type": "null" },
                { "type": "array", "items": { "type": "string" } }
            ],
            "description": "Restrict to specific parent block types"
        },
        "allowedChildren": {
            "oneOf": [
                { "type": "null" },
                { "type": "array", "items": { "type": "string" } }
            ],
            "description": "Restrict which blocks can be children"
        },
        "supports": {
            "type": "object",
            "description": "Feature flags for editor controls",
            "properties": {
                "align": {
                    "oneOf": [
                        { "type": "boolean" },
                        { "type": "array", "items": { "type": "string" } }
                    ]
                },
                "color": {
                    "type": "object",
                    "properties": {
                        "text": { "type": "boolean" },
                        "background": { "type": "boolean" }
                    }
                },
                "typography": {
                    "type": "object",
                    "properties": {
                        "fontSize": { "type": "boolean" },
                        "fontFamily": { "type": "boolean" }
                    }
                },
                "spacing": {
                    "type": "object",
                    "properties": {
                        "margin": { "type": "boolean" },
                        "padding": { "type": "boolean" }
                    }
                },
                "border": { "type": "boolean" },
                "anchor": { "type": "boolean" },
                "htmlId": { "type": "boolean" },
                "className": { "type": "boolean" }
            }
        }
    }
}
```

---

## Migration Path

You don't need to do this all at once. The `resolveView()` method in the proposed `BaseBlock` already includes a legacy fallback, so you can migrate one block at a time:

### Phase 1: Add block.json files
Create `block.json` for each block. Update `BaseBlock` to load metadata from it. Block classes can keep their properties — the getters would check `$this->metadata` first, then fall back to properties.

### Phase 2: Reorganize directories
Move each block into its own subdirectory within its category (e.g., `Text/HeadingBlock.php` → `Text/Heading/HeadingBlock.php`). Update namespaces.

### Phase 3: Co-locate views
Move blade templates from `resources/views/blocks/heading.blade.php` into `src/Blocks/Text/Heading/views/save.blade.php`. The `resolveView()` fallback means old locations still work during migration.

### Phase 4: Strip properties from PHP classes
Remove the properties that are now in `block.json` (`$type`, `$name`, `$description`, etc.). Remove `getSupports()` overrides from blocks since it's in JSON.

### Phase 5: Add custom inspector/toolbar templates
For blocks that need them (Image, Gallery, etc.), add `inspector.blade.php` and `toolbar.blade.php`.

### Phase 6: Auto-discovery
Replace the manual `$coreBlocks` array in the service provider with directory scanning.

---

## Benefits of This Approach

1. **Co-location** — Everything about a block is in one place. Developers only need to look in one directory.

2. **Machine-readable metadata** — `block.json` can be parsed without instantiating PHP. Useful for documentation generation, block directories, CLI tools, or even a future block marketplace.

3. **Slimmer PHP classes** — Block classes focus on *behavior* (schemas, transforms, migrations), not *description*. Less boilerplate.

4. **Optional complexity** — Simple blocks just need `block.json`, a PHP class with schemas, and two blade files. Complex blocks can add custom inspector/toolbar templates. No forced boilerplate.

5. **Auto-discovery** — New blocks are automatically found by scanning for `block.json` files. No manual registration arrays.

6. **Testability** — Co-located `tests/` directories make it natural to have per-block tests.

7. **Third-party blocks** — Custom blocks from other packages follow the exact same pattern. They create a directory with `block.json` + class + views and register it.

8. **Schema validation** — The JSON schema file gives IDE autocompletion and catches metadata errors at edit-time.

9. **Backward compatible** — The `resolveView()` fallback means existing view overrides (published to `resources/views/vendor/visual-editor/`) keep working.

---

## Potential Concerns

### "Won't views inside `src/` violate Laravel conventions?"

Traditionally, Laravel puts views in `resources/views/`. However, for a *package* this is a matter of preference — many packages co-locate views with their source code, especially component libraries. The service provider already controls where views are loaded from, so Laravel doesn't care about the physical location.

If this feels wrong, Approach B (subdirectories under `resources/views/blocks/`) is a perfectly valid middle ground. You'd still get per-block organization and the `block.json` benefits, just without physical co-location of views and PHP.

### "What about view publishing for customization?"

Users who publish views (`php artisan vendor:publish --tag=visual-editor-views`) would get the new directory structure. The `resolveView()` method would need to check the published location first (which Laravel's view system already handles via `loadViewsFrom`).

### "Does file_get_contents for block.json add overhead?"

In production, you'd want to cache the parsed metadata. A simple approach is to use Laravel's config cache or add a `CachedBlockRegistry` that reads all `block.json` files once during boot and caches the results.

```php
// In production, cache all block metadata
protected function loadMetadata(): array
{
    $cacheKey = 'visual-editor.block.' . static::class;

    return cache()->rememberForever( $cacheKey, function () {
        $path = $this->blockDir . '/block.json';
        return file_exists( $path )
            ? json_decode( file_get_contents( $path ), true ) ?? []
            : [];
    });
}
```
