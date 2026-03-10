<?php

/**
 * List Block Alias.
 *
 * Maintains backward compatibility for the old namespace.
 * Use ArtisanPackUI\VisualEditor\Blocks\Text\ListBlock\ListBlock instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 * @deprecated 2.0.0 Use ArtisanPackUI\VisualEditor\Blocks\Text\ListBlock\ListBlock instead.
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text;

use ArtisanPackUI\VisualEditor\Blocks\Text\ListBlock\ListBlock as NewListBlock;

/**
 * Backward-compatible alias for ListBlock.
 *
 * Overrides resolveBlockDirectory to point to the new co-located directory.
 *
 * @since      1.0.0
 * @deprecated 2.0.0
 */
class ListBlock extends NewListBlock
{
	/**
	 * Resolve the block directory to the new co-located location.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function resolveBlockDirectory(): string
	{
		return __DIR__ . '/ListBlock';
	}
}
