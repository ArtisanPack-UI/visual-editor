/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Frontend" ~"Phase::3"

## Problem Statement

**Is your feature request related to a problem?**
Sites need navigation aids like page lists for menus and breadcrumbs for user orientation within the site hierarchy.

## Proposed Solution

**What would you like to happen?**
Implement navigation helper blocks for improved site navigation:

### Page List Block

```php
'page-list' => [
    'schema' => [
        'parent_id' => ['type' => 'integer', 'nullable' => true],
        'depth' => ['type' => 'integer', 'default' => 0],
        'exclude' => ['type' => 'array', 'default' => []],
        'order_by' => ['type' => 'string', 'enum' => ['title', 'date', 'menu_order'], 'default' => 'menu_order'],
        'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
        'show_parent' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing', 'typography'],
]
```

Features:
- Auto-generates list of pages
- Hierarchical structure support
- Parent page selection (show children of specific page)
- Depth control (0 = all levels, 1 = top level only, etc.)
- Exclude specific pages
- Order by title, date, or custom menu order
- Optional parent page link

Use Cases:
```blade
<!-- All pages -->
<x-artisanpack-page-list />

<!-- Children of "Services" page -->
<x-artisanpack-page-list parent-id="5" />

<!-- Top-level pages only -->
<x-artisanpack-page-list depth="1" />

<!-- Sitemap -->
<x-artisanpack-page-list depth="0" order-by="title" />
```

Implementation:
```php
public function getPages(): Collection
{
    $query = Content::where('type', 'page')
        ->where('status', 'published');

    if ($this->parent_id) {
        $query->where('parent_id', $this->parent_id);
    }

    if (!empty($this->exclude)) {
        $query->whereNotIn('id', $this->exclude);
    }

    $query->orderBy($this->order_by, $this->order);

    return $query->get();
}

public function renderHierarchical(Collection $pages, int $currentDepth = 0): string
{
    if ($this->depth > 0 && $currentDepth >= $this->depth) {
        return '';
    }

    // Build hierarchical HTML structure
}
```

### Breadcrumbs Block

```php
'breadcrumbs' => [
    'schema' => [
        'separator' => ['type' => 'string', 'default' => '/'],
        'separator_icon' => ['type' => 'string', 'nullable' => true],
        'show_home' => ['type' => 'boolean', 'default' => true],
        'home_label' => ['type' => 'string', 'default' => 'Home'],
        'show_current' => ['type' => 'boolean', 'default' => true],
        'link_current' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing', 'typography', 'colors'],
]
```

Features:
- Auto-generates breadcrumb trail based on current page
- Custom separator (/, >, ›, or icon)
- Optional home link
- Show/hide current page
- Link current page or keep as text
- Structured data for SEO (schema.org)

Breadcrumb Trail Examples:
```
// Page with parent hierarchy
Home / About / Our Team / John Doe

// Category archive
Home / Blog / Category: Technology

// Single post
Home / Blog / Post Title

// Date archive
Home / Blog / 2026 / February

// Search results
Home / Search Results for: "Laravel"
```

Implementation:
```php
public function getBreadcrumbs(): array
{
    $breadcrumbs = [
        ['label' => $this->home_label, 'url' => route('home')],
    ];

    // Add parent pages for hierarchical content
    if ($this->isPageOrPost()) {
        foreach ($this->getAncestors() as $ancestor) {
            $breadcrumbs[] = [
                'label' => $ancestor->title,
                'url' => $ancestor->url,
            ];
        }
    }

    // Add archive breadcrumbs
    if ($this->isArchive()) {
        $breadcrumbs[] = $this->getArchiveBreadcrumb();
    }

    // Add current page
    if ($this->show_current) {
        $breadcrumbs[] = [
            'label' => $this->getCurrentPageTitle(),
            'url' => $this->link_current ? $this->getCurrentPageUrl() : null,
        ];
    }

    return $breadcrumbs;
}
```

Structured Data (SEO):
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [{
    "@type": "ListItem",
    "position": 1,
    "name": "Home",
    "item": "https://example.com"
  }, {
    "@type": "ListItem",
    "position": 2,
    "name": "About",
    "item": "https://example.com/about"
  }]
}
```

## Alternatives Considered

- Manual breadcrumb links (rejected: not maintainable)
- JavaScript-based breadcrumbs (rejected: SEO issues)
- Hardcoded page lists (rejected: not dynamic)
- Menu system for page lists (considered: could complement this)

## Use Cases

1. User adds page list to footer for sitemap
2. User shows child pages of "Services" section
3. User creates sidebar navigation with page list
4. User adds breadcrumbs to all pages for navigation
5. User customizes breadcrumb separator icon
6. User excludes certain pages from page list

## Acceptance Criteria

- [ ] Page list block generates list from pages
- [ ] Page list supports hierarchical structure
- [ ] Page list can filter by parent page
- [ ] Page list depth control works
- [ ] Page list excludes specified pages
- [ ] Page list orders correctly
- [ ] Breadcrumbs auto-generate from current page
- [ ] Breadcrumbs show page hierarchy
- [ ] Breadcrumbs work for archives and taxonomies
- [ ] Breadcrumbs support custom separators
- [ ] Breadcrumbs include structured data
- [ ] Breadcrumbs link behavior works correctly

---

**Related Issues:**
- Depends on: Block Registry, artisanpack-ui/cms-framework
- Related: Navigation Block (#014), Template System (#013)
