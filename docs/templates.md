# Templates

Templates are full-page block trees that drive front-end rendering. The
package follows WordPress's `wp_template` model: templates are looked up
by **slug** against a hierarchy, the resolver walks the chain from
specific to generic, and each template can include reusable
`core/template-part` blocks.

This page covers the template hierarchy, the resolver, template parts,
and how the renderer turns it all into HTML.

---

## 1. Template hierarchy

A request for a single post (slug `hello-world`, post type `post`)
resolves templates in this order, stopping at the first match:

```text
single-post-hello-world  (slug-specific)
single-post              (post-type-specific)
single                   (single-record default)
index                    (final fallback)
```

The full hierarchy (mirrors WordPress core):

| Context | Slug order |
|---------|------------|
| Single post | `single-{post_type}-{slug}` → `single-{post_type}` → `single` → `index` |
| Page | `page-{slug}` → `page-{id}` → `page` → `singular` → `index` |
| Category archive | `category-{slug}` → `category-{id}` → `category` → `archive` → `index` |
| Tag archive | `tag-{slug}` → `tag-{id}` → `tag` → `archive` → `index` |
| Taxonomy archive | `taxonomy-{taxonomy}-{term}` → `taxonomy-{taxonomy}` → `taxonomy` → `archive` → `index` |
| Author archive | `author-{nicename}` → `author-{id}` → `author` → `archive` → `index` |
| Date archive | `date` → `archive` → `index` |
| Search results | `search` → `index` |
| 404 | `404` → `index` |
| Front page | `front-page` → `home` → `index` |

The host app's route handler picks the context and asks
`TemplateResolver::bySlug()` to walk the chain.

---

## 2. The `TemplateResolver`

`ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver`

```php
$resolver = app(\ArtisanPackUI\VisualEditor\SiteEditor\Resolution\TemplateResolver::class);
$template = $resolver->bySlug('single-post');
```

Returns a `ResolvedTemplate` value object:

```php
ResolvedTemplate {
    public string $slug;
    public string $theme;
    public string $title;
    public string $status;       // 'auto-draft' | 'publish'
    public string $source;       // 'theme' | 'user' | 'custom'
    public array  $content;      // ['raw' => '...', 'blocks' => [...]]
    public bool   $has_theme_file;
    public bool   $is_custom;
    public ?int   $wp_id;        // user override id, if present
}
```

`bySlug()` returns `null` if nothing matches. The host renderer is
expected to walk the hierarchy and pick the first non-null result:

```php
foreach (['single-post-hello-world', 'single-post', 'single', 'index'] as $slug) {
    $template = $resolver->bySlug($slug);
    if ($template !== null) {
        break;
    }
}
```

The Blade renderer's `<x-ve-template :slug="$slug" />` does this internally.

---

## 3. Template parts

Template parts are reusable chunks (header, footer, sidebar) included
into templates via the `core/template-part` block.

### `core/template-part` block

Attributes:

| Attribute | Type | Purpose |
|-----------|------|---------|
| `slug` | string | Template part slug to include (e.g. `header`, `footer`). |
| `theme` | string (optional) | Theme slug; defaults to the active theme. |
| `area` | string (optional) | One of `header`, `footer`, `sidebar`, `uncategorized`. Drives navigator grouping; doesn't affect rendering. |

The block stores no inner blocks — its content is whatever the linked
template part stores at render time. Editing the linked template part
updates every template that includes it.

### Server-side rendering

`TemplatePartResolver::bySlug($slug, $theme = null)` returns a
`ResolvedTemplatePart` value object with the same shape as
`ResolvedTemplate`. The Blade renderer's `<x-ve-blocks>` component
recognizes `core/template-part` nodes and inlines the resolved part's
content.

If the linked part is missing, the renderer emits an empty wrapper rather
than an error — the canvas already warns the author during editing.

---

## 4. Fallback chain

For each slug the resolver checks three sources in order, returning the
first match:

1. **User record** — a row in `cms-framework`'s templates table
   (`source = 'user'`). Always wins.
2. **Static config** — entries in
   `config('artisanpack.visual-editor.site-editor.templates')` keyed by
   slug (`source = 'theme'`).
3. **Theme file** — packaged template files shipped by themes
   (`source = 'theme'`, `has_theme_file = true`).

Authors editing a theme-provided template in the site editor create a
user override — the user record then shadows the theme version. The
"reset to theme default" button in the editor deletes the user record,
restoring the theme version.

---

## 5. REST API

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/visual-editor/api/templates` | List all templates (theme + user). |
| `POST` | `/visual-editor/api/templates` | Create a new user template. |
| `GET` | `/visual-editor/api/templates/{slug}` | Fetch a single template. |
| `PUT` | `/visual-editor/api/templates/{slug}` | Update — creates a user record if one doesn't exist. |
| `DELETE` | `/visual-editor/api/templates/{slug}` | Delete the user record (theme version remains). |
| `GET/POST/GET/PUT/DELETE` | `/visual-editor/api/template-parts[/{slug}]` | Same surface for template parts. |

All endpoints behind the API middleware stack. Slug regex allows path-style
segments (`single/post/hello-world`).

---

## 6. Custom templates

Custom templates (not part of the hierarchy) are templates an author
created from scratch in the site editor with a free-form name. They don't
auto-resolve for any route — the host app opts pages into them
explicitly:

```php
// In your page model
public function getTemplate(): string
{
    return $this->custom_template_slug ?? 'singular';
}
```

```blade
<x-ve-template :slug="$page->getTemplate()" />
```

The site editor's New Template button creates these; they're persisted
with `is_custom = true`.

---

## 7. Editing in the site editor

The Templates section of the site-editor navigator lists every template
the resolver knows about, grouped by source:

- **Theme templates** — defaults shipped by the active theme.
- **Custom templates** — created by authors.
- **User-modified** — theme templates with user overrides.

Clicking a template loads it into the canvas. The save endpoint always
writes a user record; the theme version remains available via
"reset to theme default".

---

## 8. Where templates live

- **Theme files:** loaded by the active theme; no migration required.
- **Static config:** `config('artisanpack.visual-editor.site-editor.templates')` — version-controlled with the app.
- **User overrides:** `cms-framework`'s templates table — written by the site editor.

Picking the right home for each template:

- **Theme file or static config** for templates that ship with the app,
  evolve via deploys, and should reset cleanly.
- **User record** for one-off edits authors make at runtime.

---

## See also

- [Site editor](site-editor.md) — the surface that edits templates
- [Renderers](renderers.md) — render templates on the public site
- [Patterns](patterns.md) — `core/block` references inside templates
- [Global styles](global-styles.md) — theme.json driving template appearance
