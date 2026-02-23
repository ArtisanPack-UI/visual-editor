<?php

/**
 * Group Block.
 *
 * Groups blocks together in a container with configurable
 * HTML tag, background, padding, margin, and border settings.
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
 * Group/Container block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout
 *
 * @since      1.0.0
 */
class GroupBlock extends BaseBlock
{
	protected string $type = 'group';

	protected string $name = 'Group';

	protected string $description = 'Group blocks together with shared settings';

	protected string $icon = 'rectangle-group';

	protected string $category = 'layout';

	protected array $keywords = [ 'container', 'section', 'wrapper', 'group' ];

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
			'tag'           => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.html_tag' ),
				'options' => [
					'div'     => 'div',
					'section' => 'section',
					'article' => 'article',
					'aside'   => 'aside',
					'main'    => 'main',
				],
				'default' => 'div',
			],
			'flexDirection' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.flex_direction' ),
				'options' => [
					'column' => __( 'visual-editor::ve.flex_column' ),
					'row'    => __( 'visual-editor::ve.flex_row' ),
				],
				'default' => 'column',
			],
			'flexWrap'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.flex_wrap' ),
				'options' => [
					'nowrap' => __( 'visual-editor::ve.no_wrap' ),
					'wrap'   => __( 'visual-editor::ve.wrap' ),
				],
				'default' => 'nowrap',
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
					'flexDirection' => 'row',
					'flexWrap'      => 'nowrap',
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
		];
	}

	/**
	 * Get the block's supported features.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
	{
		return [
			'align'      => [ 'wide', 'full' ],
			'color'      => [
				'text'       => false,
				'background' => true,
			],
			'typography' => [
				'fontSize'   => false,
				'fontFamily' => false,
			],
			'spacing'    => [
				'margin'  => true,
				'padding' => true,
			],
			'border'     => true,
			'anchor'     => true,
			'className'  => true,
		];
	}
}
