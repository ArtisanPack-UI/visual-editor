<?php

/**
 * Comment Date Block.
 *
 * Displays when a comment was posted with a configurable
 * format (relative or absolute) and optional permalink.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentDate
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentDate;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comment Date block for the visual editor.
 *
 * Displays the date and time when a comment was posted.
 * Supports relative (e.g. "2 hours ago") and absolute
 * date formats with an optional permalink to the comment.
 * Designed to be used within a Comment Template block.
 * Resolves date from the comment context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentDate
 *
 * @since      2.0.0
 */
class CommentDateBlock extends BaseBlock
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
			'format' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.comment_date_format' ),
				'options' => [
					'relative' => __( 'visual-editor::ve.comment_date_format_relative' ),
					'absolute' => __( 'visual-editor::ve.comment_date_format_absolute' ),
				],
				'default' => 'relative',
			],
			'isLink' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.comment_date_is_link' ),
				'default' => false,
			],
		];
	}
}
