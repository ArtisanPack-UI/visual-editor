<?php

/**
 * Comments Pagination Block.
 *
 * Displays pagination controls for the comments list with
 * configurable per-page count, labels, and optional page
 * number display.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentsPagination
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentsPagination;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Comments Pagination block for the visual editor.
 *
 * Renders pagination controls for navigating through paginated
 * comments. Supports configurable per-page count, previous/next
 * labels, and optional page number display. Resolves pagination
 * data from the content context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\CommentsPagination
 *
 * @since      2.0.0
 */
class CommentsPaginationBlock extends BaseBlock
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
			'perPage'       => [
				'type'    => 'number',
				'label'   => __( 'visual-editor::ve.comments_pagination_per_page' ),
				'default' => 20,
			],
			'previousLabel' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.comments_pagination_previous_label' ),
				'default' => '',
			],
			'nextLabel'     => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.comments_pagination_next_label' ),
				'default' => '',
			],
			'showNumbers'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.comments_pagination_show_numbers' ),
				'default' => false,
			],
		];
	}
}
