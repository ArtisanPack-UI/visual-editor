# Inspector Controls, Toolbar & Supports System

## Context

The visual editor package has a working editor shell with block selection, Alpine stores, and basic inspector/toolbar components. However, the **Styles tab** in the inspector sidebar is empty — there's no mechanism to auto-generate style controls from a block's `supports` declaration. The toolbar also lacks grouped slots for blocks to inject custom controls. This plan builds out the full inspector controls and toolbar system so that blocks can declare support for features (colors, typography, spacing, etc.) and the editor automatically renders the appropriate UI panels and toolbar controls.

---

## Phase 1: Expand the Supports System (PHP)

### 1a. Update `HasBlockSupports` trait
**File**: `src/Blocks/Concerns/HasBlockSupports.php`

Add new support keys to `getSupports()` defaults:
- `shadow` (boolean) — box-shadow
- `dimensions` (`aspectRatio`, `minHeight`) — aspect ratio and minimum height
- `background` (`backgroundImage`, `backgroundSize`, `backgroundPosition`, `backgroundGradient`)

Add new method `getActiveStyleSupports(): array` — returns a flat list of active style support dot-paths (e.g., `['color.text', 'typography.fontSize', 'shadow']`). Used internally by the panel registry.

### 1b. Create `SupportsPanelRegistry` service
**New file**: `src/Inspector/SupportsPanelRegistry.php`

Maps a block's supports declarations to panel configurations. Method `getPanelsForBlock(BlockInterface $block): array` returns an ordered array of panels, each containing:
- `key` — panel identifier (color, typography, spacing, border, shadow, dimensions, background)
- `label` — translated panel title
- `controls` — array of control configs with `type`, `field` (dot-path into `attributes.styles`), `label`, and type-specific options

Panels only appear if the block has at least one active sub-support for that category. Uses `applyFilters('ap.visualEditor.inspectorPanels', $panels, $block)` for extensibility.

### 1c. Register service in `VisualEditorServiceProvider`
**File**: `src/VisualEditorServiceProvider.php`

Register `SupportsPanelRegistry` as singleton. Also register `BlockMetadataService` (Phase 3).

### 1d. Add `getToolbarControls()` to `BlockInterface` and `BaseBlock`
**Files**: `src/Blocks/Contracts/BlockInterface.php`, `src/Blocks/BaseBlock.php`

Returns `array<string, array>` keyed by toolbar group (`block`, `inline`, `default`). Default implementation: if block supports `align`, return alignment control in `block` group.

---

## Phase 2: New UI Control Components

### 2a. Shadow Control
**New files**: `src/View/Components/ShadowControl.php`, `resources/views/components/shadow-control.blade.php`

Select-based control with Tailwind shadow presets (none, sm, md, lg, xl, 2xl) plus custom CSS input. Dispatches `ve-shadow-change` event with CSS box-shadow string value.

### 2b. Background Control
**New files**: `src/View/Components/BackgroundControl.php`, `resources/views/components/background-control.blade.php`

Composite control with:
- URL text input for background image (conditionally shown if `backgroundImage` support is on)
- Select for background-size (cover/contain/auto) — if `backgroundSize` support is on
- Select for background-position (center/top/bottom/left/right combos) — if `backgroundPosition` support is on
- Gradient input (text input for CSS gradient value) — if `backgroundGradient` support is on

Dispatches `ve-background-change` event with object value.

### 2c. Update `inspector-field.blade.php`
**File**: `resources/views/components/inspector-field.blade.php`

Add `@case('shadow')` and `@case('background')` handlers that render the new controls. Update existing cases to support the dot-path `field` format for styles (e.g., `styles.color.text`).

---

## Phase 3: Block Metadata Serialization

### 3a. Create `BlockMetadataService`
**New file**: `src/Inspector/BlockMetadataService.php`

Method `getAllBlockMeta(): array` serializes all registered blocks' metadata to a JSON-friendly structure:
```php
[
    'heading' => [
        'type', 'name', 'supports', 'contentSchema', 'styleSchema',
        'advancedSchema', 'toolbarControls', 'supportsPanels',
        'defaultContent', 'defaultStyles'
    ],
    // ... more blocks
]
```

### 3b. Inject into editor Alpine store
**File**: `resources/views/components/editor-state.blade.php`

Add `blockMeta` property to the editor store, populated from `BlockMetadataService::getAllBlockMeta()` via `Js::from()` during page load. This gives Alpine full access to all block schemas/panels without server round-trips when switching blocks.

---

## Phase 4: Auto-Generated Inspector Controls

### 4a. Create `InspectorControls` component
**New files**: `src/View/Components/InspectorControls.php`, `resources/views/components/inspector-controls.blade.php`

This component is the bridge between PHP block metadata and the Alpine-driven UI. It receives `blockType` and `blockId` props, resolves the block from the registry, and passes supports panels, schemas, and toolbar controls to the template.

The Blade template renders three sections (for the three sidebar sub-tabs):

**Settings section**: Iterates `contentSchema` fields, renders `<x-ve-inspector-field>` for each. Allows hook injection via `ap.visualEditor.inspectorControls`.

**Styles section**:
1. Auto-generated panels from `supportsPanels` — each wrapped in `<x-ve-panel-body>` (collapsible). Controls inside use `<x-ve-inspector-field>` with `styles.{category}.{property}` field paths.
2. Custom style fields from `getStyleSchema()` — rendered in a "Block Styles" panel below the auto-generated ones.

**Advanced section**: Renders `advancedSchema` fields (anchor, htmlId, className) — only shown if the block supports them.

### 4b. Update `editor-sidebar.blade.php`
**File**: `resources/views/components/editor-sidebar.blade.php`

Replace the empty slot placeholders (`$settingsPanel`, `$stylesPanel`, `$advancedPanel`) with dynamic content. The sidebar uses Alpine to watch `$store.selection.focused`, reads the focused block's type, and renders the inspector controls.

Since PHP Blade is server-rendered but the block selection is client-side, the approach is:
- `blockMeta` is available in the Alpine store (from Phase 3b)
- Use Alpine `x-for` loops over `blockMeta[selectedType].supportsPanels` to render panel sections
- Pre-render the control component templates (color picker, spacing box, etc.) as Alpine-compatible templates
- Each control reads its current value from `$store.editor.getBlock(blockId).attributes.styles.{path}` and dispatches `ve-field-change` on change

---

## Phase 5: Enhanced Block Toolbar

### 5a. Refactor `block-toolbar.blade.php`
**File**: `resources/views/components/block-toolbar.blade.php`

Replace single `{{ $slot }}` with named group slots:

```
[Type indicator + transforms] | [blockGroup] | [inlineGroup] | [defaultGroup] | [Move] | [More]
```

Each group is wrapped in `<x-ve-toolbar-group>` with a divider. Groups only render if they have content (use `isset($groupSlot) && !empty(trim($groupSlot))`). Backward-compatible: the existing `{{ $slot }}` still works for simple cases.

### 5b. Auto-populate toolbar from `getToolbarControls()`
The toolbar reads `blockMeta[selectedType].toolbarControls` from the Alpine store. For each group key (`block`, `inline`, `default`), it renders the declared controls:
- `alignment` type → renders `<x-ve-alignment-control>` inline in the toolbar
- `heading_level` type → renders a level selector dropdown
- Custom types can be added via the `ap.visualEditor.toolbarItems` hook

### 5c. Update `BlockToolbar.php` component class
**File**: `src/View/Components/BlockToolbar.php`

Accept `toolbarControls` array prop. No other structural changes needed since the template handles rendering.

---

## Phase 6: Update Existing Blocks

Update the 15 existing core blocks to declare appropriate supports:

| Block | color | typography | spacing | border | shadow | dimensions | background | align |
|-------|-------|-----------|---------|--------|--------|------------|------------|-------|
| Heading | text, bg | fontSize | - | - | - | - | - | left, center, right |
| Paragraph | text, bg | fontSize | - | - | - | - | - | left, center, right |
| Quote | text, bg | fontSize | margin | border | - | - | - | left, center, right |
| List | text | fontSize | - | - | - | - | - | - |
| Image | - | - | margin | border | shadow | aspectRatio | - | left, center, right, wide, full |
| Video | - | - | margin | - | - | aspectRatio, minHeight | - | wide, full |
| Audio | - | - | margin | - | - | - | - | - |
| Gallery | - | - | margin, padding | - | - | - | - | wide, full |
| File | text | - | - | border | - | - | - | - |
| Columns | - | - | padding | - | - | minHeight | bg (all) | wide, full |
| Column | - | - | padding | border | - | - | bg (all) | - |
| Group | - | - | padding | border | shadow | minHeight | bg (all) | wide, full |
| Spacer | - | - | - | - | - | minHeight | - | - |
| Divider | - | - | margin | border | - | - | - | wide, full |
| Button | text, bg | fontSize | padding | border | shadow | - | - | left, center, right |
| Code | text, bg | fontSize | padding | border | - | - | - | wide |

---

## Phase 7: Translations & Registration

### 7a. Update translation file
**File**: `resources/lang/en/ve.php`

Add keys for: typography, font_family, spacing, padding, margin, dimensions, aspect_ratio, min_height, background, background_image, background_size, background_position, background_gradient, block_specific_styles, block_controls, inline_controls, custom_controls, shadow presets, and dimension option labels.

### 7b. Register new Blade components
**File**: `src/VisualEditorServiceProvider.php`

Register: `inspector-controls`, `shadow-control`, `background-control`.

---

## Phase 8: Tests

### Unit Tests
- **`HasBlockSupportsTest`** — Test new support keys, `getActiveStyleSupports()`, `supportsFeature()` with dot-paths for new supports
- **`SupportsPanelRegistryTest`** — Block with no supports → empty panels; block with `color.text` → color panel; block with all supports → all panels in order; hook filter modifies panels; control field paths are correct
- **`BlockMetadataServiceTest`** — `getAllBlockMeta()` returns correct structure; all registered blocks included; schemas serialized correctly
- **`getToolbarControls` test** — Blocks with `align` support return alignment in `block` group; blocks without `align` return empty

### Feature Tests
- Inspector controls component renders panels for a block with mixed supports
- Shadow control renders preset options and dispatches events
- Background control conditionally shows sub-controls based on sub-supports
- Block toolbar renders grouped slots with dividers

---

## Data Flow Summary

```
Block declares supports in PHP
    → SupportsPanelRegistry maps to panel configs
    → BlockMetadataService serializes all to JSON
    → editor-state.blade.php injects as Alpine.store('editor').blockMeta
    → User selects block in canvas
    → selection store updates focused block ID
    → Inspector sidebar reads blockMeta[type].supportsPanels
    → Renders <x-ve-panel-body> per panel with <x-ve-inspector-field> per control
    → User changes value → ve-field-change event
    → Editor store updates block.attributes.styles.{category}.{property}
    → Block re-renders with new styles
```

---

## Style Attribute Structure (in block data)

```javascript
{
    id: "block-123",
    type: "paragraph",
    attributes: {
        content: { text: "Hello world" },
        styles: {
            color: { text: "#333", background: "#fff" },
            typography: { fontSize: "18px" },
            spacing: { padding: { top: "1rem", bottom: "1rem" } },
            border: { color: "#ccc", style: "solid", width: "1px", radius: "4px" },
            shadow: "0 4px 6px rgba(0,0,0,0.1)",
            dimensions: { aspectRatio: "16/9" },
            background: { backgroundImage: "url(...)", backgroundSize: "cover" }
        }
    }
}
```

---

## Verification

1. Run `./vendor/bin/pest` from the package directory to confirm all tests pass
2. Run `./vendor/bin/php-cs-fixer fix` to verify code style
3. In the dev app (`artisanpack-ui`), navigate to the editor shell demo
4. Select a block (e.g., Paragraph) — verify the Styles tab shows Color and Typography panels
5. Select an Image block — verify Styles tab shows Border, Shadow, and Dimensions panels
6. Change a color in the Color panel — verify the `ve-field-change` event fires and the editor store updates
7. Verify the toolbar shows alignment controls for blocks with `align` support
8. Verify the toolbar grouped slots render with dividers between non-empty groups

---

## Implementation Order

1. Phase 1 (PHP supports expansion + services) → Phase 8 unit tests for Phase 1
2. Phase 2 (new control components)
3. Phase 3 (metadata serialization + Alpine injection)
4. Phase 4 (inspector controls component + sidebar wiring)
5. Phase 5 (toolbar enhancement)
6. Phase 6 (update existing blocks' supports)
7. Phase 7 (translations + registration)
8. Phase 8 remaining tests
