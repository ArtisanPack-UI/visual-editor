<?php

/**
 * Site Logo Block.
 *
 * Renders the site logo dynamically from site identity settings
 * with configurable dimensions and optional homepage link.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\SiteLogo
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\SiteLogo;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Site Logo block for the visual editor.
 *
 * Displays the site logo from site identity configuration with
 * filter hook support for runtime customization. Supports
 * configurable width/height, optional link to the homepage,
 * and border controls.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\SiteLogo
 *
 * @since      2.0.0
 */
class SiteLogoBlock extends BaseBlock
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
			'isLink'     => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.link_to_homepage' ),
				'default' => true,
			],
			'linkTarget' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.link_target' ),
				'options' => [
					'_self'  => __( 'visual-editor::ve.same_window' ),
					'_blank' => __( 'visual-editor::ve.new_window' ),
				],
				'default' => '_self',
			],
		];
	}

	/**
	 * Get the style field schema for the inspector panel.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'width'  => [
				'type'    => 'unit',
				'label'   => __( 'visual-editor::ve.width' ),
				'default' => '',
			],
			'height' => [
				'type'    => 'unit',
				'label'   => __( 'visual-editor::ve.height' ),
				'default' => '',
			],
		] );
	}
}
