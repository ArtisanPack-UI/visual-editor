<?php

/**
 * Buttons Block Alias.
 *
 * Maintains backward compatibility for the old namespace.
 * Use ArtisanPackUI\VisualEditor\Blocks\Interactive\Buttons\ButtonsBlock instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 * @deprecated 2.0.0 Use ArtisanPackUI\VisualEditor\Blocks\Interactive\Buttons\ButtonsBlock instead.
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive;

use ArtisanPackUI\VisualEditor\Blocks\Interactive\Buttons\ButtonsBlock as NewButtonsBlock;

/**
 * Backward-compatible alias for ButtonsBlock.
 *
 * Overrides resolveBlockDirectory to point to the new co-located directory.
 *
 * @since      1.0.0
 * @deprecated 2.0.0
 */
class ButtonsBlock extends NewButtonsBlock
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
		return __DIR__ . '/Buttons';
	}
}
