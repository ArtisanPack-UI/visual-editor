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

## Dynamic headings

Auto-generated anchors and TOC labels come from the text the visitor
sees, not the authored text:

- **Bound headings.** When a heading's `content` is bound (see
  [Block Bindings](../block-bindings.md)), the anchor and TOC entry use
  the resolved value. A heading bound to the post title renders
  `id="core-package"` rather than `id="static-title"`. A heading hidden
  by an empty binding leaves no TOC entry. *Since v1.14.0 (#851).*
- **Inline tokens.** Headings with `{{token}}`s, including headings
  inside post content, use the token-resolved text. `About {{site.name}}`
  on a site named "Acme" renders `id="about-acme"` and the TOC lists
  "About Acme". This needs cms-framework's `DynamicContentResolver`;
  without it nothing changes. *Since v1.14.0 (#858).*
- **Dynamic loop rows.** Headings inside an `artisanpack/dynamic-loop`
  keep the template's anchor on the first row, so TOC links land there.
  Later rows add the collection and row number (`member`,
  `member-team-2`, …) so ids stay unique. *Since v1.14.0.*

Author-set anchors are never changed. Hosts calling the resolver
directly can pass a binding-resolved copy of the tree as the optional
second argument of `TocResolver::resolveTree()` to read heading text from.
