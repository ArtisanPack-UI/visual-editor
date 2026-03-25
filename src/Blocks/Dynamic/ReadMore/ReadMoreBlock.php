<?php

/**
 * Read More Block.
 *
 * Renders a "Read more" link to the full content page, typically
 * used within query loops alongside the Post Excerpt block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\ReadMore
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\ReadMore;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Read More block for the visual editor.
 *
 * Displays a customizable "Read more" link that resolves the current
 * content item's permalink via filter hooks. Designed for use within
 * query loop templates to link from excerpt cards to full content pages.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\ReadMore
 *
 * @since      2.0.0
 */
class ReadMoreBlock extends BaseBlock
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
			'content'    => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.read_more_content' ),
				'default' => __( 'visual-editor::ve.read_more_default' ),
			],
			'linkTarget' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.link_target' ),
				'options' => [
					'_self'  => __( 'visual-editor::ve.same_window' ),
					'_blank' => __( 'visual-editor::ve.new_window' ),
				],
				'default' => '_self',
			],
			'showArrow'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.read_more_show_arrow' ),
				'default' => false,
			],
		];
	}
}
