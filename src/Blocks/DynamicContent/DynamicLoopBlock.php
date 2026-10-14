<?php

/**
 * `artisanpack/dynamic-loop` server-side renderer.
 *
 * Iterates a Dynamic Content collection source (e.g. `team`) and
 * renders the block's inner-block template once per record. Bindings
 * inside the template with tokens like `team.name` are re-resolved
 * per iteration by walking the tree and rewriting the `token` arg to
 * `team[N].field`. Each row's bindings sidecars are stripped after
 * resolving so the renderer's host-context pass leaves the row alone.
 *
 * A loop nested inside a row is left unresolved by that pass. The row
 * stamps its index map onto the nested loop instead, and the nested
 * loop merges it into each of its own rows, so tokens for both the
 * inner and the outer collection resolve to the current record (#861).
 *
 * Zero-record collections emit an empty string. Missing collections
 * emit a warning placeholder identical in shape to the snippet-cycle
 * placeholder so ops sees the reference is broken.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\DynamicContent;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Blocks\WantsInnerBlocks;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Services\Bindings\Sources\DynamicContentSource;
use ArtisanPackUI\VisualEditor\Support\BlockShape;
use Throwable;

class DynamicLoopBlock extends DynamicBlock implements WantsInnerBlocks
{
	public const BLOCK_NAME = 'artisanpack/dynamic-loop';

	/**
	 * Hard cap on records iterated per render so a mistakenly-configured
	 * loop over a large collection cannot walk the entire dataset.
	 *
	 * @since 1.4.0
	 */
	public const MAX_RECORDS = 500;

	/**
	 * Attribute an enclosing loop stamps on a nested loop to pass down
	 * the current row's collection → index map (#861).
	 *
	 * @since 1.13.1
	 */
	public const PARENT_INDEX_ATTR = '_dcParentIndex';

	public function __construct( protected BindingResolver $bindingResolver )
	{
	}

	public function name(): string
	{
		return self::BLOCK_NAME;
	}

	public function render( array $attrs )
	{
		return $this->renderWithInner( $attrs, [] );
	}

	public function renderWithInner( array $attrs, array $innerBlocks )
	{
		$collectionSlug = is_string( $attrs['collection'] ?? null ) ? trim( $attrs['collection'] ) : '';

		if ( '' === $collectionSlug ) {
			return '';
		}

		// Template comes from the block's inner tree (authored inline in
		// the editor), with a fallback to a persisted `_template` attr
		// so bespoke integrations that store the template as JSON still
		// work.
		$template = [] !== $innerBlocks
			? $innerBlocks
			: ( is_array( $attrs['_template'] ?? null ) ? $attrs['_template'] : [] );

		if ( [] === $template ) {
			return '';
		}

		$records = $this->fetchCollection( $collectionSlug );

		if ( null === $records ) {
			return $this->missingCollectionPlaceholder( $collectionSlug );
		}

		if ( [] === $records ) {
			return '<div class="ve-dynamic-loop-empty" role="note">No records to display.</div>';
		}

		$rendererClass = 'ArtisanPackUI\\VisualEditorRendererBlade\\BlockRenderer';

		if ( ! class_exists( $rendererClass ) ) {
			return '';
		}

		$renderer    = app( $rendererClass );
		$out         = '';
		$limit       = min( count( $records ), self::MAX_RECORDS );
		$parentIndex = $this->parentIndex( $attrs );

		for ( $i = 0; $i < $limit; $i++ ) {
			$indexMap = array_merge( $parentIndex, [ $collectionSlug => $i ] );
			$context  = new BindingContext(
				null,
				[],
				[ DynamicContentSource::EXTRAS_INDEX_KEY => $indexMap ],
			);

			try {
				// Strip the bindings sidecars once this row has resolved:
				// the nested render() reuses the host's binding context,
				// and without the strip it would resolve the row again
				// without its index (#854). Nested loops keep their
				// template's sidecars so they can resolve it per row
				// with this row's indexes stamped on (#861).
				$resolved = $this->bindingResolver->resolveAndStrip( $template, $context, [ self::BLOCK_NAME ] );
				$out .= $renderer->render( $this->stampNestedLoops( $resolved, $indexMap ) );
			} catch ( Throwable $e ) {
				report( $e );
			}
		}

		return $out;
	}

	/**
	 * Read the index map an enclosing loop stamped on this block,
	 * keeping only `slug => non-negative int` entries.
	 *
	 * @since 1.13.1
	 *
	 * @param  array<string, mixed>  $attrs
	 *
	 * @return array<string, int>
	 */
	protected function parentIndex( array $attrs ): array
	{
		$stamped = $attrs[ self::PARENT_INDEX_ATTR ] ?? null;

		if ( ! is_array( $stamped ) ) {
			return [];
		}

		return array_filter(
			$stamped,
			static fn ( $index, $slug ): bool => is_string( $slug ) && is_int( $index ) && $index >= 0,
			ARRAY_FILTER_USE_BOTH,
		);
	}

	/**
	 * Stamp the row's index map onto every loop nested in the row. The
	 * walk stops at each nested loop: deeper loops get stamped when that
	 * loop renders its own rows.
	 *
	 * @since 1.13.1
	 *
	 * @param  array<int, array<string, mixed>>  $blocks
	 * @param  array<string, int>                $indexMap
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function stampNestedLoops( array $blocks, array $indexMap ): array
	{
		foreach ( $blocks as $key => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			if ( self::BLOCK_NAME === ( $block['name'] ?? null ) ) {
				[ $attrKey, $blockAttrs ] = BlockShape::readAttrs( $block );

				$blockAttrs[ self::PARENT_INDEX_ATTR ] = $indexMap;
				$blocks[ $key ][ $attrKey ]            = $blockAttrs;

				continue;
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && [] !== $block['innerBlocks'] ) {
				$blocks[ $key ]['innerBlocks'] = $this->stampNestedLoops( $block['innerBlocks'], $indexMap );
			}
		}

		return $blocks;
	}

	/**
	 * @return list<array<string, mixed>>|null  null → source missing / cms-framework absent.
	 *
	 * @since 1.4.0
	 */
	protected function fetchCollection( string $slug ): ?array
	{
		$accessorClass = 'ArtisanPackUI\\CMSFramework\\Modules\\DynamicContent\\Services\\DynamicContentAccessor';

		if ( ! class_exists( $accessorClass ) && ! app()->bound( $accessorClass ) ) {
			return null;
		}

		try {
			/** @var object $accessor */
			$accessor = app( $accessorClass );
			$records  = $accessor->collection( $slug );
		} catch ( Throwable $e ) {
			report( $e );

			return null;
		}

		return is_array( $records ) ? array_values( $records ) : null;
	}

	protected function missingCollectionPlaceholder( string $slug ): string
	{
		$safe = htmlspecialchars( $slug, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return sprintf(
			'<div class="ve-dynamic-loop-missing" role="note">Missing collection: "%s"</div>',
			$safe,
		);
	}
}
