<?php

/**
 * Global Styles Compiler Service.
 *
 * Unified CSS generation engine that aggregates output from the
 * ColorPaletteManager, TypographyPresetsManager, and SpacingScaleManager
 * into a single CSS output. Supports caching, minification, scoped
 * template overrides, and multiple output modes.
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

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use TypeError;

/**
 * Service for compiling all global style managers into unified CSS output.
 *
 * Wraps the three style manager singletons (colors, typography, spacing)
 * and provides a compilation pipeline with caching, minification,
 * debug comments, scoped template overrides, and configurable output modes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class GlobalStylesCompiler
{
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
	 * The compiler configuration.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, mixed>
	 */
	protected array $config;

	/**
	 * Registered template scope overrides.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $templateOverrides = [];

	/**
	 * Create a new GlobalStylesCompiler instance.
	 *
	 * @since 1.0.0
	 *
	 * @param ColorPaletteManager      $colors     The color palette manager.
	 * @param TypographyPresetsManager $typography The typography presets manager.
	 * @param SpacingScaleManager      $spacing    The spacing scale manager.
	 * @param array<string, mixed>     $config     The compiler configuration.
	 */
	public function __construct(
		ColorPaletteManager $colors,
		TypographyPresetsManager $typography,
		SpacingScaleManager $spacing,
		array $config = [],
	) {
		$this->colors     = $colors;
		$this->typography = $typography;
		$this->spacing    = $spacing;
		$this->config     = array_merge( $this->defaults(), $config );

		if ( isset( $config['template_overrides'] ) && is_array( $config['template_overrides'] ) ) {
			foreach ( $config['template_overrides'] as $slug => $overrides ) {
				if ( is_array( $overrides ) ) {
					$this->templateOverrides[ $slug ] = $overrides;
				}
			}
		}
	}

	/**
	 * Compile all manager CSS into a single :root block.
	 *
	 * Aggregates CSS custom properties from colors, typography, and
	 * spacing managers into one CSS rule using the configured root selector.
	 * Pass a selector override to force a specific selector (e.g. ':root'
	 * for editor context regardless of config).
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $selectorOverride Optional CSS selector to use instead of configured root_selector.
	 *
	 * @return string The compiled CSS string.
	 */
	public function compile( ?string $selectorOverride = null ): string
	{
		$includeShades = (bool) ( $this->config['include_color_shades'] ?? true );
		$debugComments = (bool) ( $this->config['debug_comments'] ?? false );
		$rootSelector  = $selectorOverride ?? (string) ( $this->config['root_selector'] ?? ':root' );

		$colorProps     = veApplyFilters(
			'ap.visualEditor.globalStyles.properties.colors',
			$this->colors->generateCssProperties( $includeShades ),
		);
		$typographyProps = veApplyFilters(
			'ap.visualEditor.globalStyles.properties.typography',
			$this->typography->generateCssProperties(),
		);
		$spacingProps    = veApplyFilters(
			'ap.visualEditor.globalStyles.properties.spacing',
			$this->spacing->generateCssProperties(),
		);

		$sections = [];

		if ( '' !== $colorProps ) {
			if ( $debugComments ) {
				$sections[] = '/* Colors */';
			}

			$sections[] = $colorProps;
		}

		if ( '' !== $typographyProps ) {
			if ( $debugComments ) {
				$sections[] = '/* Typography */';
			}

			$sections[] = $typographyProps;
		}

		if ( '' !== $spacingProps ) {
			if ( $debugComments ) {
				$sections[] = '/* Spacing */';
			}

			$sections[] = $spacingProps;
		}

		$allProperties = implode( "\n", $sections );

		if ( '' === $allProperties ) {
			return '';
		}

		$css = $rootSelector . " {\n" . $this->indentCss( $allProperties ) . "\n}";

		return veApplyFilters( 'ap.visualEditor.globalStyles.compiled', $css );
	}

	/**
	 * Compile scoped CSS for a template slug with overrides.
	 *
	 * Clones the managers, applies the given overrides, and generates
	 * a scoped CSS rule using the `.template-{slug}` selector.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug      The template slug.
	 * @param array<string, mixed> $overrides The style overrides per manager.
	 *
	 * @return string The scoped CSS string.
	 */
	public function compileScoped( string $slug, array $overrides ): string
	{
		$debugComments = (bool) ( $this->config['debug_comments'] ?? false );
		$includeShades = (bool) ( $this->config['include_color_shades'] ?? true );
		$sections      = [];

		if ( isset( $overrides['colors'] ) && is_array( $overrides['colors'] ) ) {
			$cloned = clone $this->colors;

			foreach ( $overrides['colors'] as $colorSlug => $colorData ) {
				if ( isset( $colorData['name'], $colorData['color'] ) ) {
					$cloned->setColor( (string) $colorSlug, $colorData['name'], $colorData['color'] );
				}
			}

			$props = $cloned->generateCssProperties( $includeShades );

			if ( '' !== $props ) {
				if ( $debugComments ) {
					$sections[] = '/* Colors */';
				}

				$sections[] = $props;
			}
		}

		if ( isset( $overrides['typography'] ) && is_array( $overrides['typography'] ) ) {
			$cloned = clone $this->typography;

			if ( isset( $overrides['typography']['fontFamilies'] ) && is_array( $overrides['typography']['fontFamilies'] ) ) {
				foreach ( $overrides['typography']['fontFamilies'] as $familySlot => $familyValue ) {
					if ( is_string( $familyValue ) ) {
						try {
							$cloned->setFontFamily( (string) $familySlot, $familyValue );
						} catch ( TypeError | InvalidArgumentException $e ) {
							continue;
						}
					}
				}
			}

			if ( isset( $overrides['typography']['elements'] ) && is_array( $overrides['typography']['elements'] ) ) {
				foreach ( $overrides['typography']['elements'] as $element => $styles ) {
					if ( is_array( $styles ) ) {
						try {
							$cloned->setElement( (string) $element, $styles );
						} catch ( TypeError | InvalidArgumentException $e ) {
							continue;
						}
					}
				}
			}

			$props = $cloned->generateCssProperties();

			if ( '' !== $props ) {
				if ( $debugComments ) {
					$sections[] = '/* Typography */';
				}

				$sections[] = $props;
			}
		}

		if ( isset( $overrides['spacing'] ) && is_array( $overrides['spacing'] ) ) {
			$cloned = clone $this->spacing;

			if ( isset( $overrides['spacing']['scale'] ) && is_array( $overrides['spacing']['scale'] ) ) {
				foreach ( $overrides['spacing']['scale'] as $stepSlug => $stepData ) {
					if ( isset( $stepData['name'], $stepData['value'] ) ) {
						try {
							$cloned->setStep( (string) $stepSlug, $stepData['name'], $stepData['value'] );
						} catch ( TypeError | InvalidArgumentException $e ) {
							continue;
						}
					}
				}
			}

			if ( isset( $overrides['spacing']['blockGap'] ) && is_string( $overrides['spacing']['blockGap'] ) ) {
				$cloned->setBlockGap( $overrides['spacing']['blockGap'] );
			}

			$props = $cloned->generateCssProperties();

			if ( '' !== $props ) {
				if ( $debugComments ) {
					$sections[] = '/* Spacing */';
				}

				$sections[] = $props;
			}
		}

		$allProperties = implode( "\n", $sections );

		if ( '' === $allProperties ) {
			return '';
		}

		$sanitizedSlug = preg_replace( '/[^a-zA-Z0-9_-]/', '', $slug );

		if ( '' === $sanitizedSlug ) {
			return '';
		}

		$selector      = '.template-' . $sanitizedSlug;
		$css           = $selector . " {\n" . $this->indentCss( $allProperties ) . "\n}";

		return veApplyFilters( 'ap.visualEditor.globalStyles.scoped', $css, $slug, $overrides );
	}

	/**
	 * Compile the root CSS plus all registered template scopes.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $selectorOverride Optional CSS selector override for the root block.
	 *
	 * @return string The complete CSS with root and all scoped blocks.
	 */
	public function compileWithScopes( ?string $selectorOverride = null ): string
	{
		$parts   = [];
		$parts[] = $this->compile( $selectorOverride );

		foreach ( $this->templateOverrides as $slug => $overrides ) {
			$scoped = $this->compileScoped( $slug, $overrides );

			if ( '' !== $scoped ) {
				$parts[] = $scoped;
			}
		}

		$css = implode( "\n\n", array_filter( $parts ) );

		return veApplyFilters( 'ap.visualEditor.globalStyles.full', $css );
	}

	/**
	 * Compile all global styles with :root as the selector.
	 *
	 * Guarantees :root is used regardless of the configured root_selector,
	 * which is necessary for the editor canvas where CSS custom properties
	 * must be inherited from the document root.
	 *
	 * @since 1.0.0
	 *
	 * @return string The compiled CSS string using :root.
	 */
	public function compileForEditor(): string
	{
		return $this->compileWithScopes( ':root' );
	}

	/**
	 * Wrap the compiled CSS in a <style> tag for inline output.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $forEditor When true, forces :root selector regardless of config.
	 *
	 * @return string The HTML <style> element.
	 */
	public function toInlineStyle( bool $forEditor = false ): string
	{
		$css = $this->resolvedCss( $forEditor ? ':root' : null );

		if ( '' === $css ) {
			return '';
		}

		// Prevent HTML parser breakout from user-controlled values
		// (e.g., a font family containing "</style>").
		$css = str_replace( '</style', '<\/style', $css );

		return '<style id="ve-global-styles">' . "\n" . $css . "\n" . '</style>';
	}

	/**
	 * Write the compiled CSS to a static file.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path to the written file.
	 */
	public function toFile(): string
	{
		$css  = $this->resolvedCss();
		$path = (string) ( $this->config['output_path'] ?? 'css/ve-global-styles.css' );
		$disk = $this->config['output_disk'] ?? null;

		if ( null !== $disk && '' !== $disk ) {
			if ( ! Storage::disk( $disk )->put( $path, $css ) ) {
				throw new RuntimeException( "Failed to write CSS to disk '{$disk}' at path '{$path}'." );
			}
		} else {
			$fullPath = public_path( $path );
			$dir      = dirname( $fullPath );

			if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
				throw new RuntimeException( "Failed to create directory '{$dir}'." );
			}

			if ( false === file_put_contents( $fullPath, $css ) ) {
				throw new RuntimeException( "Failed to write CSS to '{$fullPath}'." );
			}
		}

		return $path;
	}

	/**
	 * Output CSS based on the configured output mode.
	 *
	 * Returns inline <style> tag, writes a file, or both depending on
	 * the `output_mode` config setting.
	 *
	 * @since 1.0.0
	 *
	 * @return string The inline style tag (for 'inline' and 'both' modes) or file path (for 'file' mode).
	 */
	public function output(): string
	{
		$mode = (string) ( $this->config['output_mode'] ?? 'inline' );

		if ( 'file' === $mode ) {
			return $this->toFile();
		}

		if ( 'both' === $mode ) {
			$this->toFile();

			return $this->toInlineStyle();
		}

		return $this->toInlineStyle();
	}

	/**
	 * Get compiled CSS with caching support.
	 *
	 * Returns cached CSS when available. Falls back to fresh compilation
	 * and caches the result for the configured TTL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The compiled CSS string.
	 */
	public function getCached(): string
	{
		if ( ! $this->isCacheEnabled() ) {
			return $this->compileWithScopes();
		}

		$key   = $this->getCacheKey();
		$ttl   = (int) ( $this->config['cache']['ttl'] ?? 3600 );
		$store = $this->config['cache']['store'] ?? null;

		$cache = ( null !== $store && '' !== $store )
			? Cache::store( $store )
			: Cache::store();

		return $cache->remember( $key, $ttl, fn () => $this->compileWithScopes() );
	}

	/**
	 * Invalidate the cached CSS.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function invalidateCache(): void
	{
		$key   = $this->getCacheKey();
		$store = $this->config['cache']['store'] ?? null;

		$cache = ( null !== $store && '' !== $store )
			? Cache::store( $store )
			: Cache::store();

		$cache->forget( $key );
	}

	/**
	 * Minify a CSS string.
	 *
	 * Removes comments, excess whitespace, and unnecessary newlines.
	 * Suitable for the predictable custom-property CSS this service generates.
	 *
	 * @since 1.0.0
	 *
	 * @param string $css The CSS string to minify.
	 *
	 * @return string The minified CSS.
	 */
	public function minify( string $css ): string
	{
		// Remove comments.
		$css = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );

		// Remove newlines and collapse whitespace.
		$css = (string) preg_replace( '/\s+/', ' ', $css );

		// Remove spaces around braces and colons.
		$css = str_replace( [ ' { ', ' {', '{ ' ], '{', $css );
		$css = str_replace( [ ' } ', ' }', '} ' ], '}', $css );

		return trim( $css );
	}

	/**
	 * Get a source map of CSS properties to their originating manager.
	 *
	 * Returns an associative array mapping each CSS custom property name
	 * to the manager that produced it (colors, typography, or spacing).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of property name => source manager.
	 */
	public function getSourceMap(): array
	{
		$map           = [];
		$includeShades = (bool) ( $this->config['include_color_shades'] ?? true );

		$colorLines = $this->colors->generateCssProperties( $includeShades );

		foreach ( $this->extractPropertyNames( $colorLines ) as $name ) {
			$map[ $name ] = 'colors';
		}

		$typographyLines = $this->typography->generateCssProperties();

		foreach ( $this->extractPropertyNames( $typographyLines ) as $name ) {
			$map[ $name ] = 'typography';
		}

		$spacingLines = $this->spacing->generateCssProperties();

		foreach ( $this->extractPropertyNames( $spacingLines ) as $name ) {
			$map[ $name ] = 'spacing';
		}

		return $map;
	}

	/**
	 * Register a template override for scoped compilation.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $slug      The template slug.
	 * @param array<string, mixed> $overrides The style overrides.
	 *
	 * @return void
	 */
	public function registerTemplateOverride( string $slug, array $overrides ): void
	{
		$this->templateOverrides[ $slug ] = $overrides;

		if ( $this->isCacheEnabled() ) {
			$this->invalidateCache();
		}
	}

	/**
	 * Get all registered template overrides.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function getTemplateOverrides(): array
	{
		return $this->templateOverrides;
	}

	/**
	 * Get the current compiler configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * Get the default compiler configuration.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	protected function defaults(): array
	{
		return [
			'output_mode'          => 'inline',
			'output_path'          => 'css/ve-global-styles.css',
			'output_disk'          => null,
			'minify'               => false,
			'cache'                => [
				'enabled' => false,
				'key'     => 've-global-styles',
				'ttl'     => 3600,
				'store'   => null,
			],
			'debug_comments'       => false,
			'include_color_shades' => true,
			'root_selector'        => ':root',
			'template_overrides'   => [],
		];
	}

	/**
	 * Resolve the final CSS string, applying caching and minification.
	 *
	 * Centralizes the compilation pipeline so that toInlineStyle(),
	 * toFile(), and output() all respect the cache setting.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $selectorOverride Optional CSS selector override for the root block.
	 *
	 * @return string The resolved CSS string.
	 */
	protected function resolvedCss( ?string $selectorOverride = null ): string
	{
		$css = $this->isCacheEnabled() && null === $selectorOverride
			? $this->getCached()
			: $this->compileWithScopes( $selectorOverride );

		return $this->shouldMinify()
			? $this->minify( $css )
			: $css;
	}

	/**
	 * Check if minification is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function shouldMinify(): bool
	{
		return (bool) ( $this->config['minify'] ?? false );
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	protected function isCacheEnabled(): bool
	{
		return (bool) ( $this->config['cache']['enabled'] ?? false );
	}

	/**
	 * Get the cache key.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	protected function getCacheKey(): string
	{
		return (string) ( $this->config['cache']['key'] ?? 've-global-styles' );
	}

	/**
	 * Extract CSS custom property names from a CSS properties string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $css The raw CSS properties string.
	 *
	 * @return array<int, string> The property names.
	 */
	protected function extractPropertyNames( string $css ): array
	{
		$names = [];

		if ( preg_match_all( '/(--[\w-]+)\s*:/', $css, $matches ) ) {
			$names = $matches[1];
		}

		return $names;
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
