<?php

/**
 * Details/Accordion Block.
 *
 * Renders collapsible content using native HTML <details>
 * and <summary> elements. Supports inner blocks for
 * rich content within the disclosure area.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Details
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Details;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Details block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Details
 *
 * @since      1.0.0
 */
class DetailsBlock extends BaseBlock
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
			'isOpenByDefault' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.details_open_by_default' ),
				'default' => false,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Details-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'icon'                   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.details_icon' ),
				'options' => [
					'chevron'    => __( 'visual-editor::ve.details_icon_chevron' ),
					'plus-minus' => __( 'visual-editor::ve.details_icon_plus_minus' ),
					'none'       => __( 'visual-editor::ve.none' ),
				],
				'default' => 'chevron',
			],
			'iconPosition'           => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.icon_position' ),
				'options' => [
					'left'  => __( 'visual-editor::ve.left' ),
					'right' => __( 'visual-editor::ve.right' ),
				],
				'default' => 'left',
			],
			'borderStyle'            => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.details_border_style' ),
				'options' => [
					'default'    => __( 'visual-editor::ve.default_style' ),
					'card'       => __( 'visual-editor::ve.details_style_card' ),
					'minimal'    => __( 'visual-editor::ve.details_style_minimal' ),
					'borderless' => __( 'visual-editor::ve.details_style_borderless' ),
				],
				'default' => 'default',
			],
			'summaryBackgroundColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.details_summary_background' ),
				'default' => null,
			],
			'contentBackgroundColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.details_content_background' ),
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
		return [
			'group' => [
				'summary' => '_discard',
			],
		];
	}

	/**
	 * Get the default inner blocks for new instances.
	 *
	 * Returns one empty paragraph block so the Details content
	 * area is pre-populated when first inserted.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getDefaultInnerBlocks(): array
	{
		return [
			[
				'type'       => 'paragraph',
				'attributes' => [],
			],
		];
	}
}
