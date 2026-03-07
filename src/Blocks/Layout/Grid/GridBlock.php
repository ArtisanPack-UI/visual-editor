<?php

/**
 * Grid Block.
 *
 * Provides a CSS Grid layout container with responsive column counts.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Grid
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout\Grid;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Grid Block class.
 *
 * Renders a CSS Grid container with responsive column controls
 * and configurable gap, alignment, and row template options.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Grid
 *
 * @since      1.0.0
 */
class GridBlock extends BaseBlock
{
	/**
	 * Get the content schema for the Grid block.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'columns' => [
				'type'    => 'responsive_range',
				'label'   => __( 'visual-editor::ve.grid_columns' ),
				'min'     => 1,
				'max'     => 12,
				'step'    => 1,
				'default' => [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ],
			],
			'templateRows' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.grid_template_rows' ),
				'default' => 'auto',
			],
		];
	}

	/**
	 * Get the style schema for the Grid block.
	 *
	 * Merges auto-generated supports fields with custom Grid-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'gap'          => [
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
			'rowGap'       => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.row_gap' ),
				'options' => [
					''       => __( 'visual-editor::ve.same_as_gap' ),
					'none'   => __( 'visual-editor::ve.none' ),
					'small'  => __( 'visual-editor::ve.small' ),
					'medium' => __( 'visual-editor::ve.medium' ),
					'large'  => __( 'visual-editor::ve.large' ),
				],
				'default' => '',
			],
			'alignItems'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.align_items' ),
				'options' => [
					'stretch' => __( 'visual-editor::ve.stretch' ),
					'start'   => __( 'visual-editor::ve.top' ),
					'center'  => __( 'visual-editor::ve.center' ),
					'end'     => __( 'visual-editor::ve.bottom' ),
				],
				'default' => 'stretch',
			],
			'justifyItems' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.justify_items' ),
				'options' => [
					'stretch' => __( 'visual-editor::ve.stretch' ),
					'start'   => __( 'visual-editor::ve.justify_start' ),
					'center'  => __( 'visual-editor::ve.justify_center' ),
					'end'     => __( 'visual-editor::ve.justify_end' ),
				],
				'default' => 'stretch',
			],
		] );
	}
}
