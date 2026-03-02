<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\ResponsiveRangeControl;

test( 'responsive range control can be instantiated with defaults', function (): void {
	$component = new ResponsiveRangeControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->min )->toBe( 0 );
	expect( $component->max )->toBe( 100 );
	expect( $component->step )->toBe( 1 );
	expect( $component->value )->toBe( [ 'mode' => 'global', 'global' => 3, 'desktop' => 3, 'tablet' => 2, 'mobile' => 1 ] );
} );

test( 'responsive range control accepts custom props', function (): void {
	$component = new ResponsiveRangeControl(
		label: 'Columns',
		value: [ 'mode' => 'responsive', 'global' => 4, 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ],
		min: 1,
		max: 6,
		step: 1,
	);

	expect( $component->label )->toBe( 'Columns' );
	expect( $component->value )->toBe( [ 'mode' => 'responsive', 'global' => 4, 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ] );
	expect( $component->min )->toBe( 1 );
	expect( $component->max )->toBe( 6 );
	expect( $component->step )->toBe( 1 );
} );

test( 'responsive range control renders with label', function (): void {
	$this->blade( '<x-ve-responsive-range-control label="Columns" :value="[\'mode\' => \'global\', \'global\' => 3, \'desktop\' => 3, \'tablet\' => 2, \'mobile\' => 1]" />' )
		->assertSee( 'Columns' );
} );

test( 'responsive range control renders responsive range change event', function (): void {
	$this->blade( '<x-ve-responsive-range-control label="Columns" :value="[\'mode\' => \'global\', \'global\' => 3, \'desktop\' => 3, \'tablet\' => 2, \'mobile\' => 1]" />' )
		->assertSee( 've-responsive-range-change', false );
} );

test( 'responsive range control renders toggle button', function (): void {
	$this->blade( '<x-ve-responsive-range-control label="Columns" :value="[\'mode\' => \'global\', \'global\' => 3, \'desktop\' => 3, \'tablet\' => 2, \'mobile\' => 1]" />' )
		->assertSee( 'toggleMode()', false );
} );

test( 'responsive range control renders range inputs', function (): void {
	$view = $this->blade( '<x-ve-responsive-range-control label="Columns" :value="[\'mode\' => \'global\', \'global\' => 3, \'desktop\' => 3, \'tablet\' => 2, \'mobile\' => 1]" :min="1" :max="6" />' );

	$view->assertSee( 'type="range"', false );
	$view->assertSee( 'type="number"', false );
} );
