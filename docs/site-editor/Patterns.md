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

---

## 6. REST API

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/visual-editor/api/patterns` | List patterns (filter by `?category=...` or `?synced=true`). |
| `POST` | `/visual-editor/api/patterns` | Create a pattern. |
| `GET` | `/visual-editor/api/patterns/{slug}` | Fetch a pattern. |
| `PUT` | `/visual-editor/api/patterns/{slug}` | Update a pattern. |
| `DELETE` | `/visual-editor/api/patterns/{slug}` | Delete a user pattern. |

The slug regex allows `user/<slug>` segments — cms-framework's user-source
patterns are namespaced this way to keep them distinct from theme patterns.

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

The Blade renderer's `<x-ve-blocks>` resolves `core/block` references by
calling the pattern resolver and inlining the content. The React and Vue
renderers fetch via `GET /visual-editor/api/patterns/{slug}` and inline
client-side.

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
