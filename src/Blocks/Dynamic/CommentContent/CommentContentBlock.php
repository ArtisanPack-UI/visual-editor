<?php

/**
 * Comment Content Block.
 *
 * Renders the comment body text within a comment template.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentContent
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentContent;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comment Content block for the visual editor.
 *
 * Displays the comment body text. Designed to be used within
 * a Comment Template block. Resolves comment content from the
 * comment context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentContent
 *
 * @since      2.0.0
 */
class CommentContentBlock extends BaseBlock
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
