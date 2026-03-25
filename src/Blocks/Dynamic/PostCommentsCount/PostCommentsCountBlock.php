<?php

/**
 * Post Comments Count Block.
 *
 * Displays the number of comments on the current content item
 * with configurable format, labels, and optional icon/link.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostCommentsCount
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostCommentsCount;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Comments Count block for the visual editor.
 *
 * Displays the number of comments on the content with multiple
 * format options (number, short, long), configurable singular
 * and plural labels, optional icon, and optional link to the
 * comments section. Resolves comment count from the content
 * context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostCommentsCount
 *
 * @since      2.0.0
 */
class PostCommentsCountBlock extends BaseBlock
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
			'format'         => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_comments_count_format' ),
				'options' => [
					'number' => __( 'visual-editor::ve.post_comments_count_format_number' ),
					'short'  => __( 'visual-editor::ve.post_comments_count_format_short' ),
					'long'   => __( 'visual-editor::ve.post_comments_count_format_long' ),
				],
				'default' => 'short',
			],
			'singular'       => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_comments_count_singular' ),
				'default' => '',
			],
			'plural'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_comments_count_plural' ),
				'default' => '',
			],
			'showIcon'       => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_comments_count_show_icon' ),
				'default' => false,
			],
			'linkToComments' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_comments_count_link_to_comments' ),
				'default' => false,
			],
		];
	}
}
