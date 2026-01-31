/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Users need dynamic blocks (Query Loop, Latest Posts, Related Content) that automatically fetch and display content from the CMS.

## Proposed Solution

**What would you like to happen?**
Implement dynamic blocks that query content from cms-framework:

### Query Loop Block

```php
'query-loop' => [
    'schema' => [
        'contentType' => ['type' => 'string', 'default' => 'post'],
        'perPage' => ['type' => 'integer', 'default' => 10],
        'orderBy' => ['type' => 'string', 'default' => 'date'],
        'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
        'filters' => [
            'type' => 'object',
            'properties' => [
                'categories' => ['type' => 'array'],
                'tags' => ['type' => 'array'],
                'author' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
            ],
        ],
        'template' => ['type' => 'array'], // Template blocks
        'emptyMessage' => ['type' => 'string'],
        'pagination' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Content type selection (posts, pages, custom types)
- Filtering by taxonomy, author, date range
- Sorting options
- Per-page limit
- Custom template with template blocks
- Pagination option
- No results message

### Template Blocks (for Query Loop)

```php
// Post Title - displays within query loop
'post-title' => [
    'schema' => [
        'level' => ['type' => 'string', 'default' => 'h2'],
        'link' => ['type' => 'boolean', 'default' => true],
    ],
    'context' => 'query-loop',
]

// Post Excerpt
'post-excerpt' => [
    'schema' => [
        'length' => ['type' => 'integer', 'default' => 150],
        'readMore' => ['type' => 'boolean', 'default' => true],
    ],
    'context' => 'query-loop',
]

// Post Featured Image
'post-featured-image' => [
    'schema' => [
        'size' => ['type' => 'string', 'default' => 'medium'],
        'link' => ['type' => 'boolean', 'default' => true],
    ],
    'context' => 'query-loop',
]

// Post Date, Post Author, Post Categories, Post Tags
```

### Latest Posts Block

```php
'latest-posts' => [
    'schema' => [
        'contentType' => ['type' => 'string', 'default' => 'post'],
        'count' => ['type' => 'integer', 'default' => 5],
        'displayFeaturedImage' => ['type' => 'boolean', 'default' => true],
        'displayExcerpt' => ['type' => 'boolean', 'default' => true],
        'displayDate' => ['type' => 'boolean', 'default' => true],
        'displayAuthor' => ['type' => 'boolean', 'default' => false],
        'layout' => ['type' => 'string', 'enum' => ['list', 'grid']],
        'columns' => ['type' => 'integer', 'default' => 3],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Quick setup (no custom template needed)
- Display toggles for each element
- List or grid layout
- Links to full query loop for advanced use

### Related Content Block

```php
'related-content' => [
    'schema' => [
        'count' => ['type' => 'integer', 'default' => 3],
        'matchBy' => ['type' => 'string', 'enum' => ['category', 'tag', 'author']],
        'layout' => ['type' => 'string', 'enum' => ['list', 'grid']],
    ],
    'supports' => ['spacing'],
]
```

Features:
- Automatically finds related content
- Matching by taxonomy, author, or custom logic
- Configurable display

## Alternatives Considered

- Shortcode-based queries (rejected: not visual)
- Pre-built query templates only (rejected: not flexible)
- GraphQL-based queries (rejected: overcomplicated)

## Use Cases

1. User creates a blog page with latest posts
2. User builds a custom archive template
3. User shows related articles at bottom of post
4. User creates a portfolio grid

## Acceptance Criteria

- [ ] Query loop fetches content from cms-framework
- [ ] Filters work correctly
- [ ] Sorting works correctly
- [ ] Template blocks render in loop context
- [ ] Pagination works when enabled
- [ ] Latest posts block is a quick preset
- [ ] Related content finds matching items
- [ ] Empty state shows message
- [ ] Performance is acceptable (caching)

---

**Related Issues:**
- Depends on: Block Registry, artisanpack-ui/cms-framework
- Related: Template system, Archive templates
