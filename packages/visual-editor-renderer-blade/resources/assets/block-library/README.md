# Bundled WordPress block-library CSS

These files are copies of `@wordpress/block-library/build-style/style.css` and
`theme.css` from the parent `visual-editor` package's `node_modules`. They are
the public block stylesheet that `<x-ve-blocks-styles />` emits a `<link>` to.

## Refreshing

Run from the parent package root:

```bash
cp node_modules/@wordpress/block-library/build-style/style.css \
   packages/visual-editor-renderer-blade/resources/assets/block-library/style.css
cp node_modules/@wordpress/block-library/build-style/theme.css \
   packages/visual-editor-renderer-blade/resources/assets/block-library/theme.css
```

Refresh after bumping `@wordpress/block-library` in the parent package's
`package.json`. Skipping this step leaves the bundled CSS out of sync with the
JS block versions consumers load, which can manifest as missing utility
classes on newly-added blocks.

## Why bundle, not pull at install time?

This package ships through Composer. PHP consumers don't have npm in their
install graph, so pulling the CSS from a Node toolchain at `composer install`
time isn't viable. Bundling keeps the package self-contained — `composer
install` plus `php artisan vendor:publish --tag=visual-editor-renderer-blade-assets`
is all a consumer needs.
