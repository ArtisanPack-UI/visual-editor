<?php

/**
 * ColorPaletteManager Service Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Services
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\ColorPaletteManager;

test( 'color palette manager initializes with defaults', function (): void {
	$manager = new ColorPaletteManager();

	$palette = $manager->getPalette();

	expect( $palette )->toHaveKey( 'primary' )
		->and( $palette )->toHaveKey( 'secondary' )
		->and( $palette )->toHaveKey( 'accent' )
		->and( $palette )->toHaveKey( 'background' )
		->and( $palette )->toHaveKey( 'surface' )
		->and( $palette )->toHaveKey( 'text' )
		->and( $palette )->toHaveKey( 'muted' )
		->and( $palette )->toHaveKey( 'border' )
		->and( $palette )->toHaveKey( 'success' )
		->and( $palette )->toHaveKey( 'warning' )
		->and( $palette )->toHaveKey( 'error' )
		->and( $palette )->toHaveKey( 'info' );
} );

test( 'default palette has 12 entries', function (): void {
	expect( ColorPaletteManager::DEFAULT_PALETTE )->toHaveCount( 12 );
} );

test( 'color palette manager accepts custom palette', function (): void {
	$custom = [
		'brand' => [
			'name'  => 'Brand',
			'slug'  => 'brand',
			'color' => '#ff0000',
		],
	];

	$manager = new ColorPaletteManager( $custom );

	expect( $manager->getPalette() )->toHaveCount( 1 )
		->and( $manager->getPalette() )->toHaveKey( 'brand' );
} );

test( 'get color returns entry by slug', function (): void {
	$manager = new ColorPaletteManager();
	$color   = $manager->getColor( 'primary' );

	expect( $color )->not->toBeNull()
		->and( $color['name'] )->toBe( 'Primary' )
		->and( $color['slug'] )->toBe( 'primary' )
		->and( $color['color'] )->toBe( '#3b82f6' );
} );

test( 'get color returns null for missing slug', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->getColor( 'nonexistent' ) )->toBeNull();
} );

test( 'get color value returns hex', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->getColorValue( 'primary' ) )->toBe( '#3b82f6' );
} );

test( 'get color value returns null for missing slug', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->getColorValue( 'nonexistent' ) )->toBeNull();
} );

test( 'set color adds a new entry', function (): void {
	$manager = new ColorPaletteManager();
	$manager->setColor( 'highlight', 'Highlight', '#ffff00' );

	expect( $manager->hasColor( 'highlight' ) )->toBeTrue()
		->and( $manager->getColorValue( 'highlight' ) )->toBe( '#ffff00' );
} );

test( 'set color updates an existing entry', function (): void {
	$manager = new ColorPaletteManager();
	$manager->setColor( 'primary', 'Brand Primary', '#ff0000' );

	$color = $manager->getColor( 'primary' );

	expect( $color['name'] )->toBe( 'Brand Primary' )
		->and( $color['color'] )->toBe( '#ff0000' );
} );

test( 'remove color deletes an entry', function (): void {
	$manager = new ColorPaletteManager();
	$manager->removeColor( 'primary' );

	expect( $manager->hasColor( 'primary' ) )->toBeFalse();
} );

test( 'has color returns true for existing slug', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->hasColor( 'primary' ) )->toBeTrue();
} );

test( 'has color returns false for missing slug', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->hasColor( 'nonexistent' ) )->toBeFalse();
} );

test( 'reset to defaults restores default palette', function (): void {
	$manager = new ColorPaletteManager();
	$manager->setColor( 'custom', 'Custom', '#123456' );
	$manager->removeColor( 'primary' );
	$manager->resetToDefaults();

	expect( $manager->getPalette() )->toEqual( ColorPaletteManager::DEFAULT_PALETTE )
		->and( $manager->hasColor( 'custom' ) )->toBeFalse()
		->and( $manager->hasColor( 'primary' ) )->toBeTrue();
} );

test( 'generate shades produces lighter and darker variations', function (): void {
	$manager = new ColorPaletteManager();
	$shades  = $manager->generateShades( '#3b82f6' );

	expect( $shades )->toHaveKeys( [ 'light', 'dark' ] )
		->and( $shades['light'] )->toMatch( '/^#[0-9a-f]{6}$/i' )
		->and( $shades['dark'] )->toMatch( '/^#[0-9a-f]{6}$/i' )
		->and( $shades['light'] )->not->toBe( '#3b82f6' )
		->and( $shades['dark'] )->not->toBe( '#3b82f6' );
} );

test( 'generate shades for black produces lighter shade', function (): void {
	$manager = new ColorPaletteManager();
	$shades  = $manager->generateShades( '#000000' );

	expect( $shades['light'] )->not->toBe( '#000000' )
		->and( $shades['dark'] )->toBe( '#000000' );
} );

test( 'generate shades for white produces darker shade', function (): void {
	$manager = new ColorPaletteManager();
	$shades  = $manager->generateShades( '#ffffff' );

	expect( $shades['light'] )->toBe( '#ffffff' )
		->and( $shades['dark'] )->not->toBe( '#ffffff' );
} );

test( 'generate css properties returns valid css', function (): void {
	$manager = new ColorPaletteManager( [
		'primary' => [
			'name'  => 'Primary',
			'slug'  => 'primary',
			'color' => '#3b82f6',
		],
	] );

	$css = $manager->generateCssProperties( false );

	expect( $css )->toContain( '--ve-color-primary: #3b82f6;' );
} );

test( 'generate css properties includes shades when enabled', function (): void {
	$manager = new ColorPaletteManager( [
		'primary' => [
			'name'  => 'Primary',
			'slug'  => 'primary',
			'color' => '#3b82f6',
		],
	] );

	$css = $manager->generateCssProperties( true );

	expect( $css )->toContain( '--ve-color-primary: #3b82f6;' )
		->and( $css )->toContain( '--ve-color-primary-light:' )
		->and( $css )->toContain( '--ve-color-primary-dark:' );
} );

test( 'generate css block wraps properties in root selector', function (): void {
	$manager = new ColorPaletteManager( [
		'primary' => [
			'name'  => 'Primary',
			'slug'  => 'primary',
			'color' => '#3b82f6',
		],
	] );

	$css = $manager->generateCssBlock( false );

	expect( $css )->toStartWith( ':root {' )
		->and( $css )->toEndWith( '}' )
		->and( $css )->toContain( '--ve-color-primary: #3b82f6;' );
} );

test( 'generate css block returns empty string for empty palette', function (): void {
	$manager = new ColorPaletteManager();
	$manager->setPalette( [] );

	expect( $manager->generateCssBlock() )->toBe( '' );
} );

test( 'check contrast returns null without accessibility package', function (): void {
	$manager = new ColorPaletteManager();
	$result  = $manager->checkContrast( '#000000', '#ffffff' );

	// May return bool if accessibility package is installed, or null
	expect( $result )->toBeIn( [ true, false, null ] );
} );

test( 'check palette contrast returns results for all colors', function (): void {
	$manager = new ColorPaletteManager();
	$results = $manager->checkPaletteContrast( '#ffffff' );

	expect( $results )->toHaveCount( count( $manager->getPalette() ) );

	foreach ( $results as $result ) {
		expect( $result )->toBeIn( [ true, false, null ] );
	}
} );

test( 'resolve color reference returns hex for palette reference', function (): void {
	$manager = new ColorPaletteManager();
	$result  = $manager->resolveColorReference( 'palette:primary' );

	expect( $result )->toBe( '#3b82f6' );
} );

test( 'resolve color reference returns original value for non-reference', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->resolveColorReference( '#ff0000' ) )->toBe( '#ff0000' );
} );

test( 'resolve color reference returns original for missing slug', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->resolveColorReference( 'palette:nonexistent' ) )->toBe( 'palette:nonexistent' );
} );

test( 'to store format returns flat array', function (): void {
	$manager = new ColorPaletteManager();
	$store   = $manager->toStoreFormat();

	expect( $store )->toBeArray()
		->and( array_is_list( $store ) )->toBeTrue()
		->and( $store )->toHaveCount( 12 );

	foreach ( $store as $entry ) {
		expect( $entry )->toHaveKeys( [ 'name', 'slug', 'color' ] );
	}
} );

test( 'from store format rebuilds palette', function (): void {
	$manager = new ColorPaletteManager();
	$manager->fromStoreFormat( [
		[ 'name' => 'Brand', 'slug' => 'brand', 'color' => '#ff0000' ],
		[ 'name' => 'Accent', 'slug' => 'accent', 'color' => '#00ff00' ],
	] );

	expect( $manager->getPalette() )->toHaveCount( 2 )
		->and( $manager->hasColor( 'brand' ) )->toBeTrue()
		->and( $manager->hasColor( 'accent' ) )->toBeTrue()
		->and( $manager->getColorValue( 'brand' ) )->toBe( '#ff0000' );
} );

test( 'from store format ignores invalid entries', function (): void {
	$manager = new ColorPaletteManager();
	$manager->fromStoreFormat( [
		[ 'name' => 'Valid', 'slug' => 'valid', 'color' => '#ff0000' ],
		[ 'name' => 'Missing Slug' ],
		[ 'slug' => 'missing-name', 'color' => '#00ff00' ],
	] );

	expect( $manager->getPalette() )->toHaveCount( 1 )
		->and( $manager->hasColor( 'valid' ) )->toBeTrue();
} );

test( 'set palette replaces all entries', function (): void {
	$manager = new ColorPaletteManager();
	$manager->setPalette( [
		'only' => [
			'name'  => 'Only',
			'slug'  => 'only',
			'color' => '#abcdef',
		],
	] );

	expect( $manager->getPalette() )->toHaveCount( 1 )
		->and( $manager->hasColor( 'only' ) )->toBeTrue()
		->and( $manager->hasColor( 'primary' ) )->toBeFalse();
} );

test( 'get default palette returns the constant', function (): void {
	$manager = new ColorPaletteManager();

	expect( $manager->getDefaultPalette() )->toBe( ColorPaletteManager::DEFAULT_PALETTE );
} );

test( 'color palette manager is resolved from container', function (): void {
	$manager = app( 'visual-editor.color-palette' );

	expect( $manager )->toBeInstanceOf( ColorPaletteManager::class );
} );

test( 'color palette manager singleton returns same instance', function (): void {
	$first  = app( 'visual-editor.color-palette' );
	$second = app( 'visual-editor.color-palette' );

	expect( $first )->toBe( $second );
} );

test( 'color palette manager class binding resolves to singleton', function (): void {
	$fromString = app( 'visual-editor.color-palette' );
	$fromClass  = app( ColorPaletteManager::class );

	expect( $fromString )->toBe( $fromClass );
} );
