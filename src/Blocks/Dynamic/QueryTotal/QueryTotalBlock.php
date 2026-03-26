<?php

/**
 * Query Total Block.
 *
 * Displays the total number of query results with a
 * configurable display format and custom template string.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryTotal
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryTotal;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Query Total block for the visual editor.
 *
 * Displays the total number of results for the current query.
 * Supports a number-only format or a text format with a
 * customizable template string (e.g. "Showing :count results").
 * Resolves the count from the query context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryTotal
 *
 * @since      2.0.0
 */
class QueryTotalBlock extends BaseBlock
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
			'format'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.query_total_format' ),
				'options' => [
					'number' => __( 'visual-editor::ve.query_total_format_number' ),
					'text'   => __( 'visual-editor::ve.query_total_format_text' ),
				],
				'default' => 'text',
			],
			'template' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.query_total_template' ),
				'default' => '',
			],
		];
	}
}
