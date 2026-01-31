/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::High" ~"Area::Frontend" ~"Phase::1"

## Problem Statement

**Is your feature request related to a problem?**
The visual editor needs basic text blocks (Heading, Paragraph, List) as the foundational content blocks for any page.

## Proposed Solution

**What would you like to happen?**
Implement the core text blocks with full editing capabilities:

### Heading Block

```php
'heading' => [
    'schema' => [
        'text' => ['type' => 'string', 'default' => ''],
        'level' => ['type' => 'string', 'enum' => ['h1','h2','h3','h4','h5','h6'], 'default' => 'h2'],
    ],
    'supports' => ['align', 'color', 'typography', 'spacing', 'anchor'],
]
```

Features:
- Inline rich text editing
- Level selector (H1-H6)
- Text alignment
- Custom colors
- Typography controls

### Paragraph Block

```php
'paragraph' => [
    'schema' => [
        'content' => ['type' => 'richtext', 'default' => ''],
        'dropCap' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['align', 'color', 'typography', 'spacing'],
]
```

Features:
- Rich text editing (bold, italic, links, etc.)
- Drop cap option
- Text alignment
- Custom colors

### List Block

```php
'list' => [
    'schema' => [
        'items' => ['type' => 'array', 'items' => ['type' => 'richtext']],
        'ordered' => ['type' => 'boolean', 'default' => false],
        'start' => ['type' => 'integer', 'default' => 1],
    ],
    'supports' => ['color', 'typography', 'spacing'],
]
```

Features:
- Ordered and unordered lists
- Nested list support
- Custom start number for ordered lists
- Rich text in list items

### Rich Text Editor Integration

- Use TipTap or similar for inline editing
- Formatting toolbar appears on selection
- Keyboard shortcuts (Ctrl+B, Ctrl+I, etc.)
- Link insertion with modal

## Alternatives Considered

- Markdown-only editing (rejected: not user-friendly)
- ContentEditable without library (rejected: cross-browser issues)
- Separate edit modal (rejected: disrupts workflow)

## Use Cases

1. User adds a heading to start a section
2. User writes paragraphs of content with formatting
3. User creates bulleted lists for features
4. User creates numbered lists for steps

## Acceptance Criteria

- [ ] Heading block renders with correct HTML tag
- [ ] Heading level can be changed
- [ ] Paragraph block supports rich text formatting
- [ ] List block supports ordered/unordered toggle
- [ ] List items can be added/removed
- [ ] Inline editing works without page refresh
- [ ] All blocks have settings panels
- [ ] All blocks render correctly on frontend

---

**Related Issues:**
- Depends on: Block Registry, Canvas
- Related: Quote block, Code block
