<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\UnitControl;

test( 'unit control can be instantiated with defaults', function (): void {
	$component = new UnitControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->unit )->toBe( 'px' );
	expect( $component->units )->toBe( [ 'px', 'em', 'rem', '%', 'vw', 'vh' ] );
	expect( $component->value )->toBeNull();
	expect( $component->label )->toBeNull();
} );

test( 'unit control accepts custom props', function (): void {
	$component = new UnitControl(
		id: 'width',
		label: 'Width',
		value: 100,
		unit: 'rem',
		units: [ 'px', 'rem' ],
		min: 0,
		max: 500,
		step: 5,
	);

	expect( $component->label )->toBe( 'Width' );
	expect( $component->value )->toBe( 100 );
	expect( $component->unit )->toBe( 'rem' );
	expect( $component->units )->toBe( [ 'px', 'rem' ] );
	expect( $component->min )->toBe( 0 );
	expect( $component->max )->toBe( 500 );
	expect( $component->step )->toBe( 5 );
} );

test( 'unit control generates unit options', function (): void {
	$component = new UnitControl( units: [ 'px', 'em' ] );
	$options   = $component->unitOptions();

	expect( $options )->toBe( [
		[ 'id' => 'px', 'name' => 'px' ],
		[ 'id' => 'em', 'name' => 'em' ],
	] );
} );

test( 'unit control renders', function (): void {
	$view = $this->blade( '<x-ve-unit-control />' );
	expect( $view )->not->toBeNull();
} );

test( 'unit control renders with label', function (): void {
	$this->blade( '<x-ve-unit-control label="Width" />' )
		->assertSee( 'Width' );
} );
