<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ColorPicker;

test( 'color picker can be instantiated with defaults', function (): void {
	$component = new ColorPicker();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->value )->toBe( '#000000' );
	expect( $component->showAlpha )->toBeFalse();
	expect( $component->showFormatToggle )->toBeTrue();
	expect( $component->showCopyButton )->toBeTrue();
	expect( $component->width )->toBe( '100%' );
} );

test( 'color picker accepts custom props', function (): void {
	$component = new ColorPicker(
		id: 'my-picker',
		value: '#3b82f6',
		showAlpha: true,
		showFormatToggle: false,
		showCopyButton: false,
		width: '300px',
	);

	expect( $component->uuid )->toContain( 'my-picker' );
	expect( $component->value )->toBe( '#3b82f6' );
	expect( $component->showAlpha )->toBeTrue();
	expect( $component->showFormatToggle )->toBeFalse();
	expect( $component->showCopyButton )->toBeFalse();
	expect( $component->width )->toBe( '300px' );
} );

test( 'color picker renders', function (): void {
	$view = $this->blade( '<x-ve-color-picker />' );
	expect( $view )->not->toBeNull();
} );

test( 'color picker renders canvas area', function (): void {
	$this->blade( '<x-ve-color-picker />' )
		->assertSee( 'Color saturation and brightness picker', false );
} );

test( 'color picker renders hue slider', function (): void {
	$this->blade( '<x-ve-color-picker />' )
		->assertSee( 'Hue', false );
} );

test( 'color picker hides alpha slider by default', function (): void {
	$this->blade( '<x-ve-color-picker />' )
		->assertDontSee( 'Opacity', false );
} );

test( 'color picker shows alpha slider when enabled', function (): void {
	$this->blade( '<x-ve-color-picker :showAlpha="true" />' )
		->assertSee( 'Opacity', false );
} );

test( 'color picker renders format toggle by default', function (): void {
	$this->blade( '<x-ve-color-picker />' )
		->assertSee( 'Color format', false );
} );

test( 'color picker hides format toggle when disabled', function (): void {
	$this->blade( '<x-ve-color-picker :showFormatToggle="false" />' )
		->assertDontSee( 'Color format', false );
} );

test( 'color picker renders copy button by default', function (): void {
	$this->blade( '<x-ve-color-picker />' )
		->assertSee( 'Copy color value', false );
} );

test( 'color picker hides copy button when disabled', function (): void {
	$this->blade( '<x-ve-color-picker :showCopyButton="false" />' )
		->assertDontSee( 'Copy color value', false );
} );

test( 'color picker renders with hint', function (): void {
	$this->blade( '<x-ve-color-picker hint="Pick a color" />' )
		->assertSee( 'Pick a color' );
} );
