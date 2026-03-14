<?php

/**
 * Custom HTML Block.
 *
 * Allows users to add raw HTML code with optional sanitization
 * via kses() from artisanpack-ui/security, syntax-highlighted
 * editing, and sandboxed iframe preview.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\CustomHtml
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Embed\CustomHtml;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Custom HTML block for the visual editor.
 *
 * Provides a code editor for raw HTML with toggle between
 * edit and preview modes and configurable sanitization.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Embed\CustomHtml
 *
 * @since      1.0.0
 */
class CustomHtmlBlock extends BaseBlock
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
			'sanitize' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.custom_html_sanitize' ),
				'default' => true,
			],
			'cssClass' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.custom_html_css_class' ),
				'default' => '',
			],
		];
	}
}
