<?php

/**
 * Grid Item Block.
 *
 * Represents a single item within a CSS Grid layout.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\GridItem
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout\GridItem;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Grid Item Block class.
 *
 * Child block of the Grid block. Supports responsive column/row
 * span controls and vertical alignment.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\GridItem
 *
 * @since      1.0.0
 */
class GridItemBlock extends BaseBlock
{
	/**
	 * Get the content schema for the Grid Item block.
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
	 * Get the style schema for the Grid Item block.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [
			'columnSpan' => [
				'type'    => 'responsive_range',
				'label'   => __( 'visual-editor::ve.grid_column_span' ),
				'min'     => 1,
				'max'     => 6,
				'step'    => 1,
				'default' => [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ],
			],
			'rowSpan' => [
				'type'    => 'responsive_range',
				'label'   => __( 'visual-editor::ve.grid_row_span' ),
				'min'     => 1,
				'max'     => 6,
				'step'    => 1,
				'default' => [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ],
			],
			'verticalAlignment' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.vertical_alignment' ),
				'options' => [
					'stretch' => __( 'visual-editor::ve.stretch' ),
					'start'   => __( 'visual-editor::ve.top' ),
					'center'  => __( 'visual-editor::ve.center' ),
					'end'     => __( 'visual-editor::ve.bottom' ),
				],
				'default' => 'stretch',
			],
		];
	}
}
