<?php

/**
 * Audio Block.
 *
 * Embeds an audio file with playback controls.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Audio
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Media\Audio;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Audio block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Media\Audio
 *
 * @since      1.0.0
 */
class AudioBlock extends BaseBlock
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
