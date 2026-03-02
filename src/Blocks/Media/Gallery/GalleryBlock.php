<?php

/**
 * Gallery Block.
 *
 * Displays multiple images in a configurable grid layout
 * using inner image blocks. Gallery is a parent container
 * that only accepts image blocks as children.
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
			'linkBehavior'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.link_behavior' ),
				'options' => [
					'none'   => __( 'visual-editor::ve.none' ),
					'media'  => __( 'visual-editor::ve.link_media' ),
					'custom' => __( 'visual-editor::ve.custom' ),
				],
				'default' => 'none',
			],
			'columns'        => [
				'type'    => 'responsive_range',
				'label'   => __( 'visual-editor::ve.gallery_columns' ),
				'min'     => 1,
				'max'     => 6,
				'step'    => 1,
				'default' => [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ],
			],
			'gap'            => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.gap' ),
				'min'     => 0,
				'max'     => 3,
				'step'    => 0.25,
				'default' => 1,
				'suffix'  => 'rem',
			],
			'resolution'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.resolution' ),
				'hint'    => __( 'visual-editor::ve.resolution_hint' ),
				'options' => [
					'full'      => __( 'visual-editor::ve.full_size' ),
					'large'     => __( 'visual-editor::ve.large' ),
					'medium'    => __( 'visual-editor::ve.medium' ),
					'thumbnail' => __( 'visual-editor::ve.thumbnail' ),
				],
				'default' => 'full',
			],
			'crop'           => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.crop_images_to_fit' ),
				'default' => true,
			],
			'randomizeOrder' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.randomize_order' ),
				'default' => false,
			],
			'openInNewTab'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.open_in_new_tab' ),
				'default' => false,
			],
			'aspectRatio'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.aspect_ratio' ),
				'hint'    => __( 'visual-editor::ve.aspect_ratio_hint' ),
				'options' => [
					'original' => __( 'visual-editor::ve.original' ),
					'16/9'     => '16:9',
					'4/3'      => '4:3',
					'3/2'      => '3:2',
					'9/16'     => '9:16',
					'1/1'      => '1:1',
				],
				'default' => 'original',
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
		return [];
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * Adds an Add button to the block toolbar for adding images.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		$controls = parent::getToolbarControls();

		$controls[] = [
			'group'    => 'gallery-actions',
			'controls' => [
				[
					'type'  => 'button',
					'field' => 'add',
					'label' => __( 'visual-editor::ve.add_images' ),
					'icon'  => 'plus',
				],
			],
		];

		return $controls;
	}
}
