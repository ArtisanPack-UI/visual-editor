<?php

/**
 * Post Author Biography Block.
 *
 * Displays the current content item's author biography text.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostAuthorBiography
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostAuthorBiography;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Author Biography block for the visual editor.
 *
 * Displays the author's bio/description text for the current
 * content item. Resolves author biography from the content
 * context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostAuthorBiography
 *
 * @since      2.0.0
 */
class PostAuthorBiographyBlock extends BaseBlock
{
	/**
	 * Get the content field schema for the inspector panel.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [];
	}
}
