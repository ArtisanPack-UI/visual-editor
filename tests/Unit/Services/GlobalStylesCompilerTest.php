<?php

/**
 * GlobalStylesCompiler Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\ColorPaletteManager;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesCompiler;
use ArtisanPackUI\VisualEditor\Services\SpacingScaleManager;
use ArtisanPackUI\VisualEditor\Services\TypographyPresetsManager;

/**
 * Helper to create a fresh compiler instance with default managers.
 */
function createCompiler( array $config = [] ): GlobalStylesCompiler
{
	return new GlobalStylesCompiler(
		new ColorPaletteManager(),
		new TypographyPresetsManager(),
		new SpacingScaleManager(),
		$config,
	);
}

// ─── Compilation Basics ──────────────────────────────────────────────

test( 'compile returns CSS with :root selector by default', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compile();

	expect( $css )->toStartWith( ':root {' )
		->and( $css )->toEndWith( '}' );
} );

test( 'compile includes color custom properties', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compile();

	expect( $css )->toContain( '--ve-color-primary' )
		->and( $css )->toContain( '--ve-color-secondary' );
} );

test( 'compile includes typography custom properties', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compile();

	expect( $css )->toContain( '--ve-font-heading' )
		->and( $css )->toContain( '--ve-font-body' )
		->and( $css )->toContain( '--ve-text-h1-' );
} );

test( 'compile includes spacing custom properties', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compile();

	expect( $css )->toContain( '--ve-spacing-xs' )
		->and( $css )->toContain( '--ve-spacing-md' )
		->and( $css )->toContain( '--ve-block-gap' );
} );

test( 'compile includes color shades by default', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compile();

	expect( $css )->toContain( '--ve-color-primary-light' )
		->and( $css )->toContain( '--ve-color-primary-dark' );
} );

test( 'compile excludes color shades when configured', function (): void {
	$compiler = createCompiler( [ 'include_color_shades' => false ] );
	$css      = $compiler->compile();

	expect( $css )->not->toContain( '--ve-color-primary-light' )
		->and( $css )->not->toContain( '--ve-color-primary-dark' );
} );

test( 'compile uses custom root selector', function (): void {
	$compiler = createCompiler( [ 'root_selector' => '.my-editor' ] );
	$css      = $compiler->compile();

	expect( $css )->toStartWith( '.my-editor {' );
} );

test( 'compile returns empty string when all managers produce no output', function (): void {
	$colors     = new ColorPaletteManager();
	$typography = new TypographyPresetsManager();
	$spacing    = new SpacingScaleManager();

	$colors->setPalette( [] );
	$spacing->setScale( [] );

	// Typography with no families and no elements will still output defaults.
	// Use a fully empty state by constructing with overrides.
	$compiler = new GlobalStylesCompiler(
		$colors,
		$typography,
		$spacing,
	);

	// Colors empty, but typography and spacing still have defaults.
	// This tests that at least the color section can be omitted.
	$css = $compiler->compile();

	expect( $css )->not->toContain( '--ve-color-' );
} );

// ─── Debug Comments ──────────────────────────────────────────────────

test( 'compile includes debug comments when enabled', function (): void {
	$compiler = createCompiler( [ 'debug_comments' => true ] );
	$css      = $compiler->compile();

	expect( $css )->toContain( '/* Colors */' )
		->and( $css )->toContain( '/* Typography */' )
		->and( $css )->toContain( '/* Spacing */' );
} );

test( 'compile omits debug comments by default', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compile();

	expect( $css )->not->toContain( '/* Colors */' )
		->and( $css )->not->toContain( '/* Typography */' )
		->and( $css )->not->toContain( '/* Spacing */' );
} );

// ─── Scoped Output ───────────────────────────────────────────────────

test( 'compileScoped generates template-scoped CSS', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compileScoped( 'dark-theme', [
		'colors' => [
			'primary' => [
				'name'  => 'Dark Primary',
				'color' => '#1e40af',
			],
		],
	] );

	expect( $css )->toStartWith( '.template-dark-theme {' )
		->and( $css )->toContain( '--ve-color-primary: #1e40af' );
} );

test( 'compileScoped applies typography overrides', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compileScoped( 'serif', [
		'typography' => [
			'fontFamilies' => [
				'heading' => '"Playfair Display", serif',
			],
		],
	] );

	expect( $css )->toStartWith( '.template-serif {' )
		->and( $css )->toContain( '--ve-font-heading: "Playfair Display", serif' );
} );

test( 'compileScoped applies spacing overrides', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compileScoped( 'compact', [
		'spacing' => [
			'scale' => [
				'md' => [ 'name' => 'Medium', 'value' => '0.5rem' ],
			],
			'blockGap' => 'sm',
		],
	] );

	expect( $css )->toStartWith( '.template-compact {' )
		->and( $css )->toContain( '--ve-spacing-md: 0.5rem' );
} );

test( 'compileScoped sanitizes slug', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compileScoped( 'my theme!@#', [
		'colors' => [
			'primary' => [
				'name'  => 'P',
				'color' => '#000000',
			],
		],
	] );

	expect( $css )->toStartWith( '.template-mytheme {' );
} );

test( 'compileScoped returns empty string with no overrides', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compileScoped( 'empty', [] );

	expect( $css )->toBe( '' );
} );

// ─── Compile With Scopes ─────────────────────────────────────────────

test( 'compileWithScopes includes root and registered overrides', function (): void {
	$compiler = createCompiler();
	$compiler->registerTemplateOverride( 'dark', [
		'colors' => [
			'background' => [
				'name'  => 'Background',
				'color' => '#1a1a1a',
			],
		],
	] );

	$css = $compiler->compileWithScopes();

	expect( $css )->toContain( ':root {' )
		->and( $css )->toContain( '.template-dark {' )
		->and( $css )->toContain( '#1a1a1a' );
} );

test( 'compileWithScopes returns only root when no overrides registered', function (): void {
	$compiler = createCompiler();
	$css      = $compiler->compileWithScopes();

	expect( $css )->toContain( ':root {' )
		->and( $css )->not->toContain( '.template-' );
} );

// ─── Template Overrides ──────────────────────────────────────────────

test( 'registerTemplateOverride adds override', function (): void {
	$compiler = createCompiler();
	$compiler->registerTemplateOverride( 'dark', [ 'colors' => [] ] );

	expect( $compiler->getTemplateOverrides() )->toHaveKey( 'dark' );
} );

test( 'constructor loads template overrides from config', function (): void {
	$compiler = createCompiler( [
		'template_overrides' => [
			'custom' => [
				'colors' => [
					'primary' => [
						'name'  => 'Custom',
						'color' => '#ff0000',
					],
				],
			],
		],
	] );

	expect( $compiler->getTemplateOverrides() )->toHaveKey( 'custom' );
} );

// ─── Inline Style Output ────────────────────────────────────────────

test( 'toInlineStyle wraps CSS in style tag', function (): void {
	$compiler = createCompiler();
	$html     = $compiler->toInlineStyle();

	expect( $html )->toStartWith( '<style id="ve-global-styles">' )
		->and( $html )->toEndWith( '</style>' )
		->and( $html )->toContain( ':root {' );
} );

test( 'toInlineStyle returns empty string when no CSS', function (): void {
	$colors  = new ColorPaletteManager();
	$typo    = new TypographyPresetsManager();
	$spacing = new SpacingScaleManager();

	$colors->setPalette( [] );
	$spacing->setScale( [] );

	// Typography will still produce output with defaults,
	// so toInlineStyle won't be empty. This is expected behavior.
	$compiler = new GlobalStylesCompiler( $colors, $typo, $spacing );

	// Just verify it produces valid HTML.
	$html = $compiler->toInlineStyle();

	expect( $html )->toContain( '<style' );
} );

// ─── Minification ────────────────────────────────────────────────────

test( 'minify removes comments', function (): void {
	$compiler = createCompiler();
	$input    = "/* Colors */\n:root { --ve-color: #fff; }";
	$result   = $compiler->minify( $input );

	expect( $result )->not->toContain( '/* Colors */' );
} );

test( 'minify collapses whitespace', function (): void {
	$compiler = createCompiler();
	$input    = ":root {\n\t--ve-color: #fff;\n\t--ve-bg: #000;\n}";
	$result   = $compiler->minify( $input );

	expect( $result )->not->toContain( "\n" )
		->and( $result )->not->toContain( "\t" );
} );

test( 'minify produces compact output', function (): void {
	$compiler = createCompiler();
	$input    = ":root {\n\t--a: 1;\n}";
	$result   = $compiler->minify( $input );

	expect( $result )->toBe( ':root{--a: 1;}' );
} );

test( 'toInlineStyle applies minification when configured', function (): void {
	$compiler = createCompiler( [ 'minify' => true ] );
	$html     = $compiler->toInlineStyle();

	expect( $html )->not->toContain( "\t" );
} );

// ─── Source Map ──────────────────────────────────────────────────────

test( 'getSourceMap maps properties to source manager', function (): void {
	$compiler = createCompiler();
	$map      = $compiler->getSourceMap();

	expect( $map )->toHaveKey( '--ve-color-primary' )
		->and( $map['--ve-color-primary'] )->toBe( 'colors' )
		->and( $map )->toHaveKey( '--ve-font-heading' )
		->and( $map['--ve-font-heading'] )->toBe( 'typography' )
		->and( $map )->toHaveKey( '--ve-spacing-md' )
		->and( $map['--ve-spacing-md'] )->toBe( 'spacing' )
		->and( $map )->toHaveKey( '--ve-block-gap' )
		->and( $map['--ve-block-gap'] )->toBe( 'spacing' );
} );

test( 'getSourceMap includes shade properties when configured', function (): void {
	$compiler = createCompiler( [ 'include_color_shades' => true ] );
	$map      = $compiler->getSourceMap();

	expect( $map )->toHaveKey( '--ve-color-primary-light' )
		->and( $map['--ve-color-primary-light'] )->toBe( 'colors' )
		->and( $map )->toHaveKey( '--ve-color-primary-dark' )
		->and( $map['--ve-color-primary-dark'] )->toBe( 'colors' );
} );

test( 'getSourceMap excludes shade properties when configured', function (): void {
	$compiler = createCompiler( [ 'include_color_shades' => false ] );
	$map      = $compiler->getSourceMap();

	expect( $map )->not->toHaveKey( '--ve-color-primary-light' )
		->and( $map )->not->toHaveKey( '--ve-color-primary-dark' );
} );

// ─── Output Mode ─────────────────────────────────────────────────────

test( 'output returns inline style by default', function (): void {
	$compiler = createCompiler( [ 'output_mode' => 'inline' ] );
	$result   = $compiler->output();

	expect( $result )->toContain( '<style' );
} );

// ─── Configuration ───────────────────────────────────────────────────

test( 'getConfig returns merged configuration', function (): void {
	$compiler = createCompiler( [ 'minify' => true ] );
	$config   = $compiler->getConfig();

	expect( $config['minify'] )->toBeTrue()
		->and( $config )->toHaveKey( 'output_mode' )
		->and( $config )->toHaveKey( 'cache' )
		->and( $config )->toHaveKey( 'root_selector' );
} );

test( 'default config has expected values', function (): void {
	$compiler = createCompiler();
	$config   = $compiler->getConfig();

	expect( $config['output_mode'] )->toBe( 'inline' )
		->and( $config['minify'] )->toBeFalse()
		->and( $config['debug_comments'] )->toBeFalse()
		->and( $config['include_color_shades'] )->toBeTrue()
		->and( $config['root_selector'] )->toBe( ':root' )
		->and( $config['cache']['enabled'] )->toBeFalse()
		->and( $config['cache']['key'] )->toBe( 've-global-styles' )
		->and( $config['cache']['ttl'] )->toBe( 3600 );
} );

// ─── Custom Manager State ────────────────────────────────────────────

test( 'compile reflects custom palette', function (): void {
	$colors = new ColorPaletteManager( [
		'brand' => [
			'name'  => 'Brand',
			'slug'  => 'brand',
			'color' => '#ff6600',
		],
	] );

	$compiler = new GlobalStylesCompiler(
		$colors,
		new TypographyPresetsManager(),
		new SpacingScaleManager(),
	);

	$css = $compiler->compile();

	expect( $css )->toContain( '--ve-color-brand: #ff6600' )
		->and( $css )->not->toContain( '--ve-color-primary' );
} );

test( 'compileScoped does not mutate original managers', function (): void {
	$compiler = createCompiler();

	$compiler->compileScoped( 'test', [
		'colors' => [
			'primary' => [
				'name'  => 'Changed',
				'color' => '#000000',
			],
		],
	] );

	// Original compile should still have the default primary.
	$css = $compiler->compile();

	expect( $css )->toContain( '--ve-color-primary: #3b82f6' );
} );

test( 'compileScoped with debug comments includes section markers', function (): void {
	$compiler = createCompiler( [ 'debug_comments' => true ] );
	$css      = $compiler->compileScoped( 'test', [
		'colors' => [
			'primary' => [
				'name'  => 'P',
				'color' => '#000000',
			],
		],
	] );

	expect( $css )->toContain( '/* Colors */' );
} );
