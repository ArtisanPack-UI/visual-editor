<?php

/**
 * Code Block.
 *
 * Displays code snippets with syntax highlighting,
 * line numbers, and a copy button.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Code block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive
 *
 * @since      1.0.0
 */
class CodeBlock extends BaseBlock
{
	protected string $type = 'code';

	protected string $name = 'Code';

	protected string $description = 'Display code with syntax highlighting';

	protected string $icon = 'code-bracket';

	protected string $category = 'interactive';

	protected array $keywords = [ 'code', 'snippet', 'programming', 'syntax' ];

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
			'content'  => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.code_content' ),
				'default' => '',
			],
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
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [
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
		];
	}
}
