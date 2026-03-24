<?php

/**
 * Site Tagline Block.
 *
 * Renders the site tagline/description dynamically from site
 * identity settings with configurable element level.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\SiteTagline
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Dynamic\SiteTagline;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Site Tagline block for the visual editor.
 *
 * Displays the site tagline from site identity configuration with
 * filter hook support for runtime customization. Supports
 * configurable element level (h1-h6, p, span).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Dynamic\SiteTagline
 *
 * @since      2.0.0
 */
class SiteTaglineBlock extends BaseBlock
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
			'level' => [
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
				'default' => 'p',
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
