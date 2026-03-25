<?php

/**
 * Post Terms Block.
 *
 * Displays taxonomy terms (categories, tags, custom taxonomies)
 * for the current content item with configurable separator,
 * prefix, and suffix.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTerms
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTerms;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Terms block for the visual editor.
 *
 * Displays the content's taxonomy terms (categories, tags, or
 * custom taxonomies) with configurable separator, prefix, and
 * suffix strings. Each term links to its archive page. Resolves
 * term data from the content context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTerms
 *
 * @since      2.0.0
 */
class PostTermsBlock extends BaseBlock
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
			'term'      => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_terms_taxonomy' ),
				'default' => 'category',
			],
			'separator' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_terms_separator' ),
				'default' => ', ',
			],
			'prefix'    => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_terms_prefix' ),
				'default' => '',
			],
			'suffix'    => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_terms_suffix' ),
				'default' => '',
			],
		];
	}
}
