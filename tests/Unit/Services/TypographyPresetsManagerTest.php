<?php

/**
 * TypographyPresetsManager Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\TypographyPresetsManager;

test( 'typography presets manager initializes with defaults', function (): void {
	$manager = new TypographyPresetsManager();

	$families = $manager->getFontFamilies();
	$elements = $manager->getElements();

	expect( $families )->toHaveKey( 'heading' )
		->and( $families )->toHaveKey( 'body' )
		->and( $families )->toHaveKey( 'mono' )
		->and( $elements )->toHaveKey( 'h1' )
		->and( $elements )->toHaveKey( 'h2' )
		->and( $elements )->toHaveKey( 'h3' )
		->and( $elements )->toHaveKey( 'h4' )
		->and( $elements )->toHaveKey( 'h5' )
		->and( $elements )->toHaveKey( 'h6' )
		->and( $elements )->toHaveKey( 'body' )
		->and( $elements )->toHaveKey( 'small' )
		->and( $elements )->toHaveKey( 'caption' )
		->and( $elements )->toHaveKey( 'blockquote' )
		->and( $elements )->toHaveKey( 'code' );
} );

test( 'default font families has 3 entries', function (): void {
	expect( TypographyPresetsManager::DEFAULT_FONT_FAMILIES )->toHaveCount( 3 );
} );

test( 'default elements has 11 entries', function (): void {
	expect( TypographyPresetsManager::DEFAULT_ELEMENTS )->toHaveCount( 11 );
} );

test( 'accepts custom config overriding font families', function (): void {
	$manager = new TypographyPresetsManager( [
		'fontFamilies' => [
			'heading' => '"Playfair Display", serif',
		],
	] );

	expect( $manager->getFontFamily( 'heading' ) )->toBe( '"Playfair Display", serif' )
		->and( $manager->getFontFamily( 'body' ) )->toBe( 'Inter, sans-serif' );
} );

test( 'accepts custom config overriding elements', function (): void {
	$manager = new TypographyPresetsManager( [
		'elements' => [
			'h1' => [
				'fontSize'   => '3rem',
				'fontWeight' => '800',
				'lineHeight' => '1.1',
			],
		],
	] );

	$h1 = $manager->getElement( 'h1' );

	expect( $h1['fontSize'] )->toBe( '3rem' )
		->and( $h1['fontWeight'] )->toBe( '800' )
		->and( $manager->getElement( 'body' ) )->not->toBeNull();
} );

test( 'get font family returns value by slot', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->getFontFamily( 'heading' ) )->toBe( 'Inter, sans-serif' )
		->and( $manager->getFontFamily( 'mono' ) )->toBe( 'JetBrains Mono, monospace' );
} );

test( 'get font family returns null for missing slot', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->getFontFamily( 'nonexistent' ) )->toBeNull();
} );

test( 'set font family updates a slot', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setFontFamily( 'heading', '"Georgia", serif' );

	expect( $manager->getFontFamily( 'heading' ) )->toBe( '"Georgia", serif' );
} );

test( 'set font family throws on invalid slot', function (): void {
	$manager = new TypographyPresetsManager();

	expect( fn () => $manager->setFontFamily( 'invalid', 'Arial' ) )
		->toThrow( InvalidArgumentException::class );
} );

test( 'set font families replaces all entries', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setFontFamilies( [ 'heading' => 'Arial' ] );

	$families = $manager->getFontFamilies();

	expect( $families )->toHaveCount( 1 )
		->and( $families )->toHaveKey( 'heading' )
		->and( $families )->not->toHaveKey( 'body' );
} );

test( 'get element returns styles by key', function (): void {
	$manager = new TypographyPresetsManager();
	$h1      = $manager->getElement( 'h1' );

	expect( $h1 )->not->toBeNull()
		->and( $h1 )->toHaveKey( 'fontSize' )
		->and( $h1 )->toHaveKey( 'fontWeight' )
		->and( $h1 )->toHaveKey( 'lineHeight' );
} );

test( 'get element returns null for missing key', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->getElement( 'nonexistent' ) )->toBeNull();
} );

test( 'set element updates an element preset', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setElement( 'h1', [
		'fontSize'   => '3rem',
		'fontWeight' => '900',
		'lineHeight' => '1.1',
	] );

	$h1 = $manager->getElement( 'h1' );

	expect( $h1['fontSize'] )->toBe( '3rem' )
		->and( $h1['fontWeight'] )->toBe( '900' )
		->and( $h1['lineHeight'] )->toBe( '1.1' );
} );

test( 'set element throws on invalid element key', function (): void {
	$manager = new TypographyPresetsManager();

	expect( fn () => $manager->setElement( 'div', [ 'fontSize' => '1rem' ] ) )
		->toThrow( InvalidArgumentException::class );
} );

test( 'set element property updates a single property', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setElementProperty( 'h1', 'fontSize', '4rem' );

	expect( $manager->getElement( 'h1' )['fontSize'] )->toBe( '4rem' )
		->and( $manager->getElement( 'h1' )['fontWeight'] )->toBe( '700' );
} );

test( 'set element property throws on invalid element', function (): void {
	$manager = new TypographyPresetsManager();

	expect( fn () => $manager->setElementProperty( 'div', 'fontSize', '1rem' ) )
		->toThrow( InvalidArgumentException::class );
} );

test( 'has element returns true for existing element', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->hasElement( 'h1' ) )->toBeTrue()
		->and( $manager->hasElement( 'body' ) )->toBeTrue();
} );

test( 'has element returns false for missing element', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->hasElement( 'nonexistent' ) )->toBeFalse();
} );

test( 'remove element deletes an element', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->removeElement( 'h1' );

	expect( $manager->hasElement( 'h1' ) )->toBeFalse();
} );

test( 'set elements replaces all entries', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setElements( [
		'h1' => [
			'fontSize'   => '2rem',
			'fontWeight' => '700',
			'lineHeight' => '1.2',
		],
	] );

	$elements = $manager->getElements();

	expect( $elements )->toHaveCount( 1 )
		->and( $elements )->toHaveKey( 'h1' )
		->and( $elements )->not->toHaveKey( 'body' );
} );

test( 'reset to defaults restores all settings', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setFontFamily( 'heading', 'Custom Font' );
	$manager->removeElement( 'h1' );
	$manager->registerGoogleFont( 'Roboto' );
	$manager->registerCustomFont( 'MyFont', '/font.woff2' );
	$manager->resetToDefaults();

	expect( $manager->getFontFamilies() )->toBe( TypographyPresetsManager::DEFAULT_FONT_FAMILIES )
		->and( $manager->getElements() )->toBe( TypographyPresetsManager::DEFAULT_ELEMENTS )
		->and( $manager->getGoogleFonts() )->toBe( [] )
		->and( $manager->getCustomFonts() )->toBe( [] );
} );

test( 'get default font families returns the constant', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->getDefaultFontFamilies() )->toBe( TypographyPresetsManager::DEFAULT_FONT_FAMILIES );
} );

test( 'get default elements returns the constant', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->getDefaultElements() )->toBe( TypographyPresetsManager::DEFAULT_ELEMENTS );
} );

test( 'generate type scale produces correct sizes', function (): void {
	$manager = new TypographyPresetsManager();
	$scale   = $manager->generateTypeScale( 1.0, 1.25 );

	expect( $scale )->toHaveKeys( [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ] )
		->and( $scale['h6'] )->toBe( '1.25rem' )
		->and( $scale['h5'] )->toBe( '1.563rem' )
		->and( (float) rtrim( $scale['h1'], 'rem' ) )->toBeGreaterThan( (float) rtrim( $scale['h2'], 'rem' ) );
} );

test( 'generate type scale respects custom unit', function (): void {
	$manager = new TypographyPresetsManager();
	$scale   = $manager->generateTypeScale( 16.0, 1.25, 'px' );

	expect( $scale['h6'] )->toEndWith( 'px' );
} );

test( 'apply type scale updates heading sizes', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->applyTypeScale( 1.0, 1.5 );

	$h6 = $manager->getElement( 'h6' );
	$h1 = $manager->getElement( 'h1' );

	expect( $h6['fontSize'] )->toBe( '1.5rem' )
		->and( (float) rtrim( $h1['fontSize'], 'rem' ) )->toBeGreaterThan( (float) rtrim( $h6['fontSize'], 'rem' ) )
		->and( $h1['fontWeight'] )->toBe( '700' );
} );

test( 'register custom font stores font entry', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerCustomFont( 'MyFont', '/fonts/myfont.woff2', '700', 'italic' );

	$fonts = $manager->getCustomFonts();

	expect( $fonts )->toHaveCount( 1 )
		->and( $fonts[0]['family'] )->toBe( 'MyFont' )
		->and( $fonts[0]['src'] )->toBe( '/fonts/myfont.woff2' )
		->and( $fonts[0]['weight'] )->toBe( '700' )
		->and( $fonts[0]['style'] )->toBe( 'italic' );
} );

test( 'register google font stores font entry', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerGoogleFont( 'Roboto', [ '400', '700' ], [ 'normal', 'italic' ] );

	$fonts = $manager->getGoogleFonts();

	expect( $fonts )->toHaveCount( 1 )
		->and( $fonts[0]['family'] )->toBe( 'Roboto' )
		->and( $fonts[0]['weights'] )->toBe( [ '400', '700' ] )
		->and( $fonts[0]['styles'] )->toBe( [ 'normal', 'italic' ] );
} );

test( 'generate google fonts url returns valid url', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerGoogleFont( 'Inter', [ '400', '600', '700' ] );

	$url = $manager->generateGoogleFontsUrl();

	expect( $url )->toStartWith( 'https://fonts.googleapis.com/css2?' )
		->and( $url )->toContain( 'family=Inter' )
		->and( $url )->toContain( 'display=swap' );
} );

test( 'generate google fonts url returns null when empty', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->generateGoogleFontsUrl() )->toBeNull();
} );

test( 'generate google fonts url handles italic styles', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerGoogleFont( 'Roboto', [ '400', '700' ], [ 'normal', 'italic' ] );

	$url = $manager->generateGoogleFontsUrl();

	expect( $url )->toContain( 'ital,wght@' )
		->and( $url )->toContain( '0,400' )
		->and( $url )->toContain( '1,400' );
} );

test( 'generate google fonts url handles multiple families', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerGoogleFont( 'Inter', [ '400' ] );
	$manager->registerGoogleFont( 'Roboto Mono', [ '400' ] );

	$url = $manager->generateGoogleFontsUrl();

	expect( $url )->toContain( 'family=Inter' )
		->and( $url )->toContain( 'family=Roboto+Mono' );
} );

test( 'generate font face declarations returns valid css', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerCustomFont( 'MyFont', '/fonts/myfont.woff2', '400', 'normal' );

	$css = $manager->generateFontFaceDeclarations();

	expect( $css )->toContain( '@font-face' )
		->and( $css )->toContain( "font-family: 'MyFont'" )
		->and( $css )->toContain( '/fonts/myfont.woff2' )
		->and( $css )->toContain( "format('woff2')" )
		->and( $css )->toContain( 'font-weight: 400' )
		->and( $css )->toContain( 'font-display: swap' );
} );

test( 'generate font face declarations returns empty when no fonts', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->generateFontFaceDeclarations() )->toBe( '' );
} );

test( 'generate font face detects font formats', function (): void {
	$manager = new TypographyPresetsManager();

	$manager->registerCustomFont( 'A', '/a.woff2' );
	$manager->registerCustomFont( 'B', '/b.woff' );
	$manager->registerCustomFont( 'C', '/c.ttf' );
	$manager->registerCustomFont( 'D', '/d.otf' );

	$css = $manager->generateFontFaceDeclarations();

	expect( $css )->toContain( "format('woff2')" )
		->and( $css )->toContain( "format('woff')" )
		->and( $css )->toContain( "format('truetype')" )
		->and( $css )->toContain( "format('opentype')" );
} );

test( 'generate css properties returns valid css', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setFontFamilies( [ 'heading' => 'Arial' ] );
	$manager->setElements( [
		'h1' => [
			'fontSize'   => '2rem',
			'fontWeight' => '700',
			'lineHeight' => '1.2',
		],
	] );

	$css = $manager->generateCssProperties();

	expect( $css )->toContain( '--ve-font-heading: Arial;' )
		->and( $css )->toContain( '--ve-text-h1-font-size: 2rem;' )
		->and( $css )->toContain( '--ve-text-h1-font-weight: 700;' )
		->and( $css )->toContain( '--ve-text-h1-line-height: 1.2;' );
} );

test( 'generate css block wraps properties in root selector', function (): void {
	$manager = new TypographyPresetsManager();

	$css = $manager->generateCssBlock();

	expect( $css )->toStartWith( ':root {' )
		->and( $css )->toEndWith( '}' )
		->and( $css )->toContain( '--ve-font-heading:' );
} );

test( 'generate css block returns empty for empty manager', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setFontFamilies( [] );
	$manager->setElements( [] );

	expect( $manager->generateCssBlock() )->toBe( '' );
} );

test( 'to store format returns correct structure', function (): void {
	$manager = new TypographyPresetsManager();
	$store   = $manager->toStoreFormat();

	expect( $store )->toHaveKeys( [ 'fontFamilies', 'elements' ] )
		->and( $store['fontFamilies'] )->toHaveKeys( [ 'heading', 'body', 'mono' ] )
		->and( $store['elements'] )->toHaveKeys( [ 'h1', 'h2', 'body' ] );
} );

test( 'from store format restores data', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->fromStoreFormat( [
		'fontFamilies' => [ 'heading' => 'Custom Font' ],
		'elements'     => [
			'h1' => [
				'fontSize'   => '4rem',
				'fontWeight' => '900',
				'lineHeight' => '1.0',
			],
		],
	] );

	expect( $manager->getFontFamily( 'heading' ) )->toBe( 'Custom Font' )
		->and( $manager->getElement( 'h1' )['fontSize'] )->toBe( '4rem' );
} );

test( 'from store format ignores invalid data', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->fromStoreFormat( [
		'fontFamilies' => 'not-an-array',
		'elements'     => 'not-an-array',
	] );

	expect( $manager->getFontFamilies() )->toBe( TypographyPresetsManager::DEFAULT_FONT_FAMILIES )
		->and( $manager->getElements() )->toBe( TypographyPresetsManager::DEFAULT_ELEMENTS );
} );

test( 'set element sanitizes font weight', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setElement( 'h1', [
		'fontSize'   => '2rem',
		'fontWeight' => 'invalid',
		'lineHeight' => '1.2',
	] );

	expect( $manager->getElement( 'h1' )['fontWeight'] )->toBe( '400' );
} );

test( 'set element sanitizes font style', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setElement( 'blockquote', [
		'fontSize'   => '1rem',
		'fontWeight' => '400',
		'lineHeight' => '1.5',
		'fontStyle'  => 'invalid',
	] );

	expect( $manager->getElement( 'blockquote' )['fontStyle'] )->toBe( 'normal' );
} );

test( 'set element accepts valid font style', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setElement( 'blockquote', [
		'fontSize'   => '1rem',
		'fontWeight' => '400',
		'lineHeight' => '1.5',
		'fontStyle'  => 'italic',
	] );

	expect( $manager->getElement( 'blockquote' )['fontStyle'] )->toBe( 'italic' );
} );

test( 'typography presets manager is resolved from container', function (): void {
	$manager = app( 'visual-editor.typography-presets' );

	expect( $manager )->toBeInstanceOf( TypographyPresetsManager::class );
} );

test( 'typography presets manager singleton returns same instance', function (): void {
	$first  = app( 'visual-editor.typography-presets' );
	$second = app( 'visual-editor.typography-presets' );

	expect( $first )->toBe( $second );
} );

test( 'typography presets manager class binding resolves to singleton', function (): void {
	$fromString = app( 'visual-editor.typography-presets' );
	$fromClass  = app( TypographyPresetsManager::class );

	expect( $fromString )->toBe( $fromClass );
} );

test( 'css properties use kebab-case for property names', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->setFontFamilies( [] );
	$manager->setElements( [
		'h1' => [
			'fontSize'      => '2rem',
			'fontWeight'    => '700',
			'lineHeight'    => '1.2',
			'letterSpacing' => '-0.02em',
		],
	] );

	$css = $manager->generateCssProperties();

	expect( $css )->toContain( '--ve-text-h1-font-size:' )
		->and( $css )->toContain( '--ve-text-h1-font-weight:' )
		->and( $css )->toContain( '--ve-text-h1-line-height:' )
		->and( $css )->toContain( '--ve-text-h1-letter-spacing:' )
		->and( $css )->not->toContain( 'fontSize' )
		->and( $css )->not->toContain( 'fontWeight' );
} );

test( 'allowed elements constant is complete', function (): void {
	expect( TypographyPresetsManager::ALLOWED_ELEMENTS )->toContain( 'h1' )
		->and( TypographyPresetsManager::ALLOWED_ELEMENTS )->toContain( 'h6' )
		->and( TypographyPresetsManager::ALLOWED_ELEMENTS )->toContain( 'body' )
		->and( TypographyPresetsManager::ALLOWED_ELEMENTS )->toContain( 'code' )
		->and( TypographyPresetsManager::ALLOWED_ELEMENTS )->toContain( 'blockquote' )
		->and( TypographyPresetsManager::ALLOWED_ELEMENTS )->toContain( 'caption' )
		->and( TypographyPresetsManager::ALLOWED_ELEMENTS )->toContain( 'small' );
} );

// ===== Font Collection Registry Tests =====

test( 'font collection initializes with default system fonts', function (): void {
	$manager = new TypographyPresetsManager();
	$fonts   = $manager->getAvailableFonts();

	expect( $fonts )->toHaveKey( 'system-ui' )
		->and( $fonts )->toHaveKey( 'arial' )
		->and( $fonts )->toHaveKey( 'georgia' )
		->and( $fonts )->toHaveKey( 'monospace' )
		->and( $fonts )->toHaveCount( count( TypographyPresetsManager::DEFAULT_SYSTEM_FONTS ) );
} );

test( 'default system fonts has 12 entries', function (): void {
	expect( TypographyPresetsManager::DEFAULT_SYSTEM_FONTS )->toHaveCount( 12 );
} );

test( 'each default system font has required keys', function (): void {
	foreach ( TypographyPresetsManager::DEFAULT_SYSTEM_FONTS as $slug => $font ) {
		expect( $font )->toHaveKeys( [ 'name', 'family', 'category', 'source' ] )
			->and( $font['source'] )->toBe( 'system' );
	}
} );

test( 'register font adds to collection', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'brand', 'Brand Font', '"Brand Font", sans-serif' );

	expect( $manager->hasFont( 'brand' ) )->toBeTrue()
		->and( $manager->getFont( 'brand' )['name'] )->toBe( 'Brand Font' )
		->and( $manager->getFont( 'brand' )['family'] )->toBe( '"Brand Font", sans-serif' )
		->and( $manager->getFont( 'brand' )['category'] )->toBe( 'all' )
		->and( $manager->getFont( 'brand' )['source'] )->toBe( 'custom' );
} );

test( 'register font with heading category', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'display', 'Display Font', '"Display Font"', 'heading' );

	expect( $manager->getFont( 'display' )['category'] )->toBe( 'heading' );
} );

test( 'register font normalizes invalid category to all', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'test', 'Test', 'Test', 'invalid' );

	expect( $manager->getFont( 'test' )['category'] )->toBe( 'all' );
} );

test( 'unregister font removes from collection', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'test', 'Test', 'Test' );
	$manager->unregisterFont( 'test' );

	expect( $manager->hasFont( 'test' ) )->toBeFalse();
} );

test( 'has font returns false for missing slug', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->hasFont( 'nonexistent' ) )->toBeFalse();
} );

test( 'get font returns null for missing slug', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->getFont( 'nonexistent' ) )->toBeNull();
} );

test( 'get available fonts returns all fonts when no category', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'heading-only', 'Heading Only', 'Heading', 'heading' );
	$manager->registerFont( 'body-only', 'Body Only', 'Body', 'body' );

	$all = $manager->getAvailableFonts();

	expect( $all )->toHaveKey( 'heading-only' )
		->and( $all )->toHaveKey( 'body-only' )
		->and( $all )->toHaveKey( 'arial' );
} );

test( 'get available fonts filters by heading category', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'heading-only', 'Heading Only', 'Heading', 'heading' );
	$manager->registerFont( 'body-only', 'Body Only', 'Body', 'body' );

	$heading = $manager->getAvailableFonts( 'heading' );

	expect( $heading )->toHaveKey( 'heading-only' )
		->and( $heading )->not->toHaveKey( 'body-only' )
		->and( $heading )->toHaveKey( 'arial' );
} );

test( 'get available fonts filters by body category', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'heading-only', 'Heading Only', 'Heading', 'heading' );
	$manager->registerFont( 'body-only', 'Body Only', 'Body', 'body' );

	$body = $manager->getAvailableFonts( 'body' );

	expect( $body )->toHaveKey( 'body-only' )
		->and( $body )->not->toHaveKey( 'heading-only' )
		->and( $body )->toHaveKey( 'arial' );
} );

test( 'get font options returns sorted name => family map', function (): void {
	$manager = new TypographyPresetsManager();
	$options = $manager->getFontOptions();

	expect( $options )->toBeArray()
		->and( $options )->not->toBeEmpty();

	$values = array_values( $options );
	$sorted = $values;
	sort( $sorted );

	expect( $values )->toBe( $sorted );
} );

test( 'get font options respects category filter', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'body-only', 'Body Only', '"Body Only"', 'body' );

	$headingOptions = $manager->getFontOptions( 'heading' );

	expect( $headingOptions )->not->toHaveKey( '"Body Only"' );
} );

test( 'register google font adds to collection', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerGoogleFont( 'Roboto', [ '400', '700' ] );

	expect( $manager->hasFont( 'roboto' ) )->toBeTrue()
		->and( $manager->getFont( 'roboto' )['name'] )->toBe( 'Roboto' )
		->and( $manager->getFont( 'roboto' )['source'] )->toBe( 'google' );
} );

test( 'register google font with category adds to collection with category', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerGoogleFont( 'Playfair Display', [ '400' ], [ 'normal' ], 'heading' );

	expect( $manager->getFont( 'playfair-display' )['category'] )->toBe( 'heading' );
} );

test( 'register custom font adds to collection', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerCustomFont( 'MyBrand', '/fonts/mybrand.woff2' );

	expect( $manager->hasFont( 'mybrand' ) )->toBeTrue()
		->and( $manager->getFont( 'mybrand' )['source'] )->toBe( 'custom' );
} );

test( 'register custom font with category adds to collection with category', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerCustomFont( 'MyBrand', '/fonts/mybrand.woff2', '400', 'normal', 'body' );

	expect( $manager->getFont( 'mybrand' )['category'] )->toBe( 'body' );
} );

test( 'register google font does not overwrite existing collection entry', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'roboto', 'Custom Roboto', '"Roboto Custom"', 'heading', 'custom' );
	$manager->registerGoogleFont( 'Roboto', [ '400' ] );

	expect( $manager->getFont( 'roboto' )['name'] )->toBe( 'Custom Roboto' )
		->and( $manager->getFont( 'roboto' )['source'] )->toBe( 'custom' );
} );

test( 'reset to defaults clears custom font collection entries', function (): void {
	$manager = new TypographyPresetsManager();
	$manager->registerFont( 'custom', 'Custom', 'Custom' );
	$manager->resetToDefaults();

	expect( $manager->hasFont( 'custom' ) )->toBeFalse()
		->and( $manager->hasFont( 'arial' ) )->toBeTrue();
} );

test( 'constructor accepts fonts via config', function (): void {
	$manager = new TypographyPresetsManager( [
		'fonts' => [
			'brand' => [
				'name'     => 'Brand Font',
				'family'   => '"Brand Font", serif',
				'category' => 'heading',
				'source'   => 'custom',
			],
		],
	] );

	expect( $manager->hasFont( 'brand' ) )->toBeTrue()
		->and( $manager->getFont( 'brand' )['name'] )->toBe( 'Brand Font' )
		->and( $manager->getFont( 'brand' )['category'] )->toBe( 'heading' )
		->and( $manager->hasFont( 'arial' ) )->toBeTrue();
} );

test( 'get default system fonts returns the constant', function (): void {
	$manager = new TypographyPresetsManager();

	expect( $manager->getDefaultSystemFonts() )->toBe( TypographyPresetsManager::DEFAULT_SYSTEM_FONTS );
} );
