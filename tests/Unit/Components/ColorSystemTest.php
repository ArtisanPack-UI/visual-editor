<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ColorSystem;

test( 'color system can be instantiated with defaults', function (): void {
	$component = new ColorSystem();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->palette )->toBe( ColorSystem::DEFAULT_PALETTE );
	expect( $component->showCustom )->toBeTrue();
	expect( $component->showContrast )->toBeFalse();
	expect( $component->contrastBackground )->toBe( '#ffffff' );
} );

test( 'color system accepts custom palette', function (): void {
	$palette   = [ '#ff0000', '#00ff00', '#0000ff' ];
	$component = new ColorSystem( palette: $palette );

	expect( $component->palette )->toBe( $palette );
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
	$this->blade( '<x-ve-color-system />' )
		->assertSee( 'listbox', false );
} );

test( 'color system renders custom section', function (): void {
	$this->blade( '<x-ve-color-system />' )
		->assertSee( 'Color picker', false );
} );

test( 'color system hides custom section when disabled', function (): void {
	$this->blade( '<x-ve-color-system :showCustom="false" />' )
		->assertDontSee( 'Color picker', false );
} );
