/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Area::Frontend" ~"Phase::1"

## Problem Statement

**Is your feature request related to a problem?**
The visual editor package needs a foundational editor shell that provides the main interface for content editing, including the sidebar, toolbar, and canvas area.

## Proposed Solution

**What would you like to happen?**
Create the core editor shell component that serves as the main container for all editor functionality:

- **Editor Layout**: Main editor container with responsive design
- **Sidebar Panel**: Collapsible sidebar for block/section insertion, settings, and navigation
- **Top Toolbar**: Primary actions (save, publish, undo/redo, preview, settings)
- **Canvas Area**: Main editing surface where content is rendered
- **Status Bar**: Editor state indicators (autosave status, word count, etc.)

### Technical Implementation

```php
// Main Livewire component
namespace ArtisanPackUI\VisualEditor\Livewire;

class VisualEditor extends Component
{
    public Content $content;
    public array $sections = [];
    public ?string $activeSection = null;
    public ?string $activeBlock = null;
    public bool $sidebarOpen = true;
    public string $sidebarTab = 'blocks';
}
```

### UI Structure

- Full-height editor layout
- Left sidebar (blocks, sections, layers)
- Center canvas with zoom controls
- Right sidebar (block/section settings)
- Floating toolbar for quick actions

## Alternatives Considered

- Single-panel layout (rejected: less functionality)
- Modal-based block insertion (rejected: disrupts workflow)
- Inline-only editing (rejected: limited for complex layouts)

## Use Cases

1. Content editors need a familiar, intuitive interface for creating pages
2. Designers need access to layout controls and styling options
3. Administrators need to manage content structure efficiently

## Acceptance Criteria

- [ ] Editor shell renders with all panels
- [ ] Sidebar is collapsible and remembers state
- [ ] Canvas area properly contains content
- [ ] Toolbar actions are wired up (even if not fully functional)
- [ ] Responsive layout works on tablet+ screens
- [ ] Keyboard shortcuts framework is in place

---

**Related Issues:**
- Depends on: Database migrations
- Blocks: Canvas component, Block insertion
