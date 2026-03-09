<?php

/**
 * List Block.
 *
 * Renders ordered or unordered lists with nested item support,
 * start number, and reversed options.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\ListBlock
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Text\ListBlock;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * List block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation
 * and block transforms.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Text\ListBlock
 *
 * @since      1.0.0
 */
class ListBlock extends BaseBlock
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
			'start'    => [
				'type'      => 'text',
				'label'     => __( 'visual-editor::ve.start_number' ),
				'panel'     => __( 'visual-editor::ve.list_settings' ),
				'default'   => '1',
				'condition' => [ 'type', '==', 'ordered' ],
			],
			'reversed' => [
				'type'      => 'toggle',
				'label'     => __( 'visual-editor::ve.reversed' ),
				'panel'     => __( 'visual-editor::ve.list_settings' ),
				'default'   => false,
				'condition' => [ 'type', '==', 'ordered' ],
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
			'paragraph' => [],
			'heading'   => [],
			'quote'     => [],
		];
	}
}
