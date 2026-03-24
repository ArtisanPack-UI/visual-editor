<?php

/**
 * Style Import/Export Service.
 *
 * Provides import and export functionality for global styles configurations,
 * including JSON validation, conflict detection, selective import, and a
 * named preset library.
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
use ArtisanPackUI\VisualEditor\Models\StylePreset;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonException;

/**
 * Service for importing and exporting global style configurations.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class StyleImportExportService
{
	/**
	 * The current export format version.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const EXPORT_VERSION = '1.0';

	/**
	 * Valid section names that can be imported/exported.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const VALID_SECTIONS = [ 'colors', 'typography', 'spacing' ];

	/**
	 * The global styles repository instance.
	 *
	 * @since 1.0.0
	 *
	 * @var GlobalStylesRepository
	 */
	protected GlobalStylesRepository $repository;

	/**
	 * Create a new StyleImportExportService instance.
	 *
	 * @since 1.0.0
	 *
	 * @param GlobalStylesRepository $repository The global styles repository.
	 */
	public function __construct( GlobalStylesRepository $repository )
	{
		$this->repository = $repository;
	}

	/**
	 * Export the current global styles as a structured array.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $name     A human-readable name for the export.
	 * @param array|null  $sections Sections to export (null for all).
	 * @param string      $key      The global style key.
	 *
	 * @return array The export data structure.
	 */
	public function export( string $name, ?array $sections = null, string $key = GlobalStyle::DEFAULT_KEY ): array
	{
		$palette    = $this->repository->getPalette( $key );
		$typography = $this->repository->getTypography( $key );
		$spacing    = $this->repository->getSpacing( $key );

		$styles = [];

		$includeSections = null === $sections ? self::VALID_SECTIONS : $sections;

		if ( in_array( 'colors', $includeSections, true ) ) {
			$styles['colors'] = $palette;
		}

		if ( in_array( 'typography', $includeSections, true ) ) {
			$styles['typography'] = $typography;
		}

		if ( in_array( 'spacing', $includeSections, true ) ) {
			$styles['spacing'] = $spacing;
		}

		return [
			'version'    => self::EXPORT_VERSION,
			'name'       => $name,
			'exportedAt' => now()->toIso8601String(),
			'styles'     => $styles,
		];
	}

	/**
	 * Export the current global styles as a JSON string.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $name     A human-readable name for the export.
	 * @param array|null  $sections Sections to export (null for all).
	 * @param string      $key      The global style key.
	 *
	 * @return string The JSON-encoded export data.
	 */
	public function exportJson( string $name, ?array $sections = null, string $key = GlobalStyle::DEFAULT_KEY ): string
	{
		$data = $this->export( $name, $sections, $key );

		return (string) json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Export the current global styles to a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $path     The file path to write to.
	 * @param string      $name     A human-readable name for the export.
	 * @param array|null  $sections Sections to export (null for all).
	 * @param string      $key      The global style key.
	 *
	 * @return bool Whether the file was written successfully.
	 */
	public function exportToFile( string $path, string $name, ?array $sections = null, string $key = GlobalStyle::DEFAULT_KEY ): bool
	{
		$json = $this->exportJson( $name, $sections, $key );
		$dir  = dirname( $path );

		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			return false;
		}

		return false !== file_put_contents( $path, $json );
	}

	/**
	 * Validate a JSON string against the expected import schema.
	 *
	 * @since 1.0.0
	 *
	 * @param string $json The JSON string to validate.
	 *
	 * @return array{valid: bool, errors: array<int, string>, data: array|null}
	 */
	public function validateJson( string $json ): array
	{
		$errors = [];

		try {
			$data = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			return [
				'valid'  => false,
				'errors' => [ __( 'visual-editor::ve.styles_import_invalid_json', [ 'error' => $e->getMessage() ] ) ],
				'data'   => null,
			];
		}

		if ( ! is_array( $data ) ) {
			return [
				'valid'  => false,
				'errors' => [ __( 'visual-editor::ve.styles_import_not_object' ) ],
				'data'   => null,
			];
		}

		if ( ! isset( $data['version'] ) || ! is_string( $data['version'] ) ) {
			$errors[] = __( 'visual-editor::ve.styles_import_missing_version' );
		}

		if ( ! isset( $data['styles'] ) || ! is_array( $data['styles'] ) ) {
			$errors[] = __( 'visual-editor::ve.styles_import_missing_styles' );

			return [
				'valid'  => false,
				'errors' => $errors,
				'data'   => null,
			];
		}

		$this->validateStylesSections( $data['styles'], $errors );

		return [
			'valid'  => [] === $errors,
			'errors' => $errors,
			'data'   => [] === $errors ? $data : null,
		];
	}

	/**
	 * Validate a file for import.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The file path to validate.
	 *
	 * @return array{valid: bool, errors: array<int, string>, data: array|null}
	 */
	public function validateFile( string $path ): array
	{
		if ( ! file_exists( $path ) ) {
			return [
				'valid'  => false,
				'errors' => [ __( 'visual-editor::ve.styles_import_file_not_found', [ 'path' => $path ] ) ],
				'data'   => null,
			];
		}

		if ( ! is_readable( $path ) ) {
			return [
				'valid'  => false,
				'errors' => [ __( 'visual-editor::ve.styles_import_file_not_readable', [ 'path' => $path ] ) ],
				'data'   => null,
			];
		}

		$contents = file_get_contents( $path );

		if ( false === $contents || '' === trim( $contents ) ) {
			return [
				'valid'  => false,
				'errors' => [ __( 'visual-editor::ve.styles_import_file_empty' ) ],
				'data'   => null,
			];
		}

		return $this->validateJson( $contents );
	}

	/**
	 * Detect conflicts between imported styles and current styles.
	 *
	 * When $sections is provided, only sections in that list are checked.
	 * When null, all sections present in the import data are checked.
	 *
	 * @since 1.0.0
	 *
	 * @param array      $importData The validated import data.
	 * @param string     $key        The global style key.
	 * @param array|null $sections   Sections to check (null for all present).
	 *
	 * @return array<string, array{current: mixed, imported: mixed}> Map of section => conflict details.
	 */
	public function detectConflicts( array $importData, string $key = GlobalStyle::DEFAULT_KEY, ?array $sections = null ): array
	{
		$conflicts = [];
		$styles    = $importData['styles'] ?? [];

		$shouldCheck = fn ( string $section ): bool => isset( $styles[ $section ] )
			&& ( null === $sections || in_array( $section, $sections, true ) );

		if ( $shouldCheck( 'colors' ) ) {
			$current = $this->repository->getPalette( $key );

			if ( $current !== $styles['colors'] ) {
				$conflicts['colors'] = [
					'current'  => $current,
					'imported' => $styles['colors'],
				];
			}
		}

		if ( $shouldCheck( 'typography' ) ) {
			$current = $this->repository->getTypography( $key );

			if ( $current !== $styles['typography'] ) {
				$conflicts['typography'] = [
					'current'  => $current,
					'imported' => $styles['typography'],
				];
			}
		}

		if ( $shouldCheck( 'spacing' ) ) {
			$current = $this->repository->getSpacing( $key );

			if ( $current !== $styles['spacing'] ) {
				$conflicts['spacing'] = [
					'current'  => $current,
					'imported' => $styles['spacing'],
				];
			}
		}

		return $conflicts;
	}

	/**
	 * Import styles from validated data.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $importData The validated import data.
	 * @param array|null  $sections   Sections to import (null for all available).
	 * @param int|null    $userId     The user performing the import.
	 * @param string      $key        The global style key.
	 *
	 * @return GlobalStyle The updated global style record.
	 */
	public function import( array $importData, ?array $sections = null, ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): GlobalStyle
	{
		$styles          = $importData['styles'] ?? [];
		$includeSections = null === $sections ? self::VALID_SECTIONS : $sections;

		$updateData = [];

		if ( in_array( 'colors', $includeSections, true ) && isset( $styles['colors'] ) ) {
			$updateData['palette'] = $styles['colors'];
		}

		if ( in_array( 'typography', $includeSections, true ) && isset( $styles['typography'] ) ) {
			$updateData['typography'] = $styles['typography'];
		}

		if ( in_array( 'spacing', $includeSections, true ) && isset( $styles['spacing'] ) ) {
			$updateData['spacing'] = $styles['spacing'];
		}

		if ( [] === $updateData ) {
			return $this->repository->getOrCreate( $key );
		}

		return $this->repository->save( $updateData, $userId, $key );
	}

	/**
	 * Import styles from a JSON string.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $json     The JSON string to import.
	 * @param array|null  $sections Sections to import (null for all).
	 * @param int|null    $userId   The user performing the import.
	 * @param string      $key      The global style key.
	 *
	 * @return array{success: bool, errors: array<int, string>, record: GlobalStyle|null}
	 */
	public function importJson( string $json, ?array $sections = null, ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): array
	{
		$validation = $this->validateJson( $json );

		if ( ! $validation['valid'] ) {
			return [
				'success' => false,
				'errors'  => $validation['errors'],
				'record'  => null,
			];
		}

		$record = $this->import( $validation['data'], $sections, $userId, $key );

		return [
			'success' => true,
			'errors'  => [],
			'record'  => $record,
		];
	}

	/**
	 * Import styles from a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $path     The file path to import from.
	 * @param array|null  $sections Sections to import (null for all).
	 * @param int|null    $userId   The user performing the import.
	 * @param string      $key      The global style key.
	 *
	 * @return array{success: bool, errors: array<int, string>, record: GlobalStyle|null}
	 */
	public function importFromFile( string $path, ?array $sections = null, ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): array
	{
		$validation = $this->validateFile( $path );

		if ( ! $validation['valid'] ) {
			return [
				'success' => false,
				'errors'  => $validation['errors'],
				'record'  => null,
			];
		}

		$record = $this->import( $validation['data'], $sections, $userId, $key );

		return [
			'success' => true,
			'errors'  => [],
			'record'  => $record,
		];
	}

	/**
	 * Save the current global styles as a named preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $name        The preset name.
	 * @param string|null $description Optional description.
	 * @param int|null    $userId      The user creating the preset.
	 * @param string      $key         The global style key.
	 *
	 * @return StylePreset The created or updated preset.
	 */
	public function savePreset( string $name, ?string $description = null, ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): StylePreset
	{
		$slug = Str::slug( $name );

		$palette    = $this->repository->getPalette( $key );
		$typography = $this->repository->getTypography( $key );
		$spacing    = $this->repository->getSpacing( $key );

		return StylePreset::updateOrCreate(
			[ 'slug' => $slug ],
			[
				'name'        => $name,
				'description' => $description,
				'palette'     => $palette,
				'typography'  => $typography,
				'spacing'     => $spacing,
				'user_id'     => $userId,
			],
		);
	}

	/**
	 * Save import data as a named preset without applying it.
	 *
	 * @since 1.0.0
	 *
	 * @param array       $importData  The validated import data.
	 * @param string|null $name        Override name (uses import name if null).
	 * @param string|null $description Optional description.
	 * @param int|null    $userId      The user creating the preset.
	 *
	 * @return StylePreset The created or updated preset.
	 */
	public function saveImportAsPreset( array $importData, ?string $name = null, ?string $description = null, ?int $userId = null ): StylePreset
	{
		$presetName = $name ?? $importData['name'] ?? __( 'visual-editor::ve.styles_preset_unnamed' );
		$slug       = Str::slug( $presetName );
		$styles     = $importData['styles'] ?? [];

		return StylePreset::updateOrCreate(
			[ 'slug' => $slug ],
			[
				'name'        => $presetName,
				'description' => $description,
				'palette'     => $styles['colors'] ?? null,
				'typography'  => $styles['typography'] ?? null,
				'spacing'     => $styles['spacing'] ?? null,
				'user_id'     => $userId,
			],
		);
	}

	/**
	 * Apply a preset to the global styles.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $slug     The preset slug.
	 * @param array|null  $sections Sections to apply (null for all).
	 * @param int|null    $userId   The user applying the preset.
	 * @param string      $key      The global style key.
	 *
	 * @return GlobalStyle|null The updated global style, or null if preset not found.
	 */
	public function applyPreset( string $slug, ?array $sections = null, ?int $userId = null, string $key = GlobalStyle::DEFAULT_KEY ): ?GlobalStyle
	{
		$preset = StylePreset::bySlug( $slug )->first();

		if ( null === $preset ) {
			return null;
		}

		$includeSections = null === $sections ? self::VALID_SECTIONS : $sections;
		$updateData      = [];

		if ( in_array( 'colors', $includeSections, true ) && null !== $preset->palette ) {
			$updateData['palette'] = $preset->palette;
		}

		if ( in_array( 'typography', $includeSections, true ) && null !== $preset->typography ) {
			$updateData['typography'] = $preset->typography;
		}

		if ( in_array( 'spacing', $includeSections, true ) && null !== $preset->spacing ) {
			$updateData['spacing'] = $preset->spacing;
		}

		if ( [] === $updateData ) {
			return $this->repository->getOrCreate( $key );
		}

		return $this->repository->save( $updateData, $userId, $key );
	}

	/**
	 * List all saved presets.
	 *
	 * @since 1.0.0
	 *
	 * @return Collection<int, StylePreset>
	 */
	public function listPresets(): Collection
	{
		return StylePreset::orderBy( 'name' )->get();
	}

	/**
	 * Delete a preset by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The preset slug.
	 *
	 * @return bool Whether the preset was deleted.
	 */
	public function deletePreset( string $slug ): bool
	{
		$preset = StylePreset::bySlug( $slug )->first();

		if ( null === $preset ) {
			return false;
		}

		return (bool) $preset->delete();
	}

	/**
	 * Validate the styles sections within import data.
	 *
	 * @since 1.0.0
	 *
	 * @param array              $styles The styles data to validate.
	 * @param array<int, string> &$errors The errors array to append to.
	 *
	 * @return void
	 */
	protected function validateStylesSections( array $styles, array &$errors ): void
	{
		$hasSection = false;

		if ( array_key_exists( 'colors', $styles ) ) {
			$hasSection = true;

			if ( ! is_array( $styles['colors'] ) ) {
				$errors[] = __( 'visual-editor::ve.styles_import_colors_not_array' );
			}
		}

		if ( array_key_exists( 'typography', $styles ) ) {
			$hasSection = true;

			if ( ! is_array( $styles['typography'] ) ) {
				$errors[] = __( 'visual-editor::ve.styles_import_typography_not_array' );
			}
		}

		if ( array_key_exists( 'spacing', $styles ) ) {
			$hasSection = true;

			if ( ! is_array( $styles['spacing'] ) ) {
				$errors[] = __( 'visual-editor::ve.styles_import_spacing_not_array' );
			}
		}

		if ( ! $hasSection ) {
			$errors[] = __( 'visual-editor::ve.styles_import_no_sections' );
		}
	}
}
