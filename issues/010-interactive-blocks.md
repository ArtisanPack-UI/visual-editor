/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Users need interactive blocks (Button, Accordion, Tabs, Toggle) to create engaging content with user interaction capabilities.

## Proposed Solution

**What would you like to happen?**
Implement interactive blocks with proper Alpine.js integration:

### Button Block

```php
'button' => [
    'schema' => [
        'text' => ['type' => 'string', 'default' => 'Click me'],
        'url' => ['type' => 'string', 'nullable' => true],
        'target' => ['type' => 'string', 'enum' => ['_self', '_blank']],
        'style' => ['type' => 'string', 'enum' => ['primary', 'secondary', 'outline', 'ghost']],
        'size' => ['type' => 'string', 'enum' => ['small', 'medium', 'large']],
        'icon' => ['type' => 'string', 'nullable' => true],
        'iconPosition' => ['type' => 'string', 'enum' => ['left', 'right']],
    ],
    'supports' => ['align', 'spacing', 'color'],
]
```

Features:
- Button text with inline editing
- Link URL with page/post selector
- Style variants (primary, secondary, outline)
- Size options
- Icon support (left or right)
- Full width option

### Accordion Block

```php
'accordion' => [
    'schema' => [
        'items' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'array'], // Nested blocks
                    'open' => ['type' => 'boolean', 'default' => false],
                ],
            ],
        ],
        'allowMultiple' => ['type' => 'boolean', 'default' => false],
        'iconPosition' => ['type' => 'string', 'default' => 'right'],
    ],
    'supports' => ['spacing', 'border'],
]
```

Features:
- Multiple accordion items
- Rich content in each item (nested blocks)
- Single or multiple open
- Animated expand/collapse
- Custom icons

### Tabs Block

```php
'tabs' => [
    'schema' => [
        'tabs' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'array'],
                ],
            ],
        ],
        'orientation' => ['type' => 'string', 'enum' => ['horizontal', 'vertical']],
        'defaultTab' => ['type' => 'integer', 'default' => 0],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Multiple tab panels
- Horizontal and vertical orientation
- Default active tab
- Nested block content
- Keyboard navigation (arrow keys)

### Toggle/Details Block

```php
'toggle' => [
    'schema' => [
        'title' => ['type' => 'string', 'default' => 'Click to reveal'],
        'content' => ['type' => 'array'],
        'open' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Single expandable section
- Uses native details/summary elements
- Nested block content

## Alternatives Considered

- JavaScript-free interactive elements (rejected: limited functionality)
- Third-party widget library (rejected: inconsistent styling)
- Server-side interactions only (rejected: poor UX)

## Use Cases

1. User adds CTA buttons to sections
2. User creates FAQ section with accordions
3. User organizes content with tabs
4. User hides supplementary content in toggle

## Acceptance Criteria

- [ ] Button renders with all style options
- [ ] Button link works correctly
- [ ] Accordion expands/collapses with animation
- [ ] Accordion respects single/multiple open setting
- [ ] Tabs switch content correctly
- [ ] Tabs support keyboard navigation
- [ ] Toggle uses semantic HTML (details/summary)
- [ ] All blocks support nested content
- [ ] Interactive blocks are accessible (ARIA)

---

**Related Issues:**
- Depends on: Block Registry, Canvas
- Related: Section patterns (FAQ, Tabs sections)
