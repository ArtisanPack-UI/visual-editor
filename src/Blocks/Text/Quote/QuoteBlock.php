<?php

/**
 * Quote Block.
 *
 * Renders a blockquote with optional citation, alignment,
 * and style variants (default, large, pull-left, pull-right).
 * Supports inner blocks for rich quote content.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Quote
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Quote;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Quote block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Quote
 *
 * @since      1.0.0
 */
class QuoteBlock extends BaseBlock
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
		return [];
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
			'textColor'          => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => null,
			],
			'backgroundColor'    => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.background_color' ),
				'default' => null,
			],
			'fontSize'           => [
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
			'padding'            => [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.padding' ),
				'sides'   => [ 'top', 'right', 'bottom', 'left' ],
				'default' => null,
			],
			'margin'             => [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.margin' ),
				'sides'   => [ 'top', 'bottom' ],
				'default' => null,
			],
			'blockSpacing'       => [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.block_spacing' ),
				'default' => null,
			],
			'border'             => [
				'type'    => 'border',
				'label'   => __( 'visual-editor::ve.border' ),
				'default' => [
					'width'      => '0',
					'widthUnit'  => 'px',
					'style'      => 'none',
					'color'      => '#000000',
					'radius'     => '0',
					'radiusUnit' => 'px',
					'perSide'    => false,
					'perCorner'  => false,
				],
			],
			'backgroundImage'    => [
				'type'    => 'media_picker',
				'label'   => __( 'visual-editor::ve.background_image' ),
				'default' => null,
			],
			'backgroundSize'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.background_size' ),
				'options' => [
					'cover'   => __( 'visual-editor::ve.fit_cover' ),
					'contain' => __( 'visual-editor::ve.fit_contain' ),
					'auto'    => __( 'visual-editor::ve.auto' ),
				],
				'default' => 'cover',
			],
			'backgroundPosition' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.background_position' ),
				'options' => [
					'center center' => __( 'visual-editor::ve.center' ),
					'top left'      => __( 'visual-editor::ve.top_left' ),
					'top right'     => __( 'visual-editor::ve.top_right' ),
					'bottom left'   => __( 'visual-editor::ve.bottom_left' ),
					'bottom right'  => __( 'visual-editor::ve.bottom_right' ),
				],
				'default' => 'center center',
			],
		];
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * Adds a citation toggle control to the block toolbar.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		$controls = parent::getToolbarControls();

		$controls[] = [
			'group'    => 'quote',
			'controls' => [
				[
					'type'  => 'toggle',
					'field' => 'showCitation',
					'label' => __( 'visual-editor::ve.citation' ),
				],
			],
		];

		return $controls;
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
			'heading'   => [
				'text' => 'text',
			],
		];
	}
}
