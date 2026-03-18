<?php

/**
 * Block Renderer Service.
 *
 * Walks a stored block JSON tree and produces clean, semantic HTML
 * for front-end display. Each block type delegates to its registered
 * save template, and the output is filterable via hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Rendering
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Rendering;

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service that converts block JSON arrays into front-end HTML.
 *
 * The renderer recursively walks the block tree, resolves each block
 * type from the registry, and calls its `render()` method (which uses
 * the co-located `save.blade.php` template). Hooks allow third-party
 * code to modify output per block or for the entire rendered result.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Rendering
 *
 * @since      1.0.0
 */
class BlockRenderer
{
	/**
	 * Default maximum recursion depth for nested inner blocks.
	 *
	 * Prevents stack overflows from maliciously deep block trees.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public const DEFAULT_MAX_DEPTH = 100;

	/**
	 * The block registry instance.
	 *
	 * @since 1.0.0
	 *
	 * @var BlockRegistry
	 */
	protected BlockRegistry $registry;

	/**
	 * The CSS class prefix for rendered block wrappers.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	protected string $classPrefix;

	/**
	 * The maximum recursion depth for nested inner blocks.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	protected int $maxDepth;

	/**
	 * Create a new BlockRenderer instance.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry for resolving block types.
	 * @param string        $classPrefix The CSS class prefix for block wrappers.
	 * @param int           $maxDepth    Maximum recursion depth for nested blocks.
	 */
	public function __construct(
		BlockRegistry $registry,
		string $classPrefix = 've-block-',
		int $maxDepth = self::DEFAULT_MAX_DEPTH,
	) {
		$this->registry    = $registry;
		$this->classPrefix = $classPrefix;
		$this->maxDepth    = $maxDepth;
	}

	/**
	 * Render an array of blocks into front-end HTML.
	 *
	 * Iterates through each top-level block, rendering it and its
	 * nested inner blocks recursively. The complete output is passed
	 * through the `ap.visualEditor.renderedContent` filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $blocks The block data array from the database.
	 *
	 * @return string The rendered HTML string.
	 */
	public function render( array $blocks ): string
	{
		$html = '';

		foreach ( $blocks as $blockData ) {
			$html .= $this->renderBlock( $blockData );
		}

		return veApplyFilters( 'ap.visualEditor.renderedContent', $html, $blocks );
	}

	/**
	 * Render a single block and its inner blocks recursively.
	 *
	 * Resolves the block type from the registry, renders any inner
	 * blocks first, then calls the block's `render()` method. The
	 * output is passed through the `ap.visualEditor.renderBlock` filter.
	 *
	 * Returns an empty string for blocks with an empty type,
	 * unregistered block types, or when the maximum recursion depth
	 * is exceeded.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $blockData The individual block data array.
	 * @param int                  $depth     Current recursion depth (0 = top level).
	 *
	 * @return string The rendered block HTML.
	 */
	public function renderBlock( array $blockData, int $depth = 0 ): string
	{
		$type = $blockData['type'] ?? '';

		if ( '' === $type ) {
			return '';
		}

		if ( $depth >= $this->maxDepth ) {
			Log::warning( "BlockRenderer: max depth ({$this->maxDepth}) exceeded for block type '{$type}'" );

			return '';
		}

		$block = $this->registry->get( $type );

		if ( null === $block ) {
			return '';
		}

		$content     = $blockData['attributes'] ?? [];
		$styles      = $blockData['styles'] ?? [];
		$innerBlocks = $blockData['innerBlocks'] ?? [];

		$renderedInnerBlocks = [];

		foreach ( $innerBlocks as $innerBlock ) {
			$renderedInnerBlocks[] = $this->renderBlock( $innerBlock, $depth + 1 );
		}

		$context = [
			'classPrefix' => $this->classPrefix,
		];

		try {
			$blockHtml = $block->render( $content, $styles, $context, $renderedInnerBlocks );
		} catch ( Throwable $e ) {
			Log::warning( "BlockRenderer: failed to render block type '{$type}'", [
				'error' => $e->getMessage(),
			] );

			return '';
		}

		return veApplyFilters( 'ap.visualEditor.renderBlock', $blockHtml, $blockData, $block );
	}
}
