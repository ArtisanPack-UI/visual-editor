# Related Posts: nested Post Template (#601)

Starting with v1.2, the `artisanpack/related-posts` block nests an
`artisanpack/post-template` as its iteration host — the same hosting
pattern `artisanpack/query` uses. This change unlocks Query Loop's
WYSIWYG canvas (one editable iteration plus N read-only ghosts),
per-post variants (`artisanpack/post-variant`), variable grid spans,
and masonry packing for related-posts rails without any per-block
fork.

## Saved-content backward compatibility

Pre-#601 Related Posts saves have a flat inner-blocks tree — the
`post-title` / `post-date` / `post-excerpt` children sit directly
under `artisanpack/related-posts` with no `artisanpack/post-template`
wrapper.

The `QueryInliner::expandRelatedPosts()` pre-pass auto-detects this on
the public-render path:

- **With a nested `post-template`** (new content): iterations are
  expanded under the post-template wrapper, exactly like Query Loop —
  variants, grid spans, and masonry packing all inherit.
- **Flat inner blocks** (legacy content): the legacy per-iteration
  expansion kicks in, producing one `core/post-template-item` per
  resolved post directly under `artisanpack/related-posts`. Public
  render output is unchanged from pre-#601.

No content migration is required. Authors who want to opt into the
new capabilities re-save the block (which rewrites the saved tree
through the editor template) or insert a new Related Posts block.

## Editor changes

- Related Posts no longer exposes layout (list / grid / masonry) or
  column controls itself — the nested `artisanpack/post-template`
  owns those via its existing toolbar + inspector, matching the
  Query Loop experience. Selecting the inner Post Template surfaces
  the layout toolbar and column count.
- Related Posts gains a Post Variants inspector panel — the same
  `PostVariantsPanel` Query Loop uses — so authors can register
  position / pattern / meta variants without leaving the parent
  block.
- The Related Posts inspector keeps the query-level controls:
  number of posts, offset, and order.
- Newly-inserted Related Posts blocks seed an `artisanpack/post-template`
  with the same default `post-title` / `post-date` / `post-excerpt`
  children. Existing flat saves are not migrated by the editor on
  load — they keep rendering through the legacy path.
- When no host post is in editor scope (e.g. a template-editor view
  with no preview post selected), the canvas surfaces an info Notice
  instead of issuing a preview fetch.
- When the host post matches zero related posts, the canvas keeps the
  editable iteration template and surfaces a warning Notice. This
  replaces the pre-#601 `ap-related-posts__preview-empty` text
  fallback.

## Resolver API change

`POST /visual-editor/api/query/resolve` accepts a new `relatedTo`
field — an integer host-post id. When present:

- The server loads the host post via the bound `QueryResolverContract`.
- It derives the host's primary taxonomy and term ids via
  `HostRelatedTermsResolver::hostRelatedTerms()` (categories → tags →
  generic `terms` fallback, mirroring `QueryInliner::expandRelatedPosts`).
- The payload is rewritten into a `taxQuery: { taxonomy, terms,
  operator: 'IN' }` related-by-taxonomy query with `exclude: [hostId]`,
  then forwarded to the resolver.
- `relatedTo` is mutually exclusive with `taxQuery` at the request
  layer.

When the host post can't be loaded or carries no related-terms
signal, the controller short-circuits with an empty paginator
envelope (no resolver call for the related query).

## Renderer parity

The related-posts wrapper now emits only the `ap-related-posts` class
across all three renderers (Blade / React / Vue). Layout (grid /
masonry / list) and column count are rendered by the nested
`artisanpack/post-template` via its existing `is-layout-*` / `columns-N`
classes — the same module the standalone Query Loop renders against,
so masonry + grid-spans + variants reuse one CSS module instead of
forking per host.

The pre-#601 wrapper classes (`ap-related-posts-has-N-columns`,
`ap-related-posts__item*`, etc.) and their styles have been removed —
they only existed for the legacy flat-card preview UX that is no
longer wired up.

## Out of scope (intentional)

- Pagination siblings (`artisanpack/query-pagination*`) — Related
  Posts is a finite list capped at `numPosts: 10`; pagination has no
  conceptual meaning.
- `artisanpack/query-title` and `artisanpack/query-no-results` siblings.
- Inline editing of individual non-first iterations (variants are the
  per-post override mechanism, same as Query Loop).
- Changing the "relatedness" rule. Related Posts stays locked to
  "same post type, sharing at least one term in the host's primary
  taxonomy".
- The `enhancedPagination` attribute on `related-posts/block.json` is
  preserved for saved-content safety but remains unsurfaced in the
  editor.
