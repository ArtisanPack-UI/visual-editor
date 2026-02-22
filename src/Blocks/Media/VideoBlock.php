<?php

/**
 * Video Block.
 *
 * Embeds a video from YouTube, Vimeo, or self-hosted sources
 * with playback controls and responsive aspect ratio.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Video block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @since      1.0.0
 */
class VideoBlock extends BaseBlock
{
	protected string $type = 'video';

	protected string $name = 'Video';

	protected string $description = 'Embed a video from YouTube, Vimeo, or upload';

	protected string $icon = 'video-camera';

	protected string $category = 'media';

	protected array $keywords = [ 'movie', 'film', 'youtube', 'vimeo' ];

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

	/**
	 * Get the block's supported features.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
	{
		return [
			'align'      => [ 'wide', 'full' ],
			'color'      => [
				'text'       => false,
				'background' => false,
			],
			'typography' => [
				'fontSize'   => false,
				'fontFamily' => false,
			],
			'spacing'    => [
				'margin'  => false,
				'padding' => false,
			],
			'border'     => false,
			'anchor'     => true,
			'className'  => true,
		];
	}
}
