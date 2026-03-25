<?php

/**
 * Comment Author Avatar Block.
 *
 * Displays the comment author's avatar/gravatar image within
 * a comment template with configurable size and border radius.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentAuthorAvatar
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentAuthorAvatar;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comment Author Avatar block for the visual editor.
 *
 * Renders the comment author's avatar image with configurable
 * size (sm, md, lg) and border radius. Designed to be used
 * within a Comment Template block. Resolves avatar URL from
 * the comment context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentAuthorAvatar
 *
 * @since      2.0.0
 */
class CommentAuthorAvatarBlock extends BaseBlock
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
			'size'         => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.comment_author_avatar_size' ),
				'options' => [
					'sm' => __( 'visual-editor::ve.comment_author_avatar_sm' ),
					'md' => __( 'visual-editor::ve.comment_author_avatar_md' ),
					'lg' => __( 'visual-editor::ve.comment_author_avatar_lg' ),
				],
				'default' => 'md',
			],
			'borderRadius' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.comment_author_avatar_border_radius' ),
				'default' => '50%',
			],
		];
	}
}
