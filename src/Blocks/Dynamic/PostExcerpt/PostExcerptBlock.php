<?php

/**
 * Post Excerpt Block.
 *
 * Renders the content excerpt with configurable length
 * and optional "Read more" link.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostExcerpt
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostExcerpt;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Excerpt block for the visual editor.
 *
 * Displays the content excerpt (auto-generated or manual) with
 * configurable word length and optional "Read more" link.
 * Resolves the excerpt from the content context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostExcerpt
 *
 * @since      2.0.0
 */
class PostExcerptBlock extends BaseBlock
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
			'excerptLength'      => [
				'type'    => 'range',
				'label'   => __( 'visual-editor::ve.post_excerpt_max_words' ),
				'min'     => 5,
				'max'     => 200,
				'default' => 55,
			],
			'moreText'           => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_excerpt_more_text' ),
				'default' => '',
			],
			'showMoreOnNewLine'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.post_excerpt_more_new_line' ),
				'default' => true,
			],
		];
	}
}
