# ArtisanPack UI Visual Editor

Description

## Installation

You can install the visual-editor package by running the following composer command.

`composer require artisanpack-ui/visual-editor`

## Version compatibility

The visual-editor and `artisanpack-ui/cms-framework` packages ship as a
version pair — both packages need to be present, and both need to be
on a compatible major version, for the Phase H site-editor integration
to work. Install one without the other and the site-editor's install
gate (#432) surfaces a "cms-framework required" page instead of mounting.

| visual-editor | cms-framework | Notes                                          |
| ------------- | ------------- | ---------------------------------------------- |
| v1.x          | v1.x          | Phase H site-editor integration (this release) |
| v0.x          | v0.x          | Pre-v1 — no site-editor integration            |

Bumping the major on either package without bumping the partner is
unsupported. Both smoke flows run against this version pair before every
release tag:
[`docs/g6-smoke-flow.md`](docs/g6-smoke-flow.md) covers Phase G
(cms-framework content integration — posts, pages, site-meta, query
loop); [`docs/h8-smoke-flow.md`](docs/h8-smoke-flow.md) covers Phase H
(site-editor — templates, parts, patterns, global styles, navigation).

## Peer Dependencies

The editor UI is built on [`@artisanpack-ui/react`](https://www.npmjs.com/package/@artisanpack-ui/react), which is styled with DaisyUI and Tailwind CSS. Host applications embedding the editor must have the following installed and loaded:

- `tailwindcss` `^4.0.0`
- `daisyui` `^5.0.0`

## Usage

You can use any of the visual editor functions like this:

```
Example Here
```

## Gutenberg adoption — transient shims (V1)

The V1 editor adopts the upstream `@wordpress/*` packages. Some of those packages expect a WordPress backend that this package does not provide yet, so we ship **temporary shims** under `resources/js/visual-editor/vendor/`:

- **`core-data-shim.ts`** — aliased in `vite.config.ts` as `@wordpress/core-data`. Registers an empty `core` Redux store via `@wordpress/data` so Gutenberg's selectors (`getEntityRecord`, `getEntityRecords`, `getCurrentUser`, `getUsers`, `getMedia`, etc.) see "no data, done loading" instead of crashing. Blocks that need WordPress-specific data (navigation, query, post-*) render empty; those blocks land in the `disabled_blocks` list in M5.
- **`media-upload-stub.tsx`** — registers `editor.MediaUpload` via `@wordpress/hooks` so the media-library picker on `core/image` is routed through a stub that displays an "M4 placeholder" notice instead of silently no-op'ing.

Both shims will be replaced by `artisanpack-ui/cms-framework` (the real Laravel-backed `core-data` store and media bridge). Every selector or filter we implement here is one we have to re-verify against Gutenberg upgrades — expand the surface only when an observed crash demands it.

## Block defaults

V1 ships with a frozen allow-list of forked blocks under the `artisanpack/*` namespace. The defaults in `config/visual-editor.php` expose every block that landed during the Phase I block fork (plan 13) — `@wordpress/block-library`'s `registerCoreBlocks()` is no longer called, and the editor registers only the in-package forks discovered under `resources/js/visual-editor/blocks/`. The `core/*` → `artisanpack/*` mapping table in [`docs/block-library-audit.md`](docs/block-library-audit.md) is the source of truth for which forks exist.

The forked allow-list covers the content, media, layout, widget, entity, and loop/feed clusters. The entity blocks (`artisanpack/post-*`, `artisanpack/site-*`, `artisanpack/template-part`, `artisanpack/navigation`) and the loop / feed cluster (`artisanpack/query`, `artisanpack/post-template`, `artisanpack/archives`, `artisanpack/categories`, `artisanpack/tag-cloud`) need an entity in scope to render meaningful content — pair the editor with [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework) (see [Using with cms-framework](#using-with-cms-framework)) and they resolve against Posts / Pages / templates / site settings end-to-end. Standalone, they fall back to empty shells rather than crashing.

`disabled_blocks` is empty by default: with the I7 cutover (#415) the editor no longer registers any `core/*` block, so there is nothing to deny-list. New `@wordpress/block-library` releases similarly bring no new registrations into this package — additions land only when a fork is added to the in-package blocks directory and to the allow-list. `from:core/*` transforms still ship on each fork so existing `core/*` markup pasted from upstream converts on insert.

Override the defaults by publishing the config to `config/artisanpack/visual-editor.php` and editing the `enabled_blocks` / `disabled_blocks` arrays. The deny-list always wins over the allow-list.

## Using with cms-framework

The visual editor is fully usable standalone, but pairs with [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework) to unlock editable `Post` and `Page` content, a real backing for `core/site-*` blocks, working `core/post-*` / `core/query` / taxonomy widget blocks, and seeded `visual_editor.*` permissions. The full integration contract lives in [`docs/plans/12-cms-framework-integration.md`](docs/plans/12-cms-framework-integration.md).

### Install both packages

```bash
composer require artisanpack-ui/visual-editor artisanpack-ui/cms-framework
```

Both packages are loosely coupled — cms-framework's editor wiring is guarded by `class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)`, so each package remains usable on its own.

### Run migrations

```bash
php artisan migrate
```

cms-framework's V1.x migration set adds a `block_content json nullable` column to its `posts` and `pages` tables (the legacy `content` longText column is preserved for search / excerpt / backwards-compatibility — see plan 12 §4.2 for the dual-state guidance).

### Resource map

When both packages are installed, cms-framework registers its `Post` and `Page` into the `ap.visual-editor.resources` filter automatically. The merged map ends up shaped like:

```php
// Effective config('artisanpack.visual-editor.resources') after the
// ap.visual-editor.resources filter has run with both packages installed.
[
    'posts' => \ArtisanPackUI\CMSFramework\Modules\Blog\Models\Post::class,
    'pages' => \ArtisanPackUI\CMSFramework\Modules\Pages\Models\Page::class,
    // …plus any slug → model entries the host app added to
    //   config/artisanpack/visual-editor.php (static config wins on collision).
]
```

Host-app overrides in `config/artisanpack/visual-editor.php` always take precedence on key collision, so swapping `posts` to a custom `App\Models\Post` is just a config edit.

### Version pairing

visual-editor V1.0.x ships against cms-framework V1.x. The compatibility matrix is in [Version compatibility](#version-compatibility) above; the [`docs/g6-smoke-flow.md`](docs/g6-smoke-flow.md) and [`docs/h8-smoke-flow.md`](docs/h8-smoke-flow.md) flows run against the version pair before every release tag.

## Extensibility

The package exposes a small filter surface so other packages can contribute editor wiring at runtime without forcing host apps to publish-and-edit the package config.

### `ap.visual-editor.resources`

Register slug → Eloquent model class mappings used by `/visual-editor/api/{resource}/{id}/content`. Filter contributions are merged with `config('artisanpack.visual-editor.resources')`; the static config wins on key collision so host-app overrides always take precedence. Models must use `ArtisanPackUI\VisualEditor\Concerns\HasBlockContent` — invalid entries surface as `InvalidArgumentException` on first request rather than at boot, so a contributor's standalone install never trips host boot.

```php
addFilter( 'ap.visual-editor.resources', function ( array $resources ): array {
    return array_merge( [
        'posts' => App\Models\Post::class,
    ], $resources );
} );
```

Full contract (input/output shape, collision behavior, validation guarantees, contributor timing): [`docs/plans/12-cms-framework-integration.md`](docs/plans/12-cms-framework-integration.md) §4.1.

## i18n

Editor strings use `@wordpress/i18n` with the `artisanpack-visual-editor` text domain. The domain is initialized via `bootI18n()` in `resources/js/visual-editor/vendor/i18n.ts`.

Regenerate the placeholder `.pot` catalog with:

```bash
npm run i18n:extract
```

The extractor scans `resources/js/visual-editor/**/*.{ts,tsx}` for `__/_x/_n/_nx` calls bound to the text domain and writes `languages/artisanpack-visual-editor.pot`. It is deliberately minimal — a richer extractor (template strings, plural forms, PHP scanning) replaces it post-V1.

## Contributing

As an open source project, this package is open to contributions from anyone. Please [read through the contributing
guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
