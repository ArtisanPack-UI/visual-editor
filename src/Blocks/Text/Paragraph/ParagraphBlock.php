<?php

/**
 * Paragraph Block.
 *
 * Renders a paragraph of text with rich text editing, alignment,
 * colors, typography, and drop cap support.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Paragraph
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Paragraph;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Paragraph block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Paragraph
 *
 * @since      1.0.0
 */
class ParagraphBlock extends BaseBlock
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
			'heading' => [
				'text' => 'text',
			],
			'list'    => [
				'text' => 'text',
			],
			'quote'   => [
				'text' => 'text',
			],
			'code'    => [
				'text' => 'code',
			],
		];
	}
}
