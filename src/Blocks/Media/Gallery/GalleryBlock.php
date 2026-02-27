<?php

/**
 * Gallery Block.
 *
 * Displays multiple images in a configurable grid layout
 * with column count, gap, captions, and crop options.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Gallery
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media\Gallery;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Gallery block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Gallery
 *
 * @since      1.0.0
 */
class GalleryBlock extends BaseBlock
{
	/**
	 * Get the content field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'images'       => [
				'type'    => 'repeater',
				'label'   => __( 'visual-editor::ve.gallery_images' ),
				'fields'  => [
					'url'     => [ 'type' => 'url', 'label' => __( 'visual-editor::ve.image_url' ) ],
					'alt'     => [ 'type' => 'text', 'label' => __( 'visual-editor::ve.alt_text' ) ],
					'caption' => [ 'type' => 'rich_text', 'label' => __( 'visual-editor::ve.caption' ) ],
				],
				'min'     => 0,
				'default' => [],
			],
			'linkBehavior' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.link_behavior' ),
				'options' => [
					'none'   => __( 'visual-editor::ve.none' ),
					'media'  => __( 'visual-editor::ve.media_file' ),
					'custom' => __( 'visual-editor::ve.custom' ),
				],
				'default' => 'none',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [
			'columns'        => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.columns' ),
				'options' => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				],
				'default' => '3',
			],
			'gap'            => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.gap' ),
				'options' => [
					'none'   => __( 'visual-editor::ve.none' ),
					'small'  => __( 'visual-editor::ve.small' ),
					'medium' => __( 'visual-editor::ve.medium' ),
					'large'  => __( 'visual-editor::ve.large' ),
				],
				'default' => 'medium',
			],
			'captionDisplay' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.caption_display' ),
				'options' => [
					'none'    => __( 'visual-editor::ve.none' ),
					'below'   => __( 'visual-editor::ve.below' ),
					'overlay' => __( 'visual-editor::ve.overlay' ),
				],
				'default' => 'below',
			],
			'crop'           => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.crop' ),
				'default' => true,
			],
		];
	}
}
