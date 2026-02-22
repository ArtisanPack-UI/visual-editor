<?php

/**
 * Gallery Block.
 *
 * Displays multiple images in a configurable grid layout
 * with column count, gap, captions, and crop options.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Gallery block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @since      1.0.0
 */
class GalleryBlock extends BaseBlock
{
	protected string $type = 'gallery';

	protected string $name = 'Gallery';

	protected string $description = 'Display multiple images in a grid';

	protected string $icon = 'squares-2x2';

	protected string $category = 'media';

	protected array $keywords = [ 'images', 'photos', 'grid' ];

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

	/**
	 * Get the block's supported features.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
	{
		return [
			'align'      => [ 'wide', 'full' ],
			'color'      => [
				'text'       => false,
				'background' => false,
			],
			'typography' => [
				'fontSize'   => false,
				'fontFamily' => false,
			],
			'spacing'    => [
				'margin'  => false,
				'padding' => false,
			],
			'border'     => false,
			'anchor'     => true,
			'className'  => true,
		];
	}
}
