<?php

/**
 * Query Pagination Block.
 *
 * Displays pagination controls for query loop results with
 * configurable labels, page numbers, and navigation options.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryPagination
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryPagination;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Query Pagination block for the visual editor.
 *
 * Renders pagination controls for navigating through paginated
 * query loop results. Supports previous/next links, page number
 * display, configurable labels, and a mid-size parameter that
 * controls how many page links appear around the current page.
 * Resolves pagination data from the query context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryPagination
 *
 * @since      2.0.0
 */
class QueryPaginationBlock extends BaseBlock
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
			'showNumbers'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.query_pagination_show_numbers' ),
				'default' => true,
			],
			'showPrevNext'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.query_pagination_show_prev_next' ),
				'default' => true,
			],
			'previousLabel' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.query_pagination_previous_label' ),
				'default' => '',
			],
			'nextLabel'     => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.query_pagination_next_label' ),
				'default' => '',
			],
			'midSize'       => [
				'type'    => 'number',
				'label'   => __( 'visual-editor::ve.query_pagination_mid_size' ),
				'default' => 2,
			],
		];
	}
}
