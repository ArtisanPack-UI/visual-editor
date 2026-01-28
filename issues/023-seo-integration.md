/label ~"Type::Feature" ~"Status::Backlog" ~"Priority::Medium" ~"Area::Backend" ~"Phase::5"

## Problem Statement

**Is your feature request related to a problem?**
Content needs SEO metadata (titles, descriptions, Open Graph tags) that can be edited within the visual editor interface.

## Proposed Solution

**What would you like to happen?**
Implement SEO panel in the editor with optional integration to dedicated SEO packages:

### SEO Fields on Content

```php
// Built-in SEO fields (in ve_contents table)
$table->string('meta_title')->nullable();
$table->text('meta_description')->nullable();
$table->string('og_image')->nullable();
```

### SEO Settings Panel

- Meta title field with character counter (60 char limit indicator)
- Meta description field with character counter (160 char limit indicator)
- Open Graph image selector (integrates with media library)
- Preview snippet (how it appears in search results)
- Preview social card (how it appears on social media)

### SEO Service

```php
namespace ArtisanPackUI\VisualEditor\Services;

class SEOService
{
    public function getMeta(Content $content): array;
    public function setMeta(Content $content, array $meta): void;
    public function generateMetaDescription(Content $content): string;
    public function getSearchPreview(Content $content): array;
    public function getSocialPreview(Content $content): array;
}
```

### External SEO Package Integration

```php
// Hook for SEO packages to extend functionality
addFilter('ve.seo.fields', function (array $fields, Content $content) {
    // Add canonical URL, robots meta, schema markup, etc.
    return array_merge($fields, [
        'canonical_url' => $content->canonical_url,
        'robots' => $content->robots_meta,
    ]);
});

addFilter('ve.seo.panel', function (string $component) {
    // Replace default SEO panel with package's panel
    return 'seo-package::editor-panel';
});
```

### Auto-generation Features

- Generate meta description from content excerpt
- Generate OG title from page title
- Suggest improvements based on content analysis

## Alternatives Considered

- No built-in SEO (rejected: too important for content sites)
- Full SEO suite (rejected: scope creep, use dedicated package)
- Metadata-only without preview (rejected: poor UX)

## Use Cases

1. Editor sets custom meta title for SEO
2. Editor writes meta description with character guidance
3. Editor selects social share image
4. Editor previews how page appears in search

## Acceptance Criteria

- [ ] SEO panel appears in editor sidebar
- [ ] Meta title can be set with character count
- [ ] Meta description can be set with character count
- [ ] OG image can be selected from media library
- [ ] Search preview shows formatted snippet
- [ ] Social preview shows card appearance
- [ ] Auto-generation works for empty fields
- [ ] External SEO packages can extend functionality

---

**Related Issues:**
- Related: Media Library integration
- Optional: Enhanced by dedicated SEO packages
