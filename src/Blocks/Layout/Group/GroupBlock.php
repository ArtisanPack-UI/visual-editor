<?php

/**
 * Group Block.
 *
 * Groups blocks together in a container with configurable
 * HTML tag, background, padding, margin, and border settings.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Group
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout\Group;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Group/Container block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Group
 *
 * @since      1.0.0
 */
class GroupBlock extends BaseBlock
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
			'tag'              => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.html_tag' ),
				'options' => [
					'div'     => 'div',
					'section' => 'section',
					'article' => 'article',
					'aside'   => 'aside',
					'main'    => 'main',
					'header'  => 'header',
					'footer'  => 'footer',
				],
				'default' => 'div',
			],
			'flexDirection'    => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.flex_direction' ),
				'options'   => [
					'column' => __( 'visual-editor::ve.flex_column' ),
					'row'    => __( 'visual-editor::ve.flex_row' ),
				],
				'default'   => 'column',
				'inspector' => false,
			],
			'flexWrap'         => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.flex_wrap' ),
				'options'   => [
					'nowrap' => __( 'visual-editor::ve.no_wrap' ),
					'wrap'   => __( 'visual-editor::ve.wrap' ),
				],
				'default'   => 'nowrap',
				'inspector' => false,
			],
			'justifyContent'   => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.justify_content' ),
				'options'   => [
					'flex-start'    => __( 'visual-editor::ve.justify_start' ),
					'center'        => __( 'visual-editor::ve.justify_center' ),
					'flex-end'      => __( 'visual-editor::ve.justify_end' ),
					'space-between' => __( 'visual-editor::ve.justify_space_between' ),
				],
				'default'   => 'flex-start',
				'inspector' => false,
			],
			'useContentWidth'  => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.use_content_width' ),
				'hint'      => __( 'visual-editor::ve.use_content_width_hint' ),
				'default'   => false,
				'inspector' => false,
			],
			'contentWidth'     => [
				'type'      => 'text',
				'label'     => __( 'visual-editor::ve.content_width' ),
				'default'   => '',
				'inspector' => false,
			],
			'wideWidth'        => [
				'type'      => 'text',
				'label'     => __( 'visual-editor::ve.wide_width_label' ),
				'default'   => '',
				'inspector' => false,
			],
		];
	}

	/**
	 * Get available block variations.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getVariations(): array
	{
		return [
			[
				'name'        => 'group',
				'label'       => __( 'visual-editor::ve.variation_group' ),
				'description' => __( 'visual-editor::ve.variation_group_desc' ),
				'icon'        => 'rectangle-group',
				'attributes'  => [
					'flexDirection' => 'column',
					'flexWrap'      => 'nowrap',
				],
				'isDefault'   => true,
			],
			[
				'name'        => 'row',
				'label'       => __( 'visual-editor::ve.variation_row' ),
				'description' => __( 'visual-editor::ve.variation_row_desc' ),
				'icon'        => 'bars-3',
				'attributes'  => [
					'flexDirection'  => 'row',
					'flexWrap'       => 'nowrap',
					'justifyContent' => 'flex-start',
				],
				'isDefault'   => false,
			],
			[
				'name'        => 'stack',
				'label'       => __( 'visual-editor::ve.variation_stack' ),
				'description' => __( 'visual-editor::ve.variation_stack_desc' ),
				'icon'        => 'bars-3-bottom-left',
				'attributes'  => [
					'flexDirection' => 'column',
					'flexWrap'      => 'nowrap',
				],
				'isDefault'   => false,
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
			'textColor'         => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => null,
			],
			'backgroundColor'   => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.background_color' ),
				'default' => null,
			],
			'padding'           => [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.padding' ),
				'sides'   => [ 'top', 'right', 'bottom', 'left' ],
				'default' => null,
			],
			'margin'            => [
				'type'    => 'spacing',
				'label'   => __( 'visual-editor::ve.margin' ),
				'sides'   => [ 'top', 'bottom' ],
				'default' => null,
			],
			'border'            => [
				'type'    => 'border',
				'label'   => __( 'visual-editor::ve.border' ),
				'default' => [
					'width'      => '0',
					'widthUnit'  => 'px',
					'style'      => 'none',
					'color'      => '#000000',
					'radius'     => '0',
					'radiusUnit' => 'px',
					'perSide'    => false,
					'perCorner'  => false,
				],
			],
			'minHeight'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.min_height' ),
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
			'gap'               => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.block_spacing' ),
				'default' => null,
			],
			'useFlexbox'        => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.use_flexbox' ),
				'default'   => false,
				'inspector' => false,
			],
			'fillHeight'        => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.fill_height' ),
				'default'   => false,
				'inspector' => false,
			],
			'innerSpacing'      => [
				'type'      => 'select',
				'label'     => __( 'visual-editor::ve.inner_spacing' ),
				'options'   => [
					'none'   => __( 'visual-editor::ve.none' ),
					'small'  => __( 'visual-editor::ve.small' ),
					'normal' => __( 'visual-editor::ve.normal_spacing' ),
					'medium' => __( 'visual-editor::ve.medium' ),
					'large'  => __( 'visual-editor::ve.large' ),
				],
				'default'   => 'normal',
				'inspector' => false,
			],
		];
	}
}
