<?php

/**
 * Spacer Block.
 *
 * Adds vertical space between blocks with configurable height and unit.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Layout;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Spacer block for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Layout
 *
 * @since      1.0.0
 */
class SpacerBlock extends BaseBlock
{
	protected string $type = 'spacer';

	protected string $name = 'Spacer';

	protected string $description = 'Add vertical space between blocks';

	protected string $icon = 'arrows-up-down';

	protected string $category = 'layout';

	protected array $keywords = [ 'space', 'gap', 'separator' ];

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

	/**
	 * Get the style field schema.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getStyleSchema(): array
	{
		return [];
	}
}
