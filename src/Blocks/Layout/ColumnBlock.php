<?php

/**
 * Column Block.
 *
 * Internal child block used within the Columns block.
 * Acts as a container for other blocks within a column layout.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Column block (internal child of Columns block).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout
 *
 * @since      1.0.0
 */
class ColumnBlock extends BaseBlock
{
	protected string $type = 'column';

	protected string $name = 'Column';

	protected string $description = 'A single column within a columns layout';

	protected string $icon = 'view-columns';

	protected string $category = 'layout';

	protected array $keywords = [];

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
			'width'             => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.column_width' ),
				'default' => '',
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
		];
	}

	/**
	 * Get allowed parent block types.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>|null
	 */
	public function getAllowedParents(): ?array
	{
		return [ 'columns' ];
	}

	/**
	 * Whether this block should appear in the block inserter.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function isPublic(): bool
	{
		return false;
	}
}
