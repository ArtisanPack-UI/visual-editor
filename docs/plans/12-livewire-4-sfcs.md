# Livewire 4 Single-File Components for Visual Editor

## Context

The visual-editor package is moving from `livewire/livewire: ^3.6|^4.0` to `^4.0` only. As part of this, any Livewire components must be built as single-file components (SFCs) for organization and clarity. The package currently has **48 standard Blade view components** (all extending `Illuminate\View\Component` with Alpine.js) and **zero Livewire components**. This plan creates NEW Livewire 4 SFCs for server-interactive features while keeping all existing Blade components untouched.

This plan implements Phase 4 from `block-restructure-research.md` and should be executed after the block restructure phases (0-3) are complete, since the SFCs depend on a stable block data format.

---

## New Livewire SFC Components (5 total)

| SFC | Purpose | Why Livewire (not Alpine) |
|-----|---------|---------------------------|
| `document-saver` | Save/load/autosave document content to DB | Database persistence |
| `media-picker` | Bridge to `artisanpack-ui/media-library` for image/file selection | File uploads, media DB queries |
| `pattern-store` | CRUD for reusable block patterns | Database persistence |
| `editor-persistence` | Draft recovery via Laravel cache/session | Server-side session management |
| `revision-history` | Document revision tracking and restore | Database queries |

---

## File Structure

```
visual-editor/
├── src/
│   ├── View/Components/           # UNCHANGED: all 48 Blade components stay
│   ├── Livewire/                  # NEW
│   │   └── Forms/
│   │       └── DocumentForm.php   # Livewire Form object for validation
│   └── VisualEditorServiceProvider.php  # Add registerLivewireComponents()
├── resources/views/
│   ├── components/                # UNCHANGED: all 48 blade templates stay
│   └── livewire/                  # NEW: Livewire 4 SFC directory
│       ├── document-saver.blade.php
│       ├── media-picker.blade.php
│       ├── pattern-store.blade.php
│       ├── editor-persistence.blade.php
│       └── revision-history.blade.php
├── database/migrations/           # NEW: for patterns + revisions tables
│   ├── create_visual_editor_patterns_table.php
│   └── create_visual_editor_revisions_table.php
└── tests/Feature/Livewire/        # NEW
    ├── DocumentSaverTest.php
    ├── MediaPickerTest.php
    ├── PatternStoreTest.php
    ├── EditorPersistenceTest.php
    └── RevisionHistoryTest.php
```

**Usage syntax:** `<livewire:visual-editor::document-saver :document-id="$id" />`
**Test syntax:** `Livewire::test( 'visual-editor::document-saver', ['documentId' => 1] )`

---

## Critical Files to Modify

- **`composer.json`** — Change `"livewire/livewire": "^3.6|^4.0"` to `"^4.0"`, drop `"illuminate/support": "^10.0|^11.0|^12.0"` to `"^11.0|^12.0"` (Livewire 4 requires Laravel 11+)
- **`src/VisualEditorServiceProvider.php`** — Add `registerLivewireComponents()` method in `boot()`, add `registerMigrations()` method, register `DocumentForm` singleton
- **`resources/views/components/editor-state.blade.php`** — Add Alpine event listeners to bridge Livewire SFC responses back into the editor store (`ve-document-saved`, `ve-document-error`, `ve-draft-restored`, etc.)
- **`tests/TestCase.php`** — Verify Livewire 4 service provider loads and SFC discovery works

---

## Implementation Phases

### Phase A: Infrastructure Setup

1. Update `composer.json`:
   - `"livewire/livewire": "^4.0"`
   - `"illuminate/support": "^11.0|^12.0"` (Livewire 4 drops Laravel 10)
   - Run `composer update`
2. Create `resources/views/livewire/` directory
3. Create `src/Livewire/Forms/` directory
4. Create `tests/Feature/Livewire/` directory
5. Add to `VisualEditorServiceProvider::boot()`:
   ```php
   protected function registerLivewireComponents(): void
   {
       if ( class_exists( \Livewire\Livewire::class ) ) {
           \Livewire\Livewire::addNamespace( 'visual-editor', __DIR__ . '/../resources/views/livewire' );
       }
   }
   ```
6. Run all existing tests — nothing should break from Livewire version bump alone

### Phase B: Document Saver SFC (pilot)

Build this first to validate the SFC-in-package pattern works end-to-end.

**`resources/views/livewire/document-saver.blade.php`:**
- Properties: `?int $documentId`, `array $blocks`, `string $saveStatus`
- Actions: `save()`, `load()`, `autosave( array $blocks )`
- Listens for `ve-autosave` Alpine event via `x-on:ve-autosave.window="$wire.autosave(...)"`
- Dispatches `ve-document-saved` / `ve-document-error` browser events back to Alpine
- Uses `DocumentForm` Livewire form object for validation

**`src/Livewire/Forms/DocumentForm.php`:**
- Validates block structure before saving
- Uses `artisanpack-ui/security` sanitization on content

**Bridge in `editor-state.blade.php`:**
- Add listeners in the Alpine `editor` store: `ve-document-saved` updates `saveStatus`, `ve-document-error` shows error state

**Tests:** `DocumentSaverTest.php` — save, load, autosave actions; validation errors; event dispatching

### Phase C: Media Picker SFC

**`resources/views/livewire/media-picker.blade.php`:**
- Wraps integration with `artisanpack-ui/media-library` `MediaModal` component
- Properties: `bool $multiSelect`, `?int $maxSelections`, `string $context`
- Dispatches `ve-media-selected` browser event with selected media data back to Alpine
- Listens for `ve-open-media-picker` Alpine event to trigger modal

**Tests:** `MediaPickerTest.php` — renders, selection dispatches event, multi-select mode

### Phase D: Pattern Store SFC

**Migration:** `create_visual_editor_patterns_table.php` — `id`, `name`, `slug`, `blocks` (JSON), `category`, `user_id`, timestamps

**`resources/views/livewire/pattern-store.blade.php`:**
- Actions: `savePattern( string $name, array $blocks )`, `loadPattern( int $id )`, `deletePattern( int $id )`, `listPatterns()`
- Dispatches `ve-pattern-loaded` browser event with block data
- Uses `applyFilters( 'ap.visualEditor.patternSaved', ... )` hook for extensibility

**Tests:** `PatternStoreTest.php` — CRUD operations, authorization, hook integration

### Phase E: Editor Persistence SFC

**`resources/views/livewire/editor-persistence.blade.php`:**
- Uses Laravel `Cache` for lightweight draft recovery (no migration needed)
- Actions: `saveDraft( array $blocks )`, `loadDraft()`, `clearDraft()`
- Cache key: `ve-draft-{documentId}-{userId}`
- Dispatches `ve-draft-restored` when a draft is found on mount
- Auto-cleans drafts older than configurable TTL (default: 24 hours)

**Tests:** `EditorPersistenceTest.php` — draft save/load/clear, TTL expiration, user-scoped isolation

### Phase F: Revision History SFC

**Migration:** `create_visual_editor_revisions_table.php` — `id`, `document_id`, `blocks` (JSON), `user_id`, `created_at`

**`resources/views/livewire/revision-history.blade.php`:**
- Actions: `listRevisions( int $documentId )`, `restoreRevision( int $revisionId )`, `deleteRevision( int $revisionId )`
- Computed property: `revisions` — paginated list for the current document
- Dispatches `ve-revision-restored` browser event with block data

**Tests:** `RevisionHistoryTest.php` — list, restore, delete, pagination, authorization

---

## Alpine-to-Livewire Bridge Pattern

All SFCs follow the same communication pattern:

```
Alpine store dispatches browser event (e.g., ve-autosave)
    → Livewire SFC listens via x-on:ve-autosave.window="$wire.autosave(...)"
    → Livewire performs server action (DB save, cache write, etc.)
    → Livewire dispatches browser event back (e.g., ve-document-saved)
    → Alpine store listens and updates UI state
```

The `editor-state.blade.php` Alpine store needs these event listeners added:
- `ve-document-saved` → `$store.editor.markSaved()`
- `ve-document-error` → `$store.editor.markError( detail.message )`
- `ve-draft-restored` → `$store.editor.loadBlocks( detail.blocks )`
- `ve-pattern-loaded` → `$store.editor.insertBlocks( detail.blocks )`
- `ve-revision-restored` → `$store.editor.loadBlocks( detail.blocks )`
- `ve-media-selected` → dispatched to the requesting block via context

---

## Service Provider Registration (final state)

```php
public function boot(): void
{
    $this->mergeConfiguration();
    $this->publishConfiguration();
    $this->registerTranslations();
    $this->registerViews();
    $this->registerBladeComponents();     // existing — 48 Blade components
    $this->registerLivewireComponents();  // NEW — 5 SFCs
    $this->registerMigrations();          // NEW — patterns + revisions tables
    $this->registerCoreBlocks();
}
```

---

## Verification

After each phase:
1. `./vendor/bin/pest` — all tests pass (existing + new)
2. `./vendor/bin/php-cs-fixer fix --dry-run --diff` — code style clean
3. In the dev app, add `<livewire:visual-editor::document-saver />` (etc.) to the editor shell demo and verify it renders
4. Test the Alpine-to-Livewire bridge: trigger a save from the Alpine store, verify the SFC receives it and dispatches a response event
5. `php artisan migrate` — verify migrations run cleanly (Phase D and F)
6. `vendor:publish --tag=artisanpack-visual-editor-config` still works

---

## Dependencies Between Plans

```
block-restructure Phases 0-3 (block.json, co-location, view restructure)
    ↓ must complete first (stable block data format)
This plan: Phase A (infra) → B (document-saver pilot) → C-F (remaining SFCs)
    ↓ runs in parallel with
inspector-toolbar-supports plan (Phases 1-8)
```

Phase A (Livewire version bump) should happen at the same time as block-restructure Phase 0 since both modify `composer.json`.
