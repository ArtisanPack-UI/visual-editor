<?php

/**
 * Button Block.
 *
 * Adds a customizable button/call-to-action with configurable
 * text, link, style, size, and variant options.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Button
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Button;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Button block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Button
 *
 * @since      1.0.0
 */
class ButtonBlock extends BaseBlock
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
			'icon'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.button_icon' ),
				'default' => '',
			],
			'iconPosition' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.icon_position' ),
				'options' => [
					'left'  => __( 'visual-editor::ve.left' ),
					'right' => __( 'visual-editor::ve.right' ),
				],
				'default' => 'left',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Button-specific fields.
	 * The 'color' field uses a non-standard name (not 'textColor') for the
	 * text color, so it is declared as a custom field.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'color'        => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => null,
			],
			'size'         => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_size' ),
				'options' => [
					'sm'  => __( 'visual-editor::ve.small' ),
					'md'  => __( 'visual-editor::ve.medium' ),
					'lg'  => __( 'visual-editor::ve.large' ),
					'xl'  => __( 'visual-editor::ve.extra_large' ),
				],
				'default' => 'md',
			],
			'variant'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_variant' ),
				'options' => [
					'filled'  => __( 'visual-editor::ve.filled' ),
					'outline' => __( 'visual-editor::ve.outline' ),
					'ghost'   => __( 'visual-editor::ve.ghost' ),
				],
				'default' => 'filled',
			],
			'borderRadius' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.border_radius' ),
				'default' => '',
			],
			'width'        => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_width' ),
				'options' => [
					'auto' => __( 'visual-editor::ve.auto' ),
					'25'   => '25%',
					'50'   => '50%',
					'75'   => '75%',
					'100'  => '100%',
				],
				'default' => 'auto',
			],
		] );
	}
}
