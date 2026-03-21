<?php

/**
 * Style Cascade Resolver Service.
 *
 * Implements the three-level style inheritance cascade:
 * Global styles → Template styles → Block styles (most specific wins).
 *
 * Provides merge logic where each level overrides only what it specifies,
 * inheriting the rest from the level above. Also provides source tracking
 * to identify where a block's current style value originates.
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

/**
 * Service for resolving the style cascade across global, template, and block levels.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class StyleCascadeResolver
{
	/**
	 * Style source level: global.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const SOURCE_GLOBAL = 'global';

	/**
	 * Style source level: template.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const SOURCE_TEMPLATE = 'template';

	/**
	 * Style source level: block.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const SOURCE_BLOCK = 'block';

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
	 * Create a new StyleCascadeResolver instance.
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
	 * Get the global styles as a normalized array.
	 *
	 * Collects the current state from all three managers into a single
	 * associative array keyed by category (colors, typography, spacing).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The global styles array.
	 */
	public function getGlobalStyles(): array
	{
		$palette = [];

		foreach ( $this->colors->getPalette() as $slug => $entry ) {
			$palette[ $slug ] = $entry['color'] ?? $entry;
		}

		return veApplyFilters( 'ap.visualEditor.cascade.globalStyles', [
			'colors'     => $palette,
			'typography' => [
				'fontFamilies' => $this->typography->getFontFamilies(),
				'elements'     => $this->typography->getElements(),
			],
			'spacing'    => [
				'scale'    => $this->formatSpacingScale(),
				'blockGap' => $this->spacing->getBlockGap(),
			],
		] );
	}

	/**
	 * Resolve the computed styles for a block given its context.
	 *
	 * Merges global ← template ← block styles using array_replace_recursive
	 * so that the most specific level wins for each property.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $blockStyles    The block-level style overrides.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return array<string, mixed> The fully resolved computed styles.
	 */
	public function resolve( array $blockStyles = [], array $templateStyles = [] ): array
	{
		$global = $this->getGlobalStyles();

		$merged = array_replace_recursive( $global, $templateStyles );
		$merged = array_replace_recursive( $merged, $blockStyles );

		return veApplyFilters( 'ap.visualEditor.cascade.resolved', $merged, $global, $templateStyles, $blockStyles );
	}

	/**
	 * Resolve the inherited styles for a block (global + template, without block overrides).
	 *
	 * Useful for showing the "default" value a block would inherit if it had
	 * no overrides, and for the "reset to default" feature.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return array<string, mixed> The inherited styles (global + template merged).
	 */
	public function resolveInherited( array $templateStyles = [] ): array
	{
		$global = $this->getGlobalStyles();

		return array_replace_recursive( $global, $templateStyles );
	}

	/**
	 * Determine the source level of a specific style property.
	 *
	 * Walks the cascade in reverse specificity (block → template → global)
	 * and returns which level provides the current effective value.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path           Dot-notation path to the property (e.g., 'colors.primary').
	 * @param array<string, mixed> $blockStyles    The block-level style overrides.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return string The source level constant: SOURCE_BLOCK, SOURCE_TEMPLATE, or SOURCE_GLOBAL.
	 */
	public function getSource( string $path, array $blockStyles = [], array $templateStyles = [] ): string
	{
		if ( $this->hasNestedKey( $blockStyles, $path ) ) {
			return self::SOURCE_BLOCK;
		}

		if ( $this->hasNestedKey( $templateStyles, $path ) ) {
			return self::SOURCE_TEMPLATE;
		}

		return self::SOURCE_GLOBAL;
	}

	/**
	 * Get the source map for all properties in the resolved styles.
	 *
	 * Returns a flat dot-notation map of property path → source level
	 * for every leaf value in the resolved styles.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $blockStyles    The block-level style overrides.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return array<string, string> Map of property path => source level.
	 */
	public function getSourceMap( array $blockStyles = [], array $templateStyles = [] ): array
	{
		$resolved = $this->resolve( $blockStyles, $templateStyles );
		$paths    = $this->flattenPaths( $resolved );
		$map      = [];

		foreach ( $paths as $path ) {
			$map[ $path ] = $this->getSource( $path, $blockStyles, $templateStyles );
		}

		return $map;
	}

	/**
	 * Get the value a property would have if the block override were removed.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path           Dot-notation path to the property.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return mixed The inherited value, or null if not set at any level.
	 */
	public function getInheritedValue( string $path, array $templateStyles = [] ): mixed
	{
		$inherited = $this->resolveInherited( $templateStyles );

		return $this->getNestedValue( $inherited, $path );
	}

	/**
	 * Check whether a block overrides a specific property.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path        Dot-notation path to the property.
	 * @param array<string, mixed> $blockStyles The block-level style overrides.
	 *
	 * @return bool True if the block provides its own value for this property.
	 */
	public function isBlockOverride( string $path, array $blockStyles = [] ): bool
	{
		return $this->hasNestedKey( $blockStyles, $path );
	}

	/**
	 * Check whether a template overrides a specific property.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $path           Dot-notation path to the property.
	 * @param array<string, mixed> $templateStyles The template-level style overrides.
	 *
	 * @return bool True if the template provides its own value for this property.
	 */
	public function isTemplateOverride( string $path, array $templateStyles = [] ): bool
	{
		return $this->hasNestedKey( $templateStyles, $path );
	}

	/**
	 * Get the spacing scale as a simplified slug => value map.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Slug => CSS value map.
	 */
	protected function formatSpacingScale(): array
	{
		$formatted = [];

		foreach ( $this->spacing->getScale() as $slug => $entry ) {
			$formatted[ $slug ] = $entry['value'] ?? $entry;
		}

		return $formatted;
	}

	/**
	 * Get a nested value from an array using dot notation.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $array The array to traverse.
	 * @param string               $path  Dot-notation path.
	 *
	 * @return mixed The value at the path, or null if not found.
	 */
	/**
	 * Check if a key exists at a dot-notation path in a nested array.
	 *
	 * Unlike getNestedValue, this distinguishes between a key set to null
	 * and a key that does not exist at all.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $array The array to check.
	 * @param string               $path  Dot-notation path.
	 *
	 * @return bool True if the key exists at the path.
	 */
	protected function hasNestedKey( array $array, string $path ): bool
	{
		$keys    = explode( '.', $path );
		$current = $array;

		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return false;
			}

			$current = $current[ $key ];
		}

		return true;
	}

	protected function getNestedValue( array $array, string $path ): mixed
	{
		$keys    = explode( '.', $path );
		$current = $array;

		foreach ( $keys as $key ) {
			if ( ! is_array( $current ) || ! array_key_exists( $key, $current ) ) {
				return null;
			}

			$current = $current[ $key ];
		}

		return $current;
	}

	/**
	 * Flatten a nested array into dot-notation paths for all leaf values.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $array  The array to flatten.
	 * @param string               $prefix The current path prefix.
	 *
	 * @return array<int, string> List of dot-notation paths.
	 */
	protected function flattenPaths( array $array, string $prefix = '' ): array
	{
		$paths = [];

		foreach ( $array as $key => $value ) {
			$path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

			if ( is_array( $value ) && ! empty( $value ) ) {
				$paths = array_merge( $paths, $this->flattenPaths( $value, $path ) );
			} else {
				$paths[] = $path;
			}
		}

		return $paths;
	}
}
