<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\AnimatePresence;

test( 'animate presence can be instantiated with defaults', function (): void {
	$component = new AnimatePresence();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->animation )->toBe( 'fade' );
	expect( $component->duration )->toBe( 200 );
	expect( $component->easing )->toBe( 'ease-in-out' );
	expect( $component->show )->toBeTrue();
} );

test( 'animate presence accepts custom props', function (): void {
	$component = new AnimatePresence(
		id: 'panel',
		animation: 'slide-up',
		duration: 300,
		easing: 'ease-out',
		show: false,
	);

	expect( $component->uuid )->toContain( 'panel' );
	expect( $component->animation )->toBe( 'slide-up' );
	expect( $component->duration )->toBe( 300 );
	expect( $component->easing )->toBe( 'ease-out' );
	expect( $component->show )->toBeFalse();
} );

test( 'animate presence falls back to fade for invalid animation', function (): void {
	$component = new AnimatePresence( animation: 'invalid' );

	expect( $component->animation )->toBe( 'fade' );
} );

test( 'animate presence has valid animation presets', function (): void {
	expect( AnimatePresence::ANIMATION_PRESETS )->toBe( [
		'fade',
		'slide-up',
		'slide-down',
		'slide-left',
		'slide-right',
		'scale',
		'collapse',
	] );
} );

test( 'animate presence renders', function (): void {
	$view = $this->blade( '<x-ve-animate-presence>Content</x-ve-animate-presence>' );
	expect( $view )->not->toBeNull();
} );

test( 'animate presence renders with slot content', function (): void {
	$this->blade( '<x-ve-animate-presence>Hello World</x-ve-animate-presence>' )
		->assertSee( 'Hello World' );
} );
