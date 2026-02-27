# Visual Editor - Work Handoff Notes

## What We're Doing

Implementing a 6-phase combined plan to restructure the visual editor package. This file contains everything needed to continue the work — no external plan files required.

## Completed

### Phase 1: HeadingBlock Pilot + Foundation (DONE)
- Updated `composer.json`: Livewire `^4.0`, illuminate/support `^11.0|^12.0`
- Created `src/Blocks/block-schema.json` (JSON Schema for block.json validation)
- Refactored `src/Blocks/BaseBlock.php` with metadata loading, view resolution chain, toolbar/inspector support
- Updated `src/Blocks/Contracts/BlockInterface.php` with new method signatures
- Refactored `src/Blocks/Concerns/HasBlockSupports.php` with shadow, dimensions, background defaults
- Created `src/Blocks/Text/Heading/` directory: `block.json`, `HeadingBlock.php`, `views/save.blade.php`, `views/edit.blade.php`, `views/toolbar.blade.php`
- Converted `src/Blocks/Text/HeadingBlock.php` to backward-compat alias (extends new class, overrides `resolveBlockDirectory()`)
- Updated `src/VisualEditorServiceProvider.php`: `registerBlockViews()`, singletons, blade components
- Added translation keys to `resources/lang/en/ve.php`
- All tests passing

### Phase 2: Inspector Supports System + New Controls (DONE)
- Created `src/Inspector/SupportsPanelRegistry.php`
- Created `src/Inspector/BlockMetadataService.php`
- Created components: `InspectorControls`, `InspectorSection`, `ShadowControl`, `BackgroundControl` (PHP + blade)
- Registered singletons and blade components in service provider
- Added translation keys for all new controls
- All tests passing

### Phase 3: Migrate Remaining 15 Blocks (DONE)

**Completed blocks (15/15):**
1. Paragraph → `src/Blocks/Text/Paragraph/`
2. Quote → `src/Blocks/Text/Quote/`
3. ListBlock → `src/Blocks/Text/ListBlock/` (directory "ListBlock" because "List" is reserved)
4. Spacer → `src/Blocks/Layout/Spacer/`
5. Divider → `src/Blocks/Layout/Divider/`
6. Button → `src/Blocks/Interactive/Button/`
7. Code → `src/Blocks/Interactive/Code/`
8. Audio → `src/Blocks/Media/Audio/`
9. File → `src/Blocks/Media/File/`
10. Video → `src/Blocks/Media/Video/`
11. Image → `src/Blocks/Media/Image/`
12. Gallery → `src/Blocks/Media/Gallery/`
13. Columns → `src/Blocks/Layout/Columns/` (allowedChildren: ['column'])
14. Column → `src/Blocks/Layout/Column/` (public: false, parent: ['columns'])
15. Group → `src/Blocks/Layout/Group/` (3 variations: group/row/stack)

Each migrated block got:
- New directory under `src/Blocks/{Category}/{BlockName}/`
- `block.json` with metadata, attributes, supports
- New namespaced PHP class (only keeps schemas + transforms)
- Alias file at the old location (extends new class, overrides `resolveBlockDirectory()`)
- Blade views copied to `views/save.blade.php` and `views/edit.blade.php`

Service provider `registerCoreBlocks()` updated to reference all new namespaced classes.
All tests passing (541 passed, 2 pre-existing failures).

## What Needs to Be Done

### Phase 4: Auto-Discovery + Production Caching

**4.1 Auto-discovery in service provider:**
- Scan `src/Blocks/{Text,Media,Layout,Interactive}/*/block.json`
- Resolve class: `ArtisanPackUI\VisualEditor\Blocks\{Category}\{Block}\{Block}Block`
- Respect config-based enable/disable

**4.2 Artisan commands:**
- `ve:cache` — serialize all block metadata to cached manifest
- `ve:clear` — remove cached manifest

**4.3 Update BaseBlock loadMetadata():**
- Use cached manifest when available, file reads in development

**4.4 Clean up legacy views:**
- Remove old flat files from `resources/views/blocks/`
- Keep class aliases for v1.x backward compat

**4.5 Update vendor:publish:**
- Collect co-located views → publish to `views/vendor/visual-editor/blocks/{type}/`

**4.6 Tests:**
- Auto-discovery, cache commands, legacy fallback, published view overrides

### Phase 5: Livewire 4 SFC Components (can run parallel with Phase 4)

**5.1 Infrastructure:**
- Create `resources/views/livewire/`, `src/Livewire/Forms/`, `tests/Feature/Livewire/`
- Add `registerLivewireComponents()` + `registerMigrations()` to service provider
- `Livewire::addNamespace( 'visual-editor', __DIR__ . '/../resources/views/livewire' )`

**5.2 Document Saver (pilot SFC):**
- `resources/views/livewire/document-saver.blade.php` — save/load/autosave
- `src/Livewire/Forms/DocumentForm.php` — validation + security sanitization
- Usage: `<livewire:visual-editor::document-saver :document-id="$id" />`

**5.3 Media Picker:**
- `resources/views/livewire/media-picker.blade.php` — wraps media-library MediaModal
- Dispatches `ve-media-selected` browser event

**5.4 Pattern Store:**
- Migration: `create_visual_editor_patterns_table.php` (id, name, slug, blocks JSON, category, user_id, timestamps)
- `resources/views/livewire/pattern-store.blade.php` — CRUD

**5.5 Editor Persistence:**
- `resources/views/livewire/editor-persistence.blade.php` — draft recovery via Cache
- No migration needed

**5.6 Revision History:**
- Migration: `create_visual_editor_revisions_table.php` (id, document_id, blocks JSON, user_id, created_at)
- `resources/views/livewire/revision-history.blade.php` — list/restore/delete

**5.7 Alpine-to-Livewire bridge:**
- In `resources/views/components/editor-state.blade.php`
- Add listeners: `ve-document-saved`, `ve-document-error`, `ve-draft-restored`, `ve-pattern-loaded`, `ve-revision-restored`, `ve-media-selected`

**5.8 Tests:**
- `DocumentSaverTest`, `MediaPickerTest`, `PatternStoreTest`, `EditorPersistenceTest`, `RevisionHistoryTest`

### Phase 6: Apply Expanded Supports to All Blocks (blocked by Phases 2+3)

Update all 16 blocks' block.json with this supports matrix:

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

## Test Baseline

- **2 pre-existing failures** (NOT from our changes): `EditorCanvasTest` (role="main") and `LayerPanelTest` ("List View")
- **541 passed** (1231 assertions)
- Run: `./vendor/bin/pest --compact` from the visual-editor package directory
- Code style: `./vendor/bin/php-cs-fixer fix`

## Key Patterns to Follow

**Reference migrated block:** Look at `src/Blocks/Text/Heading/` for the complete pattern (block.json + HeadingBlock.php + views/ + alias).

**Alias pattern** (old file location):
```php
namespace ArtisanPackUI\VisualEditor\Blocks\{Category};

use ArtisanPackUI\VisualEditor\Blocks\{Category}\{Block}\{Block}Block as Base{Block}Block;

class {Block}Block extends Base{Block}Block
{
    protected function resolveBlockDirectory(): string
    {
        return __DIR__ . '/{Block}';
    }
}
```

**block.json attributes**: Derive from `getContentSchema()` (`source: "content"`) and `getStyleSchema()` (`source: "style"`). Types map: string→string, integer→integer, boolean→boolean, array→array, rich text fields→rich_text.

## File Locations

- Package root: `~/Desktop/ArtisanPack UI Packages/visual-editor/`
- Reference migrated block: `src/Blocks/Text/Heading/` (block.json + HeadingBlock.php + views/ + alias at old location)
- Reference alias: `src/Blocks/Text/HeadingBlock.php`
- Another example: `src/Blocks/Interactive/Button/` + `src/Blocks/Interactive/Button/block.json`
