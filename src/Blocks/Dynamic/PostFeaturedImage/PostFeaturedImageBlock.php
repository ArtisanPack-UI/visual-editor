<?php

/**
 * Post Featured Image Block.
 *
 * Renders the featured image of the current content item
 * with optional link, overlay, and responsive sizing.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostFeaturedImage
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostFeaturedImage;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Featured Image block for the visual editor.
 *
 * Displays the content item's featured image with responsive sizing,
 * optional link to the content page, and overlay support for
 * text-on-image layouts. Resolves the image from the content
 * context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostFeaturedImage
 *
 * @since      2.0.0
 */
class PostFeaturedImageBlock extends BaseBlock
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
			'isLink'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_featured_image_make_link' ),
				'default' => false,
			],
			'aspectRatio' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_featured_image_aspect_ratio' ),
				'options' => [
					''     => __( 'visual-editor::ve.post_featured_image_original' ),
					'1/1'  => __( 'visual-editor::ve.square' ),
					'4/3'  => '4:3',
					'3/2'  => '3:2',
					'16/9' => '16:9',
					'21/9' => '21:9',
					'3/4'  => '3:4',
					'2/3'  => '2:3',
					'9/16' => '9:16',
				],
				'default' => '',
			],
			'width'       => [
				'type'    => 'unit',
				'label'   => __( 'visual-editor::ve.width' ),
				'default' => '',
			],
			'height'      => [
				'type'    => 'unit',
				'label'   => __( 'visual-editor::ve.post_featured_image_height' ),
				'default' => '',
			],
			'scale'       => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_featured_image_scale' ),
				'options' => [
					'cover'   => __( 'visual-editor::ve.post_featured_image_cover' ),
					'contain' => __( 'visual-editor::ve.post_featured_image_contain' ),
				],
				'default' => 'cover',
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
			'overlayColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.post_featured_image_overlay_color' ),
				'default' => null,
			],
			'dimRatio'     => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.post_featured_image_overlay_opacity' ),
				'min'     => 0,
				'max'     => 100,
				'default' => 0,
			],
		] );
	}
}
