<?php

/**
 * Image Block.
 *
 * Renders an image with optional caption, link, alignment,
 * rounded corners, and shadow effects.
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
				'type'    => 'url',
				'label'   => __( 'visual-editor::ve.image_url' ),
				'default' => '',
			],
			'alt'        => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.alt_text' ),
				'help'    => __( 'visual-editor::ve.alt_text_help' ),
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
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [
			'size'      => [
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
			'alignment' => [
				'type'    => 'alignment',
				'label'   => __( 'visual-editor::ve.text_alignment' ),
				'options' => [ 'left', 'center', 'right' ],
				'default' => 'center',
			],
			'rounded'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.rounded_corners' ),
				'default' => false,
			],
			'shadow'    => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.drop_shadow' ),
				'default' => false,
			],
			'objectFit' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.object_fit' ),
				'options' => [
					'cover'   => 'Cover',
					'contain' => 'Contain',
					'fill'    => 'Fill',
					'none'    => 'None',
				],
				'default' => 'cover',
			],
		];
	}
}
