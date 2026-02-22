<?php

/**
 * Quote Block.
 *
 * Renders a blockquote with optional citation, alignment,
 * and style variants (default, large, pull-left, pull-right).
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
 * Quote block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text
 *
 * @since      1.0.0
 */
class QuoteBlock extends BaseBlock
{
	protected string $type = 'quote';

	protected string $name = 'Quote';

	protected string $description = 'Add a quotation';

	protected string $icon = 'chat-bubble-bottom-center-text';

	protected string $category = 'text';

	protected array $keywords = [ 'blockquote', 'cite', 'quotation' ];

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
			'text'     => [
				'type'        => 'rich_text',
				'label'       => __( 'visual-editor::ve.block_quote_placeholder' ),
				'placeholder' => __( 'visual-editor::ve.block_quote_placeholder' ),
				'toolbar'     => [ 'bold', 'italic', 'link' ],
				'default'     => '',
			],
			'citation' => [
				'type'        => 'text',
				'label'       => __( 'visual-editor::ve.citation' ),
				'placeholder' => __( 'visual-editor::ve.citation_placeholder' ),
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
				'options' => [ 'left', 'center', 'right' ],
				'default' => 'left',
			],
			'style'           => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.quote_style' ),
				'options' => [
					'default'    => __( 'visual-editor::ve.default' ),
					'large'      => __( 'visual-editor::ve.large' ),
					'pull-left'  => __( 'visual-editor::ve.pull_left' ),
					'pull-right' => __( 'visual-editor::ve.pull_right' ),
				],
				'default' => 'default',
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
			'paragraph' => [
				'text' => 'text',
			],
			'heading'   => [
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
			'align'      => [ 'left', 'center', 'right', 'wide' ],
			'color'      => [
				'text'       => true,
				'background' => true,
			],
			'typography' => [
				'fontSize'   => false,
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
