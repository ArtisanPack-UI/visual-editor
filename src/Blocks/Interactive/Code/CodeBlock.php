<?php

/**
 * Code Block.
 *
 * Displays code snippets with syntax highlighting,
 * line numbers, and a copy button.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Code
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\Code;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Code block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\Code
 *
 * @since      1.0.0
 */
class CodeBlock extends BaseBlock
{
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
			'paragraph' => [
				'code' => 'text',
			],
		];
	}

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
			'language' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.code_language' ),
				'options' => [
					'plain'      => __( 'visual-editor::ve.plain_text' ),
					'html'       => 'HTML',
					'css'        => 'CSS',
					'javascript' => 'JavaScript',
					'php'        => 'PHP',
					'python'     => 'Python',
					'ruby'       => 'Ruby',
					'java'       => 'Java',
					'bash'       => 'Bash',
					'json'       => 'JSON',
					'sql'        => 'SQL',
					'xml'        => 'XML',
					'yaml'       => 'YAML',
					'markdown'   => 'Markdown',
				],
				'default' => 'plain',
			],
			'filename' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.code_filename' ),
				'default' => '',
			],
		];
	}

	/**
	 * Get the style field schema.
	 *
	 * Merges auto-generated supports fields with custom Code-specific fields.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return array_merge( parent::getStyleSchema(), [
			'showLineNumbers' => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_line_numbers' ),
				'default' => true,
			],
			'highlightLines'  => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.highlight_lines' ),
				'default' => '',
			],
			'showCopyButton'  => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.show_copy_button' ),
				'default' => true,
			],
		] );
	}
}
