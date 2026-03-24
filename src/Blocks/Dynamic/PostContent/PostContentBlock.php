<?php

/**
 * Post Content Block.
 *
 * Renders the full block content of the current content item
 * with configurable layout width.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostContent
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostContent;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Content block for the visual editor.
 *
 * Displays the full rendered block content of the current content item.
 * The content is resolved from the content context via filter hooks,
 * allowing applications to provide rendered HTML from any model.
 * Supports layout width control (default, wide, full).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostContent
 *
 * @since      2.0.0
 */
class PostContentBlock extends BaseBlock
{
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
			'layout' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_content_layout' ),
				'options' => [
					'default' => __( 'visual-editor::ve.post_content_layout_default' ),
					'wide'    => __( 'visual-editor::ve.post_content_layout_wide' ),
					'full'    => __( 'visual-editor::ve.post_content_layout_full' ),
				],
				'default' => 'default',
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
						'field'   => 'layout',
						'source'  => 'content',
						'options' => [
							[ 'value' => 'default', 'label' => __( 'visual-editor::ve.post_content_layout_default' ), 'icon' => 'bars-3' ],
							[ 'value' => 'wide', 'label' => __( 'visual-editor::ve.post_content_layout_wide' ), 'icon' => 'arrows-pointing-out' ],
							[ 'value' => 'full', 'label' => __( 'visual-editor::ve.post_content_layout_full' ), 'icon' => 'arrows-right-left' ],
						],
					],
				],
			],
		];
	}
}
