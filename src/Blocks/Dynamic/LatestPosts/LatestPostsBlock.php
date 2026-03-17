<?php

/**
 * Latest Posts Block.
 *
 * A dynamic block that queries and displays recent posts with
 * configurable filters and display templates. Renders via a
 * Livewire component for live editor preview.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\LatestPosts
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\LatestPosts;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\LatestPostsBlockComponent;

/**
 * Latest Posts dynamic block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\LatestPosts
 *
 * @since      2.0.0
 */
class LatestPostsBlock extends DynamicBlock
{
	/**
	 * Get the Livewire component class for this dynamic block.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getComponent(): string
	{
		return LatestPostsBlockComponent::class;
	}

	/**
	 * Get the content field schema.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		$postTypes = [ 'post' => __( 'visual-editor::ve.post' ) ];

		$postTypes = veApplyFilters( 've.latest-posts.post-types', $postTypes );

		return [
			'postType'           => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_type' ),
				'options' => $postTypes,
				'default' => 'post',
			],
			'numberOfPosts'      => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.number_of_posts' ),
				'min'     => 1,
				'max'     => 50,
				'default' => 5,
			],
			'orderBy'            => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.order_by' ),
				'options' => [
					'date'     => __( 'visual-editor::ve.date' ),
					'title'    => __( 'visual-editor::ve.title' ),
					'modified' => __( 'visual-editor::ve.last_modified' ),
					'random'   => __( 'visual-editor::ve.random' ),
				],
				'default' => 'date',
			],
			'order'              => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.order' ),
				'options' => [
					'desc' => __( 'visual-editor::ve.descending' ),
					'asc'  => __( 'visual-editor::ve.ascending' ),
				],
				'default' => 'desc',
			],
			'displayTemplate'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.display_template' ),
				'options' => [
					'list'  => __( 'visual-editor::ve.list' ),
					'grid'  => __( 'visual-editor::ve.grid' ),
					'cards' => __( 'visual-editor::ve.cards' ),
				],
				'default' => 'list',
			],
			'showFeaturedImage'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_featured_image' ),
				'default' => true,
			],
			'showExcerpt'        => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_excerpt' ),
				'default' => true,
			],
			'showDate'           => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_date' ),
				'default' => true,
			],
			'showAuthor'         => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_author' ),
				'default' => false,
			],
			'excerptLength'      => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.excerpt_length' ),
				'min'     => 5,
				'max'     => 100,
				'default' => 25,
			],
			'columns'            => [
				'type'    => 'responsive_range',
				'label'   => __( 'visual-editor::ve.columns' ),
				'min'     => 1,
				'max'     => 6,
				'step'    => 1,
				'default' => [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ],
			],
			'offset'             => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.offset' ),
				'min'     => 0,
				'max'     => 50,
				'default' => 0,
			],
			'excludeCurrentPost' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.exclude_current_post' ),
				'default' => false,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'gap'              => [
				'type'    => 'unit',
				'label'   => __( 'visual-editor::ve.gap' ),
				'default' => '1rem',
			],
			'imageAspectRatio' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.image_aspect_ratio' ),
				'options' => [
					'1/1'  => __( 'visual-editor::ve.square' ),
					'4/3'  => '4:3',
					'16/9' => '16:9',
					'21/9' => '21:9',
				],
				'default' => '16/9',
			],
		] );
	}

	/**
	 * Get toolbar control declarations for the block.
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
						'field'   => 'displayTemplate',
						'source'  => 'content',
						'options' => [
							[ 'value' => 'list', 'label' => __( 'visual-editor::ve.list' ), 'icon' => 'list-bullet' ],
							[ 'value' => 'grid', 'label' => __( 'visual-editor::ve.grid' ), 'icon' => 'squares-2x2' ],
							[ 'value' => 'cards', 'label' => __( 'visual-editor::ve.cards' ), 'icon' => 'rectangle-stack' ],
						],
					],
				],
			],
		];
	}
}
