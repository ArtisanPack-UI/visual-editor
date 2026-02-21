<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\DevicePreview;

test( 'device preview can be instantiated with defaults', function (): void {
	$component = new DevicePreview();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->device )->toBe( 'desktop' );
	expect( $component->tabletWidth )->toBe( 768 );
	expect( $component->mobileWidth )->toBe( 375 );
	expect( $component->showZoomControls )->toBeTrue();
	expect( $component->defaultZoom )->toBe( 100 );
	expect( $component->minZoom )->toBe( 50 );
	expect( $component->maxZoom )->toBe( 150 );
} );

test( 'device preview accepts custom props', function (): void {
	$component = new DevicePreview(
		id: 'preview',
		device: 'tablet',
		tabletWidth: 800,
		mobileWidth: 400,
		showZoomControls: false,
		defaultZoom: 75,
		minZoom: 25,
		maxZoom: 200,
	);

	expect( $component->uuid )->toContain( 'preview' );
	expect( $component->device )->toBe( 'tablet' );
	expect( $component->tabletWidth )->toBe( 800 );
	expect( $component->mobileWidth )->toBe( 400 );
	expect( $component->showZoomControls )->toBeFalse();
	expect( $component->defaultZoom )->toBe( 75 );
	expect( $component->minZoom )->toBe( 25 );
	expect( $component->maxZoom )->toBe( 200 );
} );

test( 'device preview falls back to desktop for invalid device', function (): void {
	$component = new DevicePreview( device: 'invalid' );

	expect( $component->device )->toBe( 'desktop' );
} );

test( 'device preview renders', function (): void {
	$view = $this->blade( '<x-ve-device-preview>Content</x-ve-device-preview>' );
	expect( $view )->not->toBeNull();
} );

test( 'device preview renders with region role', function (): void {
	$this->blade( '<x-ve-device-preview>Content</x-ve-device-preview>' )
		->assertSee( 'role="region"', false );
} );

test( 'device preview renders with slot content', function (): void {
	$this->blade( '<x-ve-device-preview>Preview Content</x-ve-device-preview>' )
		->assertSee( 'Preview Content' );
} );

test( 'device preview renders zoom controls by default', function (): void {
	$this->blade( '<x-ve-device-preview>Content</x-ve-device-preview>' )
		->assertSee( 'zoomIn()', false );
} );

test( 'device preview hides zoom controls when disabled', function (): void {
	$this->blade( '<x-ve-device-preview :show-zoom-controls="false">Content</x-ve-device-preview>' )
		->assertDontSee( 'Zoom in' );
} );
