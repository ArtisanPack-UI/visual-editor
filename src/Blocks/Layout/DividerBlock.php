<?php

/**
 * Divider Block Alias.
 *
 * Maintains backward compatibility for the old namespace.
 * Use ArtisanPackUI\VisualEditor\Blocks\Layout\Divider\DividerBlock instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 * @deprecated 2.0.0 Use ArtisanPackUI\VisualEditor\Blocks\Layout\Divider\DividerBlock instead.
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout;

use ArtisanPackUI\VisualEditor\Blocks\Layout\Divider\DividerBlock as NewDividerBlock;

/**
 * Backward-compatible alias for DividerBlock.
 *
 * Overrides resolveBlockDirectory to point to the new co-located directory.
 *
 * @since      1.0.0
 * @deprecated 2.0.0
 */
class DividerBlock extends NewDividerBlock
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
		return __DIR__ . '/Divider';
	}
}
