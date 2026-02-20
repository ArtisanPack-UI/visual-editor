<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\AriaLiveRegion;

test( 'aria live region can be instantiated with defaults', function (): void {
	$component = new AriaLiveRegion();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->priority )->toBe( 'polite' );
	expect( $component->clearAfter )->toBe( 5000 );
	expect( $component->debounce )->toBe( 100 );
} );

test( 'aria live region accepts custom props', function (): void {
	$component = new AriaLiveRegion(
		id: 'editor',
		priority: 'assertive',
		clearAfter: 3000,
		debounce: 200,
	);

	expect( $component->uuid )->toContain( 'editor' );
	expect( $component->priority )->toBe( 'assertive' );
	expect( $component->clearAfter )->toBe( 3000 );
	expect( $component->debounce )->toBe( 200 );
} );

test( 'aria live region falls back to polite for invalid priority', function (): void {
	$component = new AriaLiveRegion( priority: 'invalid' );

	expect( $component->priority )->toBe( 'polite' );
} );

test( 'aria live region renders', function (): void {
	$view = $this->blade( '<x-ve-aria-live-region />' );
	expect( $view )->not->toBeNull();
} );

test( 'aria live region renders with sr-only class', function (): void {
	$this->blade( '<x-ve-aria-live-region />' )
		->assertSee( 'sr-only' );
} );

test( 'aria live region renders polite and assertive regions', function (): void {
	$this->blade( '<x-ve-aria-live-region />' )
		->assertSee( 'aria-live="polite"', false )
		->assertSee( 'aria-live="assertive"', false );
} );
