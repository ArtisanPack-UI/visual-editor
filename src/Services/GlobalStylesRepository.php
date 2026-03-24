<?php

/**
 * Global Styles Repository Service.
 *
 * Provides a persistence layer for global styles, loading from the database
 * and falling back to config-based defaults when no database record exists.
 * Supports saving with revision history and resetting to defaults.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use ArtisanPackUI\VisualEditor\Models\GlobalStyle;
use ArtisanPackUI\VisualEditor\Models\Revision;
use Illuminate\Support\Collection;

/**
 * Repository for persisting and retrieving global style settings.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class GlobalStylesRepository
{
	/**
	 * The color palette manager instance.
	 *
	 * @since 1.0.0
	 *
	 * @var ColorPaletteManager
	 */
	protected ColorPaletteManager $colorPaletteManager;

	/**
	 * The typography presets manager instance.
	 *
	 * @since 1.0.0
	 *
	 * @var TypographyPresetsManager
	 */
	protected TypographyPresetsManager $typographyManager;

	/**
	 * The spacing scale manager instance.
	 *
	 * @since 1.0.0
	 *
	 * @var SpacingScaleManager
	 */
	protected SpacingScaleManager $spacingManager;

	/**
	 * Create a new GlobalStylesRepository instance.
	 *
	 * @since 1.0.0
	 *
	 * @param ColorPaletteManager      $colorPaletteManager The color palette manager.
	 * @param TypographyPresetsManager $typographyManager   The typography presets manager.
	 * @param SpacingScaleManager      $spacingManager      The spacing scale manager.
	 */
	public function __construct(
		ColorPaletteManager $colorPaletteManager,
		TypographyPresetsManager $typographyManager,
		SpacingScaleManager $spacingManager,
	) {
		$this->colorPaletteManager = $colorPaletteManager;
		$this->typographyManager   = $typographyManager;
		$this->spacingManager      = $spacingManager;
	}

	/**
	 * Get the global styles record, or null if none exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The global style key.
	 *
	 * @return GlobalStyle|null
	 */
	public function get( string $key = GlobalStyle::DEFAULT_KEY ): ?GlobalStyle
	{
		return GlobalStyle::byKey( $key )->first();
	}

	/**
	 * Get the global styles record, creating one with config defaults if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The global style key.
	 *
	 * @return GlobalStyle
	 */
	public function getOrCreate( string $key = GlobalStyle::DEFAULT_KEY ): GlobalStyle
	{
		$record = $this->get( $key );

		if ( null !== $record ) {
			return $record;
		}

		return GlobalStyle::create( [
			'key'        => $key,
			'palette'    => $this->getDefaultPalette(),
			'typography' => $this->getDefaultTypography(),
			'spacing'    => $this->getDefaultSpacing(),
		] );
	}

	/**
	 * Save global styles to the database with a revision snapshot.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $data   The style data with palette, typography, and/or spacing keys.
	 * @param int|null $userId The user performing the save.
	 * @param string   $key    The global style key.
	 *
	 * @return GlobalStyle
	 */
	public function save( array $data, ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): GlobalStyle
	{
		$record = $this->getOrCreate( $key );

		$record->createRevision( $userId );

		$updateData = [];

		if ( array_key_exists( 'palette', $data ) ) {
			$updateData['palette'] = $data['palette'];
		}

		if ( array_key_exists( 'typography', $data ) ) {
			$updateData['typography'] = $data['typography'];
		}

		if ( array_key_exists( 'spacing', $data ) ) {
			$updateData['spacing'] = $data['spacing'];
		}

		if ( null !== $userId ) {
			$updateData['user_id'] = $userId;
		}

		if ( [] !== $updateData ) {
			$record->update( $updateData );
		}

		return $record->fresh();
	}

	/**
	 * Reset global styles to config-based defaults.
	 *
	 * Creates a revision of the current state before resetting.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $userId The user performing the reset.
	 * @param string   $key    The global style key.
	 *
	 * @return GlobalStyle
	 */
	public function resetToDefaults( ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): GlobalStyle
	{
		$record = $this->getOrCreate( $key );

		$record->createRevision( $userId );

		$record->update( [
			'palette'    => $this->getDefaultPalette(),
			'typography' => $this->getDefaultTypography(),
			'spacing'    => $this->getDefaultSpacing(),
			'user_id'    => $userId,
		] );

		return $record->fresh();
	}

	/**
	 * Restore a previous revision.
	 *
	 * Creates a revision of the current state before restoring.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $revisionId The revision ID to restore.
	 * @param int|null $userId     The user performing the restore.
	 * @param string   $key        The global style key.
	 *
	 * @return GlobalStyle|null Null if revision not found.
	 */
	public function restoreRevision( int $revisionId, ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): ?GlobalStyle
	{
		$record = $this->getOrCreate( $key );

		$revision = Revision::forDocument( GlobalStyle::REVISION_DOCUMENT_TYPE, $record->id )
			->where( 'id', $revisionId )
			->first();

		if ( null === $revision ) {
			return null;
		}

		$record->createRevision( $userId );

		$record->restoreFromRevision( $revision );

		return $record->fresh();
	}

	/**
	 * Get revision history for global styles.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $limit The maximum number of revisions to return.
	 * @param string $key   The global style key.
	 *
	 * @return Collection<int, Revision>
	 */
	public function getRevisions( int $limit = 20, string $key = GlobalStyle::DEFAULT_KEY ): Collection
	{
		$record = $this->get( $key );

		if ( null === $record ) {
			return collect();
		}

		return $record->revisions()->limit( $limit )->get();
	}

	/**
	 * Get the resolved palette data from the current record or config defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The global style key.
	 *
	 * @return array
	 */
	public function getPalette( string $key = GlobalStyle::DEFAULT_KEY ): array
	{
		$record = $this->get( $key );

		if ( null !== $record && null !== $record->palette ) {
			return $record->palette;
		}

		return $this->getDefaultPalette();
	}

	/**
	 * Get the resolved typography data from the current record or config defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The global style key.
	 *
	 * @return array
	 */
	public function getTypography( string $key = GlobalStyle::DEFAULT_KEY ): array
	{
		$record = $this->get( $key );

		if ( null !== $record && null !== $record->typography ) {
			return $record->typography;
		}

		return $this->getDefaultTypography();
	}

	/**
	 * Get the resolved spacing data from the current record or config defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The global style key.
	 *
	 * @return array
	 */
	public function getSpacing( string $key = GlobalStyle::DEFAULT_KEY ): array
	{
		$record = $this->get( $key );

		if ( null !== $record && null !== $record->spacing ) {
			return $record->spacing;
		}

		return $this->getDefaultSpacing();
	}

	/**
	 * Get the default palette from the manager.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function getDefaultPalette(): array
	{
		return $this->colorPaletteManager->toStoreFormat();
	}

	/**
	 * Get the default typography from the manager.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function getDefaultTypography(): array
	{
		return $this->typographyManager->toStoreFormat();
	}

	/**
	 * Get the default spacing from the manager.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	protected function getDefaultSpacing(): array
	{
		return $this->spacingManager->toStoreFormat();
	}
}
