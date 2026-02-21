<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\Popover;

test( 'popover can be instantiated with defaults', function (): void {
	$component = new Popover();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->placement )->toBe( 'bottom' );
	expect( $component->offset )->toBe( 8 );
	expect( $component->flip )->toBeTrue();
	expect( $component->shift )->toBeTrue();
	expect( $component->arrow )->toBeFalse();
	expect( $component->triggerOn )->toBe( 'click' );
	expect( $component->closeOnClickOutside )->toBeTrue();
	expect( $component->closeOnEscape )->toBeTrue();
	expect( $component->trapFocus )->toBeFalse();
	expect( $component->animation )->toBe( 'fade' );
	expect( $component->width )->toBeNull();
} );

test( 'popover accepts custom props', function (): void {
	$component = new Popover(
		id: 'color-picker',
		placement: 'top-start',
		offset: 12,
		flip: false,
		shift: false,
		arrow: true,
		triggerOn: 'hover',
		closeOnClickOutside: false,
		closeOnEscape: false,
		trapFocus: true,
		animation: 'scale',
		width: '320px',
	);

	expect( $component->uuid )->toContain( 'color-picker' );
	expect( $component->placement )->toBe( 'top-start' );
	expect( $component->offset )->toBe( 12 );
	expect( $component->flip )->toBeFalse();
	expect( $component->arrow )->toBeTrue();
	expect( $component->triggerOn )->toBe( 'hover' );
	expect( $component->trapFocus )->toBeTrue();
	expect( $component->width )->toBe( '320px' );
} );

test( 'popover falls back to bottom for invalid placement', function (): void {
	$component = new Popover( placement: 'invalid' );

	expect( $component->placement )->toBe( 'bottom' );
} );

test( 'popover falls back to click for invalid trigger', function (): void {
	$component = new Popover( triggerOn: 'invalid' );

	expect( $component->triggerOn )->toBe( 'click' );
} );

test( 'popover has valid placements', function (): void {
	expect( Popover::PLACEMENTS )->toContain( 'top' );
	expect( Popover::PLACEMENTS )->toContain( 'bottom' );
	expect( Popover::PLACEMENTS )->toContain( 'left' );
	expect( Popover::PLACEMENTS )->toContain( 'right' );
	expect( Popover::PLACEMENTS )->toContain( 'top-start' );
	expect( Popover::PLACEMENTS )->toContain( 'bottom-end' );
} );

test( 'popover renders', function (): void {
	$view = $this->blade( '<x-ve-popover>Content</x-ve-popover>' );
	expect( $view )->not->toBeNull();
} );

test( 'popover renders with slot content', function (): void {
	$this->blade( '<x-ve-popover>Popover Content</x-ve-popover>' )
		->assertSee( 'Popover Content' );
} );
