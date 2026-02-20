<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\RangeControl;

test( 'range control can be instantiated with defaults', function (): void {
	$component = new RangeControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->min )->toBe( 0 );
	expect( $component->max )->toBe( 100 );
	expect( $component->step )->toBe( 1 );
	expect( $component->showInput )->toBeTrue();
	expect( $component->showReset )->toBeTrue();
} );

test( 'range control accepts custom props', function (): void {
	$component = new RangeControl(
		label: 'Opacity',
		value: 50,
		min: 0,
		max: 100,
		step: 5,
		defaultValue: 100,
		showInput: false,
		showReset: false,
	);

	expect( $component->label )->toBe( 'Opacity' );
	expect( $component->value )->toBe( 50 );
	expect( $component->defaultValue )->toBe( 100 );
	expect( $component->showInput )->toBeFalse();
	expect( $component->showReset )->toBeFalse();
} );

test( 'range control renders', function (): void {
	$view = $this->blade( '<x-ve-range-control />' );
	expect( $view )->not->toBeNull();
} );

test( 'range control renders with label', function (): void {
	$this->blade( '<x-ve-range-control label="Volume" />' )
		->assertSee( 'Volume' );
} );
