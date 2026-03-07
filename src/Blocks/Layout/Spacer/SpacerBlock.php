<?php

/**
 * Spacer Block.
 *
 * Adds vertical space between blocks with configurable height and unit.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Spacer
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout\Spacer;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Spacer block for the visual editor.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout\Spacer
 *
 * @since      1.0.0
 */
class SpacerBlock extends BaseBlock
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
			'height' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.spacer_height' ),
				'default' => '40',
			],
			'unit'   => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.unit' ),
				'options' => [
					'px'  => 'px',
					'em'  => 'em',
					'rem' => 'rem',
					'vh'  => 'vh',
				],
				'default' => 'px',
			],
		];
	}
}
