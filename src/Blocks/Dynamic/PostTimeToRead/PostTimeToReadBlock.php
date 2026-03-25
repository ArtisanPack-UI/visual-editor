<?php

/**
 * Post Time to Read Block.
 *
 * Calculates and displays the estimated reading time based on
 * the content word count and a configurable words-per-minute rate.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTimeToRead
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTimeToRead;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Post Time to Read block for the visual editor.
 *
 * Calculates the estimated reading time from the content word
 * count divided by a configurable words-per-minute rate. Supports
 * configurable prefix and suffix strings. Resolves word count
 * from the content context via filter hooks.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\PostTimeToRead
 *
 * @since      2.0.0
 */
class PostTimeToReadBlock extends BaseBlock
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
			'wordsPerMinute' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_time_to_read_wpm' ),
				'default' => '200',
			],
			'prefix'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_time_to_read_prefix' ),
				'default' => '',
			],
			'suffix'         => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.post_time_to_read_suffix' ),
				'default' => ' min read',
			],
		];
	}
}
