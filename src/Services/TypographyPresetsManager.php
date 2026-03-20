<?php

/**
 * Typography Presets Manager Service.
 *
 * Manages typography preset definitions, font family registration,
 * element-level typography styles, and CSS custom property generation
 * for the visual editor's global styles system.
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

use InvalidArgumentException;

/**
 * Service for managing typography presets with CSS generation and font loading.
 *
 * Provides default font families, element-level typography styles,
 * CSS custom property generation, type scale computation, and
 * Google Fonts / custom font @font-face declaration generation.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class TypographyPresetsManager
{

	/**
	 * Allowed CSS font-weight values.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_WEIGHTS = [
		'100', '200', '300', '400', '500', '600', '700', '800', '900',
		'normal', 'bold', 'lighter', 'bolder',
	];

	/**
	 * Allowed CSS font-style values.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_FONT_STYLES = [ 'normal', 'italic', 'oblique' ];

	/**
	 * Allowed element keys.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_ELEMENTS = [
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
		'body', 'small', 'caption', 'blockquote', 'code',
	];

	/**
	 * Allowed font family slot keys.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_FAMILY_SLOTS = [ 'heading', 'body', 'mono' ];

	/**
	 * Allowed font collection category values.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_FONT_CATEGORIES = [ 'all', 'heading', 'body' ];

	/**
	 * Allowed font collection source values.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_FONT_SOURCES = [ 'system', 'custom', 'google' ];

	/**
	 * Default system fonts available to all sites.
	 *
	 * Each entry has a display name, the CSS font-family stack,
	 * a category (all, heading, body), and source (system).
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{name: string, family: string, category: string, source: string}>
	 */
	public const DEFAULT_SYSTEM_FONTS = [
		'system-ui'     => [
			'name'     => 'System UI',
			'family'   => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'arial'         => [
			'name'     => 'Arial',
			'family'   => 'Arial, Helvetica, sans-serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'georgia'       => [
			'name'     => 'Georgia',
			'family'   => 'Georgia, "Times New Roman", serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'times'         => [
			'name'     => 'Times New Roman',
			'family'   => '"Times New Roman", Times, serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'trebuchet'     => [
			'name'     => 'Trebuchet MS',
			'family'   => '"Trebuchet MS", Helvetica, sans-serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'verdana'       => [
			'name'     => 'Verdana',
			'family'   => 'Verdana, Geneva, sans-serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'courier'       => [
			'name'     => 'Courier New',
			'family'   => '"Courier New", Courier, monospace',
			'category' => 'all',
			'source'   => 'system',
		],
		'palatino'      => [
			'name'     => 'Palatino',
			'family'   => '"Palatino Linotype", "Book Antiqua", Palatino, serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'garamond'      => [
			'name'     => 'Garamond',
			'family'   => 'Garamond, Baskerville, "Baskerville Old Face", serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'tahoma'        => [
			'name'     => 'Tahoma',
			'family'   => 'Tahoma, Geneva, sans-serif',
			'category' => 'all',
			'source'   => 'system',
		],
		'impact'        => [
			'name'     => 'Impact',
			'family'   => 'Impact, "Arial Narrow", sans-serif',
			'category' => 'heading',
			'source'   => 'system',
		],
		'monospace'     => [
			'name'     => 'Monospace',
			'family'   => 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace',
			'category' => 'all',
			'source'   => 'system',
		],
	];

	/**
	 * Default font families by slot.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	public const DEFAULT_FONT_FAMILIES = [
		'heading' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
		'body'    => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
		'mono'    => 'ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, monospace',
	];

	/**
	 * Default element typography presets.
	 *
	 * Each element has fontSize, fontWeight, lineHeight, and optionally
	 * letterSpacing and fontStyle.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{fontSize: string, fontWeight: string, lineHeight: string, letterSpacing?: string, fontStyle?: string}>
	 */
	public const DEFAULT_ELEMENTS = [
		'h1'         => [
			'fontSize'      => '2.5rem',
			'fontWeight'    => '700',
			'lineHeight'    => '1.2',
			'letterSpacing' => '-0.02em',
		],
		'h2'         => [
			'fontSize'      => '2rem',
			'fontWeight'    => '600',
			'lineHeight'    => '1.3',
			'letterSpacing' => '-0.01em',
		],
		'h3'         => [
			'fontSize'   => '1.75rem',
			'fontWeight' => '600',
			'lineHeight' => '1.35',
		],
		'h4'         => [
			'fontSize'   => '1.5rem',
			'fontWeight' => '600',
			'lineHeight' => '1.4',
		],
		'h5'         => [
			'fontSize'   => '1.25rem',
			'fontWeight' => '600',
			'lineHeight' => '1.4',
		],
		'h6'         => [
			'fontSize'   => '1.125rem',
			'fontWeight' => '600',
			'lineHeight' => '1.4',
		],
		'body'       => [
			'fontSize'   => '1rem',
			'fontWeight' => '400',
			'lineHeight' => '1.6',
		],
		'small'      => [
			'fontSize'   => '0.875rem',
			'fontWeight' => '400',
			'lineHeight' => '1.5',
		],
		'caption'    => [
			'fontSize'   => '0.75rem',
			'fontWeight' => '400',
			'lineHeight' => '1.4',
		],
		'blockquote' => [
			'fontSize'   => '1.125rem',
			'fontWeight' => '400',
			'lineHeight' => '1.6',
			'fontStyle'  => 'italic',
		],
		'code'       => [
			'fontSize'   => '0.875rem',
			'fontWeight' => '400',
			'lineHeight' => '1.5',
		],
	];

	/**
	 * The current font families.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, string>
	 */
	protected array $fontFamilies = [];

	/**
	 * The current element typography presets.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, string>>
	 */
	protected array $elements = [];

	/**
	 * Registered custom font sources for @font-face generation.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array{family: string, src: string, weight?: string, style?: string}>
	 */
	protected array $customFonts = [];

	/**
	 * Registered Google Fonts families.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, array{family: string, weights?: array<int, string>, styles?: array<int, string>}>
	 */
	protected array $googleFonts = [];

	/**
	 * The font collection registry.
	 *
	 * A flat array of available fonts keyed by slug. Each entry has
	 * name, family (CSS stack), category (all/heading/body), and
	 * source (system/custom/google).
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{name: string, family: string, category: string, source: string}>
	 */
	protected array $fontCollection = [];

	/**
	 * Create a new TypographyPresetsManager instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $config Initial configuration to merge with defaults.
	 */
	public function __construct( array $config = [] )
	{
		$families = $config['fontFamilies'] ?? [];
		$elements = $config['elements'] ?? [];

		$this->fontFamilies = [] === $families
			? self::DEFAULT_FONT_FAMILIES
			: array_merge( self::DEFAULT_FONT_FAMILIES, $families );

		$this->elements = [] === $elements
			? self::DEFAULT_ELEMENTS
			: array_replace_recursive( self::DEFAULT_ELEMENTS, $elements );

		$this->fontCollection = self::DEFAULT_SYSTEM_FONTS;

		$configFonts = $config['fonts'] ?? [];
		foreach ( $configFonts as $slug => $font ) {
			if ( isset( $font['name'], $font['family'] ) ) {
				$category = $font['category'] ?? 'all';
				$source   = $font['source'] ?? 'custom';

				if ( ! in_array( $category, self::ALLOWED_FONT_CATEGORIES, true ) ) {
					$category = 'all';
				}

				if ( ! in_array( $source, self::ALLOWED_FONT_SOURCES, true ) ) {
					$source = 'custom';
				}

				$this->fontCollection[ $slug ] = [
					'name'     => $font['name'],
					'family'   => $font['family'],
					'category' => $category,
					'source'   => $source,
				];
			}
		}
	}

	/**
	 * Get the font families.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function getFontFamilies(): array
	{
		return veApplyFilters( 'ap.visualEditor.typographyFontFamilies', $this->fontFamilies );
	}

	/**
	 * Set all font families, replacing existing entries.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $families The font families keyed by slot.
	 *
	 * @return void
	 */
	public function setFontFamilies( array $families ): void
	{
		$allowed = array_flip( self::ALLOWED_FAMILY_SLOTS );

		$this->fontFamilies = array_intersect_key( $families, $allowed );
	}

	/**
	 * Get a single font family by slot.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot The font family slot (heading, body, mono).
	 *
	 * @return string|null
	 */
	public function getFontFamily( string $slot ): ?string
	{
		$families = $this->getFontFamilies();

		return $families[ $slot ] ?? null;
	}

	/**
	 * Set a single font family by slot.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slot   The font family slot.
	 * @param string $family The CSS font-family value.
	 *
	 * @throws InvalidArgumentException If the slot is not allowed.
	 *
	 * @return void
	 */
	public function setFontFamily( string $slot, string $family ): void
	{
		if ( ! in_array( $slot, self::ALLOWED_FAMILY_SLOTS, true ) ) {
			throw new InvalidArgumentException(
				"Invalid font family slot: {$slot}. Allowed: " . implode( ', ', self::ALLOWED_FAMILY_SLOTS ),
			);
		}

		$this->fontFamilies[ $slot ] = $family;
	}

	/**
	 * Get all element presets.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getElements(): array
	{
		return veApplyFilters( 'ap.visualEditor.typographyElements', $this->elements );
	}

	/**
	 * Set all element presets, replacing existing entries.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, string>> $elements The element presets.
	 *
	 * @return void
	 */
	public function setElements( array $elements ): void
	{
		$this->elements = $elements;
	}

	/**
	 * Get a single element's typography preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string $element The element key (e.g. 'h1', 'body').
	 *
	 * @return array<string, string>|null
	 */
	public function getElement( string $element ): ?array
	{
		$elements = $this->getElements();

		return $elements[ $element ] ?? null;
	}

	/**
	 * Set a single element's typography preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $element The element key.
	 * @param array<string, string> $styles  The typography styles.
	 *
	 * @throws InvalidArgumentException If the element key is not allowed.
	 *
	 * @return void
	 */
	public function setElement( string $element, array $styles ): void
	{
		if ( ! in_array( $element, self::ALLOWED_ELEMENTS, true ) ) {
			throw new InvalidArgumentException(
				"Invalid element: {$element}. Allowed: " . implode( ', ', self::ALLOWED_ELEMENTS ),
			);
		}

		$this->elements[ $element ] = $this->sanitizeElementStyles( $styles );
	}

	/**
	 * Update a specific style property on an element.
	 *
	 * @since 1.0.0
	 *
	 * @param string $element  The element key.
	 * @param string $property The style property (fontSize, fontWeight, etc.).
	 * @param string $value    The property value.
	 *
	 * @throws InvalidArgumentException If the element key is not allowed.
	 *
	 * @return void
	 */
	public function setElementProperty( string $element, string $property, string $value ): void
	{
		if ( ! in_array( $element, self::ALLOWED_ELEMENTS, true ) ) {
			throw new InvalidArgumentException(
				"Invalid element: {$element}. Allowed: " . implode( ', ', self::ALLOWED_ELEMENTS ),
			);
		}

		if ( ! isset( $this->elements[ $element ] ) ) {
			$this->elements[ $element ] = [];
		}

		$sanitized                               = $this->sanitizeElementStyles( [ $property => $value ] );
		$this->elements[ $element ][ $property ] = $sanitized[ $property ] ?? $value;
	}

	/**
	 * Check if an element preset exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $element The element key.
	 *
	 * @return bool
	 */
	public function hasElement( string $element ): bool
	{
		return isset( $this->elements[ $element ] );
	}

	/**
	 * Remove an element preset.
	 *
	 * @since 1.0.0
	 *
	 * @param string $element The element key.
	 *
	 * @return void
	 */
	public function removeElement( string $element ): void
	{
		unset( $this->elements[ $element ] );
	}

	/**
	 * Register a font in the collection.
	 *
	 * Adds a font to the available fonts registry for the font family
	 * dropdown in block inspectors.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug     Unique slug for the font.
	 * @param string $name     Display name.
	 * @param string $family   CSS font-family stack.
	 * @param string $category Font category: 'all', 'heading', or 'body'.
	 * @param string $source   Font source: 'system', 'custom', or 'google'.
	 *
	 * @return void
	 */
	public function registerFont( string $slug, string $name, string $family, string $category = 'all', string $source = 'custom' ): void
	{
		if ( ! in_array( $category, self::ALLOWED_FONT_CATEGORIES, true ) ) {
			$category = 'all';
		}

		if ( ! in_array( $source, self::ALLOWED_FONT_SOURCES, true ) ) {
			$source = 'custom';
		}

		$this->fontCollection[ $slug ] = [
			'name'     => $name,
			'family'   => $family,
			'category' => $category,
			'source'   => $source,
		];
	}

	/**
	 * Remove a font from the collection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The font slug.
	 *
	 * @return void
	 */
	public function unregisterFont( string $slug ): void
	{
		unset( $this->fontCollection[ $slug ] );
	}

	/**
	 * Check if a font exists in the collection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The font slug.
	 *
	 * @return bool
	 */
	public function hasFont( string $slug ): bool
	{
		return isset( $this->fontCollection[ $slug ] );
	}

	/**
	 * Get a single font entry from the collection.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The font slug.
	 *
	 * @return array{name: string, family: string, category: string, source: string}|null
	 */
	public function getFont( string $slug ): ?array
	{
		return $this->fontCollection[ $slug ] ?? null;
	}

	/**
	 * Get all available fonts, optionally filtered by category.
	 *
	 * When category is null, all fonts are returned. When 'heading' or 'body'
	 * is specified, fonts with that category AND fonts with 'all' category
	 * are returned (since 'all' fonts are available everywhere).
	 *
	 * Results are passed through the ap.visualEditor.availableFonts filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $category Optional category filter: 'heading', 'body', or null for all.
	 *
	 * @return array<string, array{name: string, family: string, category: string, source: string}>
	 */
	public function getAvailableFonts( ?string $category = null ): array
	{
		$fonts = $this->fontCollection;

		if ( null !== $category ) {
			$fonts = array_filter(
				$fonts,
				fn ( array $font ) => 'all' === $font['category'] || $category === $font['category'],
			);
		}

		return veApplyFilters( 'ap.visualEditor.availableFonts', $fonts, $category );
	}

	/**
	 * Get available fonts as a flat options array for dropdowns.
	 *
	 * Returns an associative array of CSS family stack => display name,
	 * sorted alphabetically by name.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $category Optional category filter.
	 *
	 * @return array<string, string> CSS family => display name.
	 */
	public function getFontOptions( ?string $category = null ): array
	{
		$fonts   = $this->getAvailableFonts( $category );
		$options = [];

		foreach ( $fonts as $font ) {
			$options[ $font['family'] ] = $font['name'];
		}

		asort( $options );

		return $options;
	}

	/**
	 * Get the default system fonts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{name: string, family: string, category: string, source: string}>
	 */
	public function getDefaultSystemFonts(): array
	{
		return self::DEFAULT_SYSTEM_FONTS;
	}

	/**
	 * Get the default font families.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function getDefaultFontFamilies(): array
	{
		return self::DEFAULT_FONT_FAMILIES;
	}

	/**
	 * Get the default element presets.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function getDefaultElements(): array
	{
		return self::DEFAULT_ELEMENTS;
	}

	/**
	 * Reset all settings to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function resetToDefaults(): void
	{
		$this->fontFamilies   = self::DEFAULT_FONT_FAMILIES;
		$this->elements       = self::DEFAULT_ELEMENTS;
		$this->customFonts    = [];
		$this->googleFonts    = [];
		$this->fontCollection = self::DEFAULT_SYSTEM_FONTS;
	}

	/**
	 * Generate a type scale from a base size and ratio.
	 *
	 * Computes font sizes for all heading elements (h6 to h1) using
	 * the given ratio, starting from the base size.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $baseSize The base font size in rem.
	 * @param float  $ratio    The scale ratio (e.g. 1.25 for "Major Third").
	 * @param string $unit     The CSS unit (default: 'rem').
	 *
	 * @return array<string, string> Font sizes keyed by element (h6 through h1).
	 */
	public function generateTypeScale( float $baseSize, float $ratio, string $unit = 'rem' ): array
	{
		$scale    = [];
		$headings = [ 'h6', 'h5', 'h4', 'h3', 'h2', 'h1' ];

		foreach ( $headings as $index => $heading ) {
			$size              = $baseSize * ( $ratio ** ( $index + 1 ) );
			$scale[ $heading ] = round( $size, 3 ) . $unit;
		}

		return $scale;
	}

	/**
	 * Apply a type scale to the current element presets.
	 *
	 * Updates only the fontSize property for heading elements.
	 *
	 * @since 1.0.0
	 *
	 * @param float  $baseSize The base font size in rem.
	 * @param float  $ratio    The scale ratio.
	 * @param string $unit     The CSS unit.
	 *
	 * @return void
	 */
	public function applyTypeScale( float $baseSize, float $ratio, string $unit = 'rem' ): void
	{
		$scale = $this->generateTypeScale( $baseSize, $ratio, $unit );

		foreach ( $scale as $element => $fontSize ) {
			if ( ! isset( $this->elements[ $element ] ) ) {
				$this->elements[ $element ] = [
					'fontSize'   => $fontSize,
					'fontWeight' => '400',
					'lineHeight' => '1.4',
				];
			} else {
				$this->elements[ $element ]['fontSize'] = $fontSize;
			}
		}
	}

	/**
	 * Register a custom font source for @font-face generation.
	 *
	 * Also adds the font to the font collection registry so it
	 * appears in block inspector font family dropdowns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $family   The font family name.
	 * @param string $src      The font source URL.
	 * @param string $weight   The font weight (default: '400').
	 * @param string $style    The font style (default: 'normal').
	 * @param string $category Font category: 'all', 'heading', or 'body'.
	 *
	 * @return void
	 */
	public function registerCustomFont( string $family, string $src, string $weight = '400', string $style = 'normal', string $category = 'all' ): void
	{
		$this->customFonts[] = [
			'family' => $family,
			'src'    => $src,
			'weight' => $weight,
			'style'  => $style,
		];

		$slug = $this->slugify( $family );

		if ( ! isset( $this->fontCollection[ $slug ] ) ) {
			$this->registerFont( $slug, $family, "'" . $family . "'", $category, 'custom' );
		}
	}

	/**
	 * Get registered custom fonts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{family: string, src: string, weight?: string, style?: string}>
	 */
	public function getCustomFonts(): array
	{
		return $this->customFonts;
	}

	/**
	 * Register a Google Font family for loading.
	 *
	 * Also adds the font to the font collection registry so it
	 * appears in block inspector font family dropdowns.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $family   The font family name.
	 * @param array<int, string> $weights  Font weights to load.
	 * @param array<int, string> $styles   Font styles to load.
	 * @param string             $category Font category: 'all', 'heading', or 'body'.
	 *
	 * @return void
	 */
	public function registerGoogleFont( string $family, array $weights = [ '400', '700' ], array $styles = [ 'normal' ], string $category = 'all' ): void
	{
		$this->googleFonts[] = [
			'family'  => $family,
			'weights' => $weights,
			'styles'  => $styles,
		];

		$slug = $this->slugify( $family );

		if ( ! isset( $this->fontCollection[ $slug ] ) ) {
			$this->registerFont( $slug, $family, "'" . $family . "', sans-serif", $category, 'google' );
		}
	}

	/**
	 * Get registered Google Fonts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{family: string, weights?: array<int, string>, styles?: array<int, string>}>
	 */
	public function getGoogleFonts(): array
	{
		return $this->googleFonts;
	}

	/**
	 * Generate the Google Fonts embed URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string|null The URL or null if no Google Fonts are registered.
	 */
	public function generateGoogleFontsUrl(): ?string
	{
		if ( [] === $this->googleFonts ) {
			return null;
		}

		$families = [];

		foreach ( $this->googleFonts as $font ) {
			$spec = str_replace( ' ', '+', $font['family'] );

			$hasItalic = in_array( 'italic', $font['styles'] ?? [], true );
			$weights   = $font['weights'] ?? [ '400', '700' ];
			sort( $weights );

			if ( $hasItalic ) {
				$axes = [];
				foreach ( $weights as $w ) {
					$axes[] = '0,' . $w;
					$axes[] = '1,' . $w;
				}
				$spec .= ':ital,wght@' . implode( ';', $axes );
			} else {
				$spec .= ':wght@' . implode( ';', $weights );
			}

			$families[] = $spec;
		}

		return 'https://fonts.googleapis.com/css2?'
			. implode( '&', array_map( fn ( string $f ) => 'family=' . $f, $families ) )
			. '&display=swap';
	}

	/**
	 * Generate @font-face CSS declarations for custom fonts.
	 *
	 * @since 1.0.0
	 *
	 * @return string The CSS @font-face declarations.
	 */
	public function generateFontFaceDeclarations(): string
	{
		if ( [] === $this->customFonts ) {
			return '';
		}

		$declarations = [];

		foreach ( $this->customFonts as $font ) {
			$weight = $font['weight'] ?? '400';
			$style  = $font['style'] ?? 'normal';
			$format = $this->detectFontFormat( $font['src'] );

			$safeName = str_replace( [ "'", '\\' ], [ "\\'", '\\\\' ], $font['family'] );
			$safeSrc  = filter_var( $font['src'], FILTER_SANITIZE_URL ) ?: '';

			$declaration  = "@font-face {\n";
			$declaration .= "\tfont-family: '" . $safeName . "';\n";
			$declaration .= "\tsrc: url('" . $safeSrc . "')";

			if ( '' !== $format ) {
				$declaration .= " format('" . $format . "')";
			}

			$declaration .= ";\n";
			$declaration .= "\tfont-weight: " . $weight . ";\n";
			$declaration .= "\tfont-style: " . $style . ";\n";
			$declaration .= "\tfont-display: swap;\n";
			$declaration .= '}';

			$declarations[] = $declaration;
		}

		return implode( "\n\n", $declarations );
	}

	/**
	 * Generate CSS custom properties for typography.
	 *
	 * @since 1.0.0
	 *
	 * @return string The CSS custom properties.
	 */
	public function generateCssProperties(): string
	{
		$lines    = [];
		$families = $this->getFontFamilies();
		$elements = $this->getElements();

		foreach ( $families as $slot => $family ) {
			$lines[] = '--ve-font-' . $slot . ': ' . $family . ';';
		}

		foreach ( $elements as $element => $styles ) {
			foreach ( $styles as $property => $value ) {
				$cssProperty = $this->camelToKebab( $property );
				$lines[]     = '--ve-text-' . $element . '-' . $cssProperty . ': ' . $value . ';';
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Generate a full CSS :root rule for typography.
	 *
	 * @since 1.0.0
	 *
	 * @return string The complete CSS rule.
	 */
	public function generateCssBlock(): string
	{
		$properties = $this->generateCssProperties();

		if ( '' === $properties ) {
			return '';
		}

		return ":root {\n" . $this->indentCss( $properties ) . "\n}";
	}

	/**
	 * Build the typography data as a flat structure for the Alpine store.
	 *
	 * @since 1.0.0
	 *
	 * @return array{fontFamilies: array<string, string>, elements: array<string, array<string, string>>}
	 */
	public function toStoreFormat(): array
	{
		return [
			'fontFamilies' => $this->getFontFamilies(),
			'elements'     => $this->getElements(),
		];
	}

	/**
	 * Restore typography data from the Alpine store format.
	 *
	 * @since 1.0.0
	 *
	 * @param array{fontFamilies?: array<string, string>, elements?: array<string, array<string, string>>} $data The store data.
	 *
	 * @return void
	 */
	public function fromStoreFormat( array $data ): void
	{
		if ( isset( $data['fontFamilies'] ) && is_array( $data['fontFamilies'] ) ) {
			$allowed            = array_flip( self::ALLOWED_FAMILY_SLOTS );
			$this->fontFamilies = array_intersect_key( $data['fontFamilies'], $allowed );
		}

		if ( isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			$validElements  = array_flip( self::ALLOWED_ELEMENTS );
			$this->elements = [];

			foreach ( $data['elements'] as $key => $styles ) {
				if ( isset( $validElements[ $key ] ) && is_array( $styles ) ) {
					$this->elements[ $key ] = $this->sanitizeElementStyles( $styles );
				}
			}
		}
	}

	/**
	 * Sanitize element style values.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $styles The styles to sanitize.
	 *
	 * @return array<string, string> The sanitized styles.
	 */
	protected function sanitizeElementStyles( array $styles ): array
	{
		$sanitized = [];

		foreach ( $styles as $property => $value ) {
			$sanitized[ $property ] = match ( $property ) {
				'fontSize'      => veSanitizeCssDimension( $value, '1rem' ),
				'fontWeight'    => $this->sanitizeFontWeight( $value ),
				'lineHeight'    => veSanitizeCssNumber( $value, '1.5' ),
				'letterSpacing' => veSanitizeCssDimension( $value, '0' ),
				'fontStyle'     => $this->sanitizeFontStyle( $value ),
				default         => $value,
			};
		}

		return $sanitized;
	}

	/**
	 * Sanitize a font-weight value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The font weight value.
	 *
	 * @return string The sanitized value.
	 */
	protected function sanitizeFontWeight( string $value ): string
	{
		$value = trim( $value );

		if ( in_array( $value, self::ALLOWED_WEIGHTS, true ) ) {
			return $value;
		}

		return '400';
	}

	/**
	 * Sanitize a font-style value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The font style value.
	 *
	 * @return string The sanitized value.
	 */
	protected function sanitizeFontStyle( string $value ): string
	{
		$value = trim( $value );

		if ( in_array( $value, self::ALLOWED_FONT_STYLES, true ) ) {
			return $value;
		}

		return 'normal';
	}

	/**
	 * Generate a slug from a font family name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The font family name.
	 *
	 * @return string The slugified name.
	 */
	protected function slugify( string $name ): string
	{
		return strtolower( trim( (string) preg_replace( '/[^a-zA-Z0-9]+/', '-', $name ), '-' ) );
	}

	/**
	 * Detect font format from file extension.
	 *
	 * @since 1.0.0
	 *
	 * @param string $src The font source URL.
	 *
	 * @return string The font format string.
	 */
	protected function detectFontFormat( string $src ): string
	{
		$extension = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );

		return match ( $extension ) {
			'woff2' => 'woff2',
			'woff'  => 'woff',
			'ttf'   => 'truetype',
			'otf'   => 'opentype',
			'eot'   => 'embedded-opentype',
			'svg'   => 'svg',
			default => '',
		};
	}

	/**
	 * Convert a camelCase string to kebab-case.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The camelCase string.
	 *
	 * @return string The kebab-case string.
	 */
	protected function camelToKebab( string $value ): string
	{
		return strtolower( (string) preg_replace( '/([a-z])([A-Z])/', '$1-$2', $value ) );
	}

	/**
	 * Indent CSS lines with a tab character.
	 *
	 * @since 1.0.0
	 *
	 * @param string $css The CSS string to indent.
	 *
	 * @return string The indented CSS.
	 */
	protected function indentCss( string $css ): string
	{
		$lines = explode( "\n", $css );

		return implode( "\n", array_map( fn ( string $line) => "\t" . $line, $lines ) );
	}
}
