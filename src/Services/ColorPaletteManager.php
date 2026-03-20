<?php

/**
 * Color Palette Manager Service.
 *
 * Manages color palette definitions, CSS custom property generation,
 * color shade variations, and accessibility contrast checking for the
 * visual editor's global styles system.
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

use Throwable;

/**
 * Service for managing color palettes with CSS generation and accessibility checks.
 *
 * Provides default semantic color slots, custom color support, CSS custom
 * property generation, shade variation generation, and WCAG contrast validation
 * via optional integration with the artisanpack-ui/accessibility package.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class ColorPaletteManager
{

	/**
	 * The default semantic color palette.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{name: string, slug: string, color: string}>
	 */
	public const DEFAULT_PALETTE = [
		'primary'    => [
			'name'  => 'Primary',
			'slug'  => 'primary',
			'color' => '#3b82f6',
		],
		'secondary'  => [
			'name'  => 'Secondary',
			'slug'  => 'secondary',
			'color' => '#6366f1',
		],
		'accent'     => [
			'name'  => 'Accent',
			'slug'  => 'accent',
			'color' => '#f59e0b',
		],
		'background' => [
			'name'  => 'Background',
			'slug'  => 'background',
			'color' => '#ffffff',
		],
		'surface'    => [
			'name'  => 'Surface',
			'slug'  => 'surface',
			'color' => '#f8fafc',
		],
		'text'       => [
			'name'  => 'Text',
			'slug'  => 'text',
			'color' => '#1e293b',
		],
		'muted'      => [
			'name'  => 'Muted',
			'slug'  => 'muted',
			'color' => '#94a3b8',
		],
		'border'     => [
			'name'  => 'Border',
			'slug'  => 'border',
			'color' => '#e2e8f0',
		],
		'success'    => [
			'name'  => 'Success',
			'slug'  => 'success',
			'color' => '#22c55e',
		],
		'warning'    => [
			'name'  => 'Warning',
			'slug'  => 'warning',
			'color' => '#f59e0b',
		],
		'error'      => [
			'name'  => 'Error',
			'slug'  => 'error',
			'color' => '#ef4444',
		],
		'info'       => [
			'name'  => 'Info',
			'slug'  => 'info',
			'color' => '#3b82f6',
		],
	];

	/**
	 * The current palette entries.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{name: string, slug: string, color: string}>
	 */
	protected array $palette = [];

	/**
	 * Create a new ColorPaletteManager instance.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array{name: string, slug: string, color: string}> $palette Initial palette to use.
	 */
	public function __construct( array $palette = [] )
	{
		$this->palette = [] === $palette
			? self::DEFAULT_PALETTE
			: $palette;
	}

	/**
	 * Get the full palette.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{name: string, slug: string, color: string}>
	 */
	public function getPalette(): array
	{
		return veApplyFilters( 'ap.visualEditor.colorPalette', $this->palette );
	}

	/**
	 * Set the full palette, replacing all entries.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array{name: string, slug: string, color: string}> $palette The palette entries.
	 *
	 * @return void
	 */
	public function setPalette( array $palette ): void
	{
		$this->palette = $palette;
	}

	/**
	 * Get a single color by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The color slug.
	 *
	 * @return array{name: string, slug: string, color: string}|null
	 */
	public function getColor( string $slug ): ?array
	{
		return $this->palette[ $slug ] ?? null;
	}

	/**
	 * Get the hex value for a color by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The color slug.
	 *
	 * @return string|null The hex color value or null.
	 */
	public function getColorValue( string $slug ): ?string
	{
		$color = $this->getColor( $slug );

		return $color['color'] ?? null;
	}

	/**
	 * Add or update a color entry.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug  The unique color slug.
	 * @param string $name  The display name.
	 * @param string $color The hex color value.
	 *
	 * @return void
	 */
	public function setColor( string $slug, string $name, string $color ): void
	{
		$this->palette[ $slug ] = [
			'name'  => $name,
			'slug'  => $slug,
			'color' => $color,
		];
	}

	/**
	 * Remove a color entry by slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The color slug.
	 *
	 * @return void
	 */
	public function removeColor( string $slug ): void
	{
		unset( $this->palette[ $slug ] );
	}

	/**
	 * Check if a color exists in the palette.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug The color slug.
	 *
	 * @return bool
	 */
	public function hasColor( string $slug ): bool
	{
		return isset( $this->palette[ $slug ] );
	}

	/**
	 * Get the default semantic palette.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{name: string, slug: string, color: string}>
	 */
	public function getDefaultPalette(): array
	{
		return self::DEFAULT_PALETTE;
	}

	/**
	 * Reset the palette to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function resetToDefaults(): void
	{
		$this->palette = self::DEFAULT_PALETTE;
	}

	/**
	 * Generate CSS custom properties for the palette.
	 *
	 * Produces CSS variables for each color and its light/dark shade
	 * variations. Output is a raw CSS string (without a selector).
	 *
	 * @since 1.0.0
	 *
	 * @param bool $includeShades Whether to include light/dark shade variations.
	 *
	 * @return string The CSS custom properties.
	 */
	public function generateCssProperties( bool $includeShades = true ): string
	{
		$lines   = [];
		$palette = $this->getPalette();

		foreach ( $palette as $entry ) {
			$slug  = $entry['slug'];
			$color = $entry['color'];

			$lines[] = '--ve-color-' . $slug . ': ' . $color . ';';

			if ( $includeShades ) {
				$shades = $this->generateShades( $color );

				$lines[] = '--ve-color-' . $slug . '-light: ' . $shades['light'] . ';';
				$lines[] = '--ve-color-' . $slug . '-dark: ' . $shades['dark'] . ';';
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Generate a full CSS :root rule for the palette.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $includeShades Whether to include light/dark shade variations.
	 *
	 * @return string The complete CSS rule.
	 */
	public function generateCssBlock( bool $includeShades = true ): string
	{
		$properties = $this->generateCssProperties( $includeShades );

		if ( '' === $properties ) {
			return '';
		}

		return ":root {\n" . $this->indentCss( $properties ) . "\n}";
	}

	/**
	 * Generate lighter and darker shade variations for a hex color.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex The hex color value (e.g., '#3b82f6').
	 *
	 * @return array{light: string, dark: string}
	 */
	public function generateShades( string $hex ): array
	{
		$rgb = $this->hexToRgb( $hex );

		return [
			'light' => $this->rgbToHex(
				$this->lighten( $rgb['r'], 0.25 ),
				$this->lighten( $rgb['g'], 0.25 ),
				$this->lighten( $rgb['b'], 0.25 ),
			),
			'dark'  => $this->rgbToHex(
				$this->darken( $rgb['r'], 0.25 ),
				$this->darken( $rgb['g'], 0.25 ),
				$this->darken( $rgb['b'], 0.25 ),
			),
		];
	}

	/**
	 * Check if two colors have sufficient WCAG contrast.
	 *
	 * Uses the artisanpack-ui/accessibility package when available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $foreground Foreground hex color.
	 * @param string $background Background hex color.
	 *
	 * @return bool|null True if contrast is sufficient, false if not, null if check unavailable.
	 */
	public function checkContrast( string $foreground, string $background ): ?bool
	{
		if ( function_exists( 'a11yCheckContrastColor' ) ) {
			try {
				return a11yCheckContrastColor( $foreground, $background );
			} catch ( Throwable $e ) {
				return null;
			}
		}

		return null;
	}

	/**
	 * Run contrast checks for all palette colors against a background.
	 *
	 * Returns an array keyed by slug with contrast check results.
	 *
	 * @since 1.0.0
	 *
	 * @param string $background The background color to check against.
	 *
	 * @return array<string, bool|null> Contrast results per color slug.
	 */
	public function checkPaletteContrast( string $background ): array
	{
		$results = [];
		$palette = $this->getPalette();

		foreach ( $palette as $entry ) {
			$results[ $entry['slug'] ] = $this->checkContrast( $entry['color'], $background );
		}

		return $results;
	}

	/**
	 * Resolve a palette color reference to a hex value.
	 *
	 * Supports references in the format 'palette:slug' (e.g., 'palette:primary').
	 * Returns the original value if it is not a palette reference.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The color value or palette reference.
	 *
	 * @return string The resolved hex color value.
	 */
	public function resolveColorReference( string $value ): string
	{
		if ( ! str_starts_with( $value, 'palette:' ) ) {
			return $value;
		}

		$slug     = substr( $value, 8 );
		$resolved = $this->getColorValue( $slug );

		return $resolved ?? $value;
	}

	/**
	 * Build the palette as a flat array suitable for the Alpine store.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{name: string, slug: string, color: string}>
	 */
	public function toStoreFormat(): array
	{
		return array_values( $this->getPalette() );
	}

	/**
	 * Build the palette from a flat array (from the Alpine store).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array{name: string, slug: string, color: string}> $entries The palette entries.
	 *
	 * @return void
	 */
	public function fromStoreFormat( array $entries ): void
	{
		$palette = [];

		foreach ( $entries as $entry ) {
			if ( isset( $entry['slug'], $entry['name'], $entry['color'] ) ) {
				$palette[ $entry['slug'] ] = $entry;
			}
		}

		$this->palette = $palette;
	}

	/**
	 * Convert a hex color to RGB components.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex The hex color value.
	 *
	 * @return array{r: int, g: int, b: int}
	 */
	protected function hexToRgb( string $hex ): array
	{
		$hex = ltrim( $hex, '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return [
			'r' => (int) hexdec( substr( $hex, 0, 2 ) ),
			'g' => (int) hexdec( substr( $hex, 2, 2 ) ),
			'b' => (int) hexdec( substr( $hex, 4, 2 ) ),
		];
	}

	/**
	 * Convert RGB components to a hex color.
	 *
	 * @since 1.0.0
	 *
	 * @param int $r Red component (0-255).
	 * @param int $g Green component (0-255).
	 * @param int $b Blue component (0-255).
	 *
	 * @return string The hex color value.
	 */
	protected function rgbToHex( int $r, int $g, int $b ): string
	{
		return '#' . str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT )
			. str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT )
			. str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );
	}

	/**
	 * Lighten a color channel value.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $channel The color channel value (0-255).
	 * @param float $amount  The lighten amount (0.0-1.0).
	 *
	 * @return int The lightened channel value.
	 */
	protected function lighten( int $channel, float $amount ): int
	{
		return min( 255, (int) round( $channel + ( 255 - $channel ) * $amount ) );
	}

	/**
	 * Darken a color channel value.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $channel The color channel value (0-255).
	 * @param float $amount  The darken amount (0.0-1.0).
	 *
	 * @return int The darkened channel value.
	 */
	protected function darken( int $channel, float $amount ): int
	{
		return max( 0, (int) round( $channel * ( 1.0 - $amount ) ) );
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

		return implode( "\n", array_map( fn ( string $line ) => "\t" . $line, $lines ) );
	}
}
