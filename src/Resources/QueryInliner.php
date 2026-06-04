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

use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
	) {}

	/**
	 * Walks `$tree` and returns a copy with every `core/query` block
	 * carrying expanded post-template instances under `innerBlocks`.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function inline( array $tree ): array
	{
		$out = [];

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out[] = $this->inlineBlock( $block );
		}

		return $out;
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

		// Recurse into inner blocks so nested queries (and queries
		// inside template parts that have already been inlined) still
		// get their loop expanded.
		if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = $this->inline( $block['innerBlocks'] );
		}

		return $block;
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

		// Expand: clone the iteration template once per result, stamp
		// _resolved* attributes, and wrap each iteration in a synthetic
		// `core/post-template-item` block. The renderers turn that into
		// an `<li>` so the parent `core/post-template` emits a single
		// `<ul>` containing N `<li>` items — matching upstream
		// Gutenberg's `<ul><li>...</li></ul>` shape rather than N
		// single-item lists.
		$expandedIterations = [];

		foreach ( $results as $post ) {
			if ( ! is_object( $post ) ) {
				continue;
			}

			$postId = isset( $post->id ) ? (int) $post->id : 0;
			$iterationBlocks = [];

			foreach ( $iterationTemplate as $tmplChild ) {
				if ( ! is_array( $tmplChild ) ) {
					continue;
				}

				$iterationBlocks[] = $this->postResolver->stampBlock(
					$this->cloneBlock( $tmplChild ),
					$post
				);
			}

			$expandedIterations[] = [
				'clientId'    => 'pti-' . $postId,
				'name'        => 'core/post-template-item',
				'attributes'  => [
					'postId'    => $postId,
					'className' => 'post-' . $postId
					. ' post'
					. ' type-' . ( isset( $post->post_type ) ? (string) $post->post_type : ( isset( $post->type ) ? (string) $post->type : 'post' ) )
					. ' status-' . ( isset( $post->status ) && is_object( $post->status ) && isset( $post->status->value ) ? (string) $post->status->value : ( isset( $post->status ) && is_string( $post->status ) ? $post->status : 'publish' ) ),
				],
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
				// query-family stamps.
				$out[] = $block;
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
