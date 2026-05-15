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
unsupported. The H8 smoke flow ([`docs/h8-smoke-flow.md`](docs/h8-smoke-flow.md))
runs against this version pair before every release tag.

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

V1 ships with a frozen allow-list of blocks from `@wordpress/block-library`. The defaults in `config/visual-editor.php` expose only blocks that render correctly against the empty-state `@wordpress/core-data` shim — content, media, layout, and simple widget blocks that do not need a WordPress backend to work.

**Disabled by default.** Every block in the following categories is listed in `disabled_blocks` because it relies on data the shim does not provide:

- **Site / theme** — `core/navigation`, `core/site-logo`, `core/site-title`, `core/site-tagline`, `core/template-part`
- **Query loop** — `core/query`, `core/query-loop`
- **Post context** — `core/post-content`, `core/post-title`, `core/post-excerpt`, `core/post-date`, `core/post-author`, `core/post-featured-image`
- **Taxonomy widgets** — `core/categories`, `core/tag-cloud`, `core/archives`
- **Comments feeds** — `core/latest-comments`

These blocks will be re-enabled once `artisanpack-ui/cms-framework` replaces the shim with a real Laravel-backed `core-data` store. The full per-block classification — including blocks that render empty but do not crash — lives in [`docs/block-library-audit.md`](docs/block-library-audit.md). Because the defaults populate `enabled_blocks` as an explicit allow-list, new blocks introduced by a future `@wordpress/block-library` upgrade are implicitly disabled until the audit is revisited.

Override the defaults by publishing the config to `config/artisanpack/visual-editor.php` and editing the `enabled_blocks` / `disabled_blocks` arrays. The deny-list always wins over the allow-list.

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
