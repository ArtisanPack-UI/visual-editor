/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Users need additional layout blocks for common patterns like media-text combinations, auto-generated table of contents, and raw HTML for power users.

## Proposed Solution

**What would you like to happen?**
Implement additional layout blocks for enhanced content presentation:

### Media-Text Block

```php
'media-text' => [
    'schema' => [
        'media_id' => ['type' => 'integer', 'nullable' => true],
        'media_type' => ['type' => 'string', 'enum' => ['image', 'video'], 'default' => 'image'],
        'media_position' => ['type' => 'string', 'enum' => ['left', 'right'], 'default' => 'left'],
        'media_width' => ['type' => 'integer', 'default' => 50],
        'vertical_alignment' => ['type' => 'string', 'enum' => ['top', 'center', 'bottom'], 'default' => 'center'],
        'stack_on_mobile' => ['type' => 'boolean', 'default' => true],
        'content' => ['type' => 'array'], // Nested blocks
    ],
    'supports' => ['spacing', 'background', 'border'],
]
```

Features:
- Image or video on one side, text content on other
- Media position (left/right)
- Adjustable media width (25%, 33%, 50%, 66%, 75%)
- Vertical alignment options
- Responsive stacking on mobile
- Nested blocks in text area

Use Cases:
```blade
<!-- Feature showcase -->
<media-text media-position="left" media-width="40">
    <image src="product.jpg" />
    <heading>Amazing Product</heading>
    <text>Description here...</text>
    <button>Learn More</button>
</media-text>
```

### Table of Contents Block

```php
'table-of-contents' => [
    'schema' => [
        'levels' => ['type' => 'array', 'default' => ['h2', 'h3']],
        'ordered' => ['type' => 'boolean', 'default' => false],
        'smooth_scroll' => ['type' => 'boolean', 'default' => true],
        'sticky' => ['type' => 'boolean', 'default' => false],
        'title' => ['type' => 'string', 'default' => 'Table of Contents'],
        'show_title' => ['type' => 'boolean', 'default' => true],
        'collapsible' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing', 'background', 'border', 'typography'],
]
```

Features:
- Auto-generates from heading blocks on page
- Select which heading levels to include (h2, h3, h4, etc.)
- Ordered or unordered list
- Smooth scroll to sections
- Optional sticky positioning
- Collapsible on mobile
- Highlights current section

Implementation:
```javascript
// Auto-detect headings with anchors
document.querySelectorAll('h2[id], h3[id]').forEach(heading => {
    const link = document.createElement('a');
    link.href = `#${heading.id}`;
    link.textContent = heading.textContent;
    tocList.appendChild(link);
});

// Smooth scroll
link.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelector(link.hash).scrollIntoView({
        behavior: 'smooth'
    });
});
```

### HTML Block

```php
'html' => [
    'schema' => [
        'content' => ['type' => 'string', 'default' => ''],
        'preview_mode' => ['type' => 'string', 'enum' => ['edit', 'preview'], 'default' => 'edit'],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Raw HTML input with syntax highlighting
- Toggle between edit and preview mode
- Warning about potential security risks
- Code editor with HTML validation
- Preview rendering in editor

Editor Interface:
```blade
<div class="html-block-editor">
    @if($preview_mode === 'edit')
        <textarea class="code-editor">{{ $content }}</textarea>
        <button wire:click="$set('preview_mode', 'preview')">Preview</button>
    @else
        <div class="html-preview">{!! $content !!}</div>
        <button wire:click="$set('preview_mode', 'edit')">Edit</button>
    @endif
</div>
```

Security:
- Sanitize HTML on save using kses() from security package
- Warning message in editor
- Require specific capability/permission
- Preview in sandboxed iframe (optional)

## Alternatives Considered

- Custom components for media-text (rejected: common pattern deserves dedicated block)
- Manual TOC creation (rejected: error-prone and not maintainable)
- Disallow HTML block (rejected: power users need it)
- Markdown block instead of HTML (considered: could be additional block)

## Use Cases

1. User creates feature section with image on left, content on right
2. User adds auto-generated table of contents to long article
3. User embeds custom HTML/JavaScript widget
4. Developer adds third-party embed code
5. User creates alternating media-text sections

## Acceptance Criteria

- [ ] Media-text block displays media on left or right
- [ ] Media-text media width is adjustable
- [ ] Media-text stacks responsively on mobile
- [ ] Media-text supports nested blocks in content area
- [ ] TOC auto-generates from heading blocks
- [ ] TOC links smooth scroll to sections
- [ ] TOC can be sticky positioned
- [ ] TOC highlights current section on scroll
- [ ] HTML block provides code editor interface
- [ ] HTML block toggles between edit and preview
- [ ] HTML block sanitizes output for security
- [ ] HTML block shows security warning

---

**Related Issues:**
- Depends on: Block Registry, Media Blocks, Text Blocks
- Related: Security package (HTML sanitization)
