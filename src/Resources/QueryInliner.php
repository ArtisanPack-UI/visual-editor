<?php

/**
 * QueryInliner — pre-pass that replaces every `core/query` block in a
 * saved tree with one expanded copy of its inner blocks per result row.
 *
 * Sibling to {@see TemplatePartInliner} and {@see PatternInliner}: the
 * inliner runs once on the way into the renderer, so the per-block
 * partials/components do not need any new walk-over-results logic. Each
 * inner `core/post-template` is duplicated once per result; every
 * `core/post-*` block inside the duplicated subtree gets `_resolved*`
 * attributes stamped via {@see PostResolver} so the existing block
 * partials/components render against the right post.
 *
 * Resolution is best-effort:
 *
 *  - When no `QueryResolverContract` is bound (cms-framework absent and
 *    no host override), the original `core/query` block is left in
 *    place with a `_resolutionError = 'no-runtime'` marker. The
 *    renderers translate that to an empty render so the surrounding
 *    layout is unaffected.
 *  - When the resolver throws, the same fallback applies with
 *    `_resolutionError = 'resolver-error'`.
 *  - When the inner blocks contain no `core/post-template`, the inliner
 *    duplicates whatever is there so hosts that drop a custom template
 *    are not regressed by the pre-pass.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Resources;

use ArtisanPackUI\VisualEditor\Services\HostRelatedTermsResolver;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Throwable;

class QueryInliner
{
	public const ERROR_NO_RUNTIME     = 'no-runtime';
	public const ERROR_RESOLVER_ERROR = 'resolver-error';

	/**
	 * Per-block-slug map of resolver methods used by
	 * {@see stampQueryControls()} to populate the query family's
	 * `_resolved*` attributes. Matched on the unqualified slug so the
	 * pagination / no-results / title forks stamp regardless of whether
	 * the saved tree carries `core/*` or `artisanpack/*` names.
	 *
	 * @var array<string, string>
	 */
	protected const QUERY_CONTROL_RESOLVERS = [
		'query-no-results'           => 'stampNoResults',
		'query-pagination-next'      => 'stampPaginationNext',
		'query-pagination-previous'  => 'stampPaginationPrevious',
		'query-pagination-numbers'   => 'stampPaginationNumbers',
		'query-title'                => 'stampQueryTitle',
	];

	public function __construct(
		protected Container $container,
		protected PostResolver $postResolver,
		protected ?VariantResolver $variantResolver = null,
	) {
		if ( null === $this->variantResolver ) {
			$this->variantResolver = new VariantResolver();
		}
	}

	/**
	 * Host post passed through `inline()` so the related-posts expansion
	 * can derive taxonomy terms from it without re-threading the value
	 * through every internal helper.
	 */
	protected ?object $hostPost = null;

	/**
	 * Walks `$tree` and returns a copy with every `core/query` block
	 * carrying expanded post-template instances under `innerBlocks`.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 * @param  object|null                       $hostPost  Optional host
	 *                                                     post the page is
	 *                                                     resolving against.
	 *                                                     `artisanpack/related-posts`
	 *                                                     needs it to build
	 *                                                     a "same-taxonomy"
	 *                                                     query; the other
	 *                                                     expansions ignore
	 *                                                     it.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function inline( array $tree, ?object $hostPost = null ): array
	{
		$previousHostPost = $this->hostPost;
		$this->hostPost   = $hostPost;

		try {
			$out = [];

			foreach ( $tree as $block ) {
				if ( ! is_array( $block ) ) {
					continue;
				}

				$out[] = $this->inlineBlock( $block );
			}

			return $out;
		} finally {
			$this->hostPost = $previousHostPost;
		}
	}

	/**
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function inlineBlock( array $block ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';

		// `artisanpack/query` is the I6 fork of `core/query`; both share the
		// nested `query` attribute shape and inner-template structure, so the
		// same expansion applies to either namespace.
		if ( 'core/query' === $name || 'artisanpack/query' === $name ) {
			return $this->expandQuery( $block );
		}

		// Single-post content cluster (#501) — both blocks rely on the
		// same `QueryResolverContract` plumbing as `core/query` but
		// resolve a single entry or a related-by-taxonomy set rather
		// than a full pagination payload.
		if ( 'artisanpack/single-content' === $name ) {
			return $this->expandSingleContent( $block );
		}

		if ( 'artisanpack/related-posts' === $name ) {
			return $this->expandRelatedPosts( $block );
		}

		// Recurse into inner blocks so nested queries (and queries
		// inside template parts that have already been inlined) still
		// get their loop expanded.
		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = $this->inline( $block['innerBlocks'], $this->hostPost );
		}

		return $block;
	}

	/**
	 * Resolve the post the `artisanpack/single-content` block points at
	 * and re-stamp the saved inner-block tree against it via
	 * `PostResolver`. Falls back to the host post when the block has no
	 * `postId` set so authors can drop a "this entry" container into a
	 * single-post template without configuring the id by hand.
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function expandSingleContent( array $block ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] )
			? $block['attributes']
			: [];

		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $block['innerBlocks']
			: [];

		$postId   = isset( $attributes['postId'] ) ? (int) $attributes['postId'] : 0;
		$postType = isset( $attributes['postType'] ) && is_string( $attributes['postType'] )
			? trim( $attributes['postType'] )
			: 'post';

		if ( '' === $postType ) {
			$postType = 'post';
		}

		// Tolerate blocks saved before #501's variation pass moved to
		// singular slugs — `posts`, `pages`, `categories` come back from
		// the JSON as plural. `Str::singular` is the same inflector the
		// dev-app variations registry uses, so the round-trip stays
		// consistent (avoids the `types` -> `typ` class of mistakes
		// the prior hand-rolled `-es` strip was prone to).
		$postType = Str::singular( $postType );

		// Implicit "render against the host post" mode: no id picked, the
		// block sits in a single-post template, so PostResolver alone
		// stamps the inner tree against the host post downstream.
		if ( 0 === $postId ) {
			$attributes = array_merge( [ '_resolvedHasPost' => null !== $this->hostPost ], $attributes );

			return array_merge( $block, [
				'attributes'  => $attributes,
				'innerBlocks' => $inner,
			] );
		}

		if ( ! $this->container->bound( QueryResolverContract::class ) ) {
			return $this->markFailed( $block, self::ERROR_NO_RUNTIME );
		}

		try {
			/** @var QueryResolverContract $resolver */
			$resolver  = $this->container->make( QueryResolverContract::class );
			$paginator = $resolver->resolve( [
				'postType' => $postType,
				'include'  => [ $postId ],
				'perPage'  => 1,
			] );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->markFailed( $block, self::ERROR_RESOLVER_ERROR );
		}

		$items = $paginator->items();
		$post  = null;

		foreach ( $items as $candidate ) {
			if ( is_object( $candidate ) ) {
				$post = $candidate;
				break;
			}
		}

		if ( null === $post ) {
			return array_merge( $block, [
				'attributes'  => array_merge( [ '_resolvedHasPost' => false ], $attributes ),
				'innerBlocks' => [],
			] );
		}

		$stamped = [];

		foreach ( $inner as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$stamped[] = $this->postResolver->stampBlock( $this->cloneBlock( $child ), $post );
		}

		return array_merge( $block, [
			'attributes'  => array_merge( [ '_resolvedHasPost' => true ], $attributes ),
			'innerBlocks' => $stamped,
		] );
	}

	/**
	 * Resolve N related posts for the host post and clone the
	 * `artisanpack/related-posts` block's saved inner-block tree once
	 * per result with `_resolved*` stamps applied through `PostResolver`.
	 * Each iteration is wrapped in a synthetic `core/post-template-item`
	 * so the renderers can apply per-item layout / class-name attributes
	 * without re-implementing the iteration logic.
	 *
	 * Resolution defers to the bound `QueryResolverContract`; the related
	 * filter is "same post type, sharing at least one term in the post's
	 * primary taxonomy (categories / tags), excluding the host". Hosts
	 * that want a different relatedness rule can bind a custom resolver.
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function expandRelatedPosts( array $block ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] )
			? $block['attributes']
			: [];

		$inner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
			? $block['innerBlocks']
			: [];

		// Prefer the query-block-shape `query.perPage` so authors can
		// drive related-posts the same way they drive `artisanpack/query`;
		// fall back to the legacy `numPosts` attribute for posts saved
		// before the query-loop refactor (#501 follow-up).
		$query = isset( $attributes['query'] ) && is_array( $attributes['query'] )
			? $attributes['query']
			: [];

		if ( isset( $query['perPage'] ) && is_numeric( $query['perPage'] ) ) {
			$numPosts = (int) $query['perPage'];
		} elseif ( isset( $attributes['numPosts'] ) && is_numeric( $attributes['numPosts'] ) ) {
			$numPosts = (int) $attributes['numPosts'];
		} else {
			$numPosts = 3;
		}

		if ( $numPosts < 1 ) {
			$numPosts = 1;
		} elseif ( $numPosts > 10 ) {
			$numPosts = 10;
		}

		$order   = isset( $query['order'] ) && in_array( $query['order'], [ 'asc', 'desc' ], true )
			? $query['order']
			: 'desc';
		$orderBy = isset( $query['orderBy'] ) && is_string( $query['orderBy'] ) && '' !== $query['orderBy']
			? $query['orderBy']
			: 'date';
		$offset  = isset( $query['offset'] ) && is_numeric( $query['offset'] ) && (int) $query['offset'] >= 0
			? (int) $query['offset']
			: 0;

		// No host post in scope → nothing to compute "related" against.
		// Mark the block resolved-but-empty so the renderer drops the
		// wrapper instead of cloning the un-stamped iteration template.
		if ( null === $this->hostPost ) {
			return array_merge( $block, [
				'attributes'  => array_merge( [
					'_resolvedTotal' => 0,
					'_resolvedItems' => 0,
				], $attributes ),
				'innerBlocks' => [],
			] );
		}

		if ( ! $this->container->bound( QueryResolverContract::class ) ) {
			return $this->markFailed( $block, self::ERROR_NO_RUNTIME );
		}

		/** @var QueryResolverContract $resolver */
		$resolver = $this->container->make( QueryResolverContract::class );

		$host   = $this->hostPost;
		$helper = new HostRelatedTermsResolver( $resolver );

		$hostId                 = isset( $host->id ) ? (int) $host->id : 0;
		$hostType               = $helper->hostPostType( $host );
		[ $taxonomy, $termIds ] = $helper->hostRelatedTerms( $host );

		$queryAttrs = [
			'postType' => $hostType,
			'perPage'  => $numPosts,
			'offset'   => $offset,
			'order'    => $order,
			'orderBy'  => $orderBy,
			'exclude'  => 0 === $hostId ? [] : [ $hostId ],
			'taxonomy' => $taxonomy,
			'terms'    => $termIds,
		];

		try {
			$paginator = $resolver->resolve( $queryAttrs );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->markFailed( $block, self::ERROR_RESOLVER_ERROR );
		}

		$results = $paginator->items();

		// #601: when the saved tree nests an `artisanpack/post-template`,
		// run the iteration expansion under that template so per-post
		// variants, grid column/row spans, and masonry packing inherit
		// from the same path Query Loop uses. Pre-#601 saves with flat
		// inner blocks (no post-template wrapper) fall through to the
		// legacy expansion below.
		$postTemplateIndex = $this->findPostTemplateIndex( $inner );

		if ( null !== $postTemplateIndex ) {
			return $this->expandRelatedPostsUnderPostTemplate(
				$block,
				$attributes,
				$inner,
				$postTemplateIndex,
				$results,
				$paginator
			);
		}

		if ( [] === $results ) {
			return array_merge( $block, [
				'attributes'  => array_merge( [
					'_resolvedTotal' => $paginator->total(),
					'_resolvedItems' => 0,
				], $attributes ),
				'innerBlocks' => [],
			] );
		}

		$expanded = [];

		foreach ( $results as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$postId          = isset( $post->id ) ? (int) $post->id : 0;
			$iterationBlocks = [];

			foreach ( $inner as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}

				$iterationBlocks[] = $this->postResolver->stampBlock(
					$this->cloneBlock( $child ),
					$post
				);
			}

			$expanded[] = [
				'clientId'    => 'rp-' . $postId,
				'name'        => 'core/post-template-item',
				'attributes'  => [
					'postId'    => $postId,
					'className' => 'related-post post-' . $postId,
				],
				'innerBlocks' => $iterationBlocks,
			];
		}

		return array_merge( $block, [
			'attributes'  => array_merge( [
				'_resolvedTotal' => $paginator->total(),
				'_resolvedItems' => count( $expanded ),
			], $attributes ),
			'innerBlocks' => $expanded,
		] );
	}

	/**
	 * Find the index of the first `core/post-template` or
	 * `artisanpack/post-template` child in a saved inner-blocks array,
	 * or `null` when none is present. Shared between expandQuery and
	 * the #601 post-template path on expandRelatedPosts.
	 *
	 * @param  array<int, array<string, mixed>>  $inner
	 */
	protected function findPostTemplateIndex( array $inner ): ?int
	{
		foreach ( $inner as $i => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$name = isset( $child['name'] ) && is_string( $child['name'] ) ? $child['name'] : '';

			if ( 'core/post-template' === $name || 'artisanpack/post-template' === $name ) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * Related-posts iteration expansion that runs through a nested
	 * `artisanpack/post-template` (#601). Mirrors expandQuery's variant
	 * resolution + grid-span stamping so the editor canvas, server
	 * renderers, and the cross-renderer parity remain a single source of
	 * truth. Skips pagination / query-title / no-results stamping
	 * because the issue scopes those siblings as out of scope for
	 * related-posts.
	 *
	 * @param  array<string, mixed>              $block
	 * @param  array<string, mixed>              $attributes
	 * @param  array<int, array<string, mixed>>  $inner
	 * @param  iterable<int, object>             $results
	 *
	 * @return array<string, mixed>
	 */
	protected function expandRelatedPostsUnderPostTemplate(
		array $block,
		array $attributes,
		array $inner,
		int $postTemplateIndex,
		iterable $results,
		LengthAwarePaginator $paginator
	): array {
		$postTemplate      = $inner[ $postTemplateIndex ];
		$iterationTemplate = isset( $postTemplate['innerBlocks'] ) && is_array( $postTemplate['innerBlocks'] )
			? $postTemplate['innerBlocks']
			: [];

		$resultsList = is_array( $results ) ? array_values( $results ) : iterator_to_array( $results, false );

		// Zero-result path: keep the saved tree (so the editable
		// iteration template stays put) but clear the post-template's
		// inner blocks so the renderer emits an empty `<ul>` rather
		// than N copies of the un-stamped template.
		if ( [] === $resultsList ) {
			$emptyPostTemplate     = array_merge( $postTemplate, [ 'innerBlocks' => [] ] );
			$newInner              = $inner;
			$newInner[ $postTemplateIndex ] = $emptyPostTemplate;

			return array_merge( $block, [
				'attributes'  => array_merge( [
					'_resolvedTotal' => $paginator->total(),
					'_resolvedItems' => 0,
				], $attributes ),
				'innerBlocks' => $newInner,
			] );
		}

		[ $baseTemplate, $variantBlocks ] = $this->extractVariants( $iterationTemplate );

		$postTemplateAttrs = isset( $postTemplate['attributes'] ) && is_array( $postTemplate['attributes'] )
			? $postTemplate['attributes']
			: [];

		$compiledMap = isset( $postTemplateAttrs['_compiledVariantMap'] )
			&& is_array( $postTemplateAttrs['_compiledVariantMap'] )
				? $postTemplateAttrs['_compiledVariantMap']
				: [];

		$postTemplateIsGrid = $this->postTemplateLayoutIsGrid( $postTemplateAttrs );

		if ( null !== $this->variantResolver ) {
			$this->variantResolver->prime( $variantBlocks, $compiledMap, count( $resultsList ) );
		}

		$expandedIterations = [];

		foreach ( $resultsList as $loopIndex => $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$postId          = isset( $post->id ) ? (int) $post->id : 0;
			$iterationBlocks = [];

			$variantOrder = null !== $this->variantResolver
				? $this->variantResolver->resolve( $loopIndex, $post )
				: null;

			$activeTmpl = null !== $variantOrder && null !== $this->variantResolver
				? $this->variantResolver->innerBlocksFor( $variantOrder, $variantBlocks )
				: $baseTemplate;

			foreach ( $activeTmpl as $tmplChild ) {
				if ( ! is_array( $tmplChild ) ) {
					continue;
				}

				$iterationBlocks[] = $this->postResolver->stampBlock(
					$this->cloneBlock( $tmplChild ),
					$post
				);
			}

			$itemAttributes = [
				'postId'    => $postId,
				'className' => 'related-post post-' . $postId
					. ( null !== $variantOrder ? ' is-variant' : '' ),
			];

			if ( null !== $variantOrder && $postTemplateIsGrid ) {
				$variantBlock = $variantBlocks[ $variantOrder ] ?? null;

				if ( is_array( $variantBlock ) ) {
					$spans = $this->resolveVariantSpans( $variantBlock );

					if ( null !== $spans ) {
						$itemAttributes['_resolvedGridSpan'] = $spans;
					}
				}
			}

			$expandedIterations[] = [
				'clientId'    => 'rp-pti-' . $postId,
				'name'        => 'core/post-template-item',
				'attributes'  => $itemAttributes,
				'innerBlocks' => $iterationBlocks,
			];
		}

		$expandedPostTemplate = array_merge( $postTemplate, [
			'innerBlocks' => $expandedIterations,
		] );

		$newInner = $inner;
		$newInner[ $postTemplateIndex ] = $expandedPostTemplate;

		return array_merge( $block, [
			'attributes'  => array_merge( [
				'_resolvedTotal' => $paginator->total(),
				'_resolvedItems' => count( $expandedIterations ),
			], $attributes ),
			'innerBlocks' => $newInner,
		] );
	}

	/**
	 * Read the host post's post-type slug via the same conventions
	 * `PostResolver` uses (the `post_type` column, the `type` accessor,
	 * etc.). Defaults to `'post'` so the query resolver always gets a
	 * valid slug.
	 */
	protected function hostPostType( object $post ): string
	{
		foreach ( [ 'post_type', 'type' ] as $key ) {
			$value = $post->{$key} ?? null;

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return trim( $value );
			}
		}

		return 'post';
	}

	/**
	 * Best-effort lookup of the host post's term ids across whichever
	 * taxonomy the model exposes (`categories`, `tags`, generic `terms`).
	 * Returned as a flat int list so the query resolver receives a
	 * consistent shape regardless of host model.
	 *
	 * @return array<int, int>
	 */
	protected function hostTermIds( object $post ): array
	{
		$ids = [];

		foreach ( [ 'categories', 'tags', 'terms' ] as $relation ) {
			$collection = $post->{$relation} ?? null;

			if ( ! is_iterable( $collection ) ) {
				continue;
			}

			foreach ( $collection as $term ) {
				if ( is_object( $term ) && isset( $term->id ) ) {
					$ids[] = (int) $term->id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Resolve the host post's relatedness signal as a
	 * `[taxonomy, termIds]` pair so the related-posts query targets
	 * the right taxonomy. Prefers categories; falls back to tags;
	 * finally falls back to the generic `terms` collection scoped to
	 * `category` so tag-only posts still get a related-posts query
	 * that exercises the right index.
	 *
	 * @return array{0: string, 1: array<int, int>}
	 */
	protected function hostRelatedTerms( object $post ): array
	{
		$categoryIds = $this->termIdsFromRelation( $post, 'categories' );

		if ( [] !== $categoryIds ) {
			return [ 'category', $categoryIds ];
		}

		$tagIds = $this->termIdsFromRelation( $post, 'tags' );

		if ( [] !== $tagIds ) {
			return [ 'post_tag', $tagIds ];
		}

		return [ 'category', $this->hostTermIds( $post ) ];
	}

	/**
	 * Pluck integer term ids from one named relation on the post.
	 *
	 * @return array<int, int>
	 */
	protected function termIdsFromRelation( object $post, string $relation ): array
	{
		$collection = $post->{$relation} ?? null;

		if ( ! is_iterable( $collection ) ) {
			return [];
		}

		$ids = [];

		foreach ( $collection as $term ) {
			if ( is_object( $term ) && isset( $term->id ) ) {
				$ids[] = (int) $term->id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function expandQuery( array $block ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		// Upstream `core/query` nests the runtime attributes under a
		// `query` key. Pass that through to the resolver if present;
		// fall back to top-level attributes for hosts that flatten the
		// payload.
		$queryAttrs = isset( $attributes['query'] ) && is_array( $attributes['query'] )
			? $attributes['query']
			: $attributes;

		if ( ! $this->container->bound( QueryResolverContract::class ) ) {
			return $this->markFailed( $block, self::ERROR_NO_RUNTIME );
		}

		try {
			/** @var QueryResolverContract $resolver */
			$resolver  = $this->container->make( QueryResolverContract::class );
			$paginator = $resolver->resolve( $queryAttrs );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->markFailed( $block, self::ERROR_RESOLVER_ERROR );
		}

		$results    = $paginator->items();
		$queryInner = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];

		if ( [] === $queryInner ) {
			return array_merge( $block, [
				'innerBlocks' => [],
				'attributes'  => array_merge( $attributes, [
					'_resolvedTotal'  => $paginator->total(),
					'_resolvedItems'  => count( $results ),
				] ),
			] );
		}

		// When the resolver returned zero rows, the per-iteration
		// expansion below has nothing to clone. Walk the inner tree
		// anyway so any `artisanpack/query-no-results` block stays
		// alive (its inner-block tree is the empty-state markup) and
		// any pagination / title blocks get stamped with the
		// zero-result paginator state. Skip the iteration expansion
		// path and return early with the filtered tree.
		if ( [] === $results ) {
			$filtered = $this->filterAndStampControls( $queryInner, $paginator, $queryAttrs, true );

			return array_merge( $block, [
				'innerBlocks' => $filtered,
				'attributes'  => array_merge( $attributes, [
					'_resolvedTotal' => $paginator->total(),
					'_resolvedItems' => 0,
				] ),
			] );
		}

		// Find the post-template child block. Its inner blocks are the
		// per-iteration template that gets cloned once per result.
		// Everything else (pagination, no-results) stays alongside it.
		$postTemplateIndex = null;
		$iterationTemplate = [];

		foreach ( $queryInner as $i => $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$childName = isset( $child['name'] ) && is_string( $child['name'] ) ? $child['name'] : '';

			if ( 'core/post-template' === $childName || 'artisanpack/post-template' === $childName ) {
				$postTemplateIndex = $i;
				$iterationTemplate = isset( $child['innerBlocks'] ) && is_array( $child['innerBlocks'] )
					? $child['innerBlocks']
					: [];
				break;
			}
		}

		// No post-template found — fall back to cloning whatever is
		// there so hosts that drop a custom template without the
		// post-template wrapper are not regressed.
		if ( null === $postTemplateIndex ) {
			return $this->expandFlat( $block, $attributes, $queryInner, $results, $paginator );
		}

		// Variants (#591): an `artisanpack/post-variant` child of the
		// post-template is NOT part of the per-iteration template —
		// it's a per-post override template. Pull variants out of the
		// iteration template, prime the resolver, and the loop below
		// asks the resolver per post which template to use.
		[ $baseTemplate, $variantBlocks ] = $this->extractVariants( $iterationTemplate );

		$postTemplateAttrs = isset( $queryInner[ $postTemplateIndex ]['attributes'] )
			&& is_array( $queryInner[ $postTemplateIndex ]['attributes'] )
				? $queryInner[ $postTemplateIndex ]['attributes']
				: [];

		$compiledMap = isset( $postTemplateAttrs['_compiledVariantMap'] )
			&& is_array( $postTemplateAttrs['_compiledVariantMap'] )
				? $postTemplateAttrs['_compiledVariantMap']
				: [];

		$postTemplateIsGrid = $this->postTemplateLayoutIsGrid( $postTemplateAttrs );

		$resultsList = is_array( $results ) ? array_values( $results ) : iterator_to_array( $results, false );

		$this->variantResolver->prime( $variantBlocks, $compiledMap, count( $resultsList ) );

		// Expand: clone the iteration template once per result, stamp
		// _resolved* attributes, and wrap each iteration in a synthetic
		// `core/post-template-item` block. The renderers turn that into
		// an `<li>` so the parent `core/post-template` emits a single
		// `<ul>` containing N `<li>` items — matching upstream
		// Gutenberg's `<ul><li>...</li></ul>` shape rather than N
		// single-item lists.
		$expandedIterations = [];

		foreach ( $resultsList as $loopIndex => $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$postId          = isset( $post->id ) ? (int) $post->id : 0;
			$iterationBlocks = [];

			$variantOrder = $this->variantResolver->resolve( $loopIndex, $post );
			$activeTmpl   = null !== $variantOrder
				? $this->variantResolver->innerBlocksFor( $variantOrder, $variantBlocks )
				: $baseTemplate;

			foreach ( $activeTmpl as $tmplChild ) {
				if ( ! is_array( $tmplChild ) ) {
					continue;
				}

				$iterationBlocks[] = $this->postResolver->stampBlock(
					$this->cloneBlock( $tmplChild ),
					$post
				);
			}

			$itemAttributes = [
				'postId'    => $postId,
				'className' => 'post-' . $postId
				. ' post'
				. ( null !== $variantOrder ? ' is-variant' : '' )
				. ' type-' . ( isset( $post->post_type ) ? (string) $post->post_type : ( isset( $post->type ) ? (string) $post->type : 'post' ) )
				. ' status-' . ( isset( $post->status ) && is_object( $post->status ) && isset( $post->status->value ) ? (string) $post->status->value : ( isset( $post->status ) && is_string( $post->status ) ? $post->status : 'publish' ) ),
			];

			if ( null !== $variantOrder && $postTemplateIsGrid ) {
				$variantBlock = $variantBlocks[ $variantOrder ] ?? null;

				if ( is_array( $variantBlock ) ) {
					$spans = $this->resolveVariantSpans( $variantBlock );

					if ( null !== $spans ) {
						$itemAttributes['_resolvedGridSpan'] = $spans;
					}
				}
			}

			$expandedIterations[] = [
				'clientId'    => 'pti-' . $postId,
				'name'        => 'core/post-template-item',
				'attributes'  => $itemAttributes,
				'innerBlocks' => $iterationBlocks,
			];
		}

		// Replace post-template's inner blocks with the expanded
		// iterations; preserve its own attributes (layout, columns).
		$expandedTemplate = array_merge( $queryInner[ $postTemplateIndex ], [
			'innerBlocks' => $expandedIterations,
		] );

		$newQueryInner = $queryInner;
		$newQueryInner[ $postTemplateIndex ] = $expandedTemplate;

		// Drop `query-no-results` (results are non-empty) and stamp
		// pagination / query-title controls on every remaining
		// sibling. The post-template itself is filtered through too
		// — recursion is a no-op there since post-template children
		// already went through PostResolver.
		$newQueryInner = $this->filterAndStampControls( $newQueryInner, $paginator, $queryAttrs, false );

		return array_merge( $block, [
			'innerBlocks' => $newQueryInner,
			'attributes'  => array_merge( $attributes, [
				'_resolvedTotal' => $paginator->total(),
				'_resolvedItems' => count( $results ),
			] ),
		] );
	}

	/**
	 * Walk the query's inner-block tree once: drop any
	 * `query-no-results` block that does not match the current empty /
	 * non-empty state, and stamp the query family's `_resolved*`
	 * attributes onto every pagination / query-title block.
	 *
	 * Runs after the post-template expansion so the new pagination /
	 * no-results / title blocks can sit anywhere in the saved tree
	 * (including nested inside a group) and still get their state. The
	 * post-template subtree itself is not walked into — its iteration
	 * items already carry the per-post `_resolved*` from PostResolver.
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 * @param  array<string, mixed>              $queryAttrs
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function filterAndStampControls( array $blocks, LengthAwarePaginator $paginator, array $queryAttrs, bool $isEmpty ): array
	{
		$out = [];

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
			$slug = str_contains( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;

			// query-no-results survives only when the resolver returned
			// zero rows; the empty-state markup is its inner-block
			// tree. When the query has results, drop the wrapper.
			if ( 'query-no-results' === $slug ) {
				if ( ! $isEmpty ) {
					continue;
				}

				// Keep the wrapper; its inner-block tree is the
				// author-configured empty-state markup. The inner
				// tree is intentionally NOT recursed into — its
				// blocks (paragraphs, headings, etc.) don't carry
				// query-family stamps. Stamp the standard
				// `_resolved*` defaults (total / current page) so
				// downstream consumers can read the paginator
				// state off the wrapper attributes.
				$out[] = $this->stampQueryControls( $block, $paginator, $queryAttrs );
				continue;
			}

			// When the query resolved to zero rows, the post-template
			// branch above never runs — but the un-expanded template
			// children would still be sitting on this block from the
			// saved tree. Clear them so the renderer emits an empty
			// `<ul>` (or nothing, depending on its own short-circuit)
			// rather than rendering N copies of the un-stamped
			// template.
			if ( $isEmpty && 'post-template' === $slug ) {
				$out[] = array_merge( $block, [
					'innerBlocks' => [],
				] );
				continue;
			}

			$block = $this->stampQueryControls( $block, $paginator, $queryAttrs );

			// Recurse into wrappers (artisanpack/query-pagination,
			// group, etc.) so their pagination / title children get
			// stamped too. Skip the post-template subtree — its
			// expanded items already carry per-post `_resolved*`
			// keys and aren't query controls.
			if (
				'post-template' !== $slug
				&& isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] )
				&& [] !== $block['innerBlocks']
			) {
				$block['innerBlocks'] = $this->filterAndStampControls(
					$block['innerBlocks'],
					$paginator,
					$queryAttrs,
					$isEmpty
				);
			}

			$out[] = $block;
		}

		return $out;
	}

	/**
	 * Stamp the query family `_resolved*` attributes on a single block
	 * when its slug matches one of the registered control resolvers.
	 *
	 * @param  array<string, mixed>  $block
	 * @param  array<string, mixed>  $queryAttrs
	 *
	 * @return array<string, mixed>
	 */
	protected function stampQueryControls( array $block, LengthAwarePaginator $paginator, array $queryAttrs ): array
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
		$slug = str_contains( $name, '/' ) ? substr( $name, strpos( $name, '/' ) + 1 ) : $name;

		$method = self::QUERY_CONTROL_RESOLVERS[ $slug ] ?? null;

		if ( null === $method ) {
			return $block;
		}

		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		// Defaults are merged behind the existing attributes so the
		// host's `_resolved*` overrides win — mirrors PostResolver.
		/** @var array<string, mixed> $defaults */
		$defaults = $this->{$method}( $paginator, $queryAttrs, $attributes );

		return array_merge( $block, [
			'attributes' => array_merge( $defaults, $attributes ),
		] );
	}

	/**
	 * @param  array<string, mixed>  $queryAttrs
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampNoResults( LengthAwarePaginator $paginator, array $queryAttrs, array $attributes ): array
	{
		// query-no-results is gated by `filterAndStampControls()`, not
		// by attribute stamping — when this method runs the block
		// already survived the gate, so it only needs the standard
		// total / current-page snapshot for any downstream consumer.
		return [
			'_resolvedTotal'       => $paginator->total(),
			'_resolvedCurrentPage' => $paginator->currentPage(),
		];
	}

	/**
	 * @param  array<string, mixed>  $queryAttrs
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampPaginationNext( LengthAwarePaginator $paginator, array $queryAttrs, array $attributes ): array
	{
		$nextUrl = $paginator->nextPageUrl();

		return [
			'_resolvedNextPageUrl'  => is_string( $nextUrl ) ? $nextUrl : '',
			'_resolvedCurrentPage'  => $paginator->currentPage(),
			'_resolvedTotalPages'   => $paginator->lastPage(),
		];
	}

	/**
	 * @param  array<string, mixed>  $queryAttrs
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampPaginationPrevious( LengthAwarePaginator $paginator, array $queryAttrs, array $attributes ): array
	{
		$previousUrl = $paginator->previousPageUrl();

		return [
			'_resolvedPreviousPageUrl' => is_string( $previousUrl ) ? $previousUrl : '',
			'_resolvedCurrentPage'     => $paginator->currentPage(),
			'_resolvedTotalPages'      => $paginator->lastPage(),
		];
	}

	/**
	 * Build a `[ { number, url }, ... ]` list of every page in the
	 * resolved range. V1 emits the full range; honouring the block's
	 * `midSize` attribute (windowed view around the current page) is
	 * tracked as a follow-up customization pass.
	 *
	 * @param  array<string, mixed>  $queryAttrs
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampPaginationNumbers( LengthAwarePaginator $paginator, array $queryAttrs, array $attributes ): array
	{
		$lastPage = max( 1, (int) $paginator->lastPage() );
		$current  = max( 1, (int) $paginator->currentPage() );

		$pages = [];
		for ( $i = 1; $i <= $lastPage; $i++ ) {
			$pages[] = [
				'number' => $i,
				'url'    => $paginator->url( $i ),
			];
		}

		return [
			'_resolvedPageNumbers'  => $pages,
			'_resolvedCurrentPage'  => $current,
			'_resolvedTotalPages'   => $lastPage,
		];
	}

	/**
	 * Resolve the query-title display string from the configured
	 * `type` attribute and the query's `postType` / `search` payload.
	 * Archive-context resolution (term archive, taxonomy archive) is
	 * deferred — the V1 resolver does not carry archive metadata, so
	 * `type: archive` resolves to a generic "Archive" label that
	 * hosts can override via the block's `_resolvedQueryTitle` attr.
	 *
	 * @param  array<string, mixed>  $queryAttrs
	 * @param  array<string, mixed>  $attributes
	 *
	 * @return array<string, mixed>
	 */
	protected function stampQueryTitle( LengthAwarePaginator $paginator, array $queryAttrs, array $attributes ): array
	{
		$type           = isset( $attributes['type'] ) && is_string( $attributes['type'] ) ? $attributes['type'] : '';
		$showSearchTerm = ! isset( $attributes['showSearchTerm'] ) || (bool) $attributes['showSearchTerm'];

		$resolved = '';

		switch ( $type ) {
			case 'search':
				$search = isset( $queryAttrs['search'] ) && is_string( $queryAttrs['search'] ) ? trim( $queryAttrs['search'] ) : '';

				if ( $showSearchTerm && '' !== $search ) {
					$resolved = trans( 'Search results for: ":search"', [ 'search' => $search ] );
				} else {
					$resolved = trans( 'Search results' );
				}
				break;

			case 'post-type':
				$postType = isset( $queryAttrs['postType'] ) && is_string( $queryAttrs['postType'] ) ? $queryAttrs['postType'] : '';
				$resolved = $this->postTypeLabel( $postType );
				break;

			case 'archive':
				$resolved = trans( 'Archive' );
				break;

			default:
				$resolved = '';
				break;
		}

		return [
			'_resolvedQueryTitle' => $resolved,
		];
	}

	/**
	 * Translate a post-type slug into a human-readable label. Hosts
	 * with custom post types can override the resolved label by
	 * stamping `_resolvedQueryTitle` directly via the renderer
	 * adapter; this default covers the built-in `post` / `page`
	 * slugs the V1 query resolver supports.
	 */
	protected function postTypeLabel( string $postType ): string
	{
		switch ( $postType ) {
			case 'page':
				return trans( 'Pages' );
			case '':
			case 'post':
				return trans( 'Posts' );
			default:
				return trans( ucfirst( str_replace( [ '-', '_' ], ' ', $postType ) ) );
		}
	}

	/**
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	/**
	 * Legacy flat expansion for query blocks without a post-template child.
	 *
	 * @param  array<string, mixed>               $block
	 * @param  array<string, mixed>               $attributes
	 * @param  array<int, array<string, mixed>>   $template
	 * @param  array<int, object>                 $results
	 */
	protected function expandFlat( array $block, array $attributes, array $template, array $results, \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator ): array
	{
		$expanded = [];

		foreach ( $results as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			foreach ( $template as $child ) {
				if ( ! is_array( $child ) ) {
					continue;
				}

				$expanded[] = $this->postResolver->stampBlock(
					$this->cloneBlock( $child ),
					$post
				);
			}
		}

		return array_merge( $block, [
			'innerBlocks' => $expanded,
			'attributes'  => array_merge( $attributes, [
				'_resolvedTotal' => $paginator->total(),
				'_resolvedItems' => count( $results ),
			] ),
		] );
	}

	/**
	 * Detect whether a post-template's saved attributes describe a
	 * grid layout. Three shapes are supported so the inliner stays
	 * tolerant of variation across the ArtisanPack post-template
	 * (plain `layout` string), upstream `core/post-template` mirrors
	 * (object-form `layout = ['type' => 'grid']`), and Gutenberg's
	 * generic block-supports layout system (sibling `layoutType`
	 * attribute).
	 *
	 * @param  array<string, mixed>  $postTemplateAttrs
	 */
	protected function postTemplateLayoutIsGrid( array $postTemplateAttrs ): bool
	{
		$layout = $postTemplateAttrs['layout'] ?? null;

		if ( is_string( $layout ) && 'grid' === $layout ) {
			return true;
		}

		if ( is_array( $layout ) && isset( $layout['type'] ) && 'grid' === $layout['type'] ) {
			return true;
		}

		$layoutType = $postTemplateAttrs['layoutType'] ?? null;

		return is_string( $layoutType ) && 'grid' === $layoutType;
	}

	/**
	 * Read the matched variant's grid span attributes — both base
	 * values and any per-breakpoint responsive overrides — and
	 * normalize them into a flat shape the renderers can consume
	 * without touching the variant block tree directly.
	 *
	 * Returns `null` when the variant carries no span data (defaults
	 * 1×1 with no breakpoint overrides) so the renderers can skip the
	 * extra class emission for the common case.
	 *
	 * @param  array<string, mixed>  $variantBlock
	 *
	 * @return array{
	 *     columns: array<string, int>,
	 *     rows: array<string, int>
	 * }|null
	 */
	protected function resolveVariantSpans( array $variantBlock ): ?array
	{
		$attributes = isset( $variantBlock['attributes'] ) && is_array( $variantBlock['attributes'] )
			? $variantBlock['attributes']
			: [];

		$baseColumns = $this->clampSpanValue( $attributes['gridColumnSpan'] ?? null, 1 );
		$baseRows    = $this->clampSpanValue( $attributes['gridRowSpan'] ?? null, 1 );

		$columns = [ 'base' => $baseColumns ];
		$rows    = [ 'base' => $baseRows ];

		$responsive = isset( $attributes['responsive'] ) && is_array( $attributes['responsive'] )
			? $attributes['responsive']
			: [];

		$columnOverrides = isset( $responsive['gridColumnSpan'] ) && is_array( $responsive['gridColumnSpan'] )
			? $responsive['gridColumnSpan']
			: [];

		foreach ( $columnOverrides as $bp => $value ) {
			if ( ! is_string( $bp ) || 'base' === $bp ) {
				continue;
			}

			if ( null === $value ) {
				continue;
			}

			$columns[ $bp ] = $this->clampSpanValue( $value, $baseColumns );
		}

		$rowOverrides = isset( $responsive['gridRowSpan'] ) && is_array( $responsive['gridRowSpan'] )
			? $responsive['gridRowSpan']
			: [];

		foreach ( $rowOverrides as $bp => $value ) {
			if ( ! is_string( $bp ) || 'base' === $bp ) {
				continue;
			}

			if ( null === $value ) {
				continue;
			}

			$rows[ $bp ] = $this->clampSpanValue( $value, $baseRows );
		}

		$hasOverrides = count( $columns ) > 1 || count( $rows ) > 1;

		if ( 1 === $baseColumns && 1 === $baseRows && ! $hasOverrides ) {
			return null;
		}

		return [
			'columns' => $columns,
			'rows'    => $rows,
		];
	}

	/**
	 * Clamp a single span value to the renderer's supported 1..12 range
	 * so a malformed save never produces a CSS class that has no
	 * matching rule in the stylesheet.
	 *
	 * @param  mixed  $value
	 */
	protected function clampSpanValue( mixed $value, int $fallback ): int
	{
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}

		$int = (int) $value;

		if ( $int < 1 ) {
			return 1;
		}

		if ( $int > 12 ) {
			return 12;
		}

		return $int;
	}

	/**
	 * Split a post-template's inner-block tree into the base iteration
	 * template (everything that is NOT a `post-variant`) and the list
	 * of `artisanpack/post-variant` overrides. Variants stay in their
	 * saved document order so the resolver's `order` index lines up
	 * with what the editor's `_compiledVariantMap` was built against.
	 *
	 * @param  array<int, array<string, mixed>>  $template
	 *
	 * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
	 */
	protected function extractVariants( array $template ): array
	{
		$base     = [];
		$variants = [];

		foreach ( $template as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}

			$childName = isset( $child['name'] ) && is_string( $child['name'] ) ? $child['name'] : '';

			if ( 'artisanpack/post-variant' === $childName ) {
				$variants[] = $child;
				continue;
			}

			$base[] = $child;
		}

		return [ $base, $variants ];
	}

	protected function markFailed( array $block, string $reason ): array
	{
		$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

		return array_merge( $block, [
			'attributes'  => array_merge( $attributes, [ '_resolutionError' => $reason ] ),
			'innerBlocks' => [],
		] );
	}

	/**
	 * Deep-clone a block array so each per-result expansion has its own
	 * mutable copy of the template subtree. Block arrays are pure data,
	 * so a recursive copy of the array structure is enough.
	 *
	 * @param  array<string, mixed>  $block
	 *
	 * @return array<string, mixed>
	 */
	protected function cloneBlock( array $block ): array
	{
		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$inner = [];

			foreach ( $block['innerBlocks'] as $child ) {
				if ( is_array( $child ) ) {
					$inner[] = $this->cloneBlock( $child );
				}
			}

			$block['innerBlocks'] = $inner;
		}

		return $block;
	}
}
