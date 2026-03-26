<?php

/**
 * Query Loop Block.
 *
 * Container block that queries content and iterates over results.
 * Renders inner template blocks for each result, with configurable
 * query parameters and the ability to inherit from page context.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryLoop
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryLoop;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Query Loop block for the visual editor.
 *
 * Acts as a container block that queries content based on
 * configurable parameters (content type, ordering, taxonomy
 * filters, author, search, etc.) and iterates over results.
 * Inner blocks define how each result is rendered. Supports
 * inheriting query parameters from the page context on
 * archive pages.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\QueryLoop
 *
 * @since      2.0.0
 */
class QueryLoopBlock extends BaseBlock
{
	/**
	 * Get the content field schema for the inspector panel.
	 *
	 * The queryType field uses a select populated via the
	 * `ve.query.contentTypes` filter hook. Developers register
	 * their content types by adding filter callbacks that return
	 * an associative array of slug => label pairs.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		$defaultContentTypes = [
			'post' => __( 'visual-editor::ve.query_loop_content_type_post' ),
			'page' => __( 'visual-editor::ve.query_loop_content_type_page' ),
		];

		$contentTypes = veApplyFilters( 've.query.contentTypes', $defaultContentTypes );

		if ( ! is_array( $contentTypes ) || empty( $contentTypes ) ) {
			$contentTypes = $defaultContentTypes;
		}

		return [
			'queryType' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.query_loop_query_type' ),
				'options' => $contentTypes,
				'default' => 'post',
			],
			'perPage'   => [
				'type'    => 'number',
				'label'   => __( 'visual-editor::ve.query_loop_per_page' ),
				'default' => 10,
			],
			'orderBy'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.query_loop_order_by' ),
				'options' => [
					'date'     => __( 'visual-editor::ve.query_loop_order_by_date' ),
					'title'    => __( 'visual-editor::ve.query_loop_order_by_title' ),
					'modified' => __( 'visual-editor::ve.query_loop_order_by_modified' ),
					'random'   => __( 'visual-editor::ve.query_loop_order_by_random' ),
				],
				'default' => 'date',
			],
			'order'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.query_loop_order' ),
				'options' => [
					'desc' => __( 'visual-editor::ve.query_loop_order_desc' ),
					'asc'  => __( 'visual-editor::ve.query_loop_order_asc' ),
				],
				'default' => 'desc',
			],
			'author'    => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.query_loop_author' ),
				'default' => '',
			],
			'search'    => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.query_loop_search' ),
				'default' => '',
			],
			'offset'    => [
				'type'    => 'number',
				'label'   => __( 'visual-editor::ve.query_loop_offset' ),
				'default' => 0,
			],
			'sticky'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.query_loop_sticky' ),
				'options' => [
					'include' => __( 'visual-editor::ve.query_loop_sticky_include' ),
					'exclude' => __( 'visual-editor::ve.query_loop_sticky_exclude' ),
					'only'    => __( 'visual-editor::ve.query_loop_sticky_only' ),
				],
				'default' => 'include',
			],
			'inherit'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.query_loop_inherit' ),
				'default' => false,
			],
		];
	}

	/**
	 * Get the default inner blocks for new instances.
	 *
	 * Pre-populates the Query Loop with a Post Template block
	 * so the user has a starting point when inserting the block.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getDefaultInnerBlocks(): array
	{
		return [
			[
				'type'       => 'post-template',
				'attributes' => [],
			],
		];
	}
}
