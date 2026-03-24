<?php

/**
 * Site Title Block.
 *
 * Renders the site name dynamically from site identity settings
 * with configurable heading level and optional homepage link.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\SiteTitle
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\SiteTitle;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Site Title block for the visual editor.
 *
 * Displays the site name from site identity configuration with
 * filter hook support for runtime customization. Supports
 * configurable heading level (h1-h6, p, span) and optional
 * link to the homepage.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\SiteTitle
 *
 * @since      2.0.0
 */
class SiteTitleBlock extends BaseBlock
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
			'level'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.html_element' ),
				'options' => [
					'h1'   => 'H1',
					'h2'   => 'H2',
					'h3'   => 'H3',
					'h4'   => 'H4',
					'h5'   => 'H5',
					'h6'   => 'H6',
					'p'    => __( 'visual-editor::ve.paragraph' ),
					'span' => __( 'visual-editor::ve.span' ),
				],
				'default' => 'h1',
			],
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
	 * Get toolbar control declarations for the block.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		return [
			[
				'group'    => 'block',
				'controls' => [
					[
						'type'    => 'select',
						'field'   => 'level',
						'source'  => 'content',
						'options' => [
							[ 'value' => 'h1', 'label' => 'H1' ],
							[ 'value' => 'h2', 'label' => 'H2' ],
							[ 'value' => 'h3', 'label' => 'H3' ],
							[ 'value' => 'h4', 'label' => 'H4' ],
							[ 'value' => 'h5', 'label' => 'H5' ],
							[ 'value' => 'h6', 'label' => 'H6' ],
							[ 'value' => 'p', 'label' => 'P' ],
							[ 'value' => 'span', 'label' => 'Span' ],
						],
					],
				],
			],
		];
	}
}
