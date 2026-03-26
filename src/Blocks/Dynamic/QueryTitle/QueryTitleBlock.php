<?php

/**
 * Query Title Block.
 *
 * Displays the contextual title for a query, such as
 * "Search results for: X" or "Category: Technology".
 * Auto-detects the appropriate title from page context.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryTitle
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryTitle;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Query Title block for the visual editor.
 *
 * Displays a contextual heading for the current query. On
 * search pages it shows "Search results for: X", on archive
 * pages it shows "Category: Technology", etc. Supports a
 * configurable heading level, optional prefix display, and
 * prefix type selection. Resolves title data from the query
 * context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryTitle
 *
 * @since      2.0.0
 */
class QueryTitleBlock extends BaseBlock
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
			'level'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.query_title_level' ),
				'options' => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'default' => 'h1',
			],
			'showPrefix' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.query_title_show_prefix' ),
				'default' => true,
			],
			'prefixType' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.query_title_prefix_type' ),
				'options' => [
					'archive' => __( 'visual-editor::ve.query_title_prefix_archive' ),
					'search'  => __( 'visual-editor::ve.query_title_prefix_search' ),
				],
				'default' => 'archive',
			],
		];
	}

	/**
	 * Get toolbar controls for the block.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		return [
			[
				'group'    => 'block',
				'controls' => [
					[
						'type'    => 'select',
						'field'   => 'level',
						'source'  => 'content',
						'options' => [
							'h1' => 'H1',
							'h2' => 'H2',
							'h3' => 'H3',
							'h4' => 'H4',
							'h5' => 'H5',
							'h6' => 'H6',
						],
					],
				],
			],
		];
	}
}
