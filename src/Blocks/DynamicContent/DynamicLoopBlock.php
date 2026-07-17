<?php

/**
 * `artisanpack/dynamic-loop` server-side renderer.
 *
 * Iterates a Dynamic Content collection source (e.g. `team`) and
 * renders the block's inner-block template once per record. Bindings
 * inside the template with tokens like `team.name` are re-resolved
 * per iteration by walking the tree and rewriting the `token` arg to
 * `team[N].field`.
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

		$rendererClass = '\\ArtisanPackUI\\VisualEditorRendererBlade\\BlockRenderer';

		if ( ! class_exists( $rendererClass ) ) {
			return '';
		}

		$renderer = app( $rendererClass );
		$out      = '';
		$limit    = min( count( $records ), self::MAX_RECORDS );

		for ( $i = 0; $i < $limit; $i++ ) {
			$context = new BindingContext(
				null,
				[],
				[ DynamicContentSource::EXTRAS_INDEX_KEY => [ $collectionSlug => $i ] ]
			);

			try {
				$resolved = $this->bindingResolver->resolve( $template, $context );
				$out     .= $renderer->render( $resolved );
			} catch ( Throwable $e ) {
				report( $e );
			}
		}

		return $out;
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
			$safe
		);
	}
}
