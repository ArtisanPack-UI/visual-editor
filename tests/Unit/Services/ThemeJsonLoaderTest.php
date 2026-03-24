<?php

/**
 * ThemeJsonLoader Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\ColorPaletteManager;
use ArtisanPackUI\VisualEditor\Services\SpacingScaleManager;
use ArtisanPackUI\VisualEditor\Services\ThemeJsonLoader;
use ArtisanPackUI\VisualEditor\Services\TypographyPresetsManager;

/**
 * Create a ThemeJsonLoader with fresh manager instances.
 *
 * @return array{loader: ThemeJsonLoader, colors: ColorPaletteManager, typography: TypographyPresetsManager, spacing: SpacingScaleManager}
 */
function createLoader(): array
{
	$colors     = new ColorPaletteManager();
	$typography = new TypographyPresetsManager();
	$spacing    = new SpacingScaleManager();
	$loader     = new ThemeJsonLoader( $colors, $typography, $spacing );

	return compact( 'loader', 'colors', 'typography', 'spacing' );
}

/**
 * Tracks temporary files created during tests for cleanup.
 *
 * @var array<int, string>
 */
$tempFiles = [];

/**
 * Write a temporary theme.json for testing.
 *
 * @param array<string, mixed> $data The data to write.
 *
 * @return string The file path.
 */
function writeThemeJson( array $data ): string
{
	global $tempFiles;

	$path = sys_get_temp_dir() . '/ve-test-theme-' . uniqid() . '.json';
	file_put_contents( $path, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

	$tempFiles[] = $path;

	return $path;
}

afterEach( function (): void {
	global $tempFiles;

	foreach ( $tempFiles ?? [] as $file ) {
		@unlink( $file );
	}

	$tempFiles = [];
} );

// ---- Loading Tests ----

test( 'load returns false for nonexistent file', function (): void {
	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( '/nonexistent/theme.json' ) )->toBeFalse()
		->and( $loader->isLoaded() )->toBeFalse()
		->and( $loader->getErrors() )->toBeEmpty();
} );

test( 'load returns false for empty file', function (): void {
	global $tempFiles;
	$path = sys_get_temp_dir() . '/ve-test-empty-' . uniqid() . '.json';
	file_put_contents( $path, '' );
	$tempFiles[] = $path;

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse()
		->and( $loader->getErrors() )->not->toBeEmpty();
} );

test( 'load returns false for invalid JSON', function (): void {
	global $tempFiles;
	$path = sys_get_temp_dir() . '/ve-test-invalid-' . uniqid() . '.json';
	file_put_contents( $path, '{ invalid json }' );
	$tempFiles[] = $path;

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse()
		->and( $loader->getErrors() )->not->toBeEmpty();
} );

test( 'load returns false for non-object JSON', function (): void {
	global $tempFiles;
	$path = sys_get_temp_dir() . '/ve-test-array-' . uniqid() . '.json';
	file_put_contents( $path, '"just a string"' );
	$tempFiles[] = $path;

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse()
		->and( $loader->getErrors() )->not->toBeEmpty();
} );

test( 'load succeeds with valid minimal theme.json', function (): void {
	$path = writeThemeJson( [ 'version' => 1 ] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeTrue()
		->and( $loader->isLoaded() )->toBeTrue()
		->and( $loader->getErrors() )->toBeEmpty()
		->and( $loader->getVersion() )->toBe( 1 );

} );

test( 'load populates data and file path', function (): void {
	$path = writeThemeJson( [ 'version' => 1 ] );

	[ 'loader' => $loader ] = createLoader();
	$loader->load( $path );

	expect( $loader->getData() )->toBe( [ 'version' => 1 ] )
		->and( $loader->getFilePath() )->toBe( $path );

} );

// ---- Validation Tests ----

test( 'validate fails when version is missing', function (): void {
	$path = writeThemeJson( [ 'settings' => [] ] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse()
		->and( $loader->getErrors() )->not->toBeEmpty();

} );

test( 'validate fails when version is zero', function (): void {
	$path = writeThemeJson( [ 'version' => 0 ] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse()
		->and( $loader->getErrors() )->not->toBeEmpty();

} );

test( 'validate fails when version is too high', function (): void {
	$path = writeThemeJson( [ 'version' => 99 ] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse()
		->and( $loader->getErrors() )->not->toBeEmpty();

} );

test( 'validate fails when settings is not an object', function (): void {
	$path = writeThemeJson( [ 'version' => 1, 'settings' => 'bad' ] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse();

} );

test( 'validate fails for invalid color palette entry', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Test' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse()
		->and( $loader->getErrors() )->not->toBeEmpty();

} );

test( 'validate fails for invalid hex color', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Bad', 'slug' => 'bad', 'color' => 'not-a-hex' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse();

} );

test( 'validate passes for valid color palette', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ],
					[ 'name' => 'Red', 'slug' => 'red', 'color' => '#ff0000' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeTrue()
		->and( $loader->getErrors() )->toBeEmpty();

} );

test( 'validate fails for invalid font family slot', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'typography' => [
				'fontFamilies' => [
					'invalid-slot' => 'Arial, sans-serif',
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse();

} );

test( 'validate fails for invalid typography element', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'typography' => [
				'elements' => [
					'invalid-element' => [ 'fontSize' => '1rem' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse();

} );

test( 'validate passes for valid typography settings', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'typography' => [
				'fontFamilies' => [
					'heading' => '"Inter", sans-serif',
					'body'    => '"Inter", sans-serif',
				],
				'elements' => [
					'h1'   => [ 'fontSize' => '2.25rem', 'fontWeight' => '800' ],
					'body' => [ 'fontSize' => '1rem' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeTrue();

} );

test( 'validate passes for valid spacing settings', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'spacing' => [
				'scale'    => [
					'xs' => '0.25rem',
					'md' => '1rem',
				],
				'blockGap' => 'md',
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeTrue();

} );

test( 'validate fails when spacing scale is not an object', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'spacing' => [
				'scale' => 'bad',
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse();

} );

test( 'validate passes for valid styles blocks section', function (): void {
	$path = writeThemeJson( [
		'version' => 1,
		'styles'  => [
			'blocks' => [
				'heading' => [
					'color' => [ 'text' => 'palette:text' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeTrue();

} );

test( 'validate fails when styles is not an object', function (): void {
	$path = writeThemeJson( [ 'version' => 1, 'styles' => 'bad' ] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse();

} );

test( 'validate fails when templateOverrides entries are not objects', function (): void {
	$path = writeThemeJson( [
		'version'           => 1,
		'templateOverrides' => [
			'dark-theme' => 'not an object',
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeFalse();

} );

test( 'validate passes for valid template overrides', function (): void {
	$path = writeThemeJson( [
		'version'           => 1,
		'templateOverrides' => [
			'dark-theme' => [
				'colors' => [
					'background' => [ 'name' => 'Background', 'color' => '#1a1a2e' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->load( $path ) )->toBeTrue();

} );

// ---- Apply Tests ----

test( 'apply throws when no data is loaded', function (): void {
	[ 'loader' => $loader ] = createLoader();

	$loader->apply();
} )->throws( RuntimeException::class );

test( 'apply sets color palette on manager', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Brand', 'slug' => 'brand', 'color' => '#ff0000' ],
					[ 'name' => 'Accent', 'slug' => 'accent', 'color' => '#00ff00' ],
				],
			],
		],
	] );

	[ 'loader' => $loader, 'colors' => $colors ] = createLoader();
	$loader->load( $path );
	$loader->apply();

	$palette = $colors->getPalette();

	expect( $palette )->toHaveCount( 2 )
		->and( $palette )->toHaveKey( 'brand' )
		->and( $palette )->toHaveKey( 'accent' )
		->and( $palette['brand']['color'] )->toBe( '#ff0000' );

} );

test( 'apply sets font families on typography manager', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'typography' => [
				'fontFamilies' => [
					'heading' => '"Playfair Display", serif',
				],
			],
		],
	] );

	[ 'loader' => $loader, 'typography' => $typography ] = createLoader();
	$loader->load( $path );
	$loader->apply();

	expect( $typography->getFontFamily( 'heading' ) )->toBe( '"Playfair Display", serif' );

} );

test( 'apply sets element styles on typography manager', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'typography' => [
				'elements' => [
					'h1' => [ 'fontSize' => '3rem', 'fontWeight' => '900' ],
				],
			],
		],
	] );

	[ 'loader' => $loader, 'typography' => $typography ] = createLoader();
	$loader->load( $path );
	$loader->apply();

	$h1 = $typography->getElement( 'h1' );

	expect( $h1['fontSize'] )->toBe( '3rem' )
		->and( $h1['fontWeight'] )->toBe( '900' );

} );

test( 'apply sets spacing scale on manager', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'spacing' => [
				'scale' => [
					'tiny'  => '0.125rem',
					'small' => '0.25rem',
				],
				'blockGap' => 'small',
			],
		],
	] );

	[ 'loader' => $loader, 'spacing' => $spacing ] = createLoader();
	$loader->load( $path );
	$loader->apply();

	expect( $spacing->hasStep( 'tiny' ) )->toBeTrue()
		->and( $spacing->getStepValue( 'tiny' ) )->toBe( '0.125rem' )
		->and( $spacing->getBlockGap() )->toBe( 'small' );

} );

test( 'apply does not change managers when settings is absent', function (): void {
	$path = writeThemeJson( [ 'version' => 1 ] );

	[ 'loader' => $loader, 'colors' => $colors ] = createLoader();

	$beforePalette = $colors->getPalette();

	$loader->load( $path );
	$loader->apply();

	expect( $colors->getPalette() )->toBe( $beforePalette );

} );

// ---- Accessor Tests ----

test( 'getBlockStyles returns block style defaults', function (): void {
	$path = writeThemeJson( [
		'version' => 1,
		'styles'  => [
			'blocks' => [
				'heading' => [ 'color' => [ 'text' => 'palette:text' ] ],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();
	$loader->load( $path );

	$blocks = $loader->getBlockStyles();

	expect( $blocks )->toHaveKey( 'heading' )
		->and( $blocks['heading']['color']['text'] )->toBe( 'palette:text' );

} );

test( 'getBlockStyles returns empty when no styles defined', function (): void {
	$path = writeThemeJson( [ 'version' => 1 ] );

	[ 'loader' => $loader ] = createLoader();
	$loader->load( $path );

	expect( $loader->getBlockStyles() )->toBeEmpty();

} );

test( 'getTemplateOverrides returns template overrides', function (): void {
	$overrides = [
		'dark-theme' => [
			'colors' => [
				'background' => [ 'name' => 'Background', 'color' => '#1a1a2e' ],
			],
		],
	];

	$path = writeThemeJson( [
		'version'           => 1,
		'templateOverrides' => $overrides,
	] );

	[ 'loader' => $loader ] = createLoader();
	$loader->load( $path );

	expect( $loader->getTemplateOverrides() )->toBe( $overrides );

} );

test( 'getVersion returns null when not loaded', function (): void {
	[ 'loader' => $loader ] = createLoader();

	expect( $loader->getVersion() )->toBeNull();
} );

test( 'schema version constant is 1', function (): void {
	expect( ThemeJsonLoader::SCHEMA_VERSION )->toBe( 1 );
} );

// ---- Full Theme.json Test ----

test( 'load and apply works with full theme.json', function (): void {
	$path = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ],
					[ 'name' => 'Secondary', 'slug' => 'secondary', 'color' => '#6366f1' ],
				],
			],
			'typography' => [
				'fontFamilies' => [
					'heading' => '"Inter", sans-serif',
					'body'    => '"Inter", sans-serif',
					'mono'    => '"JetBrains Mono", monospace',
				],
				'elements' => [
					'h1'   => [ 'fontSize' => '2.25rem', 'fontWeight' => '800', 'lineHeight' => '1.2' ],
					'body' => [ 'fontSize' => '1rem', 'fontWeight' => '400', 'lineHeight' => '1.6' ],
				],
			],
			'spacing' => [
				'scale' => [
					'xs'  => '0.25rem',
					'sm'  => '0.5rem',
					'md'  => '1rem',
					'lg'  => '1.5rem',
					'xl'  => '2rem',
					'2xl' => '3rem',
					'3xl' => '4rem',
				],
				'blockGap' => 'md',
			],
		],
		'styles' => [
			'blocks' => [
				'heading' => [
					'color'      => [ 'text' => 'palette:text' ],
					'typography' => [ 'fontFamily' => 'var(--ve-font-heading)' ],
				],
			],
		],
		'templateOverrides' => [
			'dark-theme' => [
				'colors' => [
					'background' => [ 'name' => 'Background', 'color' => '#1a1a2e' ],
					'text'       => [ 'name' => 'Text', 'color' => '#e2e8f0' ],
				],
			],
		],
	] );

	[ 'loader' => $loader, 'colors' => $colors, 'typography' => $typography, 'spacing' => $spacing ] = createLoader();

	expect( $loader->load( $path ) )->toBeTrue()
		->and( $loader->getErrors() )->toBeEmpty();

	$loader->apply();

	// Colors applied
	expect( $colors->getPalette() )->toHaveCount( 2 )
		->and( $colors->getColorValue( 'primary' ) )->toBe( '#3b82f6' );

	// Typography applied
	expect( $typography->getFontFamily( 'heading' ) )->toBe( '"Inter", sans-serif' )
		->and( $typography->getElement( 'h1' )['fontSize'] )->toBe( '2.25rem' );

	// Spacing applied
	expect( $spacing->getStepValue( 'md' ) )->toBe( '1rem' )
		->and( $spacing->getBlockGap() )->toBe( 'md' );

	// Block styles accessible
	expect( $loader->getBlockStyles() )->toHaveKey( 'heading' );

	// Template overrides accessible
	expect( $loader->getTemplateOverrides() )->toHaveKey( 'dark-theme' );

} );

// ---- Multi-File Cascade Tests ----

test( 'loadPaths loads multiple files in order', function (): void {
	$base = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#ff0000' ],
					[ 'name' => 'Text', 'slug' => 'text', 'color' => '#000000' ],
				],
			],
		],
	] );

	$override = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#00ff00' ],
				],
			],
		],
	] );

	[ 'loader' => $loader, 'colors' => $colors ] = createLoader();

	expect( $loader->loadPaths( [ $base, $override ] ) )->toBeTrue();

	$loader->apply();

	$palette = $colors->getPalette();

	// Override replaced primary, base's text entry preserved.
	expect( $palette )->toHaveKey( 'primary' )
		->and( $palette['primary']['color'] )->toBe( '#00ff00' )
		->and( $palette )->toHaveKey( 'text' )
		->and( $palette['text']['color'] )->toBe( '#000000' );

} );

test( 'loadPaths returns false when no files exist', function (): void {
	[ 'loader' => $loader ] = createLoader();

	expect( $loader->loadPaths( [ '/nonexistent/a.json', '/nonexistent/b.json' ] ) )->toBeFalse()
		->and( $loader->isLoaded() )->toBeFalse();
} );

test( 'loadPaths skips nonexistent files', function (): void {
	$valid = writeThemeJson( [ 'version' => 1 ] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->loadPaths( [ '/nonexistent.json', $valid ] ) )->toBeTrue()
		->and( $loader->getLoadedPaths() )->toHaveCount( 1 );

} );

test( 'loadPaths skips invalid files and continues', function (): void {
	global $tempFiles;
	$invalid = sys_get_temp_dir() . '/ve-test-invalid-cascade-' . uniqid() . '.json';
	file_put_contents( $invalid, '{ bad json }' );
	$tempFiles[] = $invalid;

	$valid = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Brand', 'slug' => 'brand', 'color' => '#abc123' ],
				],
			],
		],
	] );

	[ 'loader' => $loader ] = createLoader();

	expect( $loader->loadPaths( [ $invalid, $valid ] ) )->toBeTrue()
		->and( $loader->getErrors() )->not->toBeEmpty()
		->and( $loader->getLoadedPaths() )->toHaveCount( 1 );

} );

test( 'loadPaths deep merges typography settings', function (): void {
	$base = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'typography' => [
				'fontFamilies' => [
					'heading' => '"Georgia", serif',
					'body'    => '"Arial", sans-serif',
				],
				'elements' => [
					'h1' => [ 'fontSize' => '2rem', 'fontWeight' => '700' ],
				],
			],
		],
	] );

	$override = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'typography' => [
				'fontFamilies' => [
					'heading' => '"Inter", sans-serif',
				],
				'elements' => [
					'h1' => [ 'fontSize' => '3rem' ],
					'h2' => [ 'fontSize' => '2rem' ],
				],
			],
		],
	] );

	[ 'loader' => $loader, 'typography' => $typography ] = createLoader();

	$loader->loadPaths( [ $base, $override ] );
	$loader->apply();

	// heading overridden, body preserved from base.
	expect( $typography->getFontFamily( 'heading' ) )->toBe( '"Inter", sans-serif' )
		->and( $typography->getFontFamily( 'body' ) )->toBe( '"Arial", sans-serif' );

	// h1 fontSize overridden, h2 added from override.
	$h1 = $typography->getElement( 'h1' );
	expect( $h1['fontSize'] )->toBe( '3rem' )
		->and( $h1['fontWeight'] )->toBe( '700' );

	$h2 = $typography->getElement( 'h2' );
	expect( $h2['fontSize'] )->toBe( '2rem' );

} );

test( 'loadPaths deep merges spacing and template overrides', function (): void {
	$base = writeThemeJson( [
		'version'           => 1,
		'settings'          => [
			'spacing' => [
				'scale'    => [ 'sm' => '0.5rem', 'md' => '1rem' ],
				'blockGap' => 'md',
			],
		],
		'templateOverrides' => [
			'dark' => [
				'colors' => [
					'bg' => [ 'name' => 'BG', 'color' => '#111111' ],
				],
			],
		],
	] );

	$override = writeThemeJson( [
		'version'           => 1,
		'settings'          => [
			'spacing' => [
				'scale'    => [ 'md' => '1.5rem', 'lg' => '2rem' ],
				'blockGap' => 'lg',
			],
		],
		'templateOverrides' => [
			'dark'  => [
				'colors' => [
					'bg' => [ 'name' => 'BG', 'color' => '#222222' ],
				],
			],
			'light' => [
				'colors' => [
					'bg' => [ 'name' => 'BG', 'color' => '#ffffff' ],
				],
			],
		],
	] );

	[ 'loader' => $loader, 'spacing' => $spacing ] = createLoader();

	$loader->loadPaths( [ $base, $override ] );
	$loader->apply();

	// sm preserved from base, md overridden, lg added.
	expect( $spacing->getStepValue( 'sm' ) )->toBe( '0.5rem' )
		->and( $spacing->getStepValue( 'md' ) )->toBe( '1.5rem' )
		->and( $spacing->hasStep( 'lg' ) )->toBeTrue()
		->and( $spacing->getBlockGap() )->toBe( 'lg' );

	// Template overrides merged.
	$overrides = $loader->getTemplateOverrides();
	expect( $overrides )->toHaveKey( 'dark' )
		->and( $overrides )->toHaveKey( 'light' )
		->and( $overrides['dark']['colors']['bg']['color'] )->toBe( '#222222' );

} );

test( 'registerPath appends to registered paths', function (): void {
	[ 'loader' => $loader ] = createLoader();

	expect( $loader->getRegisteredPaths() )->toBeEmpty();

	$loader->registerPath( '/custom/theme.json' );
	$loader->registerPath( '/another/theme.json' );

	expect( $loader->getRegisteredPaths() )->toHaveCount( 2 )
		->and( $loader->getRegisteredPaths()[0] )->toBe( '/custom/theme.json' )
		->and( $loader->getRegisteredPaths()[1] )->toBe( '/another/theme.json' );
} );

test( 'registerPath deduplicates identical paths', function (): void {
	[ 'loader' => $loader ] = createLoader();

	$loader->registerPath( '/custom/theme.json' );
	$loader->registerPath( '/custom/theme.json' );
	$loader->registerPath( '/other/theme.json' );

	expect( $loader->getRegisteredPaths() )->toHaveCount( 2 );
} );

test( 'loadPaths includes registered paths after config paths', function (): void {
	$configFile = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#ff0000' ],
				],
			],
		],
	] );

	$registeredFile = writeThemeJson( [
		'version'  => 1,
		'settings' => [
			'color' => [
				'palette' => [
					[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#0000ff' ],
				],
			],
		],
	] );

	[ 'loader' => $loader, 'colors' => $colors ] = createLoader();

	$loader->registerPath( $registeredFile );
	$loader->loadPaths( [ $configFile ] );
	$loader->apply();

	// Registered path overrides config path.
	expect( $colors->getColorValue( 'primary' ) )->toBe( '#0000ff' )
		->and( $loader->getLoadedPaths() )->toHaveCount( 2 );

} );

test( 'getLoadedPaths tracks all successfully loaded files', function (): void {
	$file1 = writeThemeJson( [ 'version' => 1 ] );
	$file2 = writeThemeJson( [ 'version' => 1 ] );

	[ 'loader' => $loader ] = createLoader();

	$loader->loadPaths( [ $file1, '/nonexistent.json', $file2 ] );

	expect( $loader->getLoadedPaths() )->toHaveCount( 2 )
		->and( $loader->getLoadedPaths()[0] )->toBe( $file1 )
		->and( $loader->getLoadedPaths()[1] )->toBe( $file2 );

} );
