<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\View\Components\BorderControl;

test( 'border control can be instantiated with defaults', function (): void {
	$component = new BorderControl();

	expect( $component->uuid )->toStartWith( 've-' );
	expect( $component->width )->toBe( '1' );
	expect( $component->widthUnit )->toBe( 'px' );
	expect( $component->style )->toBe( 'solid' );
	expect( $component->color )->toBe( '#000000' );
	expect( $component->styles )->toBe( BorderControl::DEFAULT_STYLES );
	expect( $component->perSide )->toBeFalse();
	expect( $component->radius )->toBe( '0' );
	expect( $component->radiusUnit )->toBe( 'px' );
	expect( $component->perCorner )->toBeFalse();
} );

test( 'border control default styles are correct', function (): void {
	expect( BorderControl::DEFAULT_STYLES )->toBe( [ 'none', 'solid', 'dashed', 'dotted', 'double' ] );
} );

test( 'border control accepts custom props', function (): void {
	$component = new BorderControl(
		width: '2',
		widthUnit: 'em',
		style: 'dashed',
		color: '#ff0000',
		perSide: true,
	);

	expect( $component->width )->toBe( '2' );
	expect( $component->widthUnit )->toBe( 'em' );
	expect( $component->style )->toBe( 'dashed' );
	expect( $component->color )->toBe( '#ff0000' );
	expect( $component->perSide )->toBeTrue();
} );

test( 'border control generates style options', function (): void {
	$component = new BorderControl();
	$options   = $component->styleOptions();

	expect( $options )->toBe( [
		[ 'id' => 'none', 'name' => 'None' ],
		[ 'id' => 'solid', 'name' => 'Solid' ],
		[ 'id' => 'dashed', 'name' => 'Dashed' ],
		[ 'id' => 'dotted', 'name' => 'Dotted' ],
		[ 'id' => 'double', 'name' => 'Double' ],
	] );
} );

test( 'border control renders', function (): void {
	$view = $this->blade( '<x-ve-border-control />' );
	expect( $view )->not->toBeNull();
} );

test( 'border control renders with label', function (): void {
	$this->blade( '<x-ve-border-control label="Border" />' )
		->assertSee( 'Border' );
} );

test( 'border control renders style and preview sections', function (): void {
	$this->blade( '<x-ve-border-control />' )
		->assertSee( 'Style' )
		->assertSee( 'Preview' );
} );

test( 'border control accepts custom radius props', function (): void {
	$component = new BorderControl(
		radius: '8',
		radiusUnit: 'rem',
		perCorner: true,
	);

	expect( $component->radius )->toBe( '8' );
	expect( $component->radiusUnit )->toBe( 'rem' );
	expect( $component->perCorner )->toBeTrue();
} );

test( 'border control renders radius section', function (): void {
	$this->blade( '<x-ve-border-control />' )
		->assertSee( 'Radius' );
} );

test( 'border control renders per-corner labels', function (): void {
	$this->blade( '<x-ve-border-control :per-corner="true" />' )
		->assertSee( 'Top Left' )
		->assertSee( 'Top Right' )
		->assertSee( 'Bottom Left' )
		->assertSee( 'Bottom Right' );
} );
