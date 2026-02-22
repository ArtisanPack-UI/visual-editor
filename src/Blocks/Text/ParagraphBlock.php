<?php

/**
 * Paragraph Block.
 *
 * Renders a paragraph of text with rich text editing, alignment,
 * colors, typography, and drop cap support.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Paragraph block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text
 *
 * @since      1.0.0
 */
class ParagraphBlock extends BaseBlock
{
	protected string $type = 'paragraph';

	protected string $name = 'Paragraph';

	protected string $description = 'Add a paragraph of text';

	protected string $icon = 'bars-3-bottom-left';

	protected string $category = 'text';

	protected array $keywords = [ 'text', 'content', 'body' ];

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
			'text' => [
				'type'        => 'rich_text',
				'label'       => __( 'visual-editor::ve.block_paragraph_placeholder' ),
				'placeholder' => __( 'visual-editor::ve.block_paragraph_placeholder' ),
				'toolbar'     => [ 'bold', 'italic', 'underline', 'strikethrough', 'link', 'code' ],
				'default'     => '',
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
			'alignment'       => [
				'type'    => 'alignment',
				'label'   => __( 'visual-editor::ve.text_alignment' ),
				'default' => 'left',
			],
			'textColor'       => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.text_color' ),
				'default' => null,
			],
			'backgroundColor' => [
				'type'    => 'color',
				'label'   => __( 'visual-editor::ve.background_color' ),
				'default' => null,
			],
			'fontSize'        => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.font_size' ),
				'options' => [
					'small' => __( 'visual-editor::ve.small' ),
					'base'  => __( 'visual-editor::ve.normal' ),
					'large' => __( 'visual-editor::ve.large' ),
					'xl'    => __( 'visual-editor::ve.extra_large' ),
				],
				'default' => null,
			],
			'dropCap'         => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.drop_cap' ),
				'default' => false,
			],
		];
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
			'heading' => [
				'text' => 'text',
			],
			'list'    => [
				'text' => 'text',
			],
			'quote'   => [
				'text' => 'text',
			],
		];
	}

	/**
	 * Get the block's supported features.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getSupports(): array
	{
		return [
			'align'      => false,
			'color'      => [
				'text'       => true,
				'background' => true,
			],
			'typography' => [
				'fontSize'   => true,
				'fontFamily' => false,
			],
			'spacing'    => [
				'margin'  => false,
				'padding' => false,
			],
			'border'     => false,
			'anchor'     => true,
			'className'  => true,
		];
	}
}
