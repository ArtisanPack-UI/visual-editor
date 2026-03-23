<?php

/**
 * ColorPaletteEditor Component Unit Tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Tests\Unit\Components
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ColorPaletteEditor;

test( 'color palette editor can be instantiated', function (): void {
	$component = new ColorPaletteEditor();

	expect( $component->uuid )->toStartWith( 've-' )
		->and( $component->paletteEntries )->toBeArray()
		->and( $component->paletteEntries )->not->toBeEmpty();
} );

test( 'color palette editor loads default palette entries', function (): void {
	$component = new ColorPaletteEditor();

	expect( $component->paletteEntries )->toHaveCount( 12 );

	foreach ( $component->paletteEntries as $entry ) {
		expect( $entry )->toHaveKeys( [ 'name', 'slug', 'color' ] );
	}
} );

test( 'color palette editor accepts custom palette', function (): void {
	$customPalette = [
		[ 'name' => 'Brand', 'slug' => 'brand', 'color' => '#ff0000' ],
		[ 'name' => 'Accent', 'slug' => 'accent', 'color' => '#00ff00' ],
	];

	$component = new ColorPaletteEditor( palette: $customPalette );

	expect( $component->paletteEntries )->toHaveCount( 2 );
} );

test( 'color palette editor renders', function (): void {
	$view = $this->blade( '<x-ve-color-palette-editor />' );

	expect( $view )->not->toBeNull();
} );

test( 'color palette editor renders palette title', function (): void {
	$this->blade( '<x-ve-color-palette-editor />' )
		->assertSee( 'Color Palette' );
} );

test( 'color palette editor renders reset button', function (): void {
	$this->blade( '<x-ve-color-palette-editor />' )
		->assertSee( 'Reset to default' );
} );

test( 'color palette editor renders add color button', function (): void {
	$this->blade( '<x-ve-color-palette-editor />' )
		->assertSee( 'Add Color' );
} );

test( 'color palette editor renders css preview toggle', function (): void {
	$this->blade( '<x-ve-color-palette-editor />' )
		->assertSee( 'CSS' );
} );

test( 'color palette editor accepts base values for override mode', function (): void {
	$baseValues = [
		[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ],
		[ 'name' => 'Secondary', 'slug' => 'secondary', 'color' => '#6b7280' ],
	];

	$component = new ColorPaletteEditor( baseValues: $baseValues );

	expect( $component->baseValues )->toHaveCount( 2 )
		->and( $component->baseValues[0]['slug'] )->toBe( 'primary' );
} );

test( 'color palette editor defaults to null base values', function (): void {
	$component = new ColorPaletteEditor();

	expect( $component->baseValues )->toBeNull();
} );

test( 'color palette editor renders override mode indicators', function (): void {
	$baseValues = [
		[ 'name' => 'Primary', 'slug' => 'primary', 'color' => '#3b82f6' ],
	];

	$this->blade( '<x-ve-color-palette-editor :base-values="$baseValues" />', [ 'baseValues' => $baseValues ] )
		->assertSee( 'overrideMode', false );
} );

test( 'color palette editor renders getStore dual detection', function (): void {
	$this->blade( '<x-ve-color-palette-editor />' )
		->assertSee( '_getStore()', false );
} );
