<?php

/**
 * Post Date Block.
 *
 * Renders the publish date or modified date of the current
 * content item with configurable format and optional link.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostDate
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostDate;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Date block for the visual editor.
 *
 * Displays the publish date or last-modified date of the current
 * content item. Supports configurable PHP date formats, display
 * type selection (date, modified, both), and optional link to
 * the content page. Resolves dates from the content context
 * via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostDate
 *
 * @since      2.0.0
 */
class PostDateBlock extends BaseBlock
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
			'format'      => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_date_format' ),
				'default' => '',
			],
			'displayType' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_date_display_type' ),
				'options' => [
					'date'     => __( 'visual-editor::ve.post_date_type_publish' ),
					'modified' => __( 'visual-editor::ve.post_date_type_modified' ),
					'both'     => __( 'visual-editor::ve.post_date_type_both' ),
				],
				'default' => 'date',
			],
			'isLink'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_date_make_link' ),
				'default' => false,
			],
		];
	}
}
