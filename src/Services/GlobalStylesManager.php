<?php

declare( strict_types=1 );

/**
 * Global Styles Manager
 *
 * Manages global styles and CSS custom properties for the visual editor.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */

namespace ArtisanPackUI\VisualEditor\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Global Styles Manager class.
 *
 * Provides functionality for managing global styles, CSS custom properties,
 * and generating CSS output for the visual editor.
 *
 * @since 1.0.0
 */
class GlobalStylesManager
{
	/**
	 * The current global styles.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected array $styles = [];

	/**
	 * The default styles.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	protected array $defaults = [
		'colors'     => [
			'primary'    => '#3b82f6',
			'secondary'  => '#6b7280',
			'accent'     => '#f59e0b',
			'background' => '#ffffff',
			'foreground' => '#1f2937',
			'muted'      => '#9ca3af',
			'border'     => '#e5e7eb',
			'success'    => '#10b981',
			'warning'    => '#f59e0b',
			'error'      => '#ef4444',
			'info'       => '#3b82f6',
		],
		'typography' => [
			'font_family_heading' => 'system-ui, sans-serif',
			'font_family_body'    => 'system-ui, sans-serif',
			'font_size_base'      => '16px',
			'line_height_base'    => '1.5',
			'letter_spacing_base' => '0',
		],
		'spacing'    => [
			'section_padding_y' => '4rem',
			'section_padding_x' => '1.5rem',
			'container_max'     => '1280px',
			'gap_default'       => '1rem',
		],
		'borders'    => [
			'radius_small'  => '0.25rem',
			'radius_medium' => '0.5rem',
			'radius_large'  => '1rem',
			'radius_full'   => '9999px',
		],
		'shadows'    => [
			'shadow_small'  => '0 1px 2px 0 rgb(0 0 0 / 0.05)',
			'shadow_medium' => '0 4px 6px -1px rgb(0 0 0 / 0.1)',
			'shadow_large'  => '0 10px 15px -3px rgb(0 0 0 / 0.1)',
		],
	];

	/**
	 * Initialize the manager with current styles.
	 *
	 * @since 1.0.0
	 *
	 * @param array $styles Initial styles to load.
	 */
	public function __construct( array $styles = [] )
	{
		$this->styles = array_replace_recursive( $this->defaults, $styles );
	}

	/**
	 * Get the current styles.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function getStyles(): array
	{
		return $this->styles;
	}

	/**
	 * Get a specific style value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The style key in dot notation (e.g., 'colors.primary').
	 * @param mixed  $default The default value if not found.
	 *
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ): mixed
	{
		return data_get( $this->styles, $key, $default );
	}

	/**
	 * Set a style value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The style key in dot notation.
	 * @param mixed  $value The value to set.
	 *
	 * @return self
	 */
	public function set( string $key, mixed $value ): self
	{
		data_set( $this->styles, $key, $value );
		$this->clearCache();

		return $this;
	}

	/**
	 * Merge styles with the current styles.
	 *
	 * @since 1.0.0
	 *
	 * @param array $styles The styles to merge.
	 *
	 * @return self
	 */
	public function merge( array $styles ): self
	{
		$this->styles = array_replace_recursive( $this->styles, $styles );
		$this->clearCache();

		return $this;
	}

	/**
	 * Get the default styles.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function getDefaults(): array
	{
		return $this->defaults;
	}

	/**
	 * Reset styles to defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public function reset(): self
	{
		$this->styles = $this->defaults;

		return $this;
	}

	/**
	 * Generate CSS custom properties from the current styles.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function generateCssCustomProperties(): string
	{
		$properties = [];

		// Colors
		foreach ( $this->styles['colors'] ?? [] as $name => $value ) {
			$properties[] = "--ve-color-{$name}: {$value};";
		}

		// Accessible button text colors derived from background colors
		$properties = array_merge( $properties, $this->generateButtonTextColorProperties() );

		// Typography
		foreach ( $this->styles['typography'] ?? [] as $name => $value ) {
			$cssName      = str_replace( '_', '-', $name );
			$properties[] = "--ve-{$cssName}: {$value};";
		}

		// Spacing
		foreach ( $this->styles['spacing'] ?? [] as $name => $value ) {
			$cssName      = str_replace( '_', '-', $name );
			$properties[] = "--ve-{$cssName}: {$value};";
		}

		// Borders
		foreach ( $this->styles['borders'] ?? [] as $name => $value ) {
			$cssName      = str_replace( '_', '-', $name );
			$properties[] = "--ve-{$cssName}: {$value};";
		}

		// Shadows
		foreach ( $this->styles['shadows'] ?? [] as $name => $value ) {
			$cssName      = str_replace( '_', '-', $name );
			$properties[] = "--ve-{$cssName}: {$value};";
		}

		$propertiesString = implode( "\n  ", $properties );

		return <<<CSS
:root {
  {$propertiesString}
}
CSS;
	}

	/**
	 * Generate the full CSS output.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function generateCss(): string
	{
		$css = [];

		// Custom properties
		$css[] = $this->generateCssCustomProperties();

		// Base styles
		$css[] = $this->generateBaseStyles();

		return implode( "\n\n", $css );
	}

	/**
	 * Export styles to a CSS file.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $path The output path. Defaults to config value.
	 *
	 * @return bool
	 */
	public function exportToFile( ?string $path = null ): bool
	{
		$path = $path ?? public_path( config( 'artisanpack.visual-editor.styles.css_output_path', 'css/visual-editor-styles.css' ) );

		$directory = dirname( $path );
		if ( !File::isDirectory( $directory ) ) {
			File::makeDirectory( $directory, 0755, true );
		}

		return (bool) File::put( $path, $this->generateCss() );
	}

	/**
	 * Generate Tailwind CSS configuration from the current styles.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function toTailwindConfig(): array
	{
		if ( !config( 'artisanpack.visual-editor.styles.enable_tailwind_export', true ) ) {
			return [];
		}

		return [
			'theme' => [
				'extend' => [
					'colors' => [
						've' => $this->styles['colors'] ?? [],
					],
					'fontFamily' => [
						've-heading' => [ $this->get( 'typography.font_family_heading', 'system-ui' ) ],
						've-body'    => [ $this->get( 'typography.font_family_body', 'system-ui' ) ],
					],
					'borderRadius' => [
						've-sm' => $this->get( 'borders.radius_small', '0.25rem' ),
						've-md' => $this->get( 'borders.radius_medium', '0.5rem' ),
						've-lg' => $this->get( 'borders.radius_large', '1rem' ),
					],
					'boxShadow' => [
						've-sm' => $this->get( 'shadows.shadow_small' ),
						've-md' => $this->get( 'shadows.shadow_medium' ),
						've-lg' => $this->get( 'shadows.shadow_large' ),
					],
				],
			],
		];
	}

	/**
	 * Get cached styles or generate new ones.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function getCachedCss(): string
	{
		if ( !config( 'artisanpack.visual-editor.performance.cache_rendered_content', true ) ) {
			return $this->generateCss();
		}

		$cacheKey = 'visual-editor.global-styles';
		$cacheTtl = config( 'artisanpack.visual-editor.performance.cache_ttl', 3600 );

		return Cache::remember( $cacheKey, $cacheTtl, fn () => $this->generateCss() );
	}

	/**
	 * Clear the cached styles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clearCache(): void
	{
		Cache::forget( 'visual-editor.global-styles' );
	}

	/**
	 * Generate accessible text color properties for buttons.
	 *
	 * Uses the accessibility package when available, otherwise falls back to
	 * a contrast calculation to determine whether white or black text should
	 * be used on the primary and secondary background colors.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	protected function generateButtonTextColorProperties(): array
	{
		$properties = [];
		$colorMap   = [
			'primary'   => $this->styles['colors']['primary'] ?? '#3b82f6',
			'secondary' => $this->styles['colors']['secondary'] ?? '#6b7280',
		];

		foreach ( $colorMap as $name => $bgColor ) {
			$textColor          = $this->getAccessibleTextColor( $bgColor );
			$properties[]       = "--ve-btn-{$name}-text: {$textColor};";
		}

		return $properties;
	}

	/**
	 * Get an accessible text color for the given background.
	 *
	 * Delegates to the accessibility package helper when available,
	 * otherwise falls back to a simple luminance-based calculation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $bgColor The background hex color.
	 *
	 * @return string The accessible text hex color (#000000 or #ffffff).
	 */
	protected function getAccessibleTextColor( string $bgColor ): string
	{
		// Use the accessibility package when available
		if ( function_exists( 'a11yGetContrastColor' ) ) {
			return a11yGetContrastColor( $bgColor );
		}

		// Fallback: simple relative luminance calculation
		$hex = ltrim( $bgColor, '#' );

		// Validate hex format
		if ( !preg_match( '/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $hex ) ) {
			return '#ffffff'; // Safe default for invalid input
		}

		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		// sRGB to linear
		$r = ( $r <= 0.03928 ) ? $r / 12.92 : ( ( $r + 0.055 ) / 1.055 ) ** 2.4;
		$g = ( $g <= 0.03928 ) ? $g / 12.92 : ( ( $g + 0.055 ) / 1.055 ) ** 2.4;
		$b = ( $b <= 0.03928 ) ? $b / 12.92 : ( ( $b + 0.055 ) / 1.055 ) ** 2.4;

		$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

		return ( $luminance > 0.179 ) ? '#000000' : '#ffffff';
	}

	/**
	 * Generate base styles using the custom properties.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function generateBaseStyles(): string
	{
		return <<<CSS
/* Visual Editor Base Styles */
.ve-content {
  font-family: var(--ve-font-family-body);
  font-size: var(--ve-font-size-base);
  line-height: var(--ve-line-height-base);
  color: var(--ve-color-foreground);
  background-color: var(--ve-color-background);
}

.ve-content h1,
.ve-content h2,
.ve-content h3,
.ve-content h4,
.ve-content h5,
.ve-content h6 {
  font-family: var(--ve-font-family-heading);
}

.ve-section {
  padding-top: var(--ve-section-padding-y);
  padding-bottom: var(--ve-section-padding-y);
  padding-left: var(--ve-section-padding-x);
  padding-right: var(--ve-section-padding-x);
}

.ve-container {
  max-width: var(--ve-container-max);
  margin-left: auto;
  margin-right: auto;
}

.ve-btn-primary {
  background-color: var(--ve-color-primary);
  color: var(--ve-btn-primary-text, #ffffff);
  border-radius: var(--ve-radius-medium);
  box-shadow: var(--ve-shadow-small);
}

.ve-btn-secondary {
  background-color: var(--ve-color-secondary);
  color: var(--ve-btn-secondary-text, #ffffff);
  border-radius: var(--ve-radius-medium);
}
CSS;
	}
}
