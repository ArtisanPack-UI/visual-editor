/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Area::Backend" ~"Phase::1"

## Problem Statement

**Is your feature request related to a problem?**
The visual editor needs a centralized registry system for managing block types, their schemas, rendering logic, and validation rules.

## Proposed Solution

**What would you like to happen?**
Create the Block Registry system that allows registration and management of block types:

### Block Registry Class

```php
namespace ArtisanPackUI\VisualEditor\Blocks;

class BlockRegistry
{
    protected array $blocks = [];

    public function register(string $type, array $config): void;
    public function get(string $type): ?BlockDefinition;
    public function all(): array;
    public function exists(string $type): bool;
    public function getByCategory(string $category): array;
    public function unregister(string $type): void;
}
```

### Block Definition Structure

```php
[
    'type' => 'heading',
    'name' => 'Heading',
    'description' => 'Add a heading block',
    'icon' => 'heroicon-o-h1',
    'category' => 'text',
    'keywords' => ['title', 'h1', 'h2', 'header'],
    'schema' => HeadingBlockSchema::class,
    'component' => 'visual-editor::blocks.heading',
    'editor_component' => HeadingBlockEditor::class,
    'supports' => ['align', 'color', 'spacing', 'anchor'],
    'example' => [...],
]
```

### Block Categories

- **Text**: Heading, Paragraph, List, Quote, Code
- **Media**: Image, Gallery, Video, Audio, File
- **Layout**: Columns, Group, Spacer, Separator
- **Interactive**: Button, Accordion, Tabs, Toggle
- **Embed**: YouTube, Vimeo, Twitter, Generic Embed
- **Dynamic**: Query Loop, Latest Posts, Related Content

### Helper Functions

```php
// Register a block type
veRegisterBlock('custom-block', [...]);

// Check if block exists
veBlockExists('heading');

// Get block definition
$block = veGetBlock('heading');
```

## Alternatives Considered

- Array-based configuration only (rejected: lacks type safety)
- Database-stored blocks (rejected: performance concerns)
- Plugin-based blocks (rejected: overcomplicated for initial version)

## Use Cases

1. Core blocks are registered on package boot
2. Third-party packages register custom blocks via service providers
3. Applications register application-specific blocks
4. Block inserter queries registry for available blocks

## Acceptance Criteria

- [ ] BlockRegistry singleton is available via facade and helper
- [ ] Blocks can be registered with full configuration
- [ ] Blocks can be retrieved by type
- [ ] Blocks can be filtered by category
- [ ] Block registration supports validation
- [ ] Extensible via hooks filter (`ve.blocks.register`)
- [ ] Unit tests for all registry methods

---

**Related Issues:**
- Blocks: Basic block implementations, Block schemas
