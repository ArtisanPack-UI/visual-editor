<?php

/**
 * Comments Title Block.
 *
 * Displays a heading for the comments section with an optional
 * comment count and configurable heading level.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentsTitle
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentsTitle;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comments Title block for the visual editor.
 *
 * Displays a heading for the comments section with a configurable
 * heading level (h1-h6), optional comment count display, and
 * customizable singular/plural labels. Resolves comment count
 * from the content context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentsTitle
 *
 * @since      2.0.0
 */
class CommentsTitleBlock extends BaseBlock
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
			'level'         => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.comments_title_level' ),
				'options' => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'default' => 'h2',
			],
			'showCount'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.comments_title_show_count' ),
				'default' => true,
			],
			'singularLabel' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.comments_title_singular' ),
				'default' => '',
			],
			'pluralLabel'   => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.comments_title_plural' ),
				'default' => '',
			],
		];
	}
}
