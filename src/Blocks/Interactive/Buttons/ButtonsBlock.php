<?php

/**
 * Buttons Block.
 *
 * A container block that groups multiple Button blocks together
 * with configurable justification, orientation, and wrapping.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Buttons
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Buttons;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Buttons block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Buttons
 *
 * @since      1.0.0
 */
class ButtonsBlock extends BaseBlock
{
	/**
	 * Get the content field schema.
	 *
	 * Layout settings (justification, orientation, wrapping) appear
	 * in the Settings tab of the inspector.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'justification' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.justify_content' ),
				'options' => [
					'left'          => __( 'visual-editor::ve.justify_start' ),
					'center'        => __( 'visual-editor::ve.justify_center' ),
					'right'         => __( 'visual-editor::ve.justify_end' ),
					'space-between' => __( 'visual-editor::ve.justify_space_between' ),
				],
				'default' => 'left',
			],
			'orientation'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.orientation' ),
				'options' => [
					'horizontal' => __( 'visual-editor::ve.orientation_horizontal' ),
					'vertical'   => __( 'visual-editor::ve.orientation_vertical' ),
				],
				'default' => 'horizontal',
			],
			'flexWrap'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.allow_wrap' ),
				'default' => true,
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
		return parent::getStyleSchema();
	}

	/**
	 * Get the default inner blocks for new instances.
	 *
	 * Returns one empty button block so the Buttons container
	 * is pre-populated when first inserted.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getDefaultInnerBlocks(): array
	{
		return [
			[
				'type'       => 'button',
				'attributes' => [],
			],
		];
	}
}
