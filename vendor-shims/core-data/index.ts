/**
 * Shim package entry point for `@wordpress/core-data` (issue #808 H4).
 *
 * Node module resolution + npm `overrides` route every import of
 * `@wordpress/core-data` — first-party OR from inside a pre-bundled
 * `@wordpress/block-editor` etc. — to this single file, which in turn
 * re-exports the in-repo shim source. Because both Vite's dep
 * pre-bundler and its source pipeline resolve to the same absolute
 * path, one shim module instance exists at runtime, one wp-data
 * registry holds the `core` store, and upstream Gutenberg + first-party
 * fork code read and write against the same setter/dispatch identities.
 *
 * The shim itself lives at `resources/js/visual-editor/vendor/core-data-shim.ts`;
 * this file exists so the shim can be named as a proper node package.
 */

export * from '../../resources/js/visual-editor/vendor/core-data-shim';
