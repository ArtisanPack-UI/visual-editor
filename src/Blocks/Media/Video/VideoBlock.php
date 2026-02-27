<?php

/**
 * Video Block.
 *
 * Embeds a video from YouTube, Vimeo, or self-hosted sources
 * with playback controls and responsive aspect ratio.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Video
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media\Video;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Video block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Video
 *
 * @since      1.0.0
 */
class VideoBlock extends BaseBlock
{
	/**
	 * Get the content field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getContentSchema(): array
	{
		return [
			'url'     => [
				'type'    => 'url',
				'label'   => __( 'visual-editor::ve.video_url' ),
				'default' => '',
			],
			'caption' => [
				'type'    => 'rich_text',
				'label'   => __( 'visual-editor::ve.caption' ),
				'toolbar' => [ 'bold', 'italic', 'link' ],
				'default' => '',
			],
			'poster'  => [
				'type'    => 'url',
				'label'   => __( 'visual-editor::ve.poster_image' ),
				'default' => '',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [
			'autoplay' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.autoplay' ),
				'default' => false,
			],
			'loop'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.loop' ),
				'default' => false,
			],
			'muted'    => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.muted' ),
				'default' => false,
			],
			'controls' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.controls' ),
				'default' => true,
			],
		];
	}
}
