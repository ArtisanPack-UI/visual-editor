<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\GlobalStylesManager;

beforeEach( function (): void {
	$this->manager = new GlobalStylesManager();
} );

test( 'it has default styles', function (): void {
	$styles = $this->manager->getStyles();

	expect( $styles )->toBeArray()
		->and( $styles )->toHaveKey( 'colors' )
		->and( $styles )->toHaveKey( 'typography' )
		->and( $styles )->toHaveKey( 'spacing' );
} );

test( 'it can get a style value using dot notation', function (): void {
	$primaryColor = $this->manager->get( 'colors.primary' );

	expect( $primaryColor )->toBeString()
		->and( $primaryColor )->toStartWith( '#' );
} );

test( 'it returns default value for non-existent style', function (): void {
	$value = $this->manager->get( 'non.existent', 'default-value' );

	expect( $value )->toBe( 'default-value' );
} );

test( 'it can set a style value using dot notation', function (): void {
	$this->manager->set( 'colors.primary', '#ff0000' );

	expect( $this->manager->get( 'colors.primary' ) )->toBe( '#ff0000' );
} );

test( 'it can set nested style values', function (): void {
	$this->manager->set( 'custom.nested.value', 'test' );

	expect( $this->manager->get( 'custom.nested.value' ) )->toBe( 'test' );
} );

test( 'it can merge styles', function (): void {
	$this->manager->merge( [
		'colors' => [
			'brand' => '#123456',
		],
		'custom' => [
			'value' => 'test',
		],
	] );

	expect( $this->manager->get( 'colors.brand' ) )->toBe( '#123456' )
		->and( $this->manager->get( 'custom.value' ) )->toBe( 'test' )
		->and( $this->manager->get( 'colors.primary' ) )->not->toBeNull();
} );

test( 'it can reset styles to defaults', function (): void {
	$originalPrimary = $this->manager->get( 'colors.primary' );

	$this->manager->set( 'colors.primary', '#ff0000' );
	expect( $this->manager->get( 'colors.primary' ) )->toBe( '#ff0000' );

	$this->manager->reset();
	expect( $this->manager->get( 'colors.primary' ) )->toBe( $originalPrimary );
} );

test( 'it generates CSS custom properties', function (): void {
	$css = $this->manager->generateCssCustomProperties();

	expect( $css )->toBeString()
		->and( $css )->toContain( '--ve-color-primary' )
		->and( $css )->toContain( '--ve-font-family-heading' )
		->and( $css )->toContain( '--ve-section-padding-y' );
} );

test( 'it generates accessible button text color properties', function (): void {
	$css = $this->manager->generateCssCustomProperties();

	expect( $css )->toBeString()
		->and( $css )->toContain( '--ve-btn-primary-text' )
		->and( $css )->toContain( '--ve-btn-secondary-text' );
} );

test( 'it uses accessible text color for light backgrounds', function (): void {
	$this->manager->set( 'colors.primary', '#ffffff' );

	$css = $this->manager->generateCssCustomProperties();

	expect( $css )->toContain( '--ve-btn-primary-text: #000000' );
} );

test( 'it uses accessible text color for dark backgrounds', function (): void {
	$this->manager->set( 'colors.primary', '#000000' );

	$css = $this->manager->generateCssCustomProperties();

	expect( $css )->toContain( '--ve-btn-primary-text: #ffffff' );
} );

test( 'it generates complete CSS', function (): void {
	$css = $this->manager->generateCss();

	expect( $css )->toBeString()
		->and( $css )->toContain( ':root' )
		->and( $css )->toContain( '--ve-color-primary' );
} );

test( 'it can export to Tailwind config', function (): void {
	$config = $this->manager->toTailwindConfig();

	expect( $config )->toBeArray()
		->and( $config )->toHaveKey( 'theme' )
		->and( $config['theme'] )->toHaveKey( 'extend' )
		->and( $config['theme']['extend'] )->toHaveKey( 'colors' )
		->and( $config['theme']['extend'] )->toHaveKey( 'fontFamily' );
} );

test( 'set returns self for chaining', function (): void {
	$result = $this->manager->set( 'colors.test', '#000000' );

	expect( $result )->toBeInstanceOf( GlobalStylesManager::class );
} );

test( 'merge returns self for chaining', function (): void {
	$result = $this->manager->merge( [ 'custom' => 'value' ] );

	expect( $result )->toBeInstanceOf( GlobalStylesManager::class );
} );

test( 'reset returns self for chaining', function (): void {
	$result = $this->manager->reset();

	expect( $result )->toBeInstanceOf( GlobalStylesManager::class );
} );
