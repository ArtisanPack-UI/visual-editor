<?php

/**
 * Quote Block.
 *
 * Renders a blockquote with optional citation, alignment,
 * and style variants (default, large, pull-left, pull-right).
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Quote
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Quote;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Quote block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Quote
 *
 * @since      1.0.0
 */
class QuoteBlock extends BaseBlock
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
}
