/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Area::Backend" ~"Phase::1"

## Problem Statement

**Is your feature request related to a problem?**
Content needs to be organized into sections - larger containers that hold blocks and provide layout structure. Without sections, page layout options are limited.

## Proposed Solution

**What would you like to happen?**
Create the Section system for organizing content into structured containers:

### Section Registry

```php
namespace ArtisanPackUI\VisualEditor\Sections;

class SectionRegistry
{
    public function register(string $type, array $config): void;
    public function get(string $type): ?SectionDefinition;
    public function all(): array;
    public function getByCategory(string $category): array;
}
```

### Section Structure

```php
[
    'id' => 'section-uuid',
    'type' => 'hero',
    'order' => 0,
    'lock' => null,
    'styles' => [
        'background' => 'white',
        'padding' => 'large',
        'min_height' => '80vh',
    ],
    'blocks' => [...],
]
```

### Pre-designed Section Patterns

**Hero Sections:**
- Simple Hero (heading + text + CTA)
- Hero with Image (split layout)
- Video Hero (background video)
- Animated Hero (with motion)

**Content Sections:**
- Features Grid (icon + text cards)
- Testimonials (carousel or grid)
- Pricing Tables
- Team Members
- FAQ Accordion
- Stats/Numbers
- Timeline

**Call-to-Action:**
- Newsletter Signup
- Contact Form
- CTA Banner

### Section Settings

- Background (color, image, video, gradient)
- Padding (small, medium, large, custom)
- Container width (full, boxed, narrow)
- Vertical alignment
- Custom CSS class
- HTML anchor/ID

## Alternatives Considered

- Blocks-only without sections (rejected: limited layout control)
- Fixed section templates (rejected: not flexible enough)
- CSS Grid-based sections (rejected: too complex for users)

## Use Cases

1. User inserts a hero section for page header
2. User adds a features section with 3-column grid
3. User customizes section background and spacing
4. User reorders sections via drag and drop

## Acceptance Criteria

- [ ] SectionRegistry can register section types
- [ ] Sections render with their blocks
- [ ] Section settings panel works
- [ ] Pre-designed patterns are available
- [ ] Sections can be reordered
- [ ] Section backgrounds work (color, image)
- [ ] Section spacing options work
- [ ] User can convert blank section to pattern

---

**Related Issues:**
- Depends on: Block Registry, Canvas
- Blocks: User-created sections, Section library
