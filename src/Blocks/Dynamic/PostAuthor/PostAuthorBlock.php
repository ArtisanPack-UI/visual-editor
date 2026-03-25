<?php

/**
 * Post Author Block.
 *
 * Displays the current content item's author with optional avatar,
 * biography, configurable byline prefix, and link to author page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostAuthor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostAuthor;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Author block for the visual editor.
 *
 * Displays the content author with optional avatar image, biography
 * text, configurable byline prefix, and optional link to the author
 * archive page. Resolves author data from the content context via
 * filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostAuthor
 *
 * @since      2.0.0
 */
class PostAuthorBlock extends BaseBlock
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
			'showAvatar'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_author_show_avatar' ),
				'default' => true,
			],
			'avatarSize'  => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.post_author_avatar_size' ),
				'options' => [
					'sm' => __( 'visual-editor::ve.post_author_avatar_sm' ),
					'md' => __( 'visual-editor::ve.post_author_avatar_md' ),
					'lg' => __( 'visual-editor::ve.post_author_avatar_lg' ),
				],
				'default' => 'md',
			],
			'showBio'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_author_show_bio' ),
				'default' => true,
			],
			'byline'      => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_author_byline' ),
				'default' => 'by',
			],
			'isLink'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_author_make_link' ),
				'default' => false,
			],
		];
	}
}
