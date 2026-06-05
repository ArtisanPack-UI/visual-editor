# Migration

Migrating content **from WordPress into the visual editor** is handled
by a companion package, not by `visual-editor` itself. This page
explains why, what the companion does, and what the in-package
migrations cover.

---

## 1. WordPress import — companion package

**Package:** `artisanpack-ui/visual-editor-wp-import`
**Status:** Planned. Ships separately on its own release cadence;
first version targets after V1.0.0 of `visual-editor`.

The companion handles the one-way migration from a WordPress export
(WXR or direct database connection) into your Laravel app:

- Maps WP `posts`, `pages`, `wp_template`, `wp_template_part`,
  `wp_navigation`, `wp_block` (patterns), and `wp_global_styles` into
  the corresponding Laravel models.
- Rewrites `core/*` block names to `artisanpack/*` using the same
  transform table the block forks ship (`from:core/*` in each block.json).
- Resolves attachment URLs to media library records.
- Resolves user references to local user records by email or username.
- Optionally preserves WP post ids, slugs, dates, and meta.

Reasons it lives in its own package:

- Importer dependencies (WP XML parsers, HTTP clients for remote media,
  optional MySQL drivers) are heavy and only needed once.
- Import is a one-off operation — bundling it into the runtime package
  bloats every install.
- Importer release cycle is faster than the editor's; a bug in the
  importer shouldn't force a `visual-editor` patch release.

When the companion is published, this page will link to its own
documentation. Until then, manual migration is the path: export WP
content, transform locally, seed via Laravel.

---

## 2. In-package migrations

`visual-editor` ships these migrations under `database/migrations/`:

| Migration | Purpose |
|-----------|---------|
| `2026_04_14_000000_create_ve_contents_table.php` | Creates the `ve_contents` fallback table used by the legacy `VisualEditorPost` model and the `/editor` test route. Host-app resource models don't touch this table. |
| `2026_05_15_000000_drop_legacy_visual_editor_tables.php` | Drops the Phase D legacy tables (`visual_editor_templates`, `_template_parts`, `_global_styles`, `_navigations`, `_patterns`, `_pattern_categories`, and the pivot). Idempotent — safe on fresh installs. |

cms-framework provides the actual storage tables for templates, parts,
patterns, global styles, and menus. Install cms-framework and run
`php artisan migrate` and the site-editor REST surface is backed by real
tables.

---

## 3. Upgrading existing host-app models

Adding `HasBlockContent` to an existing model requires a column:

```php
Schema::table('posts', function (Blueprint $table) {
    $table->json('content')->nullable();
});
```

Set `$blockContentColumn` on the model if the column name is something
other than `content`.

For models that already store HTML or Markdown in a different column,
keep both: the legacy column remains the source of truth until you've
backfilled the block-content column from it, then deprecate the
legacy column. cms-framework follows this pattern — its `posts` and
`pages` tables retain a `content longText` column alongside the new
`block_content json` column. See plan-12 §4.2.

---

## 4. Upgrading from `v1.0.0-alpha.1`

If you installed `v1.0.0-alpha.1` (the Gutenberg-adoption marker), the
upgrade path to `v1.0.0` is:

1. `composer update artisanpack-ui/visual-editor`.
2. `php artisan migrate` to apply the legacy-table drop.
3. `npm install` then `npm run build` to pick up the new editor bundle.
4. Review the [CHANGELOG](../CHANGELOG.md) for breaking changes —
   notable: all `core/*` blocks are now `artisanpack/*` via the
   Phase I block fork; pasted upstream `core/*` markup auto-converts on
   insert via the `from:core/*` transforms shipped on every fork.

Existing post content saved against `v1.0.0-alpha.1`'s `core/*` blocks
re-renders correctly — the renderers recognize both namespaces during
V1. From V2 onwards, only `artisanpack/*` is registered for new content;
old `core/*` content continues to render via the transform table.

---

## 5. Upgrading from a future version

`visual-editor` follows semver:

- Patch releases (`v1.0.x`) — bug fixes, no API changes.
- Minor releases (`v1.x.0`) — new features, backwards-compatible.
- Major releases (`v2.0.0`) — breaking changes, documented migration
  path in that release's CHANGELOG.

Each major release ships a `docs/upgrade-{from}-to-{to}.md` doc
covering the breaking changes and required migration steps.

---

## 6. Manual content migration

If `visual-editor-wp-import` isn't an option (custom CMS source,
hand-crafted content), the manual path is:

1. **Export source content** — get the source HTML/JSON into a
   transformable shape.
2. **Map to block trees** — write a transformer that produces
   Gutenberg-shape block JSON:

   ```php
   [
       [
           'name'         => 'artisanpack/paragraph',
           'attributes'   => ['content' => $htmlParagraph],
           'innerBlocks'  => [],
       ],
       // …
   ];
   ```

3. **Seed via Laravel** — set the block tree on each model via
   `$model->setBlockContent($blocks)` and save.

The block content column is plain JSON; you can backfill via a
database seeder, an artisan command, or a one-off migration script.

---

## See also

- [Quick Start](Quick-Start.md)
- [Content model](content-model.md) — `HasBlockContent`, resource map
- [`visual-editor-wp-import` repo](https://github.com/ArtisanPack-UI/visual-editor-wp-import) (when published)
