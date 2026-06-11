# Blocks

The visual editor's block library is the catalog of authoring primitives available to content editors. V1 ships **42 forked core blocks** under the `artisanpack/*` namespace, plus the bespoke blocks (`artisanpack/callout`, `artisanpack/form`, and — new in v1.1 — `artisanpack/icon`; see [[blocks/Icon Block]]). Host apps and packages can register their own blocks under any namespace.

This page is the overview. For authoring patterns and the registration API, see [[blocks/Custom Blocks]].

---

## The `artisanpack/*` namespace

The V1 editor adopts the upstream `@wordpress/*` packages but does **not** call `registerCoreBlocks()`. Every block exposed to authors is a fork under the `artisanpack/*` namespace, registered explicitly from `resources/js/visual-editor/blocks/`.

Why fork?

- **Predictable surface.** Upstream block additions don't silently appear in your inserter.
- **Markup control.** Each fork's `save.tsx` / `render` is owned by this package. Front-end CSS can target stable selectors.
- **Drift tracking.** Per-block `upstream-state.json` files record which upstream commit each fork derives from. CI runs `npm run upstream-diff` on every `@wordpress/*` Renovate PR.
- **Pasted markup still works.** Each fork ships a `from:core/*` transform so existing `core/*` content pasted from upstream auto-converts on insert.

The full mapping table (`core/*` → `artisanpack/*` per block) is the source of truth for which forks exist.

---

## Block clusters

The forked allow-list covers the following clusters:

| Cluster | Blocks |
|---------|--------|
| **Content** | paragraph, heading, list, quote, code, preformatted, pullquote, verse, table |
| **Media** | image, gallery, video, audio, file, embed, cover, media-text |
| **Layout** | group (with row/stack variations), columns, column, buttons, button, separator, spacer, details |
| **Widgets** | search, latest-posts |
| **Entity** | template-part, post-title, post-content, post-excerpt, post-date, post-author, post-featured-image, site-title, site-tagline, site-logo, navigation |
| **Loop / Feed** | categories, tag-cloud, archives, query, post-template |
| **Comments** | comments, comment-template, comment-author-avatar, comment-author-name, comment-content, comment-date, comment-edit-link, comment-reply-link, post-comments-form, post-comments-count, post-comments-link, post-comments-title, comments-pagination (+ next / numbers / previous) |
| **Authentication** | loginout |
| **ArtisanPack bespoke** | callout, form, icon (v1.1 — see [[blocks/Icon Block]]) |

Entity blocks (`artisanpack/post-*`, `artisanpack/site-*`, `artisanpack/template-part`, `artisanpack/navigation`) and the loop / feed cluster need an entity in scope to render meaningful content — pair the editor with [`artisanpack-ui/cms-framework`](https://github.com/ArtisanPack-UI/cms-framework) and they resolve against Posts / Pages / templates / site settings end-to-end. Standalone, they fall back to empty shells rather than crashing.

---

## Allow / deny list

The block registry is filtered through two arrays in `config/artisanpack/visual-editor.php`:

```php
'enabled_blocks'  => [ 'artisanpack/paragraph', /* ... */ ],
'disabled_blocks' => [ /* always denied */ ],
```

- `enabled_blocks` is an **allow-list** — when non-empty, only the listed blocks surface in the inserter.
- `disabled_blocks` is an **always-applied deny-list** — wins over the allow-list.

The package's default `enabled_blocks` covers the full V1 fork. `disabled_blocks` ships empty because no `core/*` blocks are registered — blocks deferred to future releases simply stay off the allow-list.

To narrow the inserter, override `enabled_blocks` with the subset you want. To deny a single block without rebuilding the allow-list, add it to `disabled_blocks`.

See [[Configuration#enabled-blocks-disabled-blocks]] for the full reference.

---

## Custom blocks

Host apps and packages register their own blocks under the `artisanpack/*` namespace (or any other custom namespace) by dropping files into a conventional directory and calling one of three PHP registration methods.

The package does the rest: auto-discovery on the JS side, allow-list filtering on the PHP side, and rendering on the public frontend through the three renderer packages (Blade, React, Vue).

Full authoring pattern: [[blocks/Custom Blocks]].

---

## Responsive and state-aware blocks

Block authors can wire **per-breakpoint** and **per-state** values into any control they expose. The editor's viewport switcher and state switcher pivot the inspector controls to whichever breakpoint or state the author selects; the resolved CSS is emitted with the right `@media` / `:hover` / `[aria-current]` selectors at render time.

- **Breakpoints** are a named registry resolved from theme.json → config → defaults. Add a key to ship a new breakpoint, override a key to resize an existing one. Full contract: [[blocks/Responsive Design Tools]].
- **States** are a named registry resolved the same way. Each state has a selector (the token `&` is replaced with the block's unique class scope) and an inheritance chain. Full contract: [[blocks/State Design Tools]].

Both registries are configured in `config/artisanpack/visual-editor.php` — see [[Configuration#breakpoints]] and [[Configuration#states]].

---

## Block JSON shape

Every block is described by a Gutenberg-compatible `block.json`. Minimum fields for the ArtisanPack pipeline:

| Field        | Purpose                                                                   |
|--------------|---------------------------------------------------------------------------|
| `name`       | `namespace/name` — lowercase, hyphens only (e.g. `artisanpack/callout`)   |
| `category`   | `artisanpack` for bundled/reference blocks; free choice for host apps     |
| `title`      | User-facing label shown in the inserter                                   |
| `attributes` | Block attributes (optionally typed/enumerated with `default` values)      |
| `supports`   | Which Gutenberg block supports to enable (alignment, color, spacing, …)   |

Full schema reference: [WordPress block.json reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/) and [[blocks/Custom Blocks#2-block-json-schema]].

---

## See also

- [[blocks/Custom Blocks]] — Authoring custom blocks (static and dynamic)
- [[blocks/Responsive Design Tools]] — Per-breakpoint values
- [[blocks/State Design Tools]] — Per-state values
- [[Renderers]] — Rendering blocks on the public site
- [[Post Editor]] — Where authors interact with blocks
