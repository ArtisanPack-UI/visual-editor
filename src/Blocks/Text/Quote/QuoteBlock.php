<?php

/**
 * Quote Block.
 *
 * Renders a blockquote with optional citation, alignment,
 * and style variants (default, large, pull-left, pull-right).
 * Supports inner blocks for rich quote content.
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
	 * Get toolbar control declarations for the block.
	 *
	 * Adds a citation toggle control to the block toolbar.
	 *
	 * @since 2.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function getToolbarControls(): array
	{
		$controls = parent::getToolbarControls();

		$controls[] = [
			'group'    => 'quote',
			'controls' => [
				[
					'type'  => 'toggle',
					'field' => 'showCitation',
					'label' => __( 'visual-editor::ve.citation' ),
				],
			],
		];

		return $controls;
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
