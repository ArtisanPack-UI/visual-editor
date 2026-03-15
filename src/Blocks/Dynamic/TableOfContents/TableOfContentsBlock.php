<?php

/**
 * Table of Contents Block.
 *
 * A dynamic block that auto-generates a table of contents from
 * heading blocks in the document. Updates live in the editor as
 * headings are added, removed, or modified.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\TableOfContents
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\TableOfContents;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\TableOfContentsBlockComponent;

/**
 * Table of Contents dynamic block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\TableOfContents
 *
 * @since      2.0.0
 */
class TableOfContentsBlock extends DynamicBlock
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
		return TableOfContentsBlockComponent::class;
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
			'headingLevels' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.heading_levels' ),
				'options' => [
					1 => 'H1',
					2 => 'H2',
					3 => 'H3',
					4 => 'H4',
					5 => 'H5',
					6 => 'H6',
				],
				'multiple' => true,
				'default'  => [ 2, 3 ],
			],
			'listStyle'     => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.list_style' ),
				'options' => [
					'bulleted' => __( 'visual-editor::ve.bulleted' ),
					'numbered' => __( 'visual-editor::ve.numbered' ),
					'plain'    => __( 'visual-editor::ve.plain' ),
				],
				'default' => 'numbered',
			],
			'hierarchical'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.hierarchical_nesting' ),
				'default' => true,
			],
			'maxDepth'      => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.max_depth' ),
				'min'     => 1,
				'max'     => 6,
				'default' => 3,
			],
			'title'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.toc_title' ),
				'default' => __( 'visual-editor::ve.table_of_contents' ),
			],
			'collapsible'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.collapsible' ),
				'default' => false,
			],
			'smoothScroll'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.smooth_scroll' ),
				'default' => true,
			],
		];
	}
}
