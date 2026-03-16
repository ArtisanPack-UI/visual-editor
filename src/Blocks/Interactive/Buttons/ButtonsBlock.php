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
	 * Layout settings (justification, orientation, wrapping) and
	 * bulk styling options appear in the Settings tab of the inspector.
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
			'bulkColor'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_group_bulk_color' ),
				'options' => [
					''          => __( 'visual-editor::ve.none' ),
					'primary'   => __( 'visual-editor::ve.color_primary' ),
					'secondary' => __( 'visual-editor::ve.color_secondary' ),
					'accent'    => __( 'visual-editor::ve.color_accent' ),
					'success'   => __( 'visual-editor::ve.color_success' ),
					'warning'   => __( 'visual-editor::ve.color_warning' ),
					'error'     => __( 'visual-editor::ve.color_error' ),
					'info'      => __( 'visual-editor::ve.color_info' ),
				],
				'default' => null,
			],
			'bulkSize'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_group_bulk_size' ),
				'options' => [
					''   => __( 'visual-editor::ve.none' ),
					'sm' => __( 'visual-editor::ve.small' ),
					'md' => __( 'visual-editor::ve.medium' ),
					'lg' => __( 'visual-editor::ve.large' ),
					'xl' => __( 'visual-editor::ve.extra_large' ),
				],
				'default' => null,
			],
			'bulkVariant'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_group_bulk_variant' ),
				'options' => [
					''        => __( 'visual-editor::ve.none' ),
					'filled'  => __( 'visual-editor::ve.filled' ),
					'outline' => __( 'visual-editor::ve.outline' ),
					'ghost'   => __( 'visual-editor::ve.ghost' ),
				],
				'default' => null,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Buttons-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'gap'            => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_group_gap' ),
				'options' => [
					'0'       => __( 'visual-editor::ve.none' ),
					'0.25rem' => 'XS',
					'0.5rem'  => __( 'visual-editor::ve.small' ),
					'0.75rem' => __( 'visual-editor::ve.medium' ),
					'1rem'    => __( 'visual-editor::ve.large' ),
					'1.5rem'  => 'XL',
				],
				'default' => '0.5rem',
			],
			'stackOnMobile'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.button_group_stack_on_mobile' ),
				'default' => false,
			],
		] );
	}

	/**
	 * Get the default inner blocks for new instances.
	 *
	 * Returns two empty button blocks so the Buttons container
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
			[
				'type'       => 'button',
				'attributes' => [],
			],
		];
	}
}
