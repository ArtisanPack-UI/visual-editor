<?php

/**
 * Block Metadata Service.
 *
 * Serializes all registered block metadata into a JSON-friendly
 * structure for use by the Alpine.js editor store.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Inspector
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Inspector;

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;

/**
 * Service for serializing block metadata to JSON.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Inspector
 *
 * @since      2.0.0
 */
class BlockMetadataService
{
	/**
	 * Create a new BlockMetadataService instance.
	 *
	 * @since 2.0.0
	 *
	 * @param BlockRegistry         $registry      The block registry.
	 * @param SupportsPanelRegistry $panelRegistry The supports panel registry.
	 */
	public function __construct(
		protected BlockRegistry $registry,
		protected SupportsPanelRegistry $panelRegistry,
	) {
	}

	/**
	 * Get all block metadata as a JSON-friendly array.
	 *
	 * Returns a keyed array of block type => metadata for all
	 * registered blocks. Used to populate the Alpine editor store.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getAllBlockMeta(): array
	{
		$meta   = [];
		$blocks = $this->registry->all();

		foreach ( $blocks as $block ) {
			$type = $block->getType();

			$meta[ $type ] = [
				'type'            => $type,
				'name'            => $block->getName(),
				'description'     => $block->getDescription(),
				'icon'            => $block->getIcon(),
				'category'        => $block->getCategory(),
				'keywords'        => $block->getKeywords(),
				'version'         => $block->getVersion(),
				'public'          => $block->isPublic(),
				'supports'        => $block->getSupports(),
				'attributes'      => $block->getAttributes(),
				'contentSchema'   => $block->getContentSchema(),
				'styleSchema'     => $block->getStyleSchema(),
				'advancedSchema'  => $block->getAdvancedSchema(),
				'transforms'      => $block->getTransforms(),
				'variations'      => $block->getVariations(),
				'toolbarControls' => $block->getToolbarControls(),
				'supportsPanels'  => $this->panelRegistry->getPanelsForBlock( $block ),
				'defaults'        => [
					'content' => $block->getDefaultContent(),
					'styles'  => $block->getDefaultStyles(),
				],
				'hasCustomInspector' => $block->hasCustomInspector(),
				'hasCustomToolbar'   => $block->hasCustomToolbar(),
			];
		}

		return $meta;
	}
}
