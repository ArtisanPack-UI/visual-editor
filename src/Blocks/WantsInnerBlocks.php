<?php

/**
 * Marker interface for dynamic blocks that need their `innerBlocks`
 * tree at render time.
 *
 * The base {@see DynamicBlock::render()} only receives `$attrs` — a
 * pragmatic choice that keeps the vast majority of dynamic blocks
 * (latest-posts, product-grid, etc.) simple. Blocks that iterate,
 * template, or otherwise consume their inner tree at render time
 * (`artisanpack/dynamic-loop` most notably) implement this marker so
 * the renderer forwards the tree via {@see renderWithInner()}.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.4.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

use Illuminate\Contracts\View\View;
use Stringable;

interface WantsInnerBlocks
{
	/**
	 * Render the block with its inner tree available.
	 *
	 * @param  array<string, mixed>             $attrs
	 * @param  array<int, array<string, mixed>> $innerBlocks
	 *
	 * @return View|Stringable|string
	 *
	 * @since 1.4.0
	 */
	public function renderWithInner( array $attrs, array $innerBlocks );
}
