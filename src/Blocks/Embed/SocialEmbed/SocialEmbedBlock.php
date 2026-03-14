<?php

/**
 * Social Embed Block.
 *
 * Embeds social media posts from Twitter/X, Instagram,
 * Facebook, TikTok, Reddit, Bluesky, and other platforms
 * via oEmbed resolution with auto-detection.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\SocialEmbed
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Embed\SocialEmbed;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Social Embed block for the visual editor.
 *
 * Auto-detects social media platform from URL and resolves
 * the embed via platform-specific oEmbed endpoints.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\SocialEmbed
 *
 * @since      1.0.0
 */
class SocialEmbedBlock extends BaseBlock
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
			'url'              => [
				'type'    => 'url',
				'label'   => __( 'visual-editor::ve.social_embed_url' ),
				'default' => '',
			],
			'hideConversation' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.social_hide_conversation' ),
				'default' => false,
			],
			'hideMedia'        => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.social_hide_media' ),
				'default' => false,
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Social-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'maxWidth' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.social_max_width' ),
				'default' => '550px',
			],
			'align'    => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.social_alignment' ),
				'options' => [
					'left'   => __( 'visual-editor::ve.left' ),
					'center' => __( 'visual-editor::ve.center' ),
					'right'  => __( 'visual-editor::ve.right' ),
				],
				'default' => 'center',
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
			'embed' => [
				'url'  => 'url',
				'html' => 'html',
			],
		];
	}
}
