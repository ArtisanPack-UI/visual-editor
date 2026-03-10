<?php

/**
 * Image Block.
 *
 * Renders an image with optional caption, link, alignment,
 * rounded corners, shadow effects, and dimension controls.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Image
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media\Image;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Image block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Image
 *
 * @since      1.0.0
 */
class ImageBlock extends BaseBlock
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
			'url'        => [
				'type'    => 'media_picker',
				'label'   => __( 'visual-editor::ve.image_url' ),
				'default' => '',
			],
			'alt'        => [
				'type'    => 'textarea',
				'label'   => __( 'visual-editor::ve.alt_text' ),
				'hint'    => __( 'visual-editor::ve.alt_text_help' ),
				'default' => '',
			],
			'caption'    => [
				'type'    => 'rich_text',
				'label'   => __( 'visual-editor::ve.caption' ),
				'toolbar' => [ 'bold', 'italic', 'link' ],
				'default' => '',
			],
			'link'       => [
				'type'    => 'url',
				'label'   => __( 'visual-editor::ve.link_url' ),
				'default' => '',
			],
			'linkTarget' => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.link_target' ),
				'options'   => [
					'_self'  => __( 'visual-editor::ve.same_window' ),
					'_blank' => __( 'visual-editor::ve.new_window' ),
				],
				'default'   => '_self',
				'condition' => [ 'link', '!=', '' ],
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Image-specific fields.
	 * Overrides auto-generated 'aspectRatio' and 'shadow' with image-specific
	 * versions that have custom options and control types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'size'        => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.image_size' ),
				'options' => [
					'small'  => __( 'visual-editor::ve.small' ),
					'medium' => __( 'visual-editor::ve.medium' ),
					'large'  => __( 'visual-editor::ve.large' ),
					'full'   => __( 'visual-editor::ve.full_width' ),
				],
				'default' => 'large',
			],
			'aspectRatio' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.aspect_ratio' ),
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
			'width'       => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.width' ),
				'placeholder' => __( 'visual-editor::ve.auto' ),
				'default'     => '',
			],
			'height'      => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.height' ),
				'placeholder' => __( 'visual-editor::ve.auto' ),
				'default'     => '',
			],
			'resolution'  => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.resolution' ),
				'options' => [
					'full'      => __( 'visual-editor::ve.full_size' ),
					'large'     => __( 'visual-editor::ve.large' ),
					'medium'    => __( 'visual-editor::ve.medium' ),
					'thumbnail' => __( 'visual-editor::ve.thumbnail' ),
				],
				'default' => 'full',
			],
			'rounded'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.rounded_corners' ),
				'default' => false,
			],
			'shadow'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.drop_shadow' ),
				'default' => false,
			],
			'objectFit'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.object_fit' ),
				'options' => [
					'cover'   => __( 'visual-editor::ve.fit_cover' ),
					'contain' => __( 'visual-editor::ve.fit_contain' ),
					'fill'    => __( 'visual-editor::ve.fit_fill' ),
					'none'    => __( 'visual-editor::ve.none' ),
				],
				'default' => 'cover',
			],
		] );
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * Adds Replace and Link controls to the block toolbar.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		$controls = parent::getToolbarControls();

		$controls[] = [
			'group'    => 'image-actions',
			'controls' => [
				[
					'type'  => 'button',
					'field' => 'replace',
					'label' => __( 'visual-editor::ve.replace_image' ),
					'icon'  => 'arrow-path',
				],
				[
					'type'  => 'button',
					'field' => 'link',
					'label' => __( 'visual-editor::ve.link' ),
					'icon'  => 'link',
				],
			],
		];

		return $controls;
	}
}
