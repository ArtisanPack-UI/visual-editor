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
