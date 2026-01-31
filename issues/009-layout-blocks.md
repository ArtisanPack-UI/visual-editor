/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Users need layout blocks (Columns, Group, Spacer, Separator) to create complex page structures beyond simple vertical stacking.

## Proposed Solution

**What would you like to happen?**
Implement layout blocks for flexible content arrangement:

### Columns Block

```php
'columns' => [
    'schema' => [
        'columns' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'width' => ['type' => 'string'],
                    'blocks' => ['type' => 'array'],
                ],
            ],
        ],
        'layout' => ['type' => 'string', 'default' => 'equal'],
        'gap' => ['type' => 'string', 'default' => 'medium'],
        'stackOn' => ['type' => 'string', 'default' => 'mobile'],
        'verticalAlignment' => ['type' => 'string', 'default' => 'top'],
    ],
    'supports' => ['spacing', 'background'],
]
```

Features:
- Preset layouts: 50/50, 33/33/33, 25/75, 75/25, 25/50/25
- Custom column widths
- Column gap control
- Responsive stacking breakpoint
- Vertical alignment (top, center, bottom, stretch)
- Nested blocks in each column

### Group Block

```php
'group' => [
    'schema' => [
        'blocks' => ['type' => 'array'],
        'tagName' => ['type' => 'string', 'default' => 'div'],
    ],
    'supports' => ['spacing', 'background', 'border', 'shadow'],
]
```

Features:
- Container for grouping blocks
- Background color/image
- Border and shadow options
- Custom HTML tag (div, section, article)
- Constrained width option

### Spacer Block

```php
'spacer' => [
    'schema' => [
        'height' => ['type' => 'string', 'default' => '50px'],
        'responsive' => [
            'type' => 'object',
            'properties' => [
                'mobile' => ['type' => 'string'],
                'tablet' => ['type' => 'string'],
            ],
        ],
    ],
]
```

Features:
- Draggable height adjustment
- Pixel or rem units
- Responsive height options

### Separator Block

```php
'separator' => [
    'schema' => [
        'style' => ['type' => 'string', 'enum' => ['solid', 'dashed', 'dotted', 'wide']],
        'color' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Line style options
- Custom color
- Width variants (narrow, wide, full)

## Alternatives Considered

- CSS Grid-only layouts (rejected: too complex for non-developers)
- Fixed layout templates (rejected: not flexible enough)
- Flexbox controls exposed to user (rejected: too technical)

## Use Cases

1. User creates a two-column layout for text and image
2. User groups related blocks with a background
3. User adds spacing between sections
4. User adds a decorative separator

## Acceptance Criteria

- [ ] Columns block creates multi-column layout
- [ ] Column widths can be adjusted
- [ ] Columns stack responsively
- [ ] Group block wraps nested blocks
- [ ] Group supports background/border
- [ ] Spacer height is adjustable
- [ ] Separator renders with style options
- [ ] All layout blocks work in editor and frontend
- [ ] Nested blocks work correctly

---

**Related Issues:**
- Depends on: Block Registry, Canvas
- Related: Section system, Responsive preview
