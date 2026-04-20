<?php

/**
 * Server-side Blade renderer for visual editor block trees.
 *
 * Walks a `{ clientId, name, attributes, innerBlocks[] }` block tree and
 * produces HTML by looking up per-block Blade partials or invoking the
 * registered {@see \ArtisanPackUI\VisualEditor\Blocks\DynamicBlock::render()}
 * for dynamic blocks. Partials receive the block attributes plus a pre-rendered
 * `$innerBlocksHtml` string so containers can splice children into place.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade;

use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Stringable;
use Throwable;

class BlockRenderer
{
	public function __construct(
		protected ViewFactory $views,
		protected DynamicBlockRegistry $dynamicBlocks,
	) {
	}

	/**
	 * Render the given block tree to an HTML string.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, array<string, mixed>>  $tree  Block tree produced by the editor.
	 */
	public function render( array $tree ): string
	{
		$out = '';

		foreach ( $tree as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$out .= $this->renderBlock( $block );
		}

		return $out;
	}

	/**
	 * Render a single block, recursing through `innerBlocks` as needed.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $block  A single block node.
	 */
	public function renderBlock( array $block ): string
	{
		$name = isset( $block['name'] ) && is_string( $block['name'] ) ? trim( $block['name'] ) : '';

		if ( '' === $name ) {
			return '';
		}

		$attributes      = $this->normalizeAttributes( $block['attributes'] ?? [] );
		$innerBlocksHtml = $this->render( $this->normalizeInnerBlocks( $block['innerBlocks'] ?? [] ) );

		if ( $this->dynamicBlocks->has( $name ) ) {
			return $this->renderDynamic( $name, $attributes, $innerBlocksHtml );
		}

		return $this->renderStatic( $name, $attributes, $innerBlocksHtml );
	}

	/**
	 * Resolve the registered {@see DynamicBlock} and coerce its return value
	 * to an HTML string.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	protected function renderDynamic( string $name, array $attributes, string $innerBlocksHtml ): string
	{
		$block = $this->dynamicBlocks->get( $name );

		if ( null === $block ) {
			return $this->renderStatic( $name, $attributes, $innerBlocksHtml );
		}

		try {
			$validated = $block->validateAttrs( $attributes );
			$result    = $block->render( $validated );

			return $this->coerceToString( $result );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->renderStatic( $name, $attributes, $innerBlocksHtml );
		}
	}

	/**
	 * Resolve the block's Blade partial and render it with the block's
	 * attributes + pre-rendered children.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<string, mixed>  $attributes
	 */
	protected function renderStatic( string $name, array $attributes, string $innerBlocksHtml ): string
	{
		$partial = $this->resolvePartial( $name );

		if ( null === $partial ) {
			return $this->renderFallback( $name, $innerBlocksHtml );
		}

		$data = [
			'blockName'       => $name,
			'attributes'      => $attributes,
			'attrs'           => $attributes,
			'innerBlocksHtml' => $innerBlocksHtml,
		];

		try {
			return $this->coerceToString( $this->views->make( $partial, $data ) );
		} catch ( Throwable $e ) {
			report( $e );

			return $this->renderFallback( $name, $innerBlocksHtml );
		}
	}

	/**
	 * Resolve the Blade view name for a block.
	 *
	 * Tries `visual-editor-renderer-blade::blocks.{namespace}.{block}` first
	 * so host apps can override individual partials via
	 * `resources/views/vendor/visual-editor-renderer-blade/blocks/`.
	 *
	 * @since 1.0.0
	 */
	protected function resolvePartial( string $name ): ?string
	{
		[ $namespace, $block ] = $this->splitBlockName( $name );

		if ( '' === $namespace || '' === $block ) {
			return null;
		}

		$view = sprintf( 'visual-editor-renderer-blade::blocks.%s.%s', $namespace, $block );

		return $this->views->exists( $view ) ? $view : null;
	}

	/**
	 * Fallback markup for blocks that have no registered partial. Wraps the
	 * rendered children in a comment-bracketed `<div>` so editors can tell
	 * which block type is unknown without breaking the surrounding layout.
	 *
	 * @since 1.0.0
	 */
	protected function renderFallback( string $name, string $innerBlocksHtml ): string
	{
		$safeName = htmlspecialchars( $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return sprintf(
			'<!-- visual-editor: no partial for %1$s --><div data-ve-unknown-block="%1$s">%2$s</div>',
			$safeName,
			$innerBlocksHtml
		);
	}

	/**
	 * Split a block name into [namespace, block] suitable for view resolution.
	 *
	 * @since 1.0.0
	 *
	 * @return array{0: string, 1: string}
	 */
	protected function splitBlockName( string $name ): array
	{
		$parts = explode( '/', $name, 2 );

		if ( 2 !== count( $parts ) ) {
			return [ '', '' ];
		}

		return [ trim( $parts[0] ), trim( $parts[1] ) ];
	}

	/**
	 * Coerce whatever a render callback returned to a string.
	 *
	 * @since 1.0.0
	 */
	protected function coerceToString( mixed $value ): string
	{
		if ( $value instanceof View ) {
			try {
				return $value->render();
			} catch ( BindingResolutionException $e ) {
				report( $e );

				return '';
			}
		}

		if ( $value instanceof Htmlable ) {
			return $value->toHtml();
		}

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( $value instanceof Stringable || ( is_object( $value ) && method_exists( $value, '__toString' ) ) ) {
			return (string) $value;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Normalize a block's `attributes` value into an array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function normalizeAttributes( mixed $attributes ): array
	{
		if ( ! is_array( $attributes ) ) {
			return [];
		}

		/** @var array<string, mixed> $attributes */
		return $attributes;
	}

	/**
	 * Normalize a block's `innerBlocks` value into a list of block arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function normalizeInnerBlocks( mixed $innerBlocks ): array
	{
		if ( ! is_array( $innerBlocks ) ) {
			return [];
		}

		$list = [];

		foreach ( $innerBlocks as $child ) {
			if ( is_array( $child ) ) {
				$list[] = $child;
			}
		}

		return $list;
	}
}
