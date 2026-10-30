---
title: Frontend Performance
---

# Frontend Performance

*Since v1.19.0.* The Blade renderer sends a page only the CSS it needs, uses the image files the media library already made, and can serve its assets without PHP. Most of this works with no setup. The optional step is [publishing the assets](#publishing-static-assets).

| What | Setup | Applies to |
|------|-------|------------|
| [Responsive images](#responsive-images) | None for dimensions and loading hints. Variants need `artisanpack-ui/media-library`. | Blade renderer |
| [Per-page stylesheets](#per-page-stylesheets) | None | HTML responses on the `web` middleware group |
| [Compiled styles file](#compiled-styles-file) | None | HTML responses on the `web` middleware group |
| [Static assets](#publishing-static-assets) | Run one command, and add a header on nginx | Hosts that opt in |

The React and Vue renderers don't do any of this. They leave image loading and CSS delivery to the host.

---

## Responsive images

These blocks read the media record behind an image:

| Block | `srcset` / `sizes` | `width` / `height` | `<picture>` | Loading hint |
|-------|--------------------|--------------------|-------------|--------------|
| Image | Yes | Yes, unless the block sets a size | Yes | Yes |
| Post Featured Image | Yes | Yes | Yes | Yes |
| Media & Text | Yes | Yes | Yes | Yes |
| Cover | Yes | No | No | Yes |
| Avatar, Post Author | Picks one smaller file | Set by the block | No | Always lazy |

Cover has no `<picture>` because duotone and the photo grid target its image as a direct child of the block.

### Finding the variants

The renderer needs a media id: `id` on Image and Cover, `mediaId` on Media & Text, and the post's featured image for Post Featured Image. Avatar and Post Author match their URL to a media record by its stored path.

Variants come from the record's `metadata`, with no call to storage. For a record whose metadata doesn't list them, such as an import or an older upload, the renderer checks the disk once and remembers the result for a day. Pixel widths come from the record's `width` and `height` and the sizes in `artisanpack.media.image_sizes` and `custom_image_sizes`, so a size removed from that config is left out.

A `srcset` only includes files with the same shape as the original, so a square thumbnail never stands in for a wide photo.

An image keeps its plain `src` and gets only its loading hint when:

- the media library isn't installed, or the record doesn't exist
- the block's URL isn't one of the record's files, which usually means the id is stale
- the record has no stored `width` and `height`
- the file URL doesn't end in the file name, as with a signed URL
- the lookup fails, which is reported through `report()`

A GIF keeps its original file, because resizing or converting one under GD drops its animation. It still prints its `width` and `height`.

### WebP and AVIF

The media library converts only the full-size file. A browser uses the first `<source>` it supports, so a `<source>` holding only `photo.webp` would send a phone the full-size file over a much smaller JPEG. Each `<source>` therefore keeps the smaller variants and swaps the modern file in at the top of the list:

```html
<picture>
    <source type="image/webp" srcset="photo-medium.jpg 300w, photo-large.jpg 1024w, photo.webp 1600w" sizes="…"/>
    <img src="photo.jpg" srcset="photo-medium.jpg 300w, photo-large.jpg 1024w, photo.jpg 1600w" sizes="…" width="1600" height="900" alt=""/>
</picture>
```

The layout baseline sets `picture { display: contents }` inside these blocks, so `img` rules keep sizing the image against its block. A theme rule that uses a direct child selector, such as `.wp-block-image > img`, won't match an image inside `<picture>`.

### Loading order

- A Cover, or a Post Featured Image outside a query loop, is treated as the page's hero. The first one gets `fetchpriority="high"`, and a page never gets more than one.
- The first image on the page, of any kind, isn't deferred, since it may be above the fold.
- Every other image gets `loading="lazy"`.

Lazy images use `sizes="auto, …"`, so the browser picks a file from the width the image actually lays out at. That's what lets a card in a grid load a small file. Browsers without `auto` skip that entry and read the rest of the list.

---

## Per-page stylesheets

`<x-ve-blocks-styles>` still prints every stylesheet. The `OptimizeBlockStyles` middleware on the `web` group then reads the finished page and removes each `frontend/*` stylesheet with no matching classes in the body. `style.css` and `theme.css` always stay, because they cover every core block. Scripts aren't removed.

Reading the output rather than the blocks that rendered has two benefits. It sees markup the renderer never touched in that request, such as a cached fragment, a page-cache hit or hand-written theme markup that uses a block's classes. It also sees classes no block type predicts, such as an inline icon or the flex support.

Anything that skips the middleware keeps the component's full output. That includes mail, `Blade::render()` into a string, JSON, streamed responses and routes outside the `web` group.

### Keeping a stylesheet

A page that adds blocks after it loads, such as through a Livewire update, has no matching classes when the middleware runs. Keep that stylesheet on every page with the [`ap.visualEditor.requiredStylesheets`](Hooks-and-Events.md#apvisualeditorrequiredstylesheets) filter:

```php
addFilter( 'ap.visualEditor.requiredStylesheets', fn ( array $required ): array => [
	...$required,
	'data-ve-tabs',
] );
```

| Value | Stylesheet |
|-------|------------|
| `data-ve-accordion` | `frontend/accordion.css` |
| `data-ve-tabs` | `frontend/tabs.css` |
| `data-ve-grid` | `frontend/grid.css` |
| `data-ve-marquee` | `frontend/marquee.css` |
| `data-ve-social-icons` | `frontend/social-icons.css` |
| `data-ve-inline-icon` | `frontend/inline-icon.css` |
| `data-ve-breadcrumbs` | `frontend/breadcrumbs.css` |
| `data-ve-query-pagination` | `frontend/query-pagination.css` |
| `data-ve-flex-layout` | `frontend/flex-layout.css`, or its inline rules when the host moves its breakpoints |
| `data-ve-photo-grid` | `frontend/photo-grid.css` |
| `data-ve-post-template` | `frontend/post-template.css` |
| `data-ve-post-variant` | `frontend/post-variant.css` |
| `data-ve-masonry` | `frontend/masonry.css` |

To keep every stylesheet on every page, set [`frontend_styles.conditional`](Configuration.md#frontend_styles) to `false`.

---

## Compiled styles file

The same middleware moves the head's inline tokens, layout baseline and global styles into one stylesheet:

```html
<link rel="stylesheet" href="/vendor/visual-editor-renderer-blade/generated/7f78ab4ce3025bcf16b8.css" data-ve-compiled-styles>
```

The file name is a hash of the CSS, so changing the global styles links a new file on the next page. Nothing needs invalidating. The route serves it with `Cache-Control: public, max-age=31536000, immutable`, so a browser downloads it once.

The CSS is stored in the application cache for 30 days, with a copy in `storage/app/visual-editor/generated/` for pages a CDN or page cache keeps for longer. A host that published its assets also gets a static copy, so the web server answers without PHP.

These tags are merged, in this order:

- `data-ve-theme-tokens`
- `data-ve-spacing-presets`
- `data-ve-default-tokens`
- `data-ve-default-styles`
- `data-ve-responsive-stacking`
- `data-ve-layout-baseline`
- `data-ve-global-styles`

Only adjacent tags merge. A `<style data-ve-global-styles>` you printed after your theme stylesheet with `@stack('ve-global-styles')` keeps its place in the cascade and becomes its own file.

The CSS stays inline when:

- it contains a relative `url()`, which would resolve against the file rather than the page
- it contains `@import` or `@charset`, which are only valid at the start of a stylesheet
- a tag carries another attribute, such as `nonce`
- neither the cache nor a published assets directory can keep it, as with the `array` cache store
- [`frontend_styles.compiled_file`](Configuration.md#frontend_styles) is `false`

> **Multi-server hosts.** A server can only answer for CSS it can read. Use a cache store the servers share, or set `compiled_file` to `false`.

---

## Publishing static assets

With no setup, a package route serves the renderer's CSS and JS. That route has to revalidate, because the URLs don't change between releases. To serve hashed files from the web server instead, run:

```bash
php artisan visual-editor:publish-assets
```

This writes `public/vendor/visual-editor-renderer-blade/` with a content-hashed copy of each file, a `manifest.json` and an `.htaccess`. `<x-ve-blocks-styles>` then links to the hashed files, unless you set `asset-base`, because a CDN mirror may not hold those names.

After that first run, every `composer install` and `composer update` refreshes the files, since Laravel runs `package:discover` when Composer finishes. A host that never ran the command gets nothing written to `public/`. Each publish keeps the previous set for pages still pointing at it and removes anything older.

If a deploy skips Composer scripts, run the command in the deploy instead.

### Cache headers

The web server sends the headers for static files.

**Apache:** the generated `.htaccess` sets them. It needs `mod_headers`.

**nginx:** add this to your server block. Keep the quotes around the regex, or nginx stops at the `{`.

```nginx
location ~* "^/vendor/visual-editor-renderer-blade/.+[./][a-f0-9]{12,20}\.(css|js)$" {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri /index.php?$query_string;
}
```

### When pages use the package route

The renderer compares the manifest with the installed files on each request, using their names, sizes and modification times. If they differ, pages link to the plain paths until the next publish, so they never serve old CSS after an update. A deploy that resets modification times has the same effect. It costs only the speed of static delivery, and running the command after the deploy restores it.

A page cached before a deploy can ask for a hash that's no longer on disk. The route answers with the current file and revalidates, rather than returning a 404.

The older `vendor:publish --tag=visual-editor-renderer-blade-assets` still works, but it copies plain file names, so you must rerun it with `--force` after every update. Use `visual-editor:publish-assets` instead.

---

## See also

- [Renderers](renderers.md#front-end-styles) — `<x-ve-blocks-styles>` attributes and global-styles placement
- [Configuration](Configuration.md#frontend_styles) — `frontend_styles`
- [Global styles](site-editor/Global-Styles.md) — CSS emission contract
- [Troubleshooting](troubleshooting.md#15-a-block-is-unstyled-after-upgrading-to-119) — Unstyled blocks after upgrading
