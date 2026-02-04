/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Content pages need additional display blocks beyond the basic query loop template blocks to show full content, author information, navigation, and archive displays.

## Proposed Solution

**What would you like to happen?**
Implement advanced content display blocks for single and archive views:

### Content Body Block

```php
'content-body' => [
    'schema' => [
        'show_featured_image' => ['type' => 'boolean', 'default' => false],
        'alignment' => ['type' => 'string', 'enum' => ['left', 'center', 'justify'], 'default' => 'left'],
    ],
    'supports' => ['spacing', 'typography'],
]
```

Features:
- Displays full content with proper formatting
- Optional featured image at top
- Text alignment control
- Preserves block structure from content editor

### Content Author Bio Block

```php
'content-author-bio' => [
    'schema' => [
        'show_avatar' => ['type' => 'boolean', 'default' => true],
        'show_name' => ['type' => 'boolean', 'default' => true],
        'show_bio' => ['type' => 'boolean', 'default' => true],
        'show_social' => ['type' => 'boolean', 'default' => false],
        'avatar_size' => ['type' => 'string', 'enum' => ['small', 'medium', 'large'], 'default' => 'medium'],
        'layout' => ['type' => 'string', 'enum' => ['horizontal', 'vertical'], 'default' => 'horizontal'],
    ],
    'supports' => ['spacing', 'background', 'border'],
]
```

Features:
- Author avatar display
- Author name (linkable to author archive)
- Author biography text
- Social links (optional)
- Horizontal or vertical layout

### Content Navigation Block

```php
'content-navigation' => [
    'schema' => [
        'show_previous' => ['type' => 'boolean', 'default' => true],
        'show_next' => ['type' => 'boolean', 'default' => true],
        'show_thumbnails' => ['type' => 'boolean', 'default' => false],
        'same_taxonomy' => ['type' => 'boolean', 'default' => false],
        'taxonomy' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing', 'background'],
]
```

Features:
- Previous/next content links
- Optional thumbnails
- Filter by same category/tag
- Customizable labels
- Skip empty (if at start/end)

### Content Read Time Block

```php
'content-read-time' => [
    'schema' => [
        'words_per_minute' => ['type' => 'integer', 'default' => 200],
        'format' => ['type' => 'string', 'enum' => ['short', 'long'], 'default' => 'short'],
        'icon' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing', 'typography', 'colors'],
]
```

Features:
- Auto-calculate reading time from content
- Configurable reading speed (WPM)
- Short format: "5 min read"
- Long format: "Estimated reading time: 5 minutes"
- Optional icon

### Content Archives Block

```php
'content-archives' => [
    'schema' => [
        'type' => ['type' => 'string', 'enum' => ['monthly', 'yearly'], 'default' => 'monthly'],
        'format' => ['type' => 'string', 'enum' => ['list', 'dropdown'], 'default' => 'list'],
        'show_count' => ['type' => 'boolean', 'default' => true],
        'limit' => ['type' => 'integer', 'nullable' => true],
    ],
    'supports' => ['spacing', 'typography'],
]
```

Features:
- Monthly or yearly archives
- List or dropdown display
- Post counts per archive
- Limit number of archives shown

### Content Categories Block

```php
'content-categories' => [
    'schema' => [
        'show_count' => ['type' => 'boolean', 'default' => true],
        'show_hierarchy' => ['type' => 'boolean', 'default' => true],
        'format' => ['type' => 'string', 'enum' => ['list', 'dropdown', 'grid'], 'default' => 'list'],
        'exclude' => ['type' => 'array', 'default' => []],
    ],
    'supports' => ['spacing', 'typography'],
]
```

Features:
- List all categories
- Show post counts
- Hierarchical display
- Multiple display formats
- Exclude specific categories

### Content Tags Block

```php
'content-tags' => [
    'schema' => [
        'show_count' => ['type' => 'boolean', 'default' => false],
        'min_count' => ['type' => 'integer', 'default' => 1],
        'max_count' => ['type' => 'integer', 'nullable' => true],
        'order_by' => ['type' => 'string', 'enum' => ['name', 'count'], 'default' => 'name'],
        'limit' => ['type' => 'integer', 'nullable' => true],
    ],
    'supports' => ['spacing', 'typography'],
]
```

Features:
- Tag cloud display
- Font size scaling by popularity
- Show post counts
- Minimum post count filter
- Limit number of tags

### Content Calendar Block

```php
'content-calendar' => [
    'schema' => [
        'content_type' => ['type' => 'string', 'default' => 'post'],
    ],
    'supports' => ['spacing', 'colors', 'typography'],
]
```

Features:
- Monthly calendar grid
- Highlight days with posts
- Link to daily archives
- Previous/next month navigation

## Alternatives Considered

- Using query loop for all displays (rejected: too complex for simple use cases)
- Hardcoded templates only (rejected: not flexible enough)
- Shortcode-based system (rejected: not visual)

## Use Cases

1. User displays full article content in single view
2. User adds author bio box at end of articles
3. User adds prev/next navigation between articles
4. User shows estimated reading time for content
5. User creates archive sidebar widget
6. User displays category/tag lists

## Acceptance Criteria

- [ ] Content body block renders full content with formatting
- [ ] Author bio block shows avatar, name, bio, social links
- [ ] Content navigation shows previous/next links
- [ ] Navigation can filter by same taxonomy
- [ ] Read time calculates correctly from word count
- [ ] Archives block lists monthly/yearly archives
- [ ] Categories block shows hierarchical list
- [ ] Tags block renders as tag cloud
- [ ] Calendar block displays monthly grid
- [ ] All blocks integrate with cms-framework

---

**Related Issues:**
- Depends on: Issue #012 (Dynamic Blocks), artisanpack-ui/cms-framework
- Related: Query Loop, Template System
