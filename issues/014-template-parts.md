/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::3"

## Problem Statement

**Is your feature request related to a problem?**
Templates need reusable parts (headers, footers, sidebars) that can be edited once and used across multiple templates.

## Proposed Solution

**What would you like to happen?**
Implement template parts system for reusable template components:

### Template Part Registry

```php
namespace ArtisanPackUI\VisualEditor\Templates;

class TemplatePartRegistry
{
    public function register(string $slug, array $config): void;
    public function get(string $slug): ?TemplatePartDefinition;
    public function getByArea(string $area): array;
    public function getVariations(string $slug): array;
}
```

### Template Part Areas

- **Header**: Site header with logo, navigation, etc.
- **Footer**: Site footer with links, copyright, etc.
- **Sidebar**: Widget areas, secondary navigation
- **Custom**: User-defined areas

### Template Part Definition

```php
[
    'slug' => 'header-default',
    'name' => 'Default Header',
    'area' => 'header',
    'variation' => null, // null = default
    'blocks' => [
        [
            'type' => 'group',
            'blocks' => [
                ['type' => 'site-logo'],
                ['type' => 'navigation', 'menu' => 'primary'],
            ],
        ],
    ],
    'styles' => [
        'background' => '#ffffff',
        'padding' => 'medium',
    ],
]
```

### Built-in Header Variations

- **Default**: Logo + Navigation
- **Centered**: Centered logo with nav below
- **Split**: Logo center, nav split left/right
- **Minimal**: Logo only, hamburger menu
- **Transparent**: Overlay style for hero sections

### Built-in Footer Variations

- **Simple**: Copyright only
- **Standard**: Columns with links + copyright
- **Large**: Multi-column with newsletter signup
- **Minimal**: Single line

### Template Part Model

```php
namespace ArtisanPackUI\VisualEditor\Models;

class TemplatePart extends Model
{
    protected $table = 've_template_parts';

    protected $casts = [
        'blocks' => 'array',
        'styles' => 'array',
        'lock' => 'array',
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeForArea($query, string $area);
}
```

### Site-Wide Blocks (for Template Parts)

```php
// Site Logo Block
'site-logo' => [
    'schema' => [
        'link' => ['type' => 'boolean', 'default' => true],
        'width' => ['type' => 'integer', 'nullable' => true],
    ],
]

// Navigation Block
'navigation' => [
    'schema' => [
        'menu' => ['type' => 'string'],
        'orientation' => ['type' => 'string', 'default' => 'horizontal'],
        'showSubmenu' => ['type' => 'boolean', 'default' => true],
    ],
]

// Social Links Block
'social-links' => [
    'schema' => [
        'links' => ['type' => 'array'],
        'iconOnly' => ['type' => 'boolean', 'default' => true],
    ],
]
```

## Alternatives Considered

- Global includes via Blade (rejected: not editable in visual editor)
- Duplicated content in each template (rejected: maintenance nightmare)
- Widget-based sidebars (rejected: inconsistent with block approach)

## Use Cases

1. Designer edits the site header globally
2. User switches to a different header variation
3. Developer creates custom template part area
4. Template references header and footer parts

## Acceptance Criteria

- [ ] Template parts can be registered
- [ ] Template parts render in templates
- [ ] Template parts can be edited visually
- [ ] Built-in header variations work
- [ ] Built-in footer variations work
- [ ] Site logo block displays site logo
- [ ] Navigation block renders menus
- [ ] Changes to parts reflect across all templates
- [ ] Template part editor is accessible

---

**Related Issues:**
- Depends on: Template System, Block Registry
- Related: Navigation menu management
