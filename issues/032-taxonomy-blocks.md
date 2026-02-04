/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::3"

## Problem Statement

**Is your feature request related to a problem?**
Taxonomy archive pages (categories, tags) need dedicated blocks to display taxonomy information and query content by taxonomies.

## Proposed Solution

**What would you like to happen?**
Implement taxonomy display and query blocks for taxonomy archive pages:

### Taxonomy Name Block

```php
'taxonomy-name' => [
    'schema' => [
        'taxonomy_id' => ['type' => 'integer', 'nullable' => true],
        'level' => ['type' => 'string', 'enum' => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], 'default' => 'h1'],
        'show_prefix' => ['type' => 'boolean', 'default' => false],
        'prefix_text' => ['type' => 'string', 'default' => 'Category: '],
        'link' => ['type' => 'boolean', 'default' => false],
    ],
    'supports' => ['spacing', 'typography', 'colors'],
]
```

Features:
- Auto-detects current taxonomy from context
- Manual taxonomy ID selection
- Heading level control
- Optional prefix (Category:, Tag:, etc.)
- Optional link to taxonomy archive

Context Detection:
```php
public function getTaxonomy(): ?Taxonomy
{
    // If taxonomy_id is set, use it
    if ($this->taxonomy_id) {
        return Taxonomy::find($this->taxonomy_id);
    }

    // Otherwise detect from current page context
    if ($currentTaxonomy = $this->getCurrentTaxonomy()) {
        return $currentTaxonomy;
    }

    return null;
}
```

### Taxonomy Description Block

```php
'taxonomy-description' => [
    'schema' => [
        'taxonomy_id' => ['type' => 'integer', 'nullable' => true],
        'fallback_text' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing', 'typography', 'colors', 'background'],
]
```

Features:
- Displays taxonomy description/bio
- Auto-detects from context or uses manual ID
- Fallback text if description is empty
- Rich text support in description

Use Cases:
```blade
<!-- Category archive page -->
<x-artisanpack-taxonomy-name level="h1" prefix-text="Category: " />
<x-artisanpack-taxonomy-description />

<!-- Display "Technology" category info anywhere -->
<x-artisanpack-taxonomy-name :taxonomy-id="5" />
<x-artisanpack-taxonomy-description :taxonomy-id="5" />
```

### Taxonomy Count Block

```php
'taxonomy-count' => [
    'schema' => [
        'taxonomy_id' => ['type' => 'integer', 'nullable' => true],
        'format' => ['type' => 'string', 'enum' => ['number', 'short', 'long'], 'default' => 'short'],
        'singular' => ['type' => 'string', 'default' => 'item'],
        'plural' => ['type' => 'string', 'default' => 'items'],
        'show_icon' => ['type' => 'boolean', 'default' => false],
        'icon' => ['type' => 'string', 'nullable' => true],
    ],
    'supports' => ['spacing', 'typography', 'colors'],
]
```

Features:
- Display number of content items in taxonomy
- Number, short, or long format
- Custom singular/plural labels
- Optional icon

Formats:
```
number: "42"
short: "42 items"
long: "This category contains 42 items"
```

### Taxonomy Query Block

```php
'taxonomy-query' => [
    'schema' => [
        'taxonomy' => ['type' => 'string', 'default' => 'category'],
        'terms' => ['type' => 'array', 'default' => []],
        'operator' => ['type' => 'string', 'enum' => ['AND', 'OR', 'IN'], 'default' => 'IN'],
        'content_type' => ['type' => 'string', 'default' => 'post'],
        'per_page' => ['type' => 'integer', 'default' => 10],
        'order_by' => ['type' => 'string', 'default' => 'date'],
        'order' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
        'template' => ['type' => 'array'], // Template blocks
    ],
    'supports' => ['spacing'],
]
```

Features:
- Query content by taxonomy terms
- Multiple taxonomy support (categories, tags, custom)
- AND/OR operator for multiple terms
- Content type filtering
- Sorting and pagination
- Custom template with content blocks

Use Cases:
```blade
<!-- Show posts in "Technology" category -->
<x-artisanpack-taxonomy-query
    taxonomy="category"
    :terms="[5]"
    per-page="5"
/>

<!-- Show posts with "Laravel" AND "PHP" tags -->
<x-artisanpack-taxonomy-query
    taxonomy="tag"
    :terms="[12, 15]"
    operator="AND"
/>

<!-- Show products in multiple categories -->
<x-artisanpack-taxonomy-query
    taxonomy="product-category"
    :terms="[1, 2, 3]"
    operator="OR"
    content-type="product"
/>
```

Implementation:
```php
public function getContent(): Collection
{
    $query = Content::where('type', $this->content_type)
        ->where('status', 'published');

    // Add taxonomy filter
    if (!empty($this->terms)) {
        $query->whereHas('taxonomies', function ($q) {
            $q->where('taxonomy', $this->taxonomy);

            if ($this->operator === 'AND') {
                // Must have all terms
                foreach ($this->terms as $term) {
                    $q->whereHas('terms', fn($q) => $q->where('id', $term));
                }
            } else {
                // Must have at least one term
                $q->whereHas('terms', fn($q) => $q->whereIn('id', $this->terms));
            }
        });
    }

    return $query->orderBy($this->order_by, $this->order)
        ->paginate($this->per_page);
}
```

## Alternatives Considered

- Use query-loop for all taxonomy queries (rejected: not specialized enough)
- Hardcoded taxonomy displays (rejected: not flexible)
- Custom PHP templates (rejected: not visual editor friendly)

## Use Cases

1. User creates category archive with taxonomy name and description
2. User displays taxonomy post count
3. User queries posts by specific category
4. User creates tag cloud with taxonomy query
5. User shows related content by shared taxonomy
6. User builds category landing page

## Acceptance Criteria

- [ ] Taxonomy name block displays taxonomy name
- [ ] Taxonomy name auto-detects from context
- [ ] Taxonomy name supports manual ID selection
- [ ] Taxonomy description displays description text
- [ ] Taxonomy description supports fallback text
- [ ] Taxonomy count displays item count
- [ ] Taxonomy count formats correctly
- [ ] Taxonomy query filters content by terms
- [ ] Taxonomy query supports AND/OR operators
- [ ] Taxonomy query supports custom templates
- [ ] All blocks integrate with cms-framework

---

**Related Issues:**
- Depends on: Block Registry, artisanpack-ui/cms-framework
- Related: Query Loop (#012), Content Display Blocks (#026)
