<?php

/**
 * Comment Template Block.
 *
 * Container block that defines the layout for a single comment.
 * Repeats for each comment in the comments list, supporting
 * threaded/nested display for replies.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentTemplate
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentTemplate;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comment Template block for the visual editor.
 *
 * Acts as a container block that repeats for each comment in
 * the comments list. Inner blocks define how each individual
 * comment is rendered. Supports threaded/nested display for
 * comment replies. Resolves comment data from the content
 * context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentTemplate
 *
 * @since      2.0.0
 */
class CommentTemplateBlock extends BaseBlock
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
