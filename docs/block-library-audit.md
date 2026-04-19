# Block Library Audit

Classification of every block registered by `@wordpress/block-library` (v9.44.0)
against the current editor's empty-state `@wordpress/core-data` shim
(`resources/js/visual-editor/vendor/core-data-shim.ts`).

Each block falls into one of three buckets:

- **green** — inserts, renders, and edits without a backing WordPress data
  store. Safe to expose in the default `enabled_blocks` list.
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

## Empty-state — disabled by default

These blocks do not crash, but the shim returns empty data for the selectors
they depend on, so the block renders a placeholder, empty list, or blank
title. Better UX is no block at all until `cms-framework` lands.

| Block | Required data |
| --- | --- |
| `core/latest-comments` | Comments feed. |
| `core/archives` | Archive list query. |
| `core/categories` | Term hierarchy. |
| `core/tag-cloud` | Term weights. |
| `core/rss` | External feed fetch — not blocked by shim but disabled for consistency with the other feed widgets. |
| `core/calendar` | Post date aggregation. |
| `core/page-list` | Hierarchical page query. |
| `core/table-of-contents` | Heading scan against rendered entity. |

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

- `core/post-title`
- `core/post-content`
- `core/post-excerpt`
- `core/post-date`
- `core/post-author`
- `core/post-author-name`
- `core/post-author-biography`
- `core/post-featured-image`
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
