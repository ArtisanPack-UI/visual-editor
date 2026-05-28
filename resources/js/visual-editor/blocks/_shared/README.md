---
title: Forked-block shared primitives
---

# `blocks/_shared/`

This directory is the agreed home for primitives vendored out of
`@wordpress/block-library`'s **private** surface so forked blocks can
import them without reaching into `node_modules/@wordpress/block-library/src/…`.

## Decision tree (per plan 13 §4.4)

Before vendoring an upstream helper, walk this checklist:

1. **Is it re-exported from a public `@wordpress/*` package?** (e.g. the
   color/font-size class helpers live on `@wordpress/block-editor`.) →
   Import from there. No vendoring needed.
2. **Is it under `@wordpress/block-library/src/utils/`?** → Treat it as
   block-library-private. Copy into `_shared/`, prefix the file with the
   upstream subpath comment, and log the source path + commit SHA in the
   block's `upstream-state.json`.
3. **Is it a single-block-private helper (e.g. paragraph's `use-enter.js`)?**
   → Co-locate inside that block's directory, not here. `_shared/` is for
   primitives reused across two or more forked blocks.

## I0 (paragraph pilot) outcome

Paragraph touches `@wordpress/block-editor`, `@wordpress/blocks`,
`@wordpress/components`, `@wordpress/i18n`, `@wordpress/icons`,
`@wordpress/element`, `@wordpress/compose`, `@wordpress/data`,
`@wordpress/deprecated`, `@wordpress/keycodes` — all public surface.

`getColorClassName`, `getFontSizeClass`, `useBlockProps`, `useSettings`,
and `useBlockEditingMode` are public exports of `@wordpress/block-editor`.
The two paragraph-internal helpers (`use-enter`, `use-deprecated-align`)
are co-located inside `blocks/paragraph/` rather than promoted here,
since they're single-block-private.

**Result:** zero shared primitives need to be vendored for paragraph.
This directory exists with this README to establish the convention for
I1–I6, where multi-block primitives (link-control, alignment helpers,
etc.) are expected to land.

## Naming convention

- One file per primitive, kebab-cased.
- Top-of-file comment: `// Vendored from @wordpress/block-library/src/<path> @<commit-sha>`.
- TypeScript only (`.ts` / `.tsx`).
- No default exports — every primitive is a named export.

## When to refresh

Run `npm run upstream-diff -- --block <name>` after bumping the pinned
`@wordpress/block-library` version. Any vendored primitive whose
upstream file changed needs a review pass + a refreshed
`upstream-state.json` entry.
