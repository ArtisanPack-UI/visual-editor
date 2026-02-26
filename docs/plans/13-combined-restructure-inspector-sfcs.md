# Combined Plan: Visual Editor Block Restructure + Inspector/Toolbar + Livewire 4 SFCs

## Context

The visual editor package currently has 16 core blocks with metadata scattered across PHP class properties, 32 flat blade templates in one directory, a supports system limited to color/typography/spacing/border, an empty Styles inspector tab, and zero Livewire components. Three separate plans address these gaps:

1. **Block restructure** — co-located directories with `block.json` metadata
2. **Inspector/toolbar supports** — auto-generated inspector panels from supports declarations
3. **Livewire 4 SFCs** — server-interactive components for persistence, media, patterns

This plan weaves them into a single execution path, piloting end-to-end with HeadingBlock before touching remaining blocks.

### Key Architecture Decisions

- **WordPress-style `attributes` in block.json**: Declares data shape (types, defaults, source). PHP `getContentSchema()`/`getStyleSchema()` add UI presentation (labels, translations, options).
- **Custom inspector views are additive and tab-targeted**: A block's `inspector.blade.php` renders custom controls inside the **Settings tab** by default. Blocks can target the **Styles tab** instead using `<x-ve-inspector-section target="styles">`. Blocks can use `<x-ve-*>`, `<x-artisanpack-*>`, or any custom components.
- **Drop Laravel 10**: Livewire 4 only (`^4.0`, `illuminate/support: ^11.0|^12.0`).
- **Pilot end-to-end with HeadingBlock** before migrating remaining blocks.

---

## Target Directory Structure

```
src/Blocks/
    block-schema.json                          # JSON schema for IDE validation
    BaseBlock.php                              # Loads block.json, resolves co-located views
    BlockRegistry.php                          # Auto-discovers blocks via directory scan
    BlockTransformService.php
    Contracts/BlockInterface.php               # + getToolbarControls(), getAttributes()
    Concerns/HasBlockSupports.php              # + shadow, dimensions, background
    Text/
        Heading/
            block.json                         # Metadata + attributes + supports
            HeadingBlock.php                   # Only schemas, transforms, migrations
            views/
                save.blade.php
                edit.blade.php
                toolbar.blade.php              # Custom heading level selector
        Paragraph/ List/ Quote/
    Media/
        Image/
            block.json
            ImageBlock.php
            views/
                save.blade.php
                edit.blade.php
                inspector.blade.php            # Custom upload/focal point UI
                toolbar.blade.php
        Gallery/ Video/ Audio/ File/
    Layout/
        Columns/ Column/ Group/ Spacer/ Divider/
    Interactive/
        Button/ Code/

src/Inspector/
    SupportsPanelRegistry.php                  # Maps supports -> inspector panels
    BlockMetadataService.php                   # Serializes all block meta to JSON

src/Livewire/
    Forms/DocumentForm.php                     # Livewire Form object for validation

src/Console/
    BlockCacheCommand.php                      # ve:cache
    BlockClearCommand.php                      # ve:clear

resources/views/
    components/
        inspector-controls.blade.php           # NEW: auto-generated panels
        inspector-section.blade.php            # NEW: tab-targeted section wrapper
        shadow-control.blade.php               # NEW
        background-control.blade.php           # NEW
    livewire/                                  # NEW: Livewire 4 SFCs
        document-saver.blade.php
        media-picker.blade.php
        pattern-store.blade.php
        editor-persistence.blade.php
        revision-history.blade.php

database/migrations/
    create_visual_editor_patterns_table.php
    create_visual_editor_revisions_table.php
```

---

## How block.json Attributes Work

```json
{
    "attributes": {
        "text":            { "type": "rich_text", "source": "content", "default": "" },
        "level":           { "type": "string",    "source": "content", "default": "h2" },
        "alignment":       { "type": "string",    "source": "style",   "default": "left" },
        "textColor":       { "type": "string",    "source": "style",   "default": null },
        "backgroundColor": { "type": "string",    "source": "style",   "default": null },
        "fontSize":        { "type": "string",    "source": "style",   "default": null }
    }
}
```

- **`type`**: Data type (`string`, `integer`, `boolean`, `array`, `object`, `rich_text`, `url`)
- **`source`**: Maps to `content` or `style`, maintaining the existing separation
- **`default`**: Default value used when creating a new block instance

PHP `getContentSchema()` / `getStyleSchema()` add UI presentation on top:
```php
// HeadingBlock::getContentSchema() adds labels, options, placeholders
'level' => [
    'type'    => 'select',
    'label'   => __( 'visual-editor::ve.heading_level' ),
    'options' => [ 'h1' => 'H1', 'h2' => 'H2', ... ],
    'default' => 'h2',  // can be omitted — falls back to block.json
],
```

---

## How Custom Inspector/Toolbar Views Work

Blocks can optionally provide these files in their `views/` directory:

- **`inspector.blade.php`** — Custom controls rendered inside the inspector tabs. By default, content goes into the **Settings tab**. Use `<x-ve-inspector-section target="styles">` to place controls in the **Styles tab** instead. Both can be used in the same file. Controls are always additive — auto-generated supports panels still render in the Styles tab below any custom style sections.

  ```blade
  {{-- src/Blocks/Media/Image/views/inspector.blade.php --}}

  {{-- This renders in the Settings tab (default) --}}
  <x-ve-inspector-section>
      <x-ve-panel-body :title="__('visual-editor::ve.image_settings')">
          <x-artisanpack-file wire:model="image" accept="image/*" />
          <x-artisanpack-input :label="__('visual-editor::ve.alt_text')" ... />
      </x-ve-panel-body>
  </x-ve-inspector-section>

  {{-- This renders in the Styles tab, above auto-generated supports panels --}}
  <x-ve-inspector-section target="styles">
      <x-ve-panel-body :title="__('visual-editor::ve.focal_point')">
          {{-- custom focal point picker --}}
      </x-ve-panel-body>
  </x-ve-inspector-section>
  ```

- **`toolbar.blade.php`** — Custom controls injected into the block toolbar's grouped slots:
  ```blade
  {{-- src/Blocks/Text/Heading/views/toolbar.blade.php --}}
  <x-ve-toolbar-group>
      @foreach ( ['h1','h2','h3','h4','h5','h6'] as $h )
          <x-ve-toolbar-button :active="$content['level'] === $h" ...>
              {{ strtoupper($h) }}
          </x-ve-toolbar-button>
      @endforeach
  </x-ve-toolbar-group>
  ```

---

## Phase 1: HeadingBlock Pilot + Foundation

**Goal**: Restructure BaseBlock, create block.json pattern, pilot with HeadingBlock through restructure + attributes + inspector + toolbar.

### 1.1 Update composer.json
**File**: `composer.json`
- `"livewire/livewire": "^3.6|^4.0"` → `"^4.0"`
- `"illuminate/support": "^10.0|^11.0|^12.0"` → `"^11.0|^12.0"`

### 1.2 Create block-schema.json
**New file**: `src/Blocks/block-schema.json`
- JSON Schema v7 covering: type, name, description, icon, category, keywords, version, public, parent, allowedChildren, supports, and the new `attributes` object

### 1.3 Create Heading block.json
**New file**: `src/Blocks/Text/Heading/block.json`
- Full metadata + attributes (text, level, alignment, textColor, backgroundColor, fontSize) + supports

### 1.4 Refactor BaseBlock
**File**: `src/Blocks/BaseBlock.php`

Key changes:
- `$metadata` array + `$blockDir` string properties
- `resolveBlockDirectory()` with static `$resolvedDirs` cache (fixes ReflectionClass overhead)
- `loadMetadata()` with `\RuntimeException` on malformed JSON in non-production
- Metadata getters read `$this->metadata` first, fall back to class properties (backward compat)
- Translation-aware `getName()`/`getDescription()`: `__('visual-editor::ve.block_{type}_name')` with block.json fallback
- `getAttributes(): array` — returns attributes from block.json
- `getDefaultContent()`/`getDefaultStyles()` merge defaults from block.json attributes by `source`
- `resolveView( string $name )` with correct namespace chain:
  1. `"visual-editor-block-{$type}::{$name}"` (co-located)
  2. `"visual-editor::blocks.{$type}.{$name}"` (reorganized)
  3. `"visual-editor::blocks.{$type}{$legacySuffix}"` (legacy flat)
- `hasCustomInspector()`, `renderInspector()`, `hasCustomToolbar()`, `renderToolbar()`

### 1.5 Move HeadingBlock
- Move `src/Blocks/Text/HeadingBlock.php` → `src/Blocks/Text/Heading/HeadingBlock.php`
- Update namespace to `ArtisanPackUI\VisualEditor\Blocks\Text\Heading`
- Strip `$type`, `$name`, `$description`, `$icon`, `$category`, `$keywords`, `getSupports()` (all in block.json now)
- Keep `getContentSchema()`, `getStyleSchema()`, `getTransforms()`
- Add class alias in service provider for old namespace

### 1.6 Move HeadingBlock views
- `resources/views/blocks/heading.blade.php` → `src/Blocks/Text/Heading/views/save.blade.php`
- `resources/views/blocks/heading-editor.blade.php` → `src/Blocks/Text/Heading/views/edit.blade.php`
- Keep originals temporarily for legacy fallback

### 1.7 Update service provider view registration
**File**: `src/VisualEditorServiceProvider.php`
- Add `registerBlockViews()` scanning block dirs for `views/` subdirectories
- Register each as namespace `visual-editor-block-{type}`
- Update HeadingBlock reference in `$coreBlocks`

### 1.8 Create Heading custom toolbar
**New file**: `src/Blocks/Text/Heading/views/toolbar.blade.php`
- H1-H6 level selector buttons

### 1.9 Add translation keys
**File**: `resources/lang/en/ve.php`
- `block_heading_name`, `block_heading_description` (and incrementally for other blocks)

### 1.10 Tests
- Update `HeadingBlockTest` for new namespace
- New tests: block.json loading, view resolution, translation-aware getters, `getAttributes()`, `hasCustomToolbar()`

**Verify**: `./vendor/bin/pest`, `./vendor/bin/php-cs-fixer fix --dry-run --diff`, test in dev app

---

## Phase 2: Inspector Supports System + New Controls

**Goal**: Build supports-to-panels pipeline, new support types, auto-generated inspector controls.

**Depends on**: Phase 1

### 2.1 Expand HasBlockSupports
**File**: `src/Blocks/Concerns/HasBlockSupports.php`
- Add `shadow` (boolean), `dimensions` (`aspectRatio`, `minHeight`), `background` (`backgroundImage`, `backgroundSize`, `backgroundPosition`, `backgroundGradient`)
- Add `getActiveStyleSupports(): array` (flat dot-path list)

### 2.2 Create SupportsPanelRegistry
**New file**: `src/Inspector/SupportsPanelRegistry.php`
- `getPanelsForBlock( BlockInterface $block ): array` → ordered panels with key, label, controls
- Hook: `applyFilters( 'ap.visualEditor.inspectorPanels', $panels, $block )`

### 2.3 Create BlockMetadataService
**New file**: `src/Inspector/BlockMetadataService.php`
- `getAllBlockMeta(): array` → JSON-friendly structure for all registered blocks (type, name, supports, schemas, toolbarControls, supportsPanels, defaults, attributes)

### 2.4 Add getToolbarControls() to BlockInterface + BaseBlock
- Default: returns alignment control in `block` group if `align` is supported

### 2.5 New UI control components
- **ShadowControl**: `src/View/Components/ShadowControl.php` + blade — Tailwind shadow presets + custom CSS
- **BackgroundControl**: `src/View/Components/BackgroundControl.php` + blade — composite control, conditionally shows sub-controls

### 2.6 Create InspectorControls component + InspectorSection component
**New files**:
- `src/View/Components/InspectorControls.php` + `resources/views/components/inspector-controls.blade.php`
- `src/View/Components/InspectorSection.php` + `resources/views/components/inspector-section.blade.php`

`InspectorSection` accepts a `target` prop (`"settings"` default, or `"styles"`) to let blocks place custom controls in the correct tab.

`InspectorControls` renders three tabs:
- **Settings tab**: Custom inspector sections targeting "settings" (from block's `inspector.blade.php`) + auto-generated fields from `contentSchema`
- **Styles tab**: Custom inspector sections targeting "styles" (from block's `inspector.blade.php`) + auto-generated supports panels + style fields from `styleSchema`
- **Advanced tab**: anchor, htmlId, className fields

### 2.7 Wire up editor-sidebar
**File**: `resources/views/components/editor-sidebar.blade.php`
- Replace empty placeholders with `<x-ve-inspector-controls>` driven by block selection

### 2.8 Inject blockMeta into Alpine store
**File**: `resources/views/components/editor-state.blade.php`
- Add `blockMeta` property via `Js::from( $blockMetadataService->getAllBlockMeta() )`

### 2.9 Enhanced block toolbar
**File**: `resources/views/components/block-toolbar.blade.php`
- Grouped slots: `[Type + transforms] | [blockGroup] | [inlineGroup] | [defaultGroup] | [Move] | [More]`
- Auto-populate from `blockMeta[selectedType].toolbarControls`

### 2.10 Register new services + components
**File**: `src/VisualEditorServiceProvider.php`
- Singletons: `SupportsPanelRegistry`, `BlockMetadataService`
- Blade components: `inspector-controls`, `inspector-section`, `shadow-control`, `background-control`

### 2.11 Translations
**File**: `resources/lang/en/ve.php`
- Keys for: typography, font_family, spacing, dimensions, aspect_ratio, min_height, background, shadow, etc.

### 2.12 Tests
- Unit: `SupportsPanelRegistryTest`, `BlockMetadataServiceTest`, `HasBlockSupportsTest`, toolbar controls
- Feature: InspectorControls renders panels, ShadowControl, BackgroundControl, toolbar groups

**Verify**: HeadingBlock Styles tab shows Color + Typography panels, toolbar shows alignment + level controls

---

## Phase 3: Migrate Remaining 15 Blocks

**Goal**: Apply co-located block.json pattern to all remaining blocks.

**Depends on**: Phases 1 + 2

**Order** (simplest → most complex):
1. Paragraph, Quote (Text — similar to Heading)
2. List (Text — list-specific schema)
3. Spacer, Divider (Layout — minimal)
4. Button, Code (Interactive)
5. Audio, File (Media — simple)
6. Video (Media — moderate)
7. Image (Media — add custom `inspector.blade.php` for upload/focal point)
8. Gallery (Media — complex custom inspector)
9. Columns, Column, Group (Layout — parent/child constraints)

**Per block**:
1. Create `src/Blocks/{Category}/{Block}/` directory
2. Create `block.json` (metadata + attributes + supports from matrix)
3. Move PHP class, update namespace, add class alias
4. Move blade views to `views/save.blade.php` + `views/edit.blade.php`
5. Add custom `inspector.blade.php` / `toolbar.blade.php` where needed
6. Add translation keys
7. Update tests, run after each block

---

## Phase 4: Auto-Discovery + Production Caching

**Goal**: Replace manual `$coreBlocks` array, add caching commands.

**Depends on**: Phase 3

### 4.1 Auto-discovery in service provider
- Scan `src/Blocks/{Text,Media,Layout,Interactive}/*/block.json`
- Resolve class: `ArtisanPackUI\VisualEditor\Blocks\{Category}\{Block}\{Block}Block`
- Respect config-based enable/disable

### 4.2 Artisan commands
- `ve:cache` — serialize all block metadata to cached manifest
- `ve:clear` — remove cached manifest

### 4.3 Update BaseBlock loadMetadata()
- Use cached manifest when available, file reads in development

### 4.4 Clean up legacy views
- Remove old flat files from `resources/views/blocks/`
- Keep class aliases for v1.x backward compat

### 4.5 Update vendor:publish
- Collect co-located views → publish to `views/vendor/visual-editor/blocks/{type}/`

### 4.6 Tests
- Auto-discovery, cache commands, legacy fallback, published view overrides

---

## Phase 5: Livewire 4 SFC Components

**Goal**: Add 5 Livewire SFCs for server-interactive features.

**Depends on**: Phase 1 only (Livewire version bump). Can run **in parallel** with Phases 2-4.

### 5.1 Infrastructure
- Create `resources/views/livewire/`, `src/Livewire/Forms/`, `tests/Feature/Livewire/`
- Add `registerLivewireComponents()` + `registerMigrations()` to service provider
- `Livewire::addNamespace( 'visual-editor', __DIR__ . '/../resources/views/livewire' )`

### 5.2 Document Saver (pilot SFC)
- `resources/views/livewire/document-saver.blade.php` — save/load/autosave
- `src/Livewire/Forms/DocumentForm.php` — validation + security sanitization
- Usage: `<livewire:visual-editor::document-saver :document-id="$id" />`

### 5.3 Media Picker
- `resources/views/livewire/media-picker.blade.php` — wraps media-library MediaModal
- Dispatches `ve-media-selected` browser event

### 5.4 Pattern Store
- Migration: `create_visual_editor_patterns_table.php` (id, name, slug, blocks JSON, category, user_id, timestamps)
- `resources/views/livewire/pattern-store.blade.php` — CRUD

### 5.5 Editor Persistence
- `resources/views/livewire/editor-persistence.blade.php` — draft recovery via Cache
- No migration needed

### 5.6 Revision History
- Migration: `create_visual_editor_revisions_table.php` (id, document_id, blocks JSON, user_id, created_at)
- `resources/views/livewire/revision-history.blade.php` — list/restore/delete

### 5.7 Alpine-to-Livewire bridge
**File**: `resources/views/components/editor-state.blade.php`
- Add listeners: `ve-document-saved`, `ve-document-error`, `ve-draft-restored`, `ve-pattern-loaded`, `ve-revision-restored`, `ve-media-selected`

### 5.8 Tests
- `DocumentSaverTest`, `MediaPickerTest`, `PatternStoreTest`, `EditorPersistenceTest`, `RevisionHistoryTest`

---

## Phase 6: Apply Expanded Supports to All Blocks

**Goal**: Update all 16 blocks' block.json with the full supports matrix.

**Depends on**: Phases 2 + 3

| Block | shadow | dimensions | background |
|-------|--------|------------|------------|
| Heading | - | - | - |
| Paragraph | - | - | - |
| Quote | - | - | - |
| List | - | - | - |
| Image | yes | aspectRatio | - |
| Video | - | aspectRatio, minHeight | - |
| Audio | - | - | - |
| Gallery | - | - | - |
| File | - | - | - |
| Columns | - | minHeight | all |
| Column | - | - | all |
| Group | yes | minHeight | all |
| Spacer | - | minHeight | - |
| Divider | - | - | - |
| Button | yes | - | - |
| Code | - | - | - |

---

## Dependency Graph

```
Phase 1 (HeadingBlock pilot + foundation)
    |
    +----> Phase 2 (Inspector supports + new controls)
    |          |
    |          +----> Phase 3 (Migrate remaining 15 blocks)
    |                     |
    |                     +----> Phase 4 (Auto-discovery + caching)
    |                     |
    |                     +----> Phase 6 (Expanded supports for all blocks)
    |
    +----> Phase 5 (Livewire 4 SFCs) [parallel with Phases 2-4]
```

---

## Verification (after each phase)

1. `./vendor/bin/pest` — all tests pass
2. `./vendor/bin/php-cs-fixer fix --dry-run --diff` — code style clean
3. `./vendor/bin/phpcs` — PHPCS passes
4. Manual test in dev app at `/components` routes
5. `vendor:publish` commands still work correctly
6. Existing published view overrides still work
