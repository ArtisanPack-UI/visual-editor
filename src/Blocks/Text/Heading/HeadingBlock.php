<?php

/**
 * Heading Block.
 *
 * Renders a heading element (h1-h6) with configurable level,
 * alignment, colors, and typography settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Heading
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Heading;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Heading block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Heading
 *
 * @since      1.0.0
 */
class HeadingBlock extends BaseBlock
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
			'text'  => [
				'type'        => 'rich_text',
				'label'       => __( 'visual-editor::ve.block_heading_placeholder' ),
				'placeholder' => __( 'visual-editor::ve.block_heading_placeholder' ),
				'toolbar'     => [ 'bold', 'italic', 'link' ],
				'default'     => '',
			],
			'level' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.heading_level' ),
				'options' => [
					'h1' => 'H1',
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'default' => 'h2',
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
			'alignment'       => [
				'type'    => 'alignment',
				'label'   => __( 'visual-editor::ve.text_alignment' ),
				'options' => [ 'left', 'center', 'right' ],
				'default' => 'left',
			],
			'textColor'       => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => null,
			],
			'backgroundColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.background_color' ),
				'default' => null,
			],
			'fontSize'        => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.font_size' ),
				'options' => [
					'small' => __( 'visual-editor::ve.small' ),
					'base'  => __( 'visual-editor::ve.normal' ),
					'large' => __( 'visual-editor::ve.large' ),
					'xl'    => __( 'visual-editor::ve.extra_large' ),
				],
				'default' => null,
			],
		];
	}

	/**
	 * Get available block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [
			'paragraph' => [
				'text' => 'text',
			],
			'quote'     => [
				'text' => 'text',
			],
		];
	}
}
