/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Beyond basic text blocks, users need additional text formatting blocks (Quote, Code, Preformatted, Verse) for varied content needs.

## Proposed Solution

**What would you like to happen?**
Implement additional text blocks for specialized content:

### Quote Block

```php
'quote' => [
    'schema' => [
        'content' => ['type' => 'richtext', 'default' => ''],
        'citation' => ['type' => 'string', 'nullable' => true],
        'style' => ['type' => 'string', 'enum' => ['default', 'large', 'plain']],
    ],
    'supports' => ['align', 'color', 'spacing'],
]
```

Features:
- Rich text quote content
- Citation/attribution field
- Style variants (bordered, large quote marks, plain)
- Background color option

### Code Block

```php
'code' => [
    'schema' => [
        'content' => ['type' => 'string', 'default' => ''],
        'language' => ['type' => 'string', 'nullable' => true],
        'showLineNumbers' => ['type' => 'boolean', 'default' => true],
        'highlightLines' => ['type' => 'array', 'default' => []],
        'filename' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Syntax highlighting (using highlight.js or Prism)
- Language selector (auto-detect option)
- Line numbers toggle
- Line highlighting
- Copy button
- Filename header option

### Preformatted Block

```php
'preformatted' => [
    'schema' => [
        'content' => ['type' => 'string', 'default' => ''],
    ],
    'supports' => ['spacing', 'typography'],
]
```

Features:
- Preserves whitespace and formatting
- Monospace font
- No syntax highlighting (plain text)

### Verse Block

```php
'verse' => [
    'schema' => [
        'content' => ['type' => 'string', 'default' => ''],
    ],
    'supports' => ['align', 'color', 'typography', 'spacing'],
]
```

Features:
- Preserves line breaks
- Centered by default
- Italic styling option
- For poetry, lyrics, etc.

### Table Block

```php
'table' => [
    'schema' => [
        'rows' => ['type' => 'array', 'items' => ['type' => 'array']],
        'hasHeader' => ['type' => 'boolean', 'default' => true],
        'hasFooter' => ['type' => 'boolean', 'default' => false],
        'caption' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing', 'border'],
]
```

Features:
- Add/remove rows and columns
- Header row toggle
- Footer row toggle
- Cell editing with rich text
- Table caption
- Responsive table wrapper

## Alternatives Considered

- Single "text" block for all (rejected: poor UX, no semantics)
- Markdown-only code blocks (rejected: limited styling)
- External table builder (rejected: inconsistent with block approach)

## Use Cases

1. Writer adds a pull quote from an interview
2. Developer adds code snippets to documentation
3. Poet adds formatted verse to creative writing
4. Content creator adds comparison tables

## Acceptance Criteria

- [ ] Quote block renders with citation
- [ ] Quote styles work correctly
- [ ] Code block has syntax highlighting
- [ ] Code block language selector works
- [ ] Code block copy button works
- [ ] Preformatted block preserves whitespace
- [ ] Verse block preserves line breaks
- [ ] Table block allows row/column management
- [ ] Table header row toggles correctly
- [ ] All blocks render correctly on frontend

---

**Related Issues:**
- Depends on: Block Registry, Basic Text Blocks
- Related: Rich text editor integration
