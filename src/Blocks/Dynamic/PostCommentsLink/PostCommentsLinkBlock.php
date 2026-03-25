<?php

/**
 * Post Comments Link Block.
 *
 * Displays a link that navigates to the comments section with
 * contextual text based on comment count.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostCommentsLink
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostCommentsLink;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Comments Link block for the visual editor.
 *
 * Renders a link that scrolls to or navigates to the comments
 * section. Displays contextual text based on whether there are
 * existing comments or not. Resolves comment data from the
 * content context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostCommentsLink
 *
 * @since      2.0.0
 */
class PostCommentsLinkBlock extends BaseBlock
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
			'text'     => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_comments_link_text' ),
				'default' => '',
			],
			'zeroText' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_comments_link_zero_text' ),
				'default' => '',
			],
		];
	}
}
