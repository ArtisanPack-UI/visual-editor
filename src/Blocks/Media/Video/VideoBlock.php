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
			'url'        => [
				'type'    => 'media_picker',
				'label'   => __( 'visual-editor::ve.video_url' ),
				'default' => '',
			],
			'caption'    => [
				'type'    => 'rich_text',
				'label'   => __( 'visual-editor::ve.caption' ),
				'toolbar' => [ 'bold', 'italic', 'link' ],
				'default' => '',
			],
			'autoplay'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.autoplay' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'loop'       => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.loop' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'muted'      => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.muted' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'controls'   => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.playback_controls' ),
				'default' => true,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'playInline' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.play_inline' ),
				'hint'    => __( 'visual-editor::ve.play_inline_hint' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'preload'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.preload' ),
				'options' => [
					'auto'     => __( 'visual-editor::ve.preload_auto' ),
					'metadata' => __( 'visual-editor::ve.preload_metadata' ),
					'none'     => __( 'visual-editor::ve.preload_none' ),
				],
				'default' => 'metadata',
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'poster'     => [
				'type'    => 'media_picker',
				'label'   => __( 'visual-editor::ve.poster_image' ),
				'default' => '',
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
		];
	}
}
