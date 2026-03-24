<?php

/**
 * Theme JSON Loader Service.
 *
 * Reads and validates declarative theme.json files, feeding values
 * into the three style managers (ColorPaletteManager,
 * TypographyPresetsManager, SpacingScaleManager) at boot time.
 *
 * Supports multiple theme.json files loaded in cascade order, where
 * later files deep-merge on top of earlier ones. This enables CMS
 * themes to layer their own theme.json on top of the application's
 * base file.
 *
 * Priority: config > theme.json (last path wins) > package defaults.
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

use JsonException;
use RuntimeException;

/**
 * Service for loading and validating theme.json files.
 *
 * Parses WordPress-inspired theme.json files and applies their
 * settings to the visual editor's style managers. Supports loading
 * multiple files in cascade order where later files override earlier
 * ones via deep merge.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class ThemeJsonLoader
{
	/**
	 * The supported theme.json schema version.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public const SCHEMA_VERSION = 1;

	/**
	 * The merged theme.json data from all loaded files.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>|null
	 */
	protected ?array $data = null;

	/**
	 * The file path of the last loaded theme.json file.
	 *
	 * @since 1.0.0
	 *
	 * @var string|null
	 */
	protected ?string $filePath = null;

	/**
	 * All file paths that were successfully loaded.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected array $loadedPaths = [];

	/**
	 * Registered paths for additional theme.json files.
	 *
	 * These are appended after config-defined paths when loadPaths() runs.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected array $registeredPaths = [];

	/**
	 * Validation errors from the last load or validate call.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	protected array $errors = [];

	/**
	 * The color palette manager instance.
	 *
	 * @since 1.0.0
	 *
	 * @var ColorPaletteManager
	 */
	protected ColorPaletteManager $colors;

	/**
	 * The typography presets manager instance.
	 *
	 * @since 1.0.0
	 *
	 * @var TypographyPresetsManager
	 */
	protected TypographyPresetsManager $typography;

	/**
	 * The spacing scale manager instance.
	 *
	 * @since 1.0.0
	 *
	 * @var SpacingScaleManager
	 */
	protected SpacingScaleManager $spacing;

	/**
	 * Create a new ThemeJsonLoader instance.
	 *
	 * @since 1.0.0
	 *
	 * @param ColorPaletteManager      $colors     The color palette manager.
	 * @param TypographyPresetsManager $typography The typography presets manager.
	 * @param SpacingScaleManager      $spacing    The spacing scale manager.
	 */
	public function __construct(
		ColorPaletteManager $colors,
		TypographyPresetsManager $typography,
		SpacingScaleManager $spacing,
	) {
		$this->colors     = $colors;
		$this->typography = $typography;
		$this->spacing    = $spacing;
	}

	/**
	 * Register an additional theme.json path for cascade loading.
	 *
	 * Registered paths are appended after config-defined paths when
	 * loadPaths() is called. Use this in a service provider's boot()
	 * method to layer a CMS theme's overrides on top of the application's
	 * base theme.json.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to a theme.json file.
	 *
	 * @return void
	 */
	public function registerPath( string $path ): void
	{
		if ( ! in_array( $path, $this->registeredPaths, true ) ) {
			$this->registeredPaths[] = $path;
		}
	}

	/**
	 * Get all registered additional paths.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> The registered paths.
	 */
	public function getRegisteredPaths(): array
	{
		return $this->registeredPaths;
	}

	/**
	 * Load multiple theme.json files in cascade order.
	 *
	 * Each file is validated independently. Valid files are deep-merged
	 * in order, so later files override earlier ones. Invalid files are
	 * skipped and their errors are collected.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $paths Ordered array of absolute paths to theme.json files.
	 *
	 * @return bool Whether at least one file was loaded successfully.
	 */
	public function loadPaths( array $paths ): bool
	{
		$this->errors      = [];
		$this->data        = null;
		$this->filePath    = null;
		$this->loadedPaths = [];

		// Append programmatically registered paths after config paths.
		$allPaths = array_merge( $paths, $this->registeredPaths );

		foreach ( $allPaths as $path ) {
			$this->loadAndMerge( $path );
		}

		return null !== $this->data;
	}

	/**
	 * Load and parse a single theme.json file.
	 *
	 * Returns true if the file was loaded and is valid, false otherwise.
	 * Use getErrors() to retrieve validation errors. When loading a
	 * single file, any previously loaded data is replaced.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to the theme.json file.
	 *
	 * @return bool Whether the file was loaded successfully.
	 */
	public function load( string $path ): bool
	{
		$this->errors      = [];
		$this->data        = null;
		$this->filePath    = $path;
		$this->loadedPaths = [];

		if ( ! file_exists( $path ) ) {
			return false;
		}

		if ( ! is_readable( $path ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_not_readable', [ 'path' => $path ] );

			return false;
		}

		$contents = file_get_contents( $path );

		if ( false === $contents || '' === trim( $contents ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_empty' );

			return false;
		}

		try {
			$decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_invalid_json', [ 'error' => $e->getMessage() ] );

			return false;
		}

		if ( ! is_array( $decoded ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_not_object' );

			return false;
		}

		$this->data = $decoded;

		$valid = $this->validate();

		if ( ! $valid ) {
			$this->data     = null;
			$this->filePath = null;

			return false;
		}

		$this->loadedPaths[] = $path;

		return true;
	}

	/**
	 * Validate the loaded theme.json data against the schema.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the data is valid.
	 */
	public function validate(): bool
	{
		if ( null === $this->data ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_no_data' );

			return false;
		}

		$this->errors = [];

		$this->validateVersion();
		$this->validateSettings();
		$this->validateStyles();
		$this->validateTemplateOverrides();

		return [] === $this->errors;
	}

	/**
	 * Apply the loaded theme.json settings to the style managers.
	 *
	 * This method applies theme.json values as the base layer,
	 * which can then be overridden by config values.
	 *
	 * @since 1.0.0
	 *
	 * @throws RuntimeException If no valid data has been loaded.
	 *
	 * @return void
	 */
	public function apply(): void
	{
		if ( null === $this->data ) {
			throw new RuntimeException(
				__( 'visual-editor::ve.theme_json_apply_no_data' ),
			);
		}

		$this->applyColorSettings();
		$this->applyTypographySettings();
		$this->applySpacingSettings();
	}

	/**
	 * Get the merged theme.json data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>|null The parsed data, or null if not loaded.
	 */
	public function getData(): ?array
	{
		return $this->data;
	}

	/**
	 * Get the file path of the last loaded theme.json.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null The file path, or null if not set.
	 */
	public function getFilePath(): ?string
	{
		return $this->filePath;
	}

	/**
	 * Get all file paths that were successfully loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> The loaded file paths.
	 */
	public function getLoadedPaths(): array
	{
		return $this->loadedPaths;
	}

	/**
	 * Get validation errors from the last load or validate call.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> The validation errors.
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * Check whether a theme.json file has been loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether data is loaded.
	 */
	public function isLoaded(): bool
	{
		return null !== $this->data;
	}

	/**
	 * Get the block style defaults from the loaded theme.json.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Block type to style map.
	 */
	public function getBlockStyles(): array
	{
		return $this->data['styles']['blocks'] ?? [];
	}

	/**
	 * Get the template overrides from the loaded theme.json.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Template slug to overrides map.
	 */
	public function getTemplateOverrides(): array
	{
		return $this->data['templateOverrides'] ?? [];
	}

	/**
	 * Get the schema version from the loaded data.
	 *
	 * @since 1.0.0
	 *
	 * @return int|null The version, or null if not loaded.
	 */
	public function getVersion(): ?int
	{
		if ( null === $this->data ) {
			return null;
		}

		return isset( $this->data['version'] ) ? (int) $this->data['version'] : null;
	}

	/**
	 * Load a single file and deep-merge it into the current data.
	 *
	 * Files that don't exist are silently skipped. Files with errors
	 * have their errors collected but don't prevent other files from loading.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to a theme.json file.
	 *
	 * @return void
	 */
	protected function loadAndMerge( string $path ): void
	{
		if ( ! file_exists( $path ) ) {
			return;
		}

		if ( ! is_readable( $path ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_not_readable', [ 'path' => $path ] );

			return;
		}

		$contents = file_get_contents( $path );

		if ( false === $contents || '' === trim( $contents ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_empty' );

			return;
		}

		try {
			$decoded = json_decode( $contents, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $e ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_invalid_json', [ 'error' => $e->getMessage() ] );

			return;
		}

		if ( ! is_array( $decoded ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_not_object' );

			return;
		}

		// Validate this file independently before merging.
		$previousData   = $this->data;
		$previousErrors = $this->errors;

		$this->data   = $decoded;
		$this->errors = [];

		$valid = $this->validate();

		if ( ! $valid ) {
			// Restore previous state and keep the validation errors.
			$mergedErrors = array_merge( $previousErrors, $this->errors );
			$this->data   = $previousData;
			$this->errors = $mergedErrors;

			return;
		}

		// Merge this file's data on top of any previously loaded data.
		if ( null === $previousData ) {
			// First valid file — use as-is.
			$this->data = $decoded;
		} else {
			$this->data = $this->deepMerge( $previousData, $decoded );
		}

		$this->errors        = $previousErrors;
		$this->filePath      = $path;
		$this->loadedPaths[] = $path;
	}

	/**
	 * Deep-merge two theme.json data arrays.
	 *
	 * Arrays are recursively merged. The color palette array uses a
	 * special slug-based merge strategy: entries from the override
	 * replace base entries with the same slug, and new entries are
	 * appended.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $base     The base data.
	 * @param array<string, mixed> $override The override data.
	 *
	 * @return array<string, mixed> The merged data.
	 */
	protected function deepMerge( array $base, array $override ): array
	{
		$merged = $base;

		foreach ( $override as $key => $value ) {
			if ( ! array_key_exists( $key, $merged ) ) {
				$merged[ $key ] = $value;

				continue;
			}

			// Special handling for color palette (indexed array with slug keys).
			if ( 'palette' === $key && is_array( $value ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->mergePalettes( $merged[ $key ], $value );

				continue;
			}

			// Recursive merge for associative arrays.
			if ( is_array( $value ) && is_array( $merged[ $key ] ) && $this->isAssociative( $value ) ) {
				$merged[ $key ] = $this->deepMerge( $merged[ $key ], $value );

				continue;
			}

			// Scalar values and indexed arrays: override replaces base.
			$merged[ $key ] = $value;
		}

		return $merged;
	}

	/**
	 * Merge two color palette arrays using slug-based matching.
	 *
	 * Entries from the override palette replace base entries with the
	 * same slug. Entries with new slugs are appended to the result.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{name: string, slug: string, color: string}> $base     The base palette.
	 * @param array<int, array{name: string, slug: string, color: string}> $override The override palette.
	 *
	 * @return array<int, array{name: string, slug: string, color: string}> The merged palette.
	 */
	protected function mergePalettes( array $base, array $override ): array
	{
		// Index base palette by slug.
		$indexed = [];
		foreach ( $base as $entry ) {
			if ( isset( $entry['slug'] ) ) {
				$indexed[ $entry['slug'] ] = $entry;
			}
		}

		// Override/add from the override palette.
		foreach ( $override as $entry ) {
			if ( isset( $entry['slug'] ) ) {
				$indexed[ $entry['slug'] ] = $entry;
			}
		}

		return array_values( $indexed );
	}

	/**
	 * Check if an array is associative (string keys).
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $array The array to check.
	 *
	 * @return bool True if the array has string keys.
	 */
	protected function isAssociative( array $array ): bool
	{
		if ( [] === $array ) {
			return false;
		}

		return ! array_is_list( $array );
	}

	/**
	 * Validate the version field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function validateVersion(): void
	{
		if ( ! isset( $this->data['version'] ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_missing_version' );

			return;
		}

		$version = $this->data['version'];

		if ( ! is_int( $version ) || $version < 1 ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_invalid_version' );

			return;
		}

		if ( $version > self::SCHEMA_VERSION ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_unsupported_version', [
				'version'   => $version,
				'supported' => self::SCHEMA_VERSION,
			] );
		}
	}

	/**
	 * Validate the settings section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function validateSettings(): void
	{
		if ( ! isset( $this->data['settings'] ) ) {
			return;
		}

		if ( ! is_array( $this->data['settings'] ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_settings_not_object' );

			return;
		}

		$this->validateColorSettings();
		$this->validateTypographySettings();
		$this->validateSpacingSettings();
	}

	/**
	 * Validate the color palette settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function validateColorSettings(): void
	{
		$color = $this->data['settings']['color'] ?? null;

		if ( null === $color ) {
			return;
		}

		if ( ! is_array( $color ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_color_not_object' );

			return;
		}

		$palette = $color['palette'] ?? null;

		if ( null === $palette ) {
			return;
		}

		if ( ! is_array( $palette ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_palette_not_array' );

			return;
		}

		foreach ( $palette as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_palette_entry_not_object', [ 'index' => $index ] );

				continue;
			}

			if ( ! isset( $entry['name'] ) || ! is_string( $entry['name'] ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_palette_missing_name', [ 'index' => $index ] );
			}

			if ( ! isset( $entry['slug'] ) || ! is_string( $entry['slug'] ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_palette_missing_slug', [ 'index' => $index ] );
			}

			if ( ! isset( $entry['color'] ) || ! is_string( $entry['color'] ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_palette_missing_color', [ 'index' => $index ] );
			} elseif ( ! preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $entry['color'] ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_palette_invalid_color', [
					'index' => $index,
					'color' => $entry['color'],
				] );
			}
		}
	}

	/**
	 * Validate the typography settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function validateTypographySettings(): void
	{
		$typography = $this->data['settings']['typography'] ?? null;

		if ( null === $typography ) {
			return;
		}

		if ( ! is_array( $typography ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_typography_not_object' );

			return;
		}

		$families = $typography['fontFamilies'] ?? null;

		if ( null !== $families ) {
			if ( ! is_array( $families ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_font_families_not_object' );
			} else {
				$allowed = TypographyPresetsManager::ALLOWED_FAMILY_SLOTS;
				foreach ( array_keys( $families ) as $slot ) {
					if ( ! in_array( $slot, $allowed, true ) ) {
						$this->errors[] = __( 'visual-editor::ve.theme_json_invalid_font_slot', [
							'slot'    => $slot,
							'allowed' => implode( ', ', $allowed ),
						] );
					}
				}
			}
		}

		$elements = $typography['elements'] ?? null;

		if ( null !== $elements ) {
			if ( ! is_array( $elements ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_elements_not_object' );
			} else {
				$allowed = TypographyPresetsManager::ALLOWED_ELEMENTS;
				foreach ( array_keys( $elements ) as $element ) {
					if ( ! in_array( $element, $allowed, true ) ) {
						$this->errors[] = __( 'visual-editor::ve.theme_json_invalid_element', [
							'element' => $element,
							'allowed' => implode( ', ', $allowed ),
						] );
					}
				}
			}
		}
	}

	/**
	 * Validate the spacing settings.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function validateSpacingSettings(): void
	{
		$spacing = $this->data['settings']['spacing'] ?? null;

		if ( null === $spacing ) {
			return;
		}

		if ( ! is_array( $spacing ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_spacing_not_object' );

			return;
		}

		$scale = $spacing['scale'] ?? null;

		if ( null !== $scale && ! is_array( $scale ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_scale_not_object' );
		}

		$blockGap = $spacing['blockGap'] ?? null;

		if ( null !== $blockGap && ! is_string( $blockGap ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_block_gap_not_string' );
		}
	}

	/**
	 * Validate the styles section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function validateStyles(): void
	{
		$styles = $this->data['styles'] ?? null;

		if ( null === $styles ) {
			return;
		}

		if ( ! is_array( $styles ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_styles_not_object' );

			return;
		}

		$blocks = $styles['blocks'] ?? null;

		if ( null !== $blocks && ! is_array( $blocks ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_blocks_not_object' );
		}
	}

	/**
	 * Validate the templateOverrides section.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function validateTemplateOverrides(): void
	{
		$overrides = $this->data['templateOverrides'] ?? null;

		if ( null === $overrides ) {
			return;
		}

		if ( ! is_array( $overrides ) ) {
			$this->errors[] = __( 'visual-editor::ve.theme_json_overrides_not_object' );

			return;
		}

		foreach ( $overrides as $slug => $override ) {
			if ( ! is_array( $override ) ) {
				$this->errors[] = __( 'visual-editor::ve.theme_json_override_not_object', [ 'slug' => $slug ] );
			}
		}
	}

	/**
	 * Apply color palette settings from theme.json.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function applyColorSettings(): void
	{
		$palette = $this->data['settings']['color']['palette'] ?? null;

		if ( null === $palette || ! is_array( $palette ) ) {
			return;
		}

		$converted = [];
		foreach ( $palette as $entry ) {
			if ( isset( $entry['slug'], $entry['name'], $entry['color'] ) ) {
				$converted[ $entry['slug'] ] = [
					'name'  => $entry['name'],
					'slug'  => $entry['slug'],
					'color' => $entry['color'],
				];
			}
		}

		if ( [] !== $converted ) {
			$this->colors->setPalette( $converted );
		}
	}

	/**
	 * Apply typography settings from theme.json.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function applyTypographySettings(): void
	{
		$typography = $this->data['settings']['typography'] ?? null;

		if ( null === $typography || ! is_array( $typography ) ) {
			return;
		}

		$families = $typography['fontFamilies'] ?? null;

		if ( is_array( $families ) && [] !== $families ) {
			foreach ( $families as $slot => $family ) {
				if ( in_array( $slot, TypographyPresetsManager::ALLOWED_FAMILY_SLOTS, true ) ) {
					$this->typography->setFontFamily( $slot, $family );
				}
			}
		}

		$elements = $typography['elements'] ?? null;

		if ( is_array( $elements ) && [] !== $elements ) {
			foreach ( $elements as $element => $styles ) {
				if (
					in_array( $element, TypographyPresetsManager::ALLOWED_ELEMENTS, true )
					&& is_array( $styles )
				) {
					$this->typography->setElement( $element, $styles );
				}
			}
		}
	}

	/**
	 * Apply spacing settings from theme.json.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function applySpacingSettings(): void
	{
		$spacing = $this->data['settings']['spacing'] ?? null;

		if ( null === $spacing || ! is_array( $spacing ) ) {
			return;
		}

		$scale = $spacing['scale'] ?? null;

		if ( is_array( $scale ) && [] !== $scale ) {
			$built = [];
			foreach ( $scale as $slug => $value ) {
				$built[ $slug ] = [
					'name'  => ucfirst( (string) $slug ),
					'slug'  => (string) $slug,
					'value' => $value,
				];
			}

			$this->spacing->setScale( $built );
		}

		$blockGap = $spacing['blockGap'] ?? null;

		if ( is_string( $blockGap ) ) {
			$this->spacing->setBlockGap( $blockGap );
		}
	}
}
