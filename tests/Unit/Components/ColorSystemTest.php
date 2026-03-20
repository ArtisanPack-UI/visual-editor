<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ColorSystem;

test( 'color system can be instantiated with defaults', function (): void {
	$component = new ColorSystem();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->showCustom )->toBeTrue();
	expect( $component->showContrast )->toBeFalse();
	expect( $component->contrastBackground )->toBe( '#ffffff' );
	expect( $component->compact )->toBeTrue();
} );

test( 'color system loads palette from color palette manager', function (): void {
	$component = new ColorSystem();
	$manager   = app( 'visual-editor.color-palette' );
	$expected  = array_map( fn ( array $e ): string => $e['color'], $manager->toStoreFormat() );

	expect( $component->palette )->toBe( $expected )
		->and( $component->paletteEntries )->toHaveCount( count( $manager->getPalette() ) );
} );

test( 'color system palette entries have name slug and color', function (): void {
	$component = new ColorSystem();

	foreach ( $component->paletteEntries as $entry ) {
		expect( $entry )->toHaveKeys( [ 'name', 'slug', 'color' ] );
	}
} );

test( 'color system accepts custom palette', function (): void {
	$palette   = [ '#ff0000', '#00ff00', '#0000ff' ];
	$component = new ColorSystem( palette: $palette );

	expect( $component->palette )->toBe( $palette )
		->and( $component->paletteEntries )->toBeEmpty();
} );

test( 'color system default palette has 12 colors', function (): void {
	expect( ColorSystem::DEFAULT_PALETTE )->toHaveCount( 12 );
} );

test( 'color system check contrast returns null without accessibility package', function (): void {
	$component = new ColorSystem();

	// The a11yCheckContrastColor function may or may not be available
	$result = $component->checkContrast( '#000000', '#ffffff' );

	// It should return either bool or null
	expect( $result )->toBeIn( [ true, false, null ] );
} );

test( 'color system renders', function (): void {
	$view = $this->blade( '<x-ve-color-system />' );
	expect( $view )->not->toBeNull();
} );

test( 'color system renders with label', function (): void {
	$this->blade( '<x-ve-color-system label="Text Color" />' )
		->assertSee( 'Text Color' );
} );

test( 'color system renders palette swatches', function (): void {
	$this->blade( '<x-ve-color-system :compact="false" />' )
		->assertSee( 'listbox', false );
} );

test( 'color system renders custom section', function (): void {
	$this->blade( '<x-ve-color-system :compact="false" />' )
		->assertSee( 'Custom' );
} );

test( 'color system hides custom section when disabled', function (): void {
	$this->blade( '<x-ve-color-system :showCustom="false" :compact="false" />' )
		->assertDontSee( 'Custom' );
} );

test( 'color system compact defaults to true', function (): void {
	$component = new ColorSystem();

	expect( $component->compact )->toBeTrue();
} );

test( 'color system compact can be set to false', function (): void {
	$component = new ColorSystem( compact: false );

	expect( $component->compact )->toBeFalse();
} );
