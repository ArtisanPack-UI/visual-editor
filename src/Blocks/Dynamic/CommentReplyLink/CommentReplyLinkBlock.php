<?php

/**
 * Comment Reply Link Block.
 *
 * Displays a reply link that opens the comment form in reply
 * context for the current comment.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentReplyLink
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentReplyLink;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comment Reply Link block for the visual editor.
 *
 * Renders a reply link for the current comment with
 * configurable link text. Designed to be used within a
 * Comment Template block. Resolves reply URL from the
 * comment context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentReplyLink
 *
 * @since      2.0.0
 */
class CommentReplyLinkBlock extends BaseBlock
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
				'label'   => __( 'visual-editor::ve.comment_reply_link_text' ),
				'default' => '',
			],
		];
	}
}
