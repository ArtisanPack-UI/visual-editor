<?php

/**
 * Post Template Block.
 *
 * Inner block of the Query Loop that defines the layout for
 * each query result item. Supports list and grid layouts with
 * configurable column count.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTemplate
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTemplate;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Template block for the visual editor.
 *
 * Acts as a container block within a Query Loop that defines
 * how each query result item is rendered. Contains content
 * display blocks (Post Title, Post Excerpt, etc.) and supports
 * list or grid layout options with configurable column count.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTemplate
 *
 * @since      2.0.0
 */
class PostTemplateBlock extends BaseBlock
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
			'layout'  => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_template_layout' ),
				'options' => [
					'list' => __( 'visual-editor::ve.post_template_layout_list' ),
					'grid' => __( 'visual-editor::ve.post_template_layout_grid' ),
				],
				'default' => 'list',
			],
			'columns' => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.post_template_columns' ),
				'min'     => 1,
				'max'     => 6,
				'default' => 3,
			],
		];
	}

	/**
	 * Get available block variations.
	 *
	 * Provides starter layout patterns for the Post Template block.
	 * Each variation pre-populates the template with a set of inner
	 * blocks arranged in a common layout pattern.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getVariations(): array
	{
		return [
			[
				'name'        => 'image-title-excerpt',
				'label'       => __( 'visual-editor::ve.post_template_variation_image_title_excerpt' ),
				'description' => __( 'visual-editor::ve.post_template_variation_image_title_excerpt_desc' ),
				'icon'        => 'photo',
				'innerBlocks' => [
					[ 'type' => 'post-featured-image', 'attributes' => [] ],
					[ 'type' => 'post-title', 'attributes' => [ 'level' => 'h3' ] ],
					[ 'type' => 'post-excerpt', 'attributes' => [] ],
					[ 'type' => 'read-more', 'attributes' => [] ],
				],
				'isDefault'   => true,
			],
			[
				'name'        => 'title-date',
				'label'       => __( 'visual-editor::ve.post_template_variation_title_date' ),
				'description' => __( 'visual-editor::ve.post_template_variation_title_date_desc' ),
				'icon'        => 'calendar',
				'innerBlocks' => [
					[ 'type' => 'post-title', 'attributes' => [ 'level' => 'h3' ] ],
					[ 'type' => 'post-date', 'attributes' => [] ],
				],
				'isDefault'   => false,
			],
			[
				'name'        => 'title-excerpt-author',
				'label'       => __( 'visual-editor::ve.post_template_variation_title_excerpt_author' ),
				'description' => __( 'visual-editor::ve.post_template_variation_title_excerpt_author_desc' ),
				'icon'        => 'user',
				'innerBlocks' => [
					[ 'type' => 'post-title', 'attributes' => [ 'level' => 'h3' ] ],
					[ 'type' => 'post-excerpt', 'attributes' => [] ],
					[ 'type' => 'post-author-name', 'attributes' => [] ],
					[ 'type' => 'post-date', 'attributes' => [] ],
				],
				'isDefault'   => false,
			],
			[
				'name'        => 'image-title-date',
				'label'       => __( 'visual-editor::ve.post_template_variation_image_title_date' ),
				'description' => __( 'visual-editor::ve.post_template_variation_image_title_date_desc' ),
				'icon'        => 'photo',
				'innerBlocks' => [
					[ 'type' => 'post-featured-image', 'attributes' => [] ],
					[ 'type' => 'post-title', 'attributes' => [ 'level' => 'h3' ] ],
					[ 'type' => 'post-date', 'attributes' => [] ],
				],
				'isDefault'   => false,
			],
			[
				'name'        => 'title-only',
				'label'       => __( 'visual-editor::ve.post_template_variation_title_only' ),
				'description' => __( 'visual-editor::ve.post_template_variation_title_only_desc' ),
				'icon'        => 'document-text',
				'innerBlocks' => [
					[ 'type' => 'post-title', 'attributes' => [ 'level' => 'h3', 'isLink' => true ] ],
				],
				'isDefault'   => false,
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
						'field'   => 'layout',
						'source'  => 'content',
						'options' => [
							'list' => __( 'visual-editor::ve.post_template_layout_list' ),
							'grid' => __( 'visual-editor::ve.post_template_layout_grid' ),
						],
					],
				],
			],
		];
	}
}
