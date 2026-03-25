<?php

/**
 * Post Navigation Link Block.
 *
 * Renders a previous or next post navigation link dynamically
 * from the current content context with configurable label,
 * title display, arrow style, and optional taxonomy scope.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostNavigationLink
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostNavigationLink;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Navigation Link block for the visual editor.
 *
 * Displays a link to the previous or next content item based on
 * publish date. Supports configurable labels, optional display of the
 * target content's title, arrow/chevron indicators, and taxonomy-scoped
 * navigation. Resolves adjacent content via filter hooks, allowing
 * applications to provide data from any model.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostNavigationLink
 *
 * @since      2.0.0
 */
class PostNavigationLinkBlock extends BaseBlock
{
	/**
	 * Navigation type options for toolbar and inspector.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, array{value: string, label: string}>
	 */
	private const TYPE_OPTIONS = [
		[ 'value' => 'previous', 'label' => 'Previous' ],
		[ 'value' => 'next', 'label' => 'Next' ],
	];

	/**
	 * Arrow style options for toolbar and inspector.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, array{value: string, label: string}>
	 */
	private const ARROW_OPTIONS = [
		[ 'value' => 'none', 'label' => 'None' ],
		[ 'value' => 'arrow', 'label' => 'Arrow' ],
		[ 'value' => 'chevron', 'label' => 'Chevron' ],
	];

	/**
	 * Get the content field schema for the inspector panel.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'type'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_nav_link_type' ),
				'options' => $this->inspectorTypeOptions(),
				'default' => 'previous',
			],
			'label'     => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_nav_link_label' ),
				'default' => '',
			],
			'showTitle' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_nav_link_show_title' ),
				'default' => true,
			],
			'arrow'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_nav_link_arrow' ),
				'options' => $this->inspectorArrowOptions(),
				'default' => 'none',
			],
			'taxonomy'  => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_nav_link_taxonomy' ),
				'hint'    => __( 'visual-editor::ve.post_nav_link_taxonomy_hint' ),
				'default' => '',
			],
		];
	}

	/**
	 * Get toolbar control declarations for the block.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		return [
			[
				'group'    => 'block',
				'controls' => [
					[
						'type'    => 'select',
						'field'   => 'type',
						'source'  => 'content',
						'options' => self::TYPE_OPTIONS,
					],
				],
			],
		];
	}

	/**
	 * Build inspector select options from the shared type list.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, string>
	 */
	private function inspectorTypeOptions(): array
	{
		return [
			'previous' => __( 'visual-editor::ve.post_nav_link_type_previous' ),
			'next'     => __( 'visual-editor::ve.post_nav_link_type_next' ),
		];
	}

	/**
	 * Build inspector select options from the shared arrow list.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, string>
	 */
	private function inspectorArrowOptions(): array
	{
		return [
			'none'    => __( 'visual-editor::ve.post_nav_link_arrow_none' ),
			'arrow'   => __( 'visual-editor::ve.post_nav_link_arrow_arrow' ),
			'chevron' => __( 'visual-editor::ve.post_nav_link_arrow_chevron' ),
		];
	}
}
