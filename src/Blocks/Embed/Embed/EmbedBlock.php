<?php

/**
 * Generic Embed Block.
 *
 * Embeds external content via oEmbed resolution with
 * OpenGraph fallback, sandboxed iframe preview, and
 * configurable aspect ratio.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\Embed
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Embed\Embed;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Generic Embed block for the visual editor.
 *
 * Resolves oEmbed URLs server-side and renders the embed HTML
 * inside a sandboxed iframe. Falls back to an OpenGraph preview
 * card when oEmbed resolution fails.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\Embed
 *
 * @since      1.0.0
 */
class EmbedBlock extends BaseBlock
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
				'label'   => __( 'visual-editor::ve.embed_url' ),
				'default' => '',
			],
			'caption' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.embed_caption' ),
				'default' => '',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Embed-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'aspectRatio' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.embed_aspect_ratio' ),
				'options' => [
					'16:9'   => '16:9',
					'4:3'    => '4:3',
					'1:1'    => '1:1',
					'custom' => __( 'visual-editor::ve.custom' ),
				],
				'default' => '16:9',
			],
			'responsive'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.embed_responsive' ),
				'default' => true,
			],
		] );
	}

	/**
	 * Get available block transforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getTransforms(): array
	{
		return [
			'custom-html' => [
				'html' => 'content',
			],
		];
	}
}
