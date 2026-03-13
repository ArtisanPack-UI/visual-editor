<?php

/**
 * Table Block.
 *
 * Renders a data table with configurable rows, columns,
 * header/footer rows, and styling options like striped
 * and bordered.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Table
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Table;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Table block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Table
 *
 * @since      1.0.0
 */
class TableBlock extends BaseBlock
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
			'hasHeaderRow'    => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.table_header_row' ),
				'default' => false,
			],
			'hasHeaderColumn' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.table_header_column' ),
				'default' => false,
			],
			'hasFooterRow'    => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.table_footer_row' ),
				'default' => false,
			],
			'caption'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.caption' ),
				'default' => '',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Table-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'striped'               => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.table_striped' ),
				'default' => false,
			],
			'bordered'              => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.table_bordered' ),
				'default' => true,
			],
			'fixedLayout'           => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.table_fixed_layout' ),
				'default' => false,
			],
			'headerBackgroundColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.table_header_background' ),
				'default' => null,
			],
			'stripeColor'           => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.table_stripe_color' ),
				'default' => null,
			],
			'borderColor'           => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.table_border_color' ),
				'default' => null,
			],
		] );
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
		return [];
	}
}
