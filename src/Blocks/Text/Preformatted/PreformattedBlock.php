<?php

/**
 * Preformatted Block.
 *
 * Renders whitespace-preserving content inside a <pre> tag.
 * No rich text formatting — plain text only.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Preformatted
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Preformatted;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Preformatted block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Preformatted
 *
 * @since      1.0.0
 */
class PreformattedBlock extends BaseBlock
{
	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Preformatted-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'fontFamily'      => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.preformatted_font_family' ),
				'options' => [
					'monospace'       => __( 'visual-editor::ve.font_monospace' ),
					'ui-monospace'    => __( 'visual-editor::ve.font_ui_monospace' ),
					'Courier New'     => 'Courier New',
					'Consolas'        => 'Consolas',
					'Menlo'           => 'Menlo',
					'Source Code Pro' => 'Source Code Pro',
				],
				'default' => 'monospace',
			],
			'showLineNumbers' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_line_numbers' ),
				'default' => false,
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
			'code'      => [
				'content' => 'content',
			],
			'paragraph' => [
				'content' => 'text',
			],
		];
	}
}
