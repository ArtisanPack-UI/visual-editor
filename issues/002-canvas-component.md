/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Area::Frontend" ~"Phase::1"

## Problem Statement

**Is your feature request related to a problem?**
The editor needs a canvas component that renders sections and blocks in an editable, interactive manner while supporting drag-and-drop, selection, and inline editing.

## Proposed Solution

**What would you like to happen?**
Create the Canvas Livewire component that manages the visual editing surface:

### Features

- **Section Rendering**: Display sections in order with visual boundaries
- **Block Rendering**: Render blocks within sections with edit affordances
- **Selection System**: Click to select blocks/sections with visual indicators
- **Drag and Drop**: Reorder sections and blocks via drag handles
- **Inline Editing**: Double-click to enter edit mode for text blocks
- **Zoom Controls**: Canvas zoom (50%-200%) with pan support
- **Grid Overlay**: Optional alignment grid

### Technical Implementation

```php
namespace ArtisanPackUI\VisualEditor\Livewire;

class Canvas extends Component
{
    public array $sections = [];
    public ?string $selectedSection = null;
    public ?string $selectedBlock = null;
    public int $zoom = 100;
    public bool $showGrid = false;

    public function selectSection(string $sectionId): void;
    public function selectBlock(string $blockId): void;
    public function moveSection(string $sectionId, int $newIndex): void;
    public function moveBlock(string $blockId, string $targetSectionId, int $newIndex): void;
}
```

### Alpine.js Integration

- Drag and drop via SortableJS or native HTML5 DnD
- Selection state management
- Keyboard navigation (arrow keys, tab)
- Context menu support

## Alternatives Considered

- iframe-based canvas (rejected: complexity, communication overhead)
- Pure Alpine.js canvas (rejected: state management issues)
- React/Vue canvas (rejected: inconsistent with TALL stack)

## Use Cases

1. Users drag sections to reorder page layout
2. Users click blocks to select and edit them
3. Users zoom out to see full page overview
4. Users use keyboard to navigate between blocks

## Acceptance Criteria

- [ ] Sections render in correct order
- [ ] Blocks render within sections
- [ ] Click selection works for sections and blocks
- [ ] Drag and drop reorders sections
- [ ] Drag and drop reorders blocks within sections
- [ ] Zoom controls work (50%-200%)
- [ ] Selection indicators are visible and clear
- [ ] Keyboard navigation (arrows, tab, escape)

---

**Related Issues:**
- Depends on: Editor Shell, Block System
- Blocks: Inline editing, Block settings panel
