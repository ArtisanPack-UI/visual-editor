<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DevicePreviewButtons;

test( 'device preview buttons can be instantiated with defaults', function (): void {
	$component = new DevicePreviewButtons();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->id )->toBeNull();
	expect( $component->label )->toBeNull();
} );

test( 'device preview buttons accepts custom props', function (): void {
	$component = new DevicePreviewButtons(
		id: 'preview',
		label: 'Device switcher',
	);

	expect( $component->uuid )->toContain( 'preview' );
	expect( $component->label )->toBe( 'Device switcher' );
} );

test( 'device preview buttons renders', function (): void {
	$view = $this->blade( '<x-ve-device-preview-buttons />' );
	expect( $view )->not->toBeNull();
} );

test( 'device preview buttons renders with radiogroup role', function (): void {
	$this->blade( '<x-ve-device-preview-buttons />' )
		->assertSee( 'role="radiogroup"', false );
} );

test( 'device preview buttons renders desktop button', function (): void {
	$this->blade( '<x-ve-device-preview-buttons />' )
		->assertSee( 'Desktop' );
} );

test( 'device preview buttons renders tablet button', function (): void {
	$this->blade( '<x-ve-device-preview-buttons />' )
		->assertSee( 'Tablet' );
} );

test( 'device preview buttons renders mobile button', function (): void {
	$this->blade( '<x-ve-device-preview-buttons />' )
		->assertSee( 'Mobile' );
} );
