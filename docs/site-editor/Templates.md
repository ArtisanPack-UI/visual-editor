---
title: Templates
---

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

**Block name in saved templates.** Theme template files reference parts
as `core/template-part`. When the site editor loads a template it forks
core block names to their `artisanpack/*` equivalents, so a template
saved from the site editor stores the reference as
`artisanpack/template-part`. Both names are treated as template-part
references everywhere (`BlockShape::TEMPLATE_PART_NAMES`).

### Server-side rendering

`TemplatePartResolver::bySlug($slug, $theme = null)` returns a
`ResolvedTemplatePart` value object with the same shape as
`ResolvedTemplate`. The Blade renderer's `<x-ve-blocks>` component
(through `TemplatePartInliner`) recognizes both `core/template-part` and
`artisanpack/template-part` nodes and inlines the resolved part's
content. The React and Vue inliners do the same. The resolved block keeps
the name it was saved with, and any `innerBlocks` snapshot saved with the
reference is replaced by the live part, so edits to the part always show.

*Since v1.12.1 (#822).* Earlier versions only expanded
`core/template-part`, so parts in a template saved from the site editor
rendered as empty wrappers on the front end.

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

All endpoints behind the API middleware stack. `{slug}` is a slug or row id
(`[A-Za-z0-9_-]+`), or a `<theme>~<digits>` composite id; it can't contain
`/`.

### Slugs and ids

`{slug}` takes either a slug or a row id, because the API returns a template
with a database row under its numeric id. An all-digit value such as `404`
could be either, so it's resolved in this order:

- **`GET`** looks in the active theme only. A template whose row has that id
  wins, then a template with that slug.
- **`PUT`** targets the body's `theme`, or the active theme. A row with that
  id in that theme wins, then a template with that slug, which is created as
  an override if it has no row yet. A row with that id in another theme is
  only updated when the body has no `theme`; otherwise the response is a 404.
- **`DELETE` without `?theme=`** works like `PUT` in the active theme: a row
  with that id in the active theme, then an override with that slug, then a
  row with that id in any theme.
- **`DELETE` with `?theme=`** treats the value as a slug first, because only
  clients that work by slug send a theme. The override with that slug in that
  theme wins, then a row with that id in that theme. A row in a different
  theme isn't deleted: the response is a 404.

A template or part that exists only as a theme file and whose slug is all
digits gets the id `<theme>~<slug>`, for example `my-theme~404`, so it can't
match another record's row id. `GET`, `PUT` and `DELETE` accept it. A `GET`
only finds it in the active theme, and a `PUT` or `DELETE` whose `theme`
names a different theme than the id returns a 422 with a `theme` error.
`GET /visual-editor/api/templates?theme=<theme>&slug=<slug>` finds the same
record by its parts.

A `PUT` that creates a row for a theme other than the active one starts from
the request alone: the active theme's file for that slug isn't copied into
it. Without a `title` in the body, that `PUT` returns a 422 with a `title`
error.

`?theme=` must be a single string. A value such as `?theme[]=x` returns a
422 with a `theme` error.

### Reverting an override

`DELETE /visual-editor/api/templates/{slug}` and
`DELETE /visual-editor/api/template-parts/{slug}` delete the database
override and leave the theme version in place. A slug URL needs a theme:
pass `?theme=`, or the active theme is used, and with neither the response is
a 422. A row-id URL needs no theme.

| Status | Meaning |
|--------|---------|
| `204` | The override was deleted. |
| `404` | No override matches. |
| `409` | A `deleting` listener on the model returned `false`, so the row was kept. |
| `422` | A slug URL had no theme to match, or `?theme=` wasn't a string. |

Both URL forms, by slug and by row id, delete through the model, so the
model's `deleting` and `deleted` events fire. After a delete they also fire
the `ap.cmsFramework.siteEditor.reverted` action, which a 409 doesn't. The
action reports the row's own theme. A delete by row id can remove an
override in a theme that isn't active, so this theme can differ from the
active one. See
[Hooks and Events](../Hooks-and-Events.md#apcmsframeworksiteeditorreverted).

```php
addAction('ap.cmsFramework.siteEditor.reverted', function (string $type, ?string $slug, string $theme): void {
    // $type is 'template' or 'template-part' for these endpoints.
    // Purge anything cached for $theme here.
}, 10, 3);
```

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

### Previewing real content

*Since v1.15.3 (#993)*, templates that render a single entry preview
their Post Title, Post Excerpt and Post Content blocks with a real
entry instead of placeholders. The template slug picks the post type:

| Template slug | Previews |
|---------------|----------|
| `single`, `singular`, `single-post` | The latest post |
| `single-post-{slug}` | The post with that slug, else the latest post |
| `single-{type}` | The latest entry of the custom content type `{type}` |
| `single-{type}-{slug}` | That entry of `{type}`, when `{type}` is a registered content type |
| `page` | The latest page |
| `page-{slug}` | The page with that slug, else the latest page |

A **Preview content** picker switches to another
entry. The previewed values are read-only; edit the entry itself in the
post editor. Other templates (`index`, `archive`, `home`, `404`, custom
templates) and types with no published entry keep the placeholders. A
`page-{slug}` template finds its page only among the 20 newest pages.

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

- [Site editor](../site-editor.md) — the surface that edits templates
- [Renderers](../renderers.md) — render templates on the public site
- [Patterns](Patterns.md) — `core/block` references inside templates
- [Global styles](Global-Styles.md) — theme.json driving template appearance
