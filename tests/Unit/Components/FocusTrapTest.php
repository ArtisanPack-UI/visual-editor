<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\FocusTrap;

test( 'focus trap can be instantiated with defaults', function (): void {
	$component = new FocusTrap();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->active )->toBeTrue();
	expect( $component->restoreFocus )->toBeTrue();
	expect( $component->autoFocus )->toBeTrue();
	expect( $component->inert )->toBeFalse();
	expect( $component->initialFocus )->toBeNull();
} );

test( 'focus trap accepts custom props', function (): void {
	$component = new FocusTrap(
		id: 'modal',
		active: false,
		restoreFocus: false,
		autoFocus: false,
		inert: true,
		initialFocus: '#first-input',
	);

	expect( $component->uuid )->toContain( 'modal' );
	expect( $component->active )->toBeFalse();
	expect( $component->restoreFocus )->toBeFalse();
	expect( $component->autoFocus )->toBeFalse();
	expect( $component->inert )->toBeTrue();
	expect( $component->initialFocus )->toBe( '#first-input' );
} );

test( 'focus trap renders', function (): void {
	$view = $this->blade( '<x-ve-focus-trap>Content</x-ve-focus-trap>' );
	expect( $view )->not->toBeNull();
} );

test( 'focus trap renders with slot content', function (): void {
	$this->blade( '<x-ve-focus-trap>Trapped Content</x-ve-focus-trap>' )
		->assertSee( 'Trapped Content' );
} );

test( 'focus trap renders with region role', function (): void {
	$this->blade( '<x-ve-focus-trap>Content</x-ve-focus-trap>' )
		->assertSee( 'role="region"', false );
} );
