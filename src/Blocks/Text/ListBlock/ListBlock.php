<?php

/**
 * List Block.
 *
 * Renders ordered or unordered lists with nested item support,
 * start number, and reversed options.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\ListBlock
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\ListBlock;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * List block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\ListBlock
 *
 * @since      1.0.0
 */
class ListBlock extends BaseBlock
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
			'type'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.list_type' ),
				'options' => [
					'unordered' => __( 'visual-editor::ve.unordered' ),
					'ordered'   => __( 'visual-editor::ve.ordered' ),
				],
				'default' => 'unordered',
			],
			'items'    => [
				'type'    => 'repeater',
				'label'   => __( 'visual-editor::ve.list_items' ),
				'fields'  => [
					'text' => [
						'type'  => 'rich_text',
						'label' => __( 'visual-editor::ve.list_item_text' ),
					],
				],
				'min'     => 1,
				'default' => [],
			],
			'start'    => [
				'type'      => 'text',
				'label'     => __( 'visual-editor::ve.start_number' ),
				'default'   => '1',
				'condition' => [ 'type', '==', 'ordered' ],
			],
			'reversed' => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.reversed' ),
				'default'   => false,
				'condition' => [ 'type', '==', 'ordered' ],
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
		return [
			'textColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => null,
			],
			'fontSize'  => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.font_size' ),
				'options' => [
					'small' => __( 'visual-editor::ve.small' ),
					'base'  => __( 'visual-editor::ve.normal' ),
					'large' => __( 'visual-editor::ve.large' ),
				],
				'default' => null,
			],
		];
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
				'text' => 'items',
			],
		];
	}
}
