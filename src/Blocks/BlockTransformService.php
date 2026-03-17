<?php

/**
 * Block Transform Service.
 *
 * Handles transforming blocks between compatible types by
 * mapping content fields from source to target block schemas.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;

/**
 * Service for transforming blocks between types.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @since      1.0.0
 */
class BlockTransformService
{
	/**
	 * The block registry instance.
	 *
	 * @since 1.0.0
	 *
	 * @var BlockRegistry
	 */
	protected BlockRegistry $registry;

	/**
	 * Create a new transform service instance.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockRegistry $registry The block registry.
	 */
	public function __construct( BlockRegistry $registry )
	{
		$this->registry = $registry;
	}

	/**
	 * Transform block content from one type to another.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockInterface       $source     The source block.
	 * @param string               $targetType The target block type.
	 * @param array<string, mixed> $content    The source content values.
	 *
	 * @return array<string, mixed>|null The transformed content, or null if transform is not available.
	 */
	public function transform( BlockInterface $source, string $targetType, array $content ): ?array
	{
		$transforms = $source->getTransforms();

		if ( ! isset( $transforms[ $targetType ] ) ) {
			return null;
		}

		$target = $this->registry->get( $targetType );

		if ( null === $target ) {
			return null;
		}

		$mappings       = $transforms[ $targetType ];
		$targetDefaults = $target->getDefaultContent();
		$result         = $targetDefaults;

		foreach ( $mappings as $targetField => $sourceField ) {
			if ( array_key_exists( $sourceField, $content ) ) {
				$result[ $targetField ] = $content[ $sourceField ];
			}
		}

		$result = veApplyFilters(
			'ap.visualEditor.block.transform',
			$result,
			$source->getType(),
			$targetType,
			$content,
		);

		return $result;
	}

	/**
	 * Get available transform targets for a block type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The source block type.
	 *
	 * @return array<int, string>
	 */
	public function getAvailableTransforms( string $type ): array
	{
		$block = $this->registry->get( $type );

		if ( null === $block ) {
			return [];
		}

		$transforms = $block->getTransforms();

		return array_keys( $transforms );
	}
}
