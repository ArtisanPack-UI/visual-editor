<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BoxControl;

test( 'box control can be instantiated with defaults', function (): void {
	$component = new BoxControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->linked )->toBeTrue();
	expect( $component->unit )->toBe( 'px' );
	expect( $component->units )->toBe( [ 'px', 'em', 'rem', '%' ] );
	expect( $component->top )->toBeNull();
	expect( $component->right )->toBeNull();
	expect( $component->bottom )->toBeNull();
	expect( $component->left )->toBeNull();
} );

test( 'box control accepts custom props', function (): void {
	$component = new BoxControl(
		label: 'Padding',
		top: 10,
		right: 20,
		bottom: 10,
		left: 20,
		unit: 'rem',
		linked: false,
	);

	expect( $component->label )->toBe( 'Padding' );
	expect( $component->top )->toBe( 10 );
	expect( $component->right )->toBe( 20 );
	expect( $component->bottom )->toBe( 10 );
	expect( $component->left )->toBe( 20 );
	expect( $component->unit )->toBe( 'rem' );
	expect( $component->linked )->toBeFalse();
} );

test( 'box control renders', function (): void {
	$view = $this->blade( '<x-ve-box-control />' );
	expect( $view )->not->toBeNull();
} );

test( 'box control renders with label', function (): void {
	$this->blade( '<x-ve-box-control label="Padding" />' )
		->assertSee( 'Padding' );
} );

test( 'box control renders four input areas', function (): void {
	$this->blade( '<x-ve-box-control />' )
		->assertSee( 'Top', false )
		->assertSee( 'Right', false )
		->assertSee( 'Bottom', false )
		->assertSee( 'Left', false );
} );
