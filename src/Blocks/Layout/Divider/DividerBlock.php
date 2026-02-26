<?php

/**
 * Divider Block.
 *
 * Adds a horizontal line between blocks with configurable
 * style, width, color, and thickness.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Divider
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout\Divider;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Divider/Separator block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Divider
 *
 * @since      1.0.0
 */
class DividerBlock extends BaseBlock
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
			'style'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.divider_style' ),
				'options' => [
					'solid'  => __( 'visual-editor::ve.solid' ),
					'dashed' => __( 'visual-editor::ve.dashed' ),
					'dotted' => __( 'visual-editor::ve.dotted' ),
					'double' => __( 'visual-editor::ve.double' ),
				],
				'default' => 'solid',
			],
			'width'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.divider_width' ),
				'options' => [
					'full'   => __( 'visual-editor::ve.full_width' ),
					'wide'   => __( 'visual-editor::ve.wide_width' ),
					'narrow' => __( 'visual-editor::ve.narrow_width' ),
				],
				'default' => 'full',
			],
			'color'     => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.color' ),
				'default' => null,
			],
			'thickness' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.divider_thickness' ),
				'default' => '1px',
			],
		];
	}
}
