<?php

/**
 * Comment Author Name Block.
 *
 * Displays the comment author's name with an optional link
 * to the author's URL within a comment template.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentAuthorName
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentAuthorName;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comment Author Name block for the visual editor.
 *
 * Renders the comment author's display name with an optional
 * link to the author's website URL. Designed to be used within
 * a Comment Template block. Resolves author data from the
 * comment context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentAuthorName
 *
 * @since      2.0.0
 */
class CommentAuthorNameBlock extends BaseBlock
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
		return [
			'isLink' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.comment_author_name_is_link' ),
				'default' => false,
			],
		];
	}
}
