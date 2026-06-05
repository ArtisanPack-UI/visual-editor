# artisanpack-ui/visual-editor-renderer-blade

Server-side Blade renderer for the ArtisanPack UI visual editor.

Takes a saved block tree (the JSON shape the editor persists to any
`HasBlockContent` Eloquent model) and renders it to HTML using per-block Blade
partials. Dynamic blocks — any block registered with the visual-editor's
`DynamicBlockRegistry` via `VisualEditor::registerDynamicBlock()` — have their
`DynamicBlock::render()` invoked inline, server-side, with no extra HTTP
round trip.

## Installation

```sh
composer require artisanpack-ui/visual-editor-renderer-blade
```

The package is auto-registered by Laravel's package discovery. The
`<x-ve-blocks>` Blade component is available everywhere.

## Usage

```blade
<article>
    <x-ve-blocks :tree="$post->content" />
</article>
```

`:tree` accepts:

- An array of blocks in the `{ clientId, name, attributes, innerBlocks }`
  shape the visual editor persists.
- A JSON-encoded string of that shape.
- Any `Arrayable` whose `toArray()` returns either of the above.

## Public stylesheet bundle (`<x-ve-blocks-styles />`)

`<x-ve-blocks>` produces semantically-correct Gutenberg block markup, but the
public site still needs Gutenberg's block CSS for `core/columns` to flex,
`core/buttons` to size properly, `core/quote` to get its left border, etc.
The `<x-ve-blocks-styles />` Blade component handles that boilerplate:

```blade
<head>
    <x-ve-blocks-styles :theme-json="$themeJson" />
</head>
<body>
    <x-ve-blocks :tree="$page->content" />
</body>
```

It emits:

1. Two `<link>` tags to the bundled `@wordpress/block-library` `style.css`
   and `theme.css` — the same public block CSS the editor uses.
2. A `<style>` block with `--wp--preset--*` CSS custom properties compiled
   from the `theme.json` you pass in, so blocks that reference e.g.
   `var(--wp--preset--color--primary)` resolve against the active theme's
   palette.

Publish the bundled CSS into your `public/` directory first:

```sh
php artisan vendor:publish --tag=visual-editor-renderer-blade-assets
```

That drops `style.css` and `theme.css` at
`public/vendor/visual-editor-renderer-blade/`, which `<x-ve-blocks-styles />`
points to by default.

### Props

| Prop          | Type                          | Default                                  | Notes                                                                                |
| ------------- | ----------------------------- | ---------------------------------------- | ------------------------------------------------------------------------------------ |
| `theme-json`  | `array<string, mixed>\|null`  | `null`                                   | Decoded `theme.json` payload. When `null`, the token `<style>` is omitted.           |
| `asset-base`  | `string\|null`                | `/vendor/visual-editor-renderer-blade`   | Override the bundled-CSS URL prefix — point at a CDN or your own asset pipeline.     |
| `bundle`      | `bool`                        | `true`                                   | Set to `false` if your stack already loads `@wordpress/block-library` CSS elsewhere. |

### What gets compiled from `theme.json`

The token compiler walks the recognised preset categories and emits one
custom property per entry:

| theme.json path                 | Output                                          |
| ------------------------------- | ----------------------------------------------- |
| `settings.color.palette[]`      | `--wp--preset--color--{slug}: {color};`         |
| `settings.color.gradient[]`     | `--wp--preset--gradient--{slug}: {gradient};`   |
| `settings.typography.fontSizes[]` | `--wp--preset--font-size--{slug}: {size};`    |
| `settings.spacing.spacingSizes[]` | `--wp--preset--spacing--{slug}: {size};`      |

Slugs are normalised to lowercase, ASCII-safe identifiers (matching
WordPress). Entries missing `slug` or the corresponding value key are
skipped silently — `theme.json` validation is the consumer's job.

## Publishing views

To override any individual block's markup, publish the partials into your app:

```sh
php artisan vendor:publish --tag=visual-editor-blade-views
```

That copies every partial to
`resources/views/vendor/visual-editor-renderer-blade/blocks/`, and Laravel's
view finder picks up your overrides before the package defaults.

## Included block partials

All blocks in the frozen V1 allow-list from the visual-editor package's M5
audit ship with a partial, plus the nested sub-blocks (`core/column`,
`core/button`, `core/list-item`):

- Text: paragraph, heading, list, list-item, quote, code, preformatted,
  pullquote, verse
- Media: image, gallery, video, audio, file, embed
- Design: cover, media-text, table, separator, spacer, details, search
- Layout: columns, column, group, row, stack, buttons, button

`core/latest-posts` is intentionally not shipped as a static partial because
it requires a server query — register it as a `DynamicBlock` in the main
`artisanpack-ui/visual-editor` package and this renderer will invoke it
automatically.

## Unknown block fallback

A block whose name has no matching partial and no registered `DynamicBlock`
renders as:

```html
<!-- visual-editor: no partial for third-party/widget -->
<div data-ve-unknown-block="third-party/widget">{inner blocks, if any}</div>
```

This keeps surrounding layout intact and makes missing partials easy to spot
in the DOM.

## Status

This package ships as part of the ArtisanPack UI visual editor V1 (epic
`#309`, milestone M9). During the V1 cycle it lives inside the `visual-editor`
monorepo under `packages/visual-editor-renderer-blade/` and is distributed to
Packagist via a subtree split — see `PACKAGING.md` at the monorepo root for
the release workflow.
