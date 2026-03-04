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
			'url'      => [
				'type'      => 'url',
				'label'     => __( 'visual-editor::ve.audio_url' ),
				'default'   => '',
				'inspector' => false,
			],
			'caption'  => [
				'type'      => 'rich_text',
				'label'     => __( 'visual-editor::ve.caption' ),
				'toolbar'   => [ 'bold', 'italic', 'link' ],
				'default'   => '',
				'inspector' => false,
			],
			'autoplay' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.autoplay' ),
				'hint'    => __( 'visual-editor::ve.autoplay_hint' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'loop'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.loop' ),
				'default' => false,
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
			],
			'preload'  => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.preload' ),
				'options' => [
					''         => __( 'visual-editor::ve.preload_browser_default' ),
					'auto'     => __( 'visual-editor::ve.preload_auto' ),
					'metadata' => __( 'visual-editor::ve.preload_metadata' ),
					'none'     => __( 'visual-editor::ve.preload_none' ),
				],
				'default' => '',
				'panel'   => __( 'visual-editor::ve.settings_tab' ),
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
		return [];
	}
}
