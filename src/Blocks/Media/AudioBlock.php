<?php

/**
 * Audio Block Alias.
 *
 * Maintains backward compatibility for the old namespace.
 * Use ArtisanPackUI\VisualEditor\Blocks\Media\Audio\AudioBlock instead.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 * @deprecated 2.0.0 Use ArtisanPackUI\VisualEditor\Blocks\Media\Audio\AudioBlock instead.
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media;

use ArtisanPackUI\VisualEditor\Blocks\Media\Audio\AudioBlock as NewAudioBlock;

/**
 * Backward-compatible alias for AudioBlock.
 *
 * Overrides resolveBlockDirectory to point to the new co-located directory.
 *
 * @since      1.0.0
 * @deprecated 2.0.0
 */
class AudioBlock extends NewAudioBlock
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
		return __DIR__ . '/Audio';
	}
}
