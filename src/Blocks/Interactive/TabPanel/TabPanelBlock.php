<?php

/**
 * Tab Panel Block.
 *
 * Internal child block used within the Tabs block.
 * Acts as a container for content within a single tab.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\TabPanel
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\TabPanel;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Tab Panel block (internal child of Tabs block).
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\TabPanel
 *
 * @since      1.0.0
 */
class TabPanelBlock extends BaseBlock
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
			'label' => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.tabs_tab_label' ),
				'default' => '',
			],
			'icon'  => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.tabs_tab_icon' ),
				'default' => '',
			],
		];
	}
}
