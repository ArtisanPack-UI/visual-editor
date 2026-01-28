/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::3"

## Problem Statement

**Is your feature request related to a problem?**
Content pages need consistent layouts through templates that define the overall page structure including headers, footers, sidebars, and content area positioning.

## Proposed Solution

**What would you like to happen?**
Implement a comprehensive template system with hierarchy and template parts:

### Template Registry

```php
namespace ArtisanPackUI\VisualEditor\Templates;

class TemplateRegistry
{
    public function register(string $slug, array $config): void;
    public function get(string $slug): ?TemplateDefinition;
    public function all(): array;
    public function getForContentType(string $contentType): array;
    public function resolve(Content $content): TemplateDefinition;
}
```

### Template Hierarchy

Resolution order (similar to WordPress):
1. `{content-type}-{slug}` (e.g., page-about)
2. `{content-type}-{id}` (e.g., page-42)
3. `{content-type}` (e.g., page)
4. `single` (for single content items)
5. `archive` (for listing pages)
6. `index` (fallback)

### Template Definition

```php
[
    'slug' => 'default',
    'name' => 'Default Page',
    'description' => 'Standard page layout with header and footer',
    'type' => 'page',
    'for_content_type' => null, // null = all types
    'template_parts' => ['header', 'footer'],
    'content_area_settings' => [
        'width' => 'boxed', // full, boxed, narrow
        'sidebar' => null, // left, right, null
    ],
    'styles' => [
        'background' => '#ffffff',
    ],
]
```

### Template Types

- **Page Templates**: Default, Full Width, Landing Page, Sidebar Left/Right
- **Single Templates**: Post, Custom Post Types
- **Archive Templates**: Blog, Category, Tag, Author, Search, 404

### Template Model

```php
namespace ArtisanPackUI\VisualEditor\Models;

class Template extends Model
{
    protected $table = 've_templates';

    protected $casts = [
        'template_parts' => 'array',
        'content_area_settings' => 'array',
        'styles' => 'array',
        'lock' => 'array',
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
    ];
}
```

### Helper Functions

```php
// Register a template
veRegisterTemplate('landing-page', [...]);

// Get template for content
$template = veGetTemplate($content);

// Get all templates for content type
$templates = veGetTemplatesForType('page');
```

## Alternatives Considered

- Blade-only templates (rejected: not editable in visual editor)
- Per-page template definition (rejected: no reusability)
- CSS-based layouts only (rejected: limited structure control)

## Use Cases

1. Designer creates a landing page template without sidebars
2. Developer registers templates for custom content types
3. User selects template when creating a page
4. Template changes apply to all pages using it

## Acceptance Criteria

- [ ] Template registry accepts template definitions
- [ ] Template hierarchy resolves correctly
- [ ] Templates can be selected in editor
- [ ] Built-in templates are registered on boot
- [ ] Custom templates can be created in editor
- [ ] Templates support content type restrictions
- [ ] Template settings apply to content
- [ ] Template editor allows visual editing

---

**Related Issues:**
- Depends on: Database migrations, Editor Shell
- Blocks: Template Parts, Template library
