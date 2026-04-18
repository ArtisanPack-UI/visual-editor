# Legacy editor — reference only

Everything under this directory is the **from-scratch custom React editor**
that was in flight as of `add/phase-two`. It is **not** the active editor code
going forward.

## Why it's here

This package is pivoting to adopt Gutenberg's React packages
(`@wordpress/blocks`, `@wordpress/block-editor`, `@wordpress/block-library`,
`@wordpress/components`) as a library dependency rather than continuing to
re-implement block-editor primitives from scratch. See:

- Umbrella issue: [`#309` — Gutenberg adoption — V1 editor core](https://github.com/ArtisanPack-UI/visual-editor/issues/309)
- Strategy doc: [`docs/gutenberg-adoption.md`](../../../../docs/gutenberg-adoption.md)

## What's preserved here

- `editor/blocks/` — `block.json` manifests and `edit.tsx` for the six core
  text blocks (paragraph, heading, list, quote, code, preformatted) along with
  the block registry, inner-blocks primitive, RichText/Tiptap wiring, DnD
  scaffolding, block toolbar / inspector sidebar, and inserter.
- `editor/components/` — the custom editor shell (Canvas, BlockList,
  BlockWrapper, TopBar, StatusBar, EditorBoot, Inspector, etc.).
- `editor/store/` — the Zustand-based block tree store with undo/redo.
- `editor/registry/` — the custom block type registry (pre-Gutenberg).
- `editor/primitives/` — custom `InnerBlocks`, `RichText`, `BlockContext`.
- `editor/rest/` — REST client + autosave hook.
- `editor/test-setup.ts` — jsdom shims for Tiptap/ProseMirror tests.

## What's referenced from active code

The service provider still reads block.json manifests from
`_legacy/editor/blocks/` so the server-side block registry has something to
inventory during the transition. **M5** revisits which block.json files
survive as canonical Gutenberg-compatible manifests (mostly the metadata
fields like `name`, `title`, `category`, `attributes`, `supports`) versus
which get dropped in favor of Gutenberg's own `@wordpress/block-library`
registrations.

`vite.config.ts`, `vitest.config.ts`, `tsconfig.json`, and
`resources/views/editor/mount.blade.php` all currently point at paths inside
this directory. **M1** introduces the new Gutenberg-powered editor tree at
`resources/js/visual-editor/editor/` and rewires these configs.
**M3** replaces `mount.blade.php` with the new `<x-visual-editor>` Blade
component.

## What happens to this directory

It stays as `_legacy/` for the duration of the V1 Gutenberg adoption work so
we can cross-reference patterns (block.json authoring, autosave debouncing,
REST client shape) without losing the prior prototype. Once V1 ships (M15),
the directory is deleted outright.

If you find yourself reaching for code here in a non-transitional capacity,
stop — the active editor lives under `resources/js/visual-editor/editor/`
(starting with M1) and all new work happens there.
