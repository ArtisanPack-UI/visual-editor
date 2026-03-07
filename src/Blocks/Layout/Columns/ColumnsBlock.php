<?php

/**
 * Columns Block.
 *
 * Creates multi-column layouts with configurable column count,
 * width variations, gap, and responsive stacking.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Columns
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout\Columns;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Columns block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Columns
 *
 * @since      1.0.0
 */
class ColumnsBlock extends BaseBlock
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
			'columns'   => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.number_of_columns' ),
				'min'     => 1,
				'max'     => 6,
				'step'    => 1,
				'default' => 2,
			],
			'layout'    => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.column_layout' ),
				'options'   => [
					'equal' => __( 'visual-editor::ve.equal_width' ),
					'2-1'   => '66% / 33%',
					'1-2'   => '33% / 66%',
					'1-2-1' => '25% / 50% / 25%',
				],
				'default'   => 'equal',
				'inspector' => false,
			],
			'isStacked' => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.orientation' ),
				'default'   => false,
				'inspector' => false,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Columns-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'gap'               => [
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
			'verticalAlignment' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.vertical_alignment' ),
				'options' => [
					'top'     => __( 'visual-editor::ve.top' ),
					'center'  => __( 'visual-editor::ve.center' ),
					'bottom'  => __( 'visual-editor::ve.bottom' ),
					'stretch' => __( 'visual-editor::ve.stretch' ),
				],
				'default' => 'top',
			],
			'stackOnMobile'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.stack_on_mobile' ),
				'default' => true,
			],
		] );
	}
}
