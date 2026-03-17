<?php

/**
 * Accordion Section Block.
 *
 * Internal child block used within the Accordion block.
 * Acts as a collapsible section with a title header and
 * inner blocks content area.
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\AccordionSection
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks\Interactive\AccordionSection;

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;

/**
 * Accordion Section block (internal child of Accordion block).
 *
 * Metadata, attributes, and supports are declared in block.json.
 * This class provides content/style schemas for UI presentation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks\Interactive\AccordionSection
 *
 * @since      1.0.0
 */
class AccordionSectionBlock extends BaseBlock
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
			'title'        => [
				'type'    => 'text',
				'label'   => __( 'visual-editor::ve.accordion_section_title' ),
				'default' => '',
			],
			'isOpen'       => [
				'type'    => 'toggle',
				'label'   => __( 'visual-editor::ve.details_open_by_default' ),
				'default' => false,
			],
			'headingLevel' => [
				'type'    => 'select',
				'label'   => __( 'visual-editor::ve.accordion_heading_level' ),
				'options' => [
					'h2' => 'H2',
					'h3' => 'H3',
					'h4' => 'H4',
					'h5' => 'H5',
					'h6' => 'H6',
				],
				'default' => 'h3',
			],
		];
	}
}
