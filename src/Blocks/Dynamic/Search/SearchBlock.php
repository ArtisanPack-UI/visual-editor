<?php

/**
 * Search Block.
 *
 * A dynamic block that renders a configurable search form.
 * In the editor it shows a non-functional preview; on the
 * frontend it submits to a configurable search route.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\Search
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\Search;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\SearchBlockComponent;

/**
 * Search dynamic block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\Search
 *
 * @since      2.0.0
 */
class SearchBlock extends DynamicBlock
{
	/**
	 * Get the Livewire component class for this dynamic block.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getComponent(): string
	{
		return SearchBlockComponent::class;
	}

	/**
	 * Get the content field schema.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'placeholder'    => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.placeholder_text' ),
				'default' => __( 'visual-editor::ve.search_placeholder' ),
			],
			'buttonText'     => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.button_text' ),
				'default' => __( 'visual-editor::ve.search' ),
			],
			'buttonPosition' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.button_position' ),
				'options' => [
					'inside'  => __( 'visual-editor::ve.inside' ),
					'outside' => __( 'visual-editor::ve.outside' ),
					'none'    => __( 'visual-editor::ve.no_button' ),
				],
				'default' => 'outside',
			],
			'buttonIcon'     => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.button_icon' ),
				'default' => 'magnifying-glass',
			],
			'showLabel'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_label' ),
				'default' => true,
			],
			'label'          => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.label_text' ),
				'default' => __( 'visual-editor::ve.search' ),
			],
			'resultsPerPage' => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.results_per_page' ),
				'min'     => 1,
				'max'     => 50,
				'default' => 10,
			],
			'searchScope'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.search_scope' ),
				'options' => [
					'all' => __( 'visual-editor::ve.all_content' ),
				],
				'default' => 'all',
			],
			'displayStyle'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.display_style' ),
				'options' => [
					'inline'  => __( 'visual-editor::ve.inline' ),
					'stacked' => __( 'visual-editor::ve.stacked' ),
				],
				'default' => 'inline',
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
						'field'   => 'displayStyle',
						'source'  => 'content',
						'options' => [
							[ 'value' => 'inline', 'label' => __( 'visual-editor::ve.inline' ) ],
							[ 'value' => 'stacked', 'label' => __( 'visual-editor::ve.stacked' ) ],
						],
					],
				],
			],
		];
	}
}
