<?php

/**
 * Comment Edit Link Block.
 *
 * Displays an edit link for the current comment, visible only
 * to the comment author or administrators.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentEditLink
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentEditLink;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comment Edit Link block for the visual editor.
 *
 * Renders an edit link for the current comment with configurable
 * link text. The link is only displayed when the current user has
 * permission to edit the comment (author or admin). Designed to
 * be used within a Comment Template block. Resolves edit URL from
 * the comment context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentEditLink
 *
 * @since      2.0.0
 */
class CommentEditLinkBlock extends BaseBlock
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
			'text' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.comment_edit_link_text' ),
				'default' => '',
			],
		];
	}
}
