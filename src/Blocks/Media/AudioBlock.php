<?php

/**
 * Audio Block.
 *
 * Embeds an audio file with playback controls.
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
 * Audio block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media
 *
 * @since      1.0.0
 */
class AudioBlock extends BaseBlock
{
	protected string $type = 'audio';

	protected string $name = 'Audio';

	protected string $description = 'Embed an audio file';

	protected string $icon = 'musical-note';

	protected string $category = 'media';

	protected array $keywords = [ 'music', 'sound', 'podcast' ];

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
				'label'   => __( 'visual-editor::ve.audio_url' ),
				'default' => '',
			],
			'caption' => [
				'type'    => 'rich_text',
				'label'   => __( 'visual-editor::ve.caption' ),
				'toolbar' => [ 'bold', 'italic', 'link' ],
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
			'preload'  => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.preload' ),
				'options' => [
					'auto'     => 'Auto',
					'metadata' => 'Metadata',
					'none'     => __( 'visual-editor::ve.none' ),
				],
				'default' => 'metadata',
			],
		];
	}
}
