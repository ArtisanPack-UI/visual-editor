# Table of Contents Block

**Status:** v1.9 · issue #760
**Block name:** `artisanpack/toc`

The TOC block auto-derives a nested list of anchor links from the page's headings.

A pre-render pass (`TocResolver`) walks the block tree, stamps a stable, unique slug into the `anchor` attribute of every `core/heading` / `artisanpack/heading` that lacks one (existing author-set anchors are preserved; duplicate slugs receive a `-1`, `-2`, … suffix), and stamps `_resolvedItems` onto every TOC block filtered by its `minLevel` / `maxLevel` range.

---

## Attributes

| Attribute        | Type                            | Notes |
|------------------|---------------------------------|-------|
| `minLevel`       | `number` (default `2`)          | Lowest heading level included. |
| `maxLevel`       | `number` (default `6`)          | Highest heading level included. |
| `ordered`        | `boolean` (default `false`)     | When `true` renders as `<ol>` instead of `<ul>`. |
| `_resolvedItems` | Reserved                        | Populated by `TocResolver`; do not set from the editor. |

## Markup

Renders a `<nav>` landmark wrapping a nested `<ul>` (or `<ol>` when `ordered` is on). Smooth in-page scrolling activates automatically via `html:has(.ap-toc) { scroll-behavior: smooth }`.

React and Vue renderers ship the same DOM when the resolved payload reaches them.
