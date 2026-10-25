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
| `source`         | `string` (default `page`)       | `page` lists every heading on the page. `post-content` lists only the headings inside Post Content, leaving out the template's own (a related-posts heading, a footer's). *Since v1.18.0.* |
| `_resolvedItems` | Reserved                        | Populated by `TocResolver`; do not set from the editor. |

## Headings from Post Content only

A TOC placed in a single-post template sits in one block tree with the template's headings, its parts' headings and the post body, so with the default `source` it lists all of them. Set `source` to `post-content` (**Headings from → Post content** in the editor) to keep only the post's own headings:

```html
<!-- wp:artisanpack/toc {"source":"post-content"} -->
<nav class="wp-block-artisanpack-toc ap-toc"></nav>
<!-- /wp:artisanpack/toc -->
```

Headings count as post content when they are in a Post Content block's rendered HTML or nested inside the block. Every heading still gets its anchor; `source` only decides which ones the TOC lists. A page with no Post Content block, such as a post body with the TOC inside it, lists every heading either way.

In the site editor, Post Content is a placeholder, so a `post-content` TOC in a template previews a note instead of a list. The published page lists the post's headings.


Renders a `<nav>` landmark wrapping a nested `<ul>` (or `<ol>` when `ordered` is on). Smooth in-page scrolling activates automatically via `html:has(.ap-toc) { scroll-behavior: smooth }`.

React and Vue renderers ship the same DOM when the resolved payload reaches them.

## Anchor generation

Every heading gets an `id`: its author-set anchor, or an auto anchor
slugged from its text. The Blade renderer (`TocResolver`), the React
and Vue renderers and the editor's anchor autogeneration use the same
rules:

1. Accents are removed (`Café` → `cafe`), runs of characters that
   aren't letters or numbers become one `-`, and the text is
   lowercased.
2. The slug is reduced to the characters a rendered `id` keeps,
   `[A-Za-z0-9_:.-]`, and runs of `-` are collapsed. *Since v1.15.0.*
3. Text whose letters and numbers all drop out in step 2 (`日本語`)
   falls back to `heading`. Text with no letters or numbers at all
   (`???`) gets no anchor and no TOC entry.
4. A slug already used on the page gets `-1`, `-2`, … (`heading`,
   `heading-1`, `heading-2`, …).

Author-set anchors are kept as they render: characters outside
`[A-Za-z0-9_:.-]` are dropped (`foo bar` renders `id="foobar"`), and
the rendered value is what later auto anchors avoid and what the TOC
links to. An anchor that sanitizes to nothing gets an auto anchor
instead. A numeric anchor such as `42` is an author anchor too
(`id="42"`). Tags in heading text are stripped the way PHP's
`strip_tags()` strips them, so `a < b > c` slugs to `a-b-c` in every
renderer.

Headings inside Post Content (`core/post-content` /
`artisanpack/post-content`) take part in the same set, in Blade, React
and Vue: a heading that already has an `id` claims it, so a later block
heading with the same text gets `-1`, and a heading without one gets a
unique auto anchor stamped into the content HTML.

Anchors are unique within one render. In React and Vue that is one
`<BlockTree>`: two trees on the same page (for example a header tree
and a content tree) can repeat an id. Render them as one tree, or set
author anchors on headings that could collide. The JS renderers also
decode only the HTML 4 named entities (plus `&apos;`) when slugging, so
a heading containing an HTML5-only entity such as `&bigstar;` can slug
differently from Blade.

> **Changed in v1.15.0.** Before 1.15.0 the slug wasn't reduced to
> id-safe characters before the uniqueness check, so headings with
> non-Latin or symbol-heavy text could render duplicate ids, or TOC
> links to an id that wasn't printed. Some auto anchors change as a
> result: `Crème ½ x` renders `id="creme-x"` instead of
> `id="creme--x"`, and `日本語` renders `id="heading"`. Author-set
> anchors, and anchors the editor already saved into the block, are
> unchanged. Update inbound links that point at an old auto anchor.

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
