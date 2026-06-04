# ArtisanPack UI Visual Editor

A Gutenberg-powered visual editor for Laravel applications. Brings the
WordPress block editor to any Eloquent model, plus a full site editor
for templates, template parts, global styles, navigation menus, and
patterns.

V1.0 ships:

- **Post editor** — block-based content editing on any model that opts
  in via the `HasBlockContent` trait.
- **Site editor** — templates, template parts, theme.json-backed global
  styles, navigation menus, and patterns; mounted at `/visual-editor/site`.
- **42 forked core blocks** under the `artisanpack/*` namespace.
  `core/*` markup pasted from upstream auto-converts on insert.
- **Three renderer packages** — Blade (server-side), React, and Vue —
  for rendering saved block content on the public site.
- **First-class pairing** with [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework)
  for Posts, Pages, site-meta, navigation, and global-styles persistence.
  Both packages remain usable standalone.

Full documentation: see [`docs/`](docs/). Start with [Getting started](docs/getting-started.md).

---

## Installation

```bash
composer require artisanpack-ui/visual-editor
php artisan migrate
```

For the site editor (recommended for most apps):

```bash
composer require artisanpack-ui/cms-framework
php artisan migrate
```

See [Getting started](docs/getting-started.md) for the full setup.

---

## Version compatibility

The visual-editor and `artisanpack-ui/cms-framework` packages ship as a
version pair — both packages need to be present, and both need to be on
a compatible major version, for the site-editor integration to work.
Install one without the other and the site-editor's install gate (#432)
surfaces a "cms-framework required" page instead of mounting.

| visual-editor | cms-framework | Notes                                          |
| ------------- | ------------- | ---------------------------------------------- |
| v1.x          | v1.x          | Site-editor integration (this release)         |
| v0.x          | v0.x          | Pre-v1 — no site-editor integration            |

Bumping the major on either package without bumping the partner is
unsupported. Both smoke flows run against this version pair before every
release tag: [`docs/g6-smoke-flow.md`](docs/g6-smoke-flow.md) covers
Phase G (cms-framework content integration — posts, pages, site-meta,
query loop); [`docs/h8-smoke-flow.md`](docs/h8-smoke-flow.md) covers
Phase H (site-editor — templates, parts, patterns, global styles,
navigation).

---

## Peer dependencies

The editor UI is built on [`@artisanpack-ui/react`](https://www.npmjs.com/package/@artisanpack-ui/react),
which is styled with DaisyUI and Tailwind CSS. Host applications
embedding the editor must have the following installed and loaded:

- `tailwindcss` `^4.0.0`
- `daisyui` `^5.0.0`

---

## Usage

Add the `HasBlockContent` trait to any model and register it in
`config/artisanpack/visual-editor.php`:

```php
use ArtisanPackUI\VisualEditor\Concerns\HasBlockContent;

class Post extends Model
{
    use HasBlockContent;
}
```

```php
// config/artisanpack/visual-editor.php
return [
    'resources' => [
        'posts' => App\Models\Post::class,
    ],
];
```

Mount the editor in a Blade view:

```blade
<x-visual-editor :model="$post" />
```

The site editor mounts automatically at `/visual-editor/site` when
cms-framework is installed and the configured `SiteEditorAccessGate`
permits the request.

See [`docs/blade-component.md`](docs/blade-component.md) for the full
component reference and [`docs/site-editor.md`](docs/site-editor.md) for
the site-editor surface.

---

## Documentation

| Topic | Doc |
|-------|-----|
| Install and first post | [`docs/getting-started.md`](docs/getting-started.md) |
| `HasBlockContent` + resource map | [`docs/content-model.md`](docs/content-model.md) |
| `<x-visual-editor />` reference | [`docs/blade-component.md`](docs/blade-component.md) |
| Post editor surface tour | [`docs/post-editor.md`](docs/post-editor.md) |
| Authoring custom blocks | [`docs/custom-blocks.md`](docs/custom-blocks.md) |
| Blade / React / Vue renderers | [`docs/renderers.md`](docs/renderers.md) |
| Site editor surface tour | [`docs/site-editor.md`](docs/site-editor.md) |
| Template hierarchy + parts | [`docs/templates.md`](docs/templates.md) |
| theme.json-backed global styles | [`docs/global-styles.md`](docs/global-styles.md) |
| Menus, locations, fallbacks | [`docs/navigation.md`](docs/navigation.md) |
| Synced vs unsynced patterns | [`docs/patterns.md`](docs/patterns.md) |
| Embedding in Livewire | [`docs/livewire.md`](docs/livewire.md) |
| Embedding in Inertia (React/Vue) | [`docs/inertia.md`](docs/inertia.md) |
| Theming editor chrome | [`docs/theming.md`](docs/theming.md) |
| Common problems | [`docs/troubleshooting.md`](docs/troubleshooting.md) |
| Migration & WP import | [`docs/migration.md`](docs/migration.md) |

---

## Gutenberg adoption — transient shims

The V1 editor adopts the upstream `@wordpress/*` packages. Some of those
packages expect a WordPress backend that this package doesn't provide,
so we ship temporary shims under `resources/js/visual-editor/vendor/`:

- **`core-data-shim.ts`** — aliased in `vite.config.ts` as
  `@wordpress/core-data`. Provides the entity registrations and
  selectors Gutenberg expects (`getEntityRecord`, `getEntityRecords`,
  resolvers, permissions stubs). Templates, template parts, navigation,
  patterns, global styles, attachments, and site-meta are wired through
  to the package's REST surface.
- **`media-upload-stub.tsx`** — registers `editor.MediaUpload` via
  `@wordpress/hooks` so the media-library picker on `core/image` is
  routed through a stub or the real
  `registerArtisanpackMediaBridge(MediaModal, uploadMedia)` bridge.

Both shims will be replaced over the V1.x release line as the cms-framework
side surfaces real backings. Every selector or filter implemented here is
one to re-verify against Gutenberg upgrades. See
[`docs/core-data-shim.md`](docs/core-data-shim.md) and
[`docs/troubleshooting.md`](docs/troubleshooting.md#2-core-data-shim-entities-and-missing-data).

---

## Block defaults

V1 ships a frozen allow-list of forked blocks under the `artisanpack/*`
namespace. The defaults in `config/artisanpack/visual-editor.php` expose every block
that landed during the Phase I block fork — `@wordpress/block-library`'s
`registerCoreBlocks()` is no longer called, and the editor registers
only the in-package forks discovered under
`resources/js/visual-editor/blocks/`. The `core/*` → `artisanpack/*`
mapping table in [`docs/block-library-audit.md`](docs/block-library-audit.md)
is the source of truth for which forks exist.

The forked allow-list covers the content, media, layout, widget, entity,
loop/feed, comments, query/pagination, and authentication clusters.
Entity blocks (`artisanpack/post-*`, `artisanpack/site-*`,
`artisanpack/template-part`, `artisanpack/navigation`) and the loop /
feed cluster (`artisanpack/query`, `artisanpack/post-template`,
`artisanpack/archives`, `artisanpack/categories`,
`artisanpack/tag-cloud`) need an entity in scope to render meaningful
content — pair the editor with
[`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework)
and they resolve against Posts / Pages / templates / site settings
end-to-end. Standalone, they fall back to empty shells rather than
crashing.

`disabled_blocks` is empty by default: with the I7 cutover (#415) the
editor no longer registers any `core/*` block, so there's nothing to
deny-list. New `@wordpress/block-library` releases similarly bring no
new registrations into this package — additions land only when a fork
is added to the in-package blocks directory and to the allow-list.
`from:core/*` transforms ship on each fork so existing `core/*` markup
pasted from upstream converts on insert.

Override the defaults by publishing the config to
`config/artisanpack/visual-editor.php` and editing the `enabled_blocks`
/ `disabled_blocks` arrays. The deny-list always wins over the allow-list.

---

## Using with cms-framework

The visual editor is fully usable standalone, but pairs with
[`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework)
to unlock:

- Editable `Post` and `Page` content out of the box.
- A real backing for `core/site-*` blocks (site title, tagline, logo, icon).
- Working `core/post-*`, `core/query`, taxonomy widget, and navigation
  blocks.
- Templates, template parts, patterns, global styles, and menus
  persisted via cms-framework's models.
- Seeded `visual_editor.*` permissions.

```bash
composer require artisanpack-ui/visual-editor artisanpack-ui/cms-framework
php artisan migrate
```

Both packages are loosely coupled — cms-framework's editor wiring is
guarded by `class_exists(\ArtisanPackUI\VisualEditor\VisualEditor::class)`,
so each remains usable on its own. The full integration contract lives in
[`docs/plans/12-cms-framework-integration.md`](docs/plans/12-cms-framework-integration.md).

---

## Extensibility

### `ap.visual-editor.resources`

Register slug → Eloquent model class mappings used by
`/visual-editor/api/{resource}/{id}/content`. Filter contributions are
merged with `config('artisanpack.visual-editor.resources')`; the static
config wins on key collision so host-app overrides always take
precedence. Models must use
`ArtisanPackUI\VisualEditor\Concerns\HasBlockContent` — invalid entries
surface as `InvalidArgumentException` on first request rather than at
boot, so a contributor's standalone install never trips host boot.

```php
addFilter('ap.visual-editor.resources', function (array $resources): array {
    return array_merge([
        'posts' => App\Models\Post::class,
    ], $resources);
});
```

Full contract:
[`docs/plans/12-cms-framework-integration.md`](docs/plans/12-cms-framework-integration.md) §4.1.

### `ap.icons.register-icon-sets`

Editor chrome icons resolve through `artisanpack-ui/icons`. Register
additional icon sets in a service provider — see the
[`artisanpack-ui/icons`](https://github.com/ArtisanPack-UI/icons) docs.

---

## i18n

Editor strings use `@wordpress/i18n` with the `artisanpack-visual-editor`
text domain. The domain is initialized via `bootI18n()` in
`resources/js/visual-editor/vendor/i18n.ts`.

Regenerate the placeholder `.pot` catalog with:

```bash
npm run i18n:extract
```

The extractor scans `resources/js/visual-editor/**/*.{ts,tsx}` for
`__/_x/_n/_nx` calls bound to the text domain and writes
`languages/artisanpack-visual-editor.pot`.

---

## Contributing

As an open source project, this package is open to contributions from
anyone. Please [read through the contributing
guidelines](CONTRIBUTING.md) to learn more about how you can contribute
to this project.
