/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::2"

## Problem Statement

**Is your feature request related to a problem?**
Query loop and archive pages need additional blocks to display query metadata like titles, result counts, and filtering information.

## Proposed Solution

**What would you like to happen?**
Implement query enhancement blocks for better archive and search result displays:

### Query Title Block

```php
'query-title' => [
    'schema' => [
        'show_prefix' => ['type' => 'boolean', 'default' => true],
        'level' => ['type' => 'string', 'enum' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 'default' => 'h1'],
        'link' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing', 'typography', 'colors'],
]
```

Features:
- Auto-generates archive titles based on context
  - Category archive: "Category: {Name}"
  - Tag archive: "Tag: {Name}"
  - Author archive: "Author: {Name}"
  - Date archive: "Archive: {Month Year}"
  - Search results: "Search Results for: {Query}"
- Toggle prefix display
- Heading level control
- Optional link to archive

Context Detection:
```php
// Automatically detects archive type and generates appropriate title
if (is_category()) {
    return 'Category: ' . $category->name;
} elseif (is_tag()) {
    return 'Tag: ' . $tag->name;
} elseif (is_author()) {
    return 'Author: ' . $author->name;
} elseif (is_date()) {
    return 'Archive: ' . $date;
} elseif (is_search()) {
    return 'Search Results for: ' . $query;
}
```

### Query Total Block

```php
'query-total' => [
    'schema' => [
        'format' => ['type' => 'string', 'enum' => ['number', 'short', 'long'], 'default' => 'short'],
        'singular' => ['type' => 'string', 'default' => 'result'],
        'plural' => ['type' => 'string', 'default' => 'results'],
        'show_icon' => ['type' => 'boolean', 'default' => false],
        'icon' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing', 'typography', 'colors'],
]
```

Features:
- Display total result count
- Multiple format options:
  - Number: "42"
  - Short: "42 results"
  - Long: "Found 42 results"
- Custom singular/plural labels
- Optional icon

Use Cases:
```blade
<!-- Above query loop -->
<x-artisanpack-query-total format="long" /> {{-- "Found 42 results" --}}

<!-- In header with query title -->
<div>
    <x-artisanpack-query-title />
    <x-artisanpack-query-total format="short" /> {{-- "42 results" --}}
</div>
```

### Query No Results Block

```php
'query-no-results' => [
    'schema' => [
        'message' => ['type' => 'richtext', 'default' => 'No results found.'],
        'show_search' => ['type' => 'boolean', 'default' => false],
        'show_suggestions' => ['type' => 'boolean', 'default' => false],
        'suggestions_title' => ['type' => 'string', 'default' => 'You might be interested in:'],
        'suggestions_count' => ['type' => 'integer', 'default' => 3],
    ],
    'supports' => ['spacing', 'background', 'typography'],
]
```

Features:
- Custom "no results" message
- Optional search form
- Suggested content (latest or related)
- Empty state styling

## Alternatives Considered

- Hardcoded archive titles (rejected: not translatable or customizable)
- JavaScript-based result count (rejected: SEO issues)
- PHP template tags (rejected: not visual editor friendly)

## Use Cases

1. User creates category archive page with "Category: News" title
2. User displays search result count: "Found 15 results for 'Laravel'"
3. User shows archive title without prefix: "News" instead of "Category: News"
4. User displays result count in sidebar
5. User shows empty state with search form when no results

## Acceptance Criteria

- [ ] Query title block detects archive type
- [ ] Query title generates correct title per context
- [ ] Query title prefix can be toggled
- [ ] Query total displays result count
- [ ] Query total formats correctly (number, short, long)
- [ ] Query total updates with filtered results
- [ ] Query no results shows when query is empty
- [ ] No results block can show search form
- [ ] No results block can show suggestions
- [ ] All blocks work within query loop context

---

**Related Issues:**
- Depends on: Issue #012 (Dynamic Blocks), artisanpack-ui/cms-framework
- Related: Query Loop, Search Block
