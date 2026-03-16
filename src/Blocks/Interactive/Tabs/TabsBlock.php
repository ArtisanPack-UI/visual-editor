<?php

/**
 * Tabs Block.
 *
 * A container block that organizes content into tabbed sections.
 * Each tab is a TabPanel child block containing inner blocks.
 * Uses Alpine.js for frontend interactivity and daisyUI tab classes.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Tabs
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Tabs;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Tabs block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Tabs
 *
 * @since      1.0.0
 */
class TabsBlock extends BaseBlock
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
			'tabPosition'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.tabs_position' ),
				'options' => [
					'top'    => __( 'visual-editor::ve.top' ),
					'bottom' => __( 'visual-editor::ve.bottom' ),
					'left'   => __( 'visual-editor::ve.left' ),
					'right'  => __( 'visual-editor::ve.right' ),
				],
				'default' => 'top',
			],
			'rememberActive' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.tabs_remember_active' ),
				'default' => false,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Tabs-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'tabStyle'          => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.tabs_style' ),
				'options' => [
					'default'  => __( 'visual-editor::ve.tabs_style_default' ),
					'boxed'    => __( 'visual-editor::ve.tabs_style_boxed' ),
					'bordered' => __( 'visual-editor::ve.tabs_style_bordered' ),
					'lifted'   => __( 'visual-editor::ve.tabs_style_lifted' ),
				],
				'default' => 'default',
			],
			'tabSize'           => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.tabs_size' ),
				'options' => [
					'sm' => __( 'visual-editor::ve.small' ),
					'md' => __( 'visual-editor::ve.medium' ),
					'lg' => __( 'visual-editor::ve.large' ),
				],
				'default' => 'md',
			],
			'fullWidth'         => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.tabs_full_width' ),
				'default' => false,
			],
			'fadeTransition'    => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.tabs_fade_transition' ),
				'default' => true,
			],
			'tabTextColor'      => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.tabs_tab_text_color' ),
				'default' => null,
			],
			'activeTabColor'    => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.tabs_active_color' ),
				'default' => null,
			],
			'contentBackground' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.tabs_content_background' ),
				'default' => null,
			],
		] );
	}

	/**
	 * Get available block transforms.
	 *
	 * Each tab becomes an accordion section.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [
			'accordion' => [
				'tab-panel' => 'accordion-section',
			],
		];
	}

	/**
	 * Get the default inner blocks for new instances.
	 *
	 * Returns two empty tab panels so the Tabs container
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
				'type'       => 'tab-panel',
				'attributes' => [ 'label' => __( 'visual-editor::ve.tabs_tab_label_placeholder' ) . ' 1' ],
			],
			[
				'type'       => 'tab-panel',
				'attributes' => [ 'label' => __( 'visual-editor::ve.tabs_tab_label_placeholder' ) . ' 2' ],
			],
		];
	}
}
