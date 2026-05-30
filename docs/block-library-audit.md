# Block Library Audit

> **I7 cutover (#415):** As of V1, all enabled blocks use the
> `artisanpack/*` namespace. `@wordpress/block-library`'s
> `registerCoreBlocks()` is no longer called — the editor registers only
> the forked blocks discovered under `resources/js/visual-editor/blocks/`.
> The `core/*` column below is preserved for the upstream diff trail.

## Fork mapping — `core/*` → `artisanpack/*`

| Upstream (`core/*`) | Fork (`artisanpack/*`) | Cluster |
| --- | --- | --- |
| `core/paragraph` | `artisanpack/paragraph` | I0 |
| `core/heading` | `artisanpack/heading` | I1 |
| `core/list` | `artisanpack/list` | I1 |
| `core/list-item` | `artisanpack/list-item` | I1 |
| `core/quote` | `artisanpack/quote` | I1 |
| `core/code` | `artisanpack/code` | I1 |
| `core/preformatted` | `artisanpack/preformatted` | I1 |
| `core/pullquote` | `artisanpack/pullquote` | I1 |
| `core/verse` | `artisanpack/verse` | I1 |
| `core/table` | `artisanpack/table` | I1 |
| `core/image` | `artisanpack/image` | I2 |
| `core/gallery` | `artisanpack/gallery` | I2 |
| `core/video` | `artisanpack/video` | I2 |
| `core/audio` | `artisanpack/audio` | I2 |
| `core/file` | `artisanpack/file` | I2 |
| `core/embed` | `artisanpack/embed` | I2 |
| `core/cover` | `artisanpack/cover` | I2 |
| `core/media-text` | `artisanpack/media-text` | I2 |
| `core/columns` | `artisanpack/columns` | I3 |
| `core/column` | `artisanpack/column` | I3 |
| `core/group` | `artisanpack/group` | I3 |
| `core/row` | `artisanpack/row` (variation of group) | I3 |
| `core/stack` | `artisanpack/stack` (variation of group) | I3 |
| `core/buttons` | `artisanpack/buttons` | I3 |
| `core/button` | `artisanpack/button` | I3 |
| `core/separator` | `artisanpack/separator` | I3 |
| `core/spacer` | `artisanpack/spacer` | I3 |
| `core/details` | `artisanpack/details` | I3 |
| `core/search` | `artisanpack/search` | I4 |
| `core/latest-posts` | `artisanpack/latest-posts` | I4 |
| `core/template-part` | `artisanpack/template-part` | I5 |
| `core/post-title` | `artisanpack/post-title` | I5 |
| `core/post-content` | `artisanpack/post-content` | I5 |
| `core/post-excerpt` | `artisanpack/post-excerpt` | I5 |
| `core/post-date` | `artisanpack/post-date` | I5 |
| `core/post-author` | `artisanpack/post-author` | I5 |
| `core/post-featured-image` | `artisanpack/post-featured-image` | I5 |
| `core/site-title` | `artisanpack/site-title` | I5 |
| `core/site-tagline` | `artisanpack/site-tagline` | I5 |
| `core/site-logo` | `artisanpack/site-logo` | I5 |
| `core/navigation` | `artisanpack/navigation` | I5 |
| `core/archives` | `artisanpack/archives` | I6 |
| `core/categories` | `artisanpack/categories` | I6 |
| `core/tag-cloud` | `artisanpack/tag-cloud` | I6 |
| `core/query` | `artisanpack/query` | I6 |
| `core/post-template` | `artisanpack/post-template` | I6 |

Additional package-native block (no upstream counterpart):
- `artisanpack/callout`
- `artisanpack/form` (V1.1+)

---

The sections below document the original upstream audit against
`@wordpress/block-library` v9.44.0 and the `@wordpress/core-data` shim.
They are preserved for the diff trail — the upstream `core/*` names
below correspond to the `artisanpack/*` forks listed in the mapping
table above.

Classification of every block registered by `@wordpress/block-library` (v9.44.0)
against the editor's `@wordpress/core-data` shim
(`resources/js/visual-editor/vendor/core-data-shim.ts`).

Each block falls into one of four buckets:

- **green** — inserts, renders, and edits without a backing WordPress data
  store. Safe to expose in the default `enabled_blocks` list.
- **entity-wired** — enabled by default, but the saved markup only renders
  meaningful content when an entity is in scope. The editor canvas resolves
  these blocks via `core-data` hooks against `artisanpack-ui/cms-framework`
  Post/Page (or any host model exposing the same WP-shape entity through
  the `ap.visual-editor.resources` filter); the Blade / React / Vue
  renderers consume `_resolved*` attributes that the host stamps onto the
  block tree at render time. Without an entity context they degrade to
  empty shells, not crashes.
- **empty-state** — inserts and renders without crashing, but every data
  selector returns `null` or `[]`, so the block shows an empty shell. Shipped
  as **disabled-by-default** until the `artisanpack-ui/cms-framework` package
  provides a real Laravel-backed `core` store.
- **crash** — throws on insert or first render because the shim does not
  implement the selector or action the block expects. Not registered.

The frozen config defaults follow this audit exactly — see
`config/visual-editor.php`.

## Green — enabled by default

These blocks render correctly against the shim and are exposed to the
inserter out of the box.

### Content

| Block | Notes |
| --- | --- |
| `core/paragraph` | Baseline text block. |
| `core/heading` | Renders h1–h6. |
| `core/list` | Container for list items. |
| `core/quote` | Blockquote wrapper. |
| `core/code` | Preformatted code. |
| `core/preformatted` | Plain preformatted text. |
| `core/pullquote` | Emphasized quote variant. |
| `core/verse` | Monospace poetry block. |
| `core/table` | Static table — no data source required. |

### Media

| Block | Notes |
| --- | --- |
| `core/image` | M4 media bridge wires `MediaUpload` via `@wordpress/hooks`. |
| `core/gallery` | Uses the same media bridge. |
| `core/video` | URL / upload. |
| `core/audio` | URL / upload. |
| `core/file` | URL / upload. |
| `core/embed` | oEmbed providers resolved client-side. |
| `core/cover` | Background image + nested blocks. |
| `core/media-text` | Side-by-side media + text. |

### Layout

| Block | Notes |
| --- | --- |
| `core/columns` | Multi-column container. |
| `core/group` | Generic container. |
| `core/row` | Variation of `core/group` (listed for clarity — no separate registration). |
| `core/stack` | Variation of `core/group` (listed for clarity — no separate registration). |
| `core/buttons` | Button row container. |
| `core/separator` | Horizontal rule. |
| `core/spacer` | Vertical whitespace. |
| `core/details` | Disclosure element. |

### Simple widgets

| Block | Notes |
| --- | --- |
| `core/search` | Static form — posts target via host app. |
| `core/latest-posts` | **Stubbed.** Renders an empty list against the core-data shim; flagged here so the UX expectation is explicit — the block is still usable as a placeholder authors can drop in before wiring a real data source. |

## Entity-wired — enabled by default (G3)

These blocks ship enabled and round-trip correctly when the editor canvas
mounts against an entity (cms-framework Post/Page via the G3 entity adapter,
or any host model registered through the `ap.visual-editor.resources`
filter). The renderer packages read pre-stamped `_resolved*` attributes —
the host application is responsible for resolving the entity and stamping
those values onto the block tree before passing it to the renderer.

When no entity is in scope (preview, fallback, missing host wiring) the
block degrades to an empty Gutenberg-shaped shell so the front-end and
editor-canvas DOMs stay in agreement.

### Post context

| Block | Resolved attribute(s) |
| --- | --- |
| `core/post-title` | `_resolvedTitle`, `_resolvedPermalink` |
| `core/post-content` | `_resolvedContent` |
| `core/post-excerpt` | `_resolvedExcerpt`, `_resolvedPermalink` |
| `core/post-date` | `_resolvedDate`, `_resolvedDateFormatted`, `_resolvedModifiedDate`, `_resolvedModifiedDateFormatted`, `_resolvedPermalink` |
| `core/post-author` | `_resolvedAuthorName`, `_resolvedAuthorBio`, `_resolvedAuthorUrl`, `_resolvedAuthorAvatar` |
| `core/post-featured-image` | `_resolvedImageUrl`, `_resolvedImageAlt`, `_resolvedImageWidth`, `_resolvedImageHeight`, `_resolvedPermalink` |

## Empty-state — disabled by default

These blocks do not crash, but the shim returns empty data for the selectors
they depend on, so the block renders a placeholder, empty list, or blank
title. Better UX is no block at all until `cms-framework` lands.

| Block | Required data |
| --- | --- |
| `core/latest-comments` | Comments feed. |
| `core/rss` | External feed fetch — not blocked by shim but disabled for consistency with the other feed widgets. |
| `core/calendar` | Post date aggregation. |
| `core/page-list` | Hierarchical page query. |
| `core/table-of-contents` | Heading scan against rendered entity. |

### G4b — re-enabled against cms-framework's term + post APIs (#401)

`core/categories`, `core/tag-cloud`, and `core/archives` were promoted out
of the empty-state list in G4b. Each block ships as a `DynamicBlock`
subclass under `src/Blocks/Core/` that reads from cms-framework's
`PostCategory`, `PostTag`, and `Post` models respectively. Registration is
gated on `class_exists(BlogManager::class)` in `VisualEditorServiceProvider`
— host apps without cms-framework still register the block client-side
(via the inserter allow-list) but the preview endpoint resolves to the
unknown-block fallback because no `DynamicBlock` is bound.

Editor preview, Blade renderer, React renderer, and Vue renderer all
resolve through the same `DynamicBlock::render()` path:

- Editor surface: Gutenberg's upstream `Edit` components for these three
  blocks already use `ServerSideRender`, which POSTs the block's name
  and attributes to `/visual-editor/api/blocks/preview` — that controller
  routes through the dynamic-block registry.
- Front-end Blade renderer (`@artisanpack-ui/visual-editor-renderer-blade`)
  invokes the registered `DynamicBlock` directly when walking the saved
  block tree.
- Front-end React (`...-renderer-react`) and Vue (`...-renderer-vue`)
  renderers POST to the same preview endpoint on mount and splice the
  returned HTML back into the tree.

Archive URLs default to `/blog/{year}/{month}` (the cms-framework blog
default route shape). Hosts with a different archive route should rebind
the dynamic block via `VisualEditor::registerDynamicBlock(ArchivesBlock::class)`
with a subclass that overrides `decorate()`. Custom-taxonomy support is
out of scope for V1 (deferred to V1.1).

The taxonomy entity registration (`{kind: 'taxonomy', name: 'category', …}`)
called for in #401's implementation notes is intentionally **not** added in
V1 — the upstream `core/categories|tag-cloud|archives` `Edit` components
all render through `ServerSideRender`, so they never query
`useEntityRecords('taxonomy', 'category')`. The shim entities can be
layered in cheaply later if a future block needs them.

### Query loop — re-enabled against cms-framework's QueryRuntime (G4c-2)

`core/query` and `core/post-template` were promoted out of the deny-list in
G4c-2 (#402). The architecture differs from G4b — `core/query` has inner
blocks that need to render once per result with the per-iteration post id
threaded through to inner `core/post-*` blocks, so a single
`DynamicBlock::render()` does not fit. Instead the package ships:

- **`QueryInliner`** (`src/Resources/QueryInliner.php`) — sibling to the
  existing `TemplatePartInliner` and `PatternInliner`. Walks the saved
  tree, replaces every `core/query` block with one stamped copy of its
  inner blocks per result. Stamping is delegated to `PostResolver`,
  which sets the `_resolved*` keys the existing `core/post-*` partials
  already read.
- **`PostResolver`** (`src/Resources/PostResolver.php`) — pure-data
  service that maps a single post object to the `_resolved*` keys for
  every supported `core/post-*` block. Uses the post's `permalink`
  accessor / Carbon date casts / loaded `author` relation. Optional
  helpers (`apGetMediaUrl`, `apGetMedia`) gate gracefully when
  media-library is absent.
- **`QueryResolverContract`** (`src/Services/QueryResolverContract.php`)
  — interface the inliner + the new `POST
  /visual-editor/api/query/resolve` controller depend on. Bound by
  `VisualEditorServiceProvider::register()` to a thin
  `CmsFrameworkQueryResolver` adapter when cms-framework is on the
  autoloader; hosts can override it with any other implementation.

All four rendering surfaces (editor canvas + Blade + React + Vue
renderers) resolve through the same pipe:

- **Editor canvas:** the `core/query` Edit is overridden with a wrapper
  that calls `useQueryPreview` (a custom hook that POSTs to
  `/query/resolve`). The first matching post id is pushed into block
  context via `BlockContextProvider` so the editable inner template
  renders with real data — every inner `core/post-*` block resolves
  through G3's entity adapter.
- **Blade renderer:** `BlocksComponent` invokes `QueryInliner` alongside
  the template-part / pattern inliners. Result is the saved tree with
  every `core/query` pre-expanded into per-result instances; the
  existing `core/post-*` partials handle stamped attributes unchanged.
- **React + Vue renderers:** ship a parallel TS implementation of
  `inlineQueries` keyed by `queryId`. Hosts that pre-fetch results
  server-side pass them via the new `queryResults` prop on `<BlockTree>`
  / its Vue analog; on-the-fly client fetching uses the same
  `useQueryPreview` hook (re-exportable for host code).

The `taxQuery` operator surface is bounded to `IN` for V1 — matches the
cms-framework `QueryRuntime` contract from G4c-1. `core/query-loop`
(deprecated upstream alias for `core/query`) plus the pagination /
no-results / read-more wrappers stay in the deny-list; they need
follow-up renderer wiring that's out of scope for #402.

## Crash or backend-required — disabled by default

These blocks call selectors the shim returns `null` for but which the block's
own edit component assumes are non-null, or they rely on a resolver the shim
does not implement. They are permanently in the deny-list until a real
`core` store replaces the shim.

### Site / theme blocks

- `core/navigation`
- `core/navigation-link`
- `core/navigation-submenu`
- `core/navigation-overlay-close`
- `core/home-link`
- `core/site-logo`
- `core/site-title`
- `core/site-tagline`
- `core/template-part`
- `core/breadcrumbs`
- `core/page-list-item`
- `core/loginout`

### Query loop

`core/query` and `core/post-template` were promoted out of this section
in G4c-2 (#402); see [Query loop — G4c-2](#query-loop--re-enabled-against-cms-frameworks-queryruntime-g4c-2) below for the
architecture. The remaining query-loop blocks stay deferred — they wrap
pagination, "no results", and read-more affordances that have not yet
been wired into the renderer pipeline.

- `core/query-loop` (deprecated upstream alias — `core/query` is the live name)
- `core/query-pagination`
- `core/query-pagination-next`
- `core/query-pagination-numbers`
- `core/query-pagination-previous`
- `core/query-no-results`
- `core/query-title`
- `core/query-total`
- `core/read-more`

### Post context

The six entity-wired post blocks (`core/post-title`, `core/post-content`,
`core/post-excerpt`, `core/post-date`, `core/post-author`,
`core/post-featured-image`) are enabled by default — see the
[Entity-wired](#entity-wired--enabled-by-default-g3) section. The remaining
post-context blocks below stay disabled because the underlying data
(author bio, navigation, taxonomy, comments, time-to-read) is not yet
exposed by the cms-framework adapter.

- `core/post-author-name`
- `core/post-author-biography`
- `core/post-navigation-link`
- `core/post-terms`
- `core/post-time-to-read`
- `core/post-comments-form`
- `core/post-comments-count`
- `core/post-comments-link`
- `core/post-comment`

### Comments

- `core/comments`
- `core/comment-author-avatar`
- `core/comment-author-name`
- `core/comment-content`
- `core/comment-date`
- `core/comment-edit-link`
- `core/comment-reply-link`
- `core/comment-template`
- `core/comments-title`
- `core/comments-pagination`
- `core/comments-pagination-next`
- `core/comments-pagination-numbers`
- `core/comments-pagination-previous`
- `core/avatar`

### Taxonomy

- `core/term-count`
- `core/term-description`
- `core/term-name`
- `core/terms-query`
- `core/term-template`

### Internal / developer-only

- `core/missing` (fallback renderer for unknown blocks; internal)
- `core/html` (raw HTML — security review before enabling)
- `core/shortcode` (WordPress-only)
- `core/freeform` (TinyMCE classic block)
- `core/pattern` (pattern insertion — needs pattern registry)
- `core/block` (reusable block — needs entity store)
- `core/footnotes` (needs meta storage)
- `core/more` (classic read-more — post-context only)
- `core/nextpage` (pagination break — post-context only)
- `core/social-links`
- `core/social-link`
- `core/text-columns` (deprecated upstream)
- `core/icon`
- `core/math`

## Experimental / opt-in blocks

These blocks only register when a window flag is set
(`window.__experimentalEnableFormBlocks`,
`window.__experimentalEnableBlockExperiments`). The editor does not set any
of these flags, so they are effectively disabled regardless of config.

- `core/form`, `core/form-input`, `core/form-submit-button`, `core/form-submission-notification`
- `core/tab`, `core/tabs`, `core/tabs-menu`, `core/tabs-menu-item`, `core/tab-panel`
- `core/playlist`, `core/playlist-track`
- `core/accordion`, `core/accordion-item`, `core/accordion-heading`, `core/accordion-panel`

## How the allow-list + deny-list resolves

`enabled_blocks` is an allow-list: when non-empty, only the listed names are
exposed to the inserter. `disabled_blocks` is an always-applied deny-list;
even if a block appears on the allow-list, the deny-list removes it.

The frozen defaults ship with `enabled_blocks` populated — so new blocks
introduced by a future `@wordpress/block-library` upgrade are **implicitly
disabled** until explicitly added to the allow-list. That is intentional:
users should never be surprised by a new block that the shim hasn't been
validated against.
