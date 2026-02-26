<?php

/**
 * Heading Block Alias.
 *
 * Maintains backward compatibility for the old namespace.
 * Use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 * @deprecated 2.0.0 Use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock instead.
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text;

use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock as NewHeadingBlock;

/**
 * Backward-compatible alias for HeadingBlock.
 *
 * Overrides resolveBlockDirectory to point to the new co-located directory.
 *
 * @since      1.0.0
 * @deprecated 2.0.0
 */
class HeadingBlock extends NewHeadingBlock
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
		return __DIR__ . '/Heading';
	}
}
