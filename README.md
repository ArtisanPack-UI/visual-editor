# ArtisanPack UI Visual Editor

Description

## Installation

You can install the visual-editor package by running the following composer command.

`composer require artisanpack-ui/visual-editor`

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
