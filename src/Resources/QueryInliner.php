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
use Throwable;

class QueryInliner
{
	public const ERROR_NO_RUNTIME     = 'no-runtime';
	public const ERROR_RESOLVER_ERROR = 'resolver-error';

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

		if ( [] === $results || [] === $queryInner ) {
			return array_merge( $block, [
				'innerBlocks' => [],
				'attributes'  => array_merge( $attributes, [
					'_resolvedTotal'  => $paginator->total(),
					'_resolvedItems'  => count( $results ),
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
		// _resolved* attributes, and wrap each iteration in an <li>
		// block with post-specific classes.
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
				'clientId'    => 'qi-' . $postId,
				'name'        => '_query-iteration',
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

		return array_merge( $block, [
			'innerBlocks' => $newQueryInner,
			'attributes'  => array_merge( $attributes, [
				'_resolvedTotal' => $paginator->total(),
				'_resolvedItems' => count( $results ),
			] ),
		] );
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
