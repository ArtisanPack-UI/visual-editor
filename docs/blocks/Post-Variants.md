# Post Variants (Query Loop)

Per-post layout overrides for the Query Loop block. A query can
declare any number of *variants*; each variant has a matcher rule and
its own block template. At render time, each post in the loop is
checked against the variants and rendered with the winning variant —
falling back to the base Post Template when no variant matches.

## Why

Pre-Gutenberg PHP gave authors full control via `the_loop()` and CSS
`:nth-child` — make the first post a hero card while the rest render
as a compact list, alternate odd/even card styles, give sticky posts a
different layout. Gutenberg's Query Loop renders every post with the
same Post Template. Variants close that gap.

## Block: `artisanpack/post-variant`

Nested under `artisanpack/post-template`. The variant's `innerBlocks`
are the override template. Attributes:

| Attribute | Type | Notes |
|-----------|------|-------|
| `matcher` | object `{ kind, value }` | The rule. See "Matcher kinds" below. |
| `priority` | number (default `10`) | Tie-breaker inside a precedence tier. Lower wins. |
| `label` | string | Optional human label shown in the "Post Variants" panel. |

## Matcher kinds

| Kind | Examples | Where it resolves |
|------|----------|-------------------|
| `position` | `first`, `last`, `nth:3`, `range:4-6`, `instance:<n1>` | Compiled to a `position → variantOrder` map at save time. |
| `pattern` | `odd`, `even`, `every-nth:3`, `every-nth:3:start:2` | Same fast path as `position`. |
| `meta` | `sticky`, `featured`, `has-featured-image`, `author:42`, `taxonomy:category:news` | Walked at render time — needs the post object. |
| `custom` | `callback:<name>` | Resolved server-side via `apve_query_variant_match_<name>` filter. |

`every-nth:<step>` follows CSS `:nth-child(<step>n)` semantics — the
first match is at position `step` (e.g. `every-nth:3` → posts 3, 6,
9). Add `:start:<offset>` to shift (`every-nth:3:start:2` → posts 2,
5, 8).

## Precedence

For each post the renderer picks the first match in this fixed order:

1. `instance` — `position` matchers with the `instance:` prefix (canvas click-to-edit)
2. `position` — `first`, `last`, `nth`, `range`
3. `pattern` — `odd`, `even`, `every-nth`
4. `meta` — structural metadata
5. `custom` — callback hooks
6. Base `artisanpack/post-template` content

Inside a tier, ties break on `priority` (ascending) then document
order.

## Editor UX

A "Post Variants" panel in the query block's right-sidebar inspector
lists every variant. Per-row controls: select-to-edit (focuses the
variant's InnerBlocks in the canvas), move up/down (re-orders),
delete. "Add" buttons add a new variant of each kind.

When a variant is selected its own inspector exposes the matcher kind
+ value, an optional label, and the priority.

## Render-side resolution (hybrid)

Save-time compilation walks `position` and `pattern` rules and writes
a 0-based `index → variantOrder` map to the parent post-template's
`_compiledVariantMap` attribute. The server-side `QueryInliner` reads
that map first for O(1) lookup. When the map misses (older saves,
`meta`, `custom`), the resolver walks variants in precedence order and
evaluates matchers against the post.

Items rendered via a variant carry an extra `is-variant` class on
their `core/post-template-item` wrapper for downstream styling.

## Custom matchers (PHP filter)

Register a custom rule by name and resolve it server-side:

```php
use function ArtisanPackUI\Hooks\addFilter;

addFilter( 'apve_query_variant_match_premium', function ( bool $matches, object $post, array $context ): bool {
    // $context provides ['index' => int, 'total' => int]
    return true === ( $post->is_premium ?? false ) && 0 === $context['index'];
}, 10, 3 );
```

Then set the variant's matcher to `{ kind: 'custom', value: 'callback:premium' }`.

## Backward compatibility

Purely additive. Query loops with zero variants render identically to
before — the inliner's variant-detection step is a no-op when no
`artisanpack/post-variant` children are present, and the precompiled
map attribute is stripped from the rendered tree.

## Renderer parity

All three renderers register the block (the React/Vue components are
pass-through; Blade is a recursive render-block include) so the parity
check stays green, but in practice the server-side `QueryInliner`
strips variants from the rendered tree — they only ever materialize
as the per-post template clone.
