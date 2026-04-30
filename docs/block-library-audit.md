# Block Library Audit

Classification of every block registered by `@wordpress/block-library` (v9.44.0)
against the current editor's empty-state `@wordpress/core-data` shim
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
  implement the selector or action the block expects. Permanently in
  `disabled_blocks` until the offending surface is implemented.

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

- `core/query`
- `core/query-loop` (listed per spec; no separate upstream block — the block
  name is `core/query` and the loop is driven by `core/post-template`).
- `core/query-pagination`
- `core/query-pagination-next`
- `core/query-pagination-numbers`
- `core/query-pagination-previous`
- `core/query-no-results`
- `core/query-title`
- `core/query-total`
- `core/post-template`
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
