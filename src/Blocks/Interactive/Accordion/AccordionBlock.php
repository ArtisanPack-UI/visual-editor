<?php

/**
 * Accordion Block.
 *
 * A container block that organizes content into collapsible sections.
 * Each section is an AccordionSection child block containing inner blocks.
 * Uses Alpine.js for frontend interactivity and daisyUI collapse classes.
 *
 * This is distinct from the Details block which uses native <details>/<summary>
 * for single collapsible sections. This block is a multi-section accordion
 * with coordinated behavior (e.g., allow-one-open-at-a-time).
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Accordion
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Accordion;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Accordion block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Accordion
 *
 * @since      1.0.0
 */
class AccordionBlock extends BaseBlock
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
			'allowMultiple' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.accordion_allow_multiple' ),
				'default' => false,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Accordion-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'iconStyle'         => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.accordion_icon_style' ),
				'options' => [
					'chevron'    => __( 'visual-editor::ve.accordion_icon_chevron' ),
					'plus-minus' => __( 'visual-editor::ve.accordion_icon_plus_minus' ),
					'caret'      => __( 'visual-editor::ve.accordion_icon_caret' ),
					'none'       => __( 'visual-editor::ve.accordion_icon_none' ),
				],
				'default' => 'chevron',
			],
			'iconPosition'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.accordion_icon_position' ),
				'options' => [
					'left'  => __( 'visual-editor::ve.left' ),
					'right' => __( 'visual-editor::ve.right' ),
				],
				'default' => 'right',
			],
			'bordered'          => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.accordion_bordered' ),
				'default' => true,
			],
			'accordionStyle'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.accordion_style' ),
				'options' => [
					'default'   => __( 'visual-editor::ve.accordion_style_default' ),
					'joined'    => __( 'visual-editor::ve.accordion_style_joined' ),
					'separated' => __( 'visual-editor::ve.accordion_style_separated' ),
				],
				'default' => 'default',
			],
			'headerBackground'  => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.accordion_header_background' ),
				'default' => null,
			],
			'contentBackground' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.accordion_content_background' ),
				'default' => null,
			],
			'borderColor'       => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.accordion_border_color' ),
				'default' => null,
			],
			'activeHeaderColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.accordion_active_header_color' ),
				'default' => null,
			],
		] );
	}

	/**
	 * Get available block transforms.
	 *
	 * Each section becomes a tab panel.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [
			'tabs' => [
				'accordion-section' => 'tab-panel',
			],
		];
	}

	/**
	 * Get the default inner blocks for new instances.
	 *
	 * Returns two empty accordion sections so the container
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
				'type'       => 'accordion-section',
				'attributes' => [
					'title'  => __( 'visual-editor::ve.accordion_section_title_placeholder' ) . ' 1',
					'isOpen' => true,
				],
			],
			[
				'type'       => 'accordion-section',
				'attributes' => [
					'title' => __( 'visual-editor::ve.accordion_section_title_placeholder' ) . ' 2',
				],
			],
		];
	}
}
