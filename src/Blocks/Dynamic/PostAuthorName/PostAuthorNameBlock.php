<?php

/**
 * Post Author Name Block.
 *
 * Lightweight block displaying just the author name with optional
 * byline prefix and link to the author archive page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostAuthorName
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostAuthorName;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Author Name block for the visual editor.
 *
 * Displays only the author name with an optional byline prefix and
 * optional link to the author page. This is a lighter-weight
 * alternative to the full Post Author block.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostAuthorName
 *
 * @since      2.0.0
 */
class PostAuthorNameBlock extends BaseBlock
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
			'isLink' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_author_name_make_link' ),
				'default' => false,
			],
			'byline' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_author_name_byline' ),
				'default' => '',
			],
		];
	}
}
