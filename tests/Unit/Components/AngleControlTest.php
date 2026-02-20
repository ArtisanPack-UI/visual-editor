<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\AngleControl;

test( 'angle control can be instantiated with defaults', function (): void {
	$component = new AngleControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->value )->toBe( 0 );
	expect( $component->min )->toBe( 0 );
	expect( $component->max )->toBe( 360 );
	expect( $component->step )->toBe( 1 );
} );

test( 'angle control accepts custom props', function (): void {
	$component = new AngleControl(
		label: 'Rotation',
		value: 45,
		min: 0,
		max: 360,
		step: 15,
	);

	expect( $component->label )->toBe( 'Rotation' );
	expect( $component->value )->toBe( 45 );
	expect( $component->step )->toBe( 15 );
} );

test( 'angle control renders', function (): void {
	$this->blade( '<x-ve-angle-control />' )
		->assertSee( 'slider', false );
} );

test( 'angle control renders with label', function (): void {
	$this->blade( '<x-ve-angle-control label="Rotation" />' )
		->assertSee( 'Rotation' );
} );

test( 'angle control renders svg element', function (): void {
	$this->blade( '<x-ve-angle-control />' )
		->assertSee( 'slider', false );
} );
