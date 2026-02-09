<?php

declare( strict_types=1 );

/**
 * Alignment Settings Service
 *
 * Manages block alignment settings including content width, wide width,
 * and block-specific alignment configurations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.9.0
 */

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Registries\BlockRegistry;

/**
 * Service for managing alignment settings.
 *
 * Provides methods to retrieve global alignment settings (content width,
 * wide width) and block-specific alignment configurations. Handles
 * per-block custom wide widths and alignment support detection.
 *
 * @since 1.9.0
 */
class AlignmentSettingsService
{
	/**
	 * The block registry instance.
	 *
	 * @since 1.9.0
	 */
	protected BlockRegistry $registry;

	/**
	 * Create a new AlignmentSettingsService instance.
	 *
	 * @since 1.9.0
	 *
	 * @param  BlockRegistry  $registry  The block registry.
	 */
	public function __construct( BlockRegistry $registry )
	{
		$this->registry = $registry;
	}

	/**
	 * Get the global content width in pixels.
	 *
	 * @since 1.9.0
	 *
	 * @return int Content width in pixels.
	 */
	public function getContentWidth(): int
	{
		return (int) config( 'artisanpack.visual-editor.alignment.content_width', 1200 );
	}

	/**
	 * Get the global wide width in pixels.
	 *
	 * @since 1.9.0
	 *
	 * @return int Wide width in pixels.
	 */
	public function getWideWidth(): int
	{
		return (int) config( 'artisanpack.visual-editor.alignment.wide_width', 1400 );
	}

	/**
	 * Get the wide width for a specific block.
	 *
	 * Returns the block's custom wide width if set, otherwise
	 * falls back to the global wide width setting.
	 *
	 * @since 1.9.0
	 *
	 * @param  array  $block  The block data array.
	 * @return int Wide width in pixels for this block.
	 */
	public function getBlockWideWidth( array $block ): int
	{
		$customWidth = $block['settings']['custom_wide_width'] ?? null;

		if ( null !== $customWidth && is_numeric( $customWidth ) ) {
			return (int) $customWidth;
		}

		return $this->getWideWidth();
	}

	/**
	 * Check if a block type supports alignment.
	 *
	 * @since 1.9.0
	 *
	 * @param  string  $blockType  The block type identifier.
	 * @return bool True if the block supports alignment.
	 */
	public function supportsAlignment( string $blockType ): bool
	{
		$config = $this->registry->get( $blockType );

		return $config['supports']['alignment'] ?? false;
	}

	/**
	 * Check if alignment is globally enabled.
	 *
	 * @since 1.9.0
	 *
	 * @return bool True if alignment is enabled.
	 */
	public function isEnabled(): bool
	{
		return (bool) config( 'artisanpack.visual-editor.alignment.enabled', true );
	}

	/**
	 * Get all alignment settings as an array.
	 *
	 * Useful for passing settings to JavaScript or for debugging.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, mixed> Array of alignment settings.
	 */
	public function getAllSettings(): array
	{
		return [
			'enabled'       => $this->isEnabled(),
			'content_width' => $this->getContentWidth(),
			'wide_width'    => $this->getWideWidth(),
		];
	}
}
