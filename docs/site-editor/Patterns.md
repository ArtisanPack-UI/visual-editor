---
title: Patterns
---

# Patterns

Patterns are reusable block snippets that authors drop into post or
template content. They come in two flavours — **synced** (referenced by
id; edits propagate everywhere the pattern is used) and **unsynced**
(inlined at insert time; later edits don't propagate).

This page covers the two flavours, authoring patterns in the site
editor, the library + inserter integration, and the REST surface.

---

## 1. Synced vs unsynced

| Aspect | Synced | Unsynced |
|--------|--------|----------|
| Storage | Pattern store (`wp_block` shape) — one canonical record. | Inlined into the host's block tree at insert time. |
| Reference | `core/block` block with `{ ref: <id> }`. | Inlined raw blocks. |
| Editing | Edit once, updates everywhere. | Edit per-page; original pattern untouched. |
| Use case | Site-wide hero, repeating CTA, footer disclaimer. | Layout starter, "boilerplate" insertable, theme starter content. |

Synced patterns are persistent first-class entities with a slug, title,
content, and lifecycle. Unsynced patterns are template fragments — they
live in the pattern library but produce inlined blocks when inserted.

The flavour is set at authoring time: every pattern record has a
`synced: bool` field that decides which behaviour applies on insert.

---

## 2. Authoring patterns

The Patterns section of the site editor's navigator lists every pattern
the system knows about, grouped by source (theme / user) and category.

### Create

The "New pattern" button prompts for:

- Title
- Slug (auto-generated from title; editable)
- Synced toggle
- Category (any string; auto-suggests from existing categories)
- Block types (optional — restrict which blocks the pattern can be
  inserted near; matches Gutenberg's `blockTypes` filter)

The pattern then opens in the canvas — author it like any other block
tree and save.

### Edit

Click a pattern in the navigator to load it into the canvas. For synced
patterns, edits update every page that references the pattern on next
render. For unsynced patterns, edits only affect future inserts —
existing inline copies stay as-is.

### Delete

Delete from the navigator's overflow menu. For synced patterns this
breaks every reference (the renderer emits an empty placeholder).
Delete with care; the editor surfaces a "this pattern is referenced N
times" warning before confirming.

---

## 3. The pattern library and inserter

Patterns appear in two places in the editor:

- **Inserter sidebar** — Patterns tab, grouped by category, searchable.
  Drag a pattern into the canvas to insert.
- **Site-editor navigator** — Patterns section, listing all patterns for
  authoring.

Filtering: the inserter filters by category and by the pattern's
`blockTypes` restriction. A pattern with `blockTypes: ['core/group']`
only appears when a group is selected.

### Pattern previews

*Since v1.13.0 (#832).* Pattern cards show a scaled, non-interactive
render of the pattern as it looks on the front end. Previews appear in:

- the inserter's **Patterns** panel,
- the site editor's pattern grid,
- the page-pattern modal (the "choose a pattern" prompt for new pages).

**How a preview is built.** The server renders the pattern with the
Blade renderer (the same output `<x-ve-blocks>` produces) and returns
the HTML plus one shared stylesheet bundle for the batch: the
`<x-ve-blocks-styles>` output for the active theme, global styles, the
installed font faces and the theme's `style.css`. The editor drops each
pattern into an `<iframe srcdoc>` with `sandbox="allow-same-origin"` and
no `allow-scripts`, so block JavaScript (carousels, animations) never
runs in a preview. The frame is `aria-hidden`, `inert` and out of the tab
order, so the card's own button stays the only focus target. Relative
`url()` references in the theme's `style.css` are rewritten to absolute
theme-asset URLs, because a `srcdoc` document's base URL is the editor
page.

**Viewport width.** Each pattern is laid out at its viewport width and
then scaled down to the card's width, with the height capped at 0.75 ×
the card's width. The width comes from the pattern's `viewport_width`
(the theme pattern file's `Viewport Width` header, or the
`viewport_width` key of a pattern registered through config or the
`ap.visualEditor.patterns` filter). It is clamped to 320–2560 px, and a
missing or invalid value uses 1200 px, WordPress's default.

**Loading.** Nothing is fetched until a card scrolls within 200 px of
its scroll container. Cards that come into view together share one
batched request of up to 24 patterns. Iframes mount a few per frame, and
a card scrolled far out of view unmounts its iframe (keeping its height)
until it scrolls back. While a preview loads, or when it fails, the card
shows the block-name outline instead. After a 403 or a
`renderer_unavailable` result the editor stops requesting previews for
the page, and after a 429 it pauses briefly.

**Caching.** Rendered HTML is cached per pattern and per:

- the pattern's renderable content (its markup and block tree),
- the active theme,
- the viewer (user class and id), the locale and the request host,
- the global-styles version and the installed package version,
- anything a host adds through the `ap.visualEditor.patternPreviewCacheVary`
  filter.

The whole preview cache is cleared when a pattern is updated or deleted,
when a template part, menu or menu item is created, updated or deleted
through the site-editor API, and when global styles are saved, since a
pattern can embed any of those by reference. Changes made outside those
endpoints (posts behind a Query loop, a direct model write) appear once
entries expire after `pattern_previews.cache_ttl` seconds (default 3600;
see [Configuration](../Configuration.md#pattern_previews)). Failed renders
are never cached.

Add your own vary data when previews depend on something the key
doesn't cover, such as a role or a tenant. The filter receives an empty
array and should return an array of scalars:

```php
addFilter( 'ap.visualEditor.patternPreviewCacheVary', function ( array $vary ): array {
    $vary['tenant'] = tenant()?->id;

    return $vary;
} );
```

**Access and throttling.** The endpoint requires an authenticated user
who passes the `visual-editor.edit-content` ability (see
[Access Gate](Access-Gate.md#content-authoring-ability-visual-editoredit-content))
and is throttled at 120 requests per minute per user, in its own
rate-limit bucket.

---

## 4. The `core/block` block (pattern reference)

Synced patterns render via the `core/block` block:

```json
{
    "name": "core/block",
    "attributes": { "ref": 42 }
}
```

Attributes:

| Attribute | Type | Purpose |
|-----------|------|---------|
| `ref` | number | The pattern's id in the pattern store. |

On render, the resolver looks up the pattern record by id and inlines
its content. Missing references emit an empty placeholder.

### `core/block` in the site editor

*Since v1.12.1 (#824).* Synced-pattern references stay `core/block` in
the site editor. The editor registers the upstream `core/block` block
type (alongside the `core/navigation` family) — it is one of the core
blocks that is not forked to `artisanpack/*` — and the site editor's
template loader no longer rewrites theme `wp:block` references to
`artisanpack/block`.

Templates saved by earlier versions can still contain `artisanpack/block`
references. Those are translated back to `core/block` when the template
is read, in both the parsed block tree and `content.raw`, so the stored
rows heal on the next save without a data migration. The front-end
renderers treat `artisanpack/block` as a pattern reference too
(`BlockShape::PATTERN_REF_NAMES`, #822), so such a template renders
correctly even before it is re-saved.

Unsynced patterns produce no `core/block` block — they paste raw blocks
into the host tree at insert time. From the renderer's perspective an
unsynced pattern is invisible after insertion.

---

## 5. Theme-provided patterns

Themes can ship patterns alongside templates by declaring them in config:

```php
// config/artisanpack/visual-editor.php
'site-editor' => [
    'patterns' => [
        'hero' => [
            'title'      => 'Hero',
            'categories' => ['layout'],
            'content'    => '<!-- wp:cover {...} --><!-- /wp:cover -->',
        ],
    ],
],
```

Static patterns are merged with DB-stored user patterns. They show up in
the inserter alongside user-created patterns and get a "theme" badge in
the navigator. Editing a theme pattern in the site editor creates a user
override (same fallback-chain pattern as templates).

### Page-pattern modal and post types

The page-pattern modal (the "choose a pattern" prompt for a new record)
only lists patterns whose `post_types` array contains the current
document's post type. Patterns without `post_types` are treated as
section snippets and stay in the sidebar inserter only.

By default the modal knows two post types: the `pages` resource maps to
`page` and `posts` maps to `post`. For any other content type, the modal
stays off unless the host names the post type when mounting the editor.
*Since v1.13.0.*

- Blade: pass `pattern-post-type` to the component:

  ```blade
  <x-visual-editor :model="$package" pattern-post-type="package" />
  ```

- Custom mounts: set `data-pattern-post-type="package"` on the
  `[data-ap-visual-editor]` element.

The value is trimmed and lowercased, the same way the server normalizes
a pattern's `post_types`. It only changes which patterns the modal
fetches (`GET patterns?post_type=package`); it doesn't register the type
with core-data or change how the record saves. Scope your full-page
patterns to the type through the `ap.visualEditor.patterns` filter, for
example `'post_types' => [ 'package' ]`.

---

## 6. REST API

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/visual-editor/api/patterns` | List patterns (filter by `?category=...`, `?synced=true` or `?post_type=...`). |
| `POST` | `/visual-editor/api/patterns` | Create a pattern. |
| `GET` | `/visual-editor/api/patterns/{slug}` | Fetch a pattern. |
| `PUT` | `/visual-editor/api/patterns/{slug}` | Update a pattern. |
| `DELETE` | `/visual-editor/api/patterns/{slug}` | Delete a user pattern. |
| `POST` | `/visual-editor/api/patterns/preview` | Render a batch of up to 24 patterns for card previews. Body: `{ "patterns": ["<id or slug>", …] }`. Returns `{ "styles": "…", "patterns": { "<id>": { "html": "…" } \| { "error": "not_found" \| "render_failed" \| "renderer_unavailable" } } }`. Requires `visual-editor.edit-content`; throttled at 120/min. *Since v1.13.0.* |

The slug regex allows `user/<slug>` segments — cms-framework's user-source
patterns are namespaced this way to keep them distinct from theme patterns.

The preview endpoint takes ids as the pattern API returns them: the
numeric id for user patterns and the slug for theme patterns. It renders
existing patterns only and never accepts markup.

---

## 7. Categories

Pattern categories are free-form strings. Common conventions:

- `featured` — patterns surfaced first in the inserter.
- `layout` — full-page or full-section starters.
- `text` — text-heavy snippets (testimonial, quote, CTA).
- `media` — media-heavy snippets (hero, gallery, video).
- `header` / `footer` — chrome patterns.

The inserter groups patterns by category and shows the most-used
category first.

---

## 8. Rendering on the public site

The Blade renderer's `<x-ve-blocks>` resolves `core/block` (and legacy
`artisanpack/block`) references through `PatternInliner` and inlines the
content. The React and Vue renderers export `inlinePatterns(tree, { patterns })`,
which splices pre-fetched pattern records (for example from
`GET /visual-editor/api/patterns`) into the tree client-side. In all three
renderers the resolved reference is output as `core/block`. Cycles and
chains deeper than 10 levels render as an empty block in production and a
visible warning in development.

*Since v1.13.0.* In Blade renders, a reference to a pattern stored by
cms-framework resolves from the pattern's `block_content` tree. The
legacy `{ raw, blocks }` envelope under `content` is still read as a
fallback for older rows.

Performance: synced patterns are cached per request — a page that
references the same pattern five times only fetches once. For long-term
caching across requests, wrap the pattern resolver in a Laravel cache or
decorate it.

---

## 9. Pattern locking

Patterns can lock their contained blocks against editing — useful for
"do not modify" boilerplate:

```json
{
    "name": "core/group",
    "attributes": {
        "templateLock": "all"     // "all" | "insert" | "contentOnly" | false
    },
    "innerBlocks": [ /* ... */ ]
}
```

`all` — no move, no insert, no remove. `insert` — no insert/remove but
can rearrange. `contentOnly` — only edit text/media within blocks, not
structure. See WordPress's [block locking documentation](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-templates/#locking)
for the full contract.

---

## See also

- [Site editor](../site-editor.md) — the surface that edits patterns
- [Templates](Templates.md) — patterns inside templates
- [Renderers](../renderers.md) — rendering `core/block` references
