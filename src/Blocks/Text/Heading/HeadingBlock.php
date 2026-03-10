<?php

/**
 * Heading Block.
 *
 * Renders a heading element (h1-h6) with configurable level,
 * alignment, colors, and typography settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Heading
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\Heading;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Heading block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\Heading
 *
 * @since      1.0.0
 */
class HeadingBlock extends BaseBlock
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
				'text' => 'text',
			],
			'list'      => [
				'text' => 'text',
			],
			'quote'     => [
				'text' => 'text',
			],
		];
	}
}
