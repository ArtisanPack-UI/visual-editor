<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;

function makeResolver(): ResponsiveValueResolver
{
	return new ResponsiveValueResolver( BreakpointRegistry::fromLayers( [], [] ) );
}

it( 'returns scalars unchanged', function () {
	expect( makeResolver()->resolve( 4, 'md' ) )->toBe( 4 );
	expect( makeResolver()->resolve( 'left', 'md' ) )->toBe( 'left' );
} );

it( 'returns the base value when no breakpoint overrides exist', function () {
	$attribute = [ 'base' => 4 ];

	expect( makeResolver()->resolve( $attribute, 'lg' ) )->toBe( 4 );
} );

it( 'cascades a smaller breakpoint up through null slots', function () {
	$attribute = [ 'base' => 4, 'sm' => 1, 'md' => null, 'lg' => null ];
	$resolver  = makeResolver();

	expect( $resolver->resolve( $attribute, 'sm' ) )->toBe( 1 );
	expect( $resolver->resolve( $attribute, 'md' ) )->toBe( 1 );
	expect( $resolver->resolve( $attribute, 'lg' ) )->toBe( 1 );
} );

it( 'returns the largest defined override at or below the active breakpoint', function () {
	$attribute = [ 'base' => 3, 'sm' => 1, 'md' => 2 ];
	$resolver  = makeResolver();

	expect( $resolver->resolve( $attribute, 'sm' ) )->toBe( 1 );
	expect( $resolver->resolve( $attribute, 'md' ) )->toBe( 2 );
	expect( $resolver->resolve( $attribute, 'lg' ) )->toBe( 2 );
	expect( $resolver->resolve( $attribute, 'xl' ) )->toBe( 2 );
	expect( $resolver->resolve( $attribute, '2xl' ) )->toBe( 2 );
	expect( $resolver->resolve( $attribute, 'base' ) )->toBe( 3 );
} );

it( 'returns null when no slot at or below the active breakpoint is defined', function () {
	$attribute = [ 'md' => 5 ];

	expect( makeResolver()->resolve( $attribute, 'sm' ) )->toBeNull();
} );

it( 'falls back to base when active breakpoint is unknown', function () {
	$attribute = [ 'base' => 7, 'md' => 9 ];

	expect( makeResolver()->resolve( $attribute, 'made-up' ) )->toBe( 7 );
} );

it( 'recognises responsive shape via base or any registry key', function () {
	$resolver = makeResolver();

	expect( $resolver->isResponsiveAttribute( [ 'base' => 1 ] ) )->toBeTrue();
	expect( $resolver->isResponsiveAttribute( [ 'md' => 1 ] ) )->toBeTrue();
	expect( $resolver->isResponsiveAttribute( [ 'orphan' => 1 ] ) )->toBeFalse();
	expect( $resolver->isResponsiveAttribute( [ 1, 2, 3 ] ) )->toBeFalse();
	expect( $resolver->isResponsiveAttribute( 'string' ) )->toBeFalse();
} );

it( 'compresses distinct overrides to skip redundant inherited values', function () {
	$resolver  = makeResolver();
	$attribute = [ 'base' => 4, 'sm' => 4, 'md' => 6, 'lg' => 6 ];

	expect( $resolver->distinctOverrides( $attribute ) )->toBe( [
		'base' => 4,
		'md'   => 6,
	] );
} );

it( 'lists override keys that are not in the active registry as orphans', function () {
	$resolver  = makeResolver();
	$attribute = [ 'base' => 1, 'md' => 2, 'legacy' => 3 ];

	expect( $resolver->orphanedKeys( $attribute ) )->toBe( [ 'legacy' ] );
} );

describe( 'desktop-first device overrides (#820)', function (): void {
	beforeEach( function (): void {
		$this->deviceResolver = new ResponsiveValueResolver( BreakpointRegistry::fromLayers( [], [] ) );
	} );

	it( 'never changes the desktop value when a smaller device is edited', function (): void {
		$attr = [ 'base' => '50%', 'mobile' => '100%' ];

		expect( $this->deviceResolver->resolve( $attr, 'base' ) )->toBe( '50%' );
		expect( $this->deviceResolver->resolve( $attr, 'tablet' ) )->toBe( '50%' );
		expect( $this->deviceResolver->resolve( $attr, 'mobile' ) )->toBe( '100%' );
	} );

	it( 'applies tablet overrides to mobile unless mobile overrides them', function (): void {
		expect( $this->deviceResolver->resolve( [ 'base' => 'a', 'tablet' => 'b' ], 'mobile' ) )->toBe( 'b' );
		expect( $this->deviceResolver->resolve( [ 'base' => 'a', 'tablet' => 'b', 'mobile' => 'c' ], 'mobile' ) )->toBe( 'c' );
	} );

	it( 'keeps legacy keys resolving mobile-first', function (): void {
		expect( $this->deviceResolver->resolve( [ 'base' => 'a', 'md' => 'b' ], 'lg' ) )->toBe( 'b' );
		expect( $this->deviceResolver->resolve( [ 'base' => 'a', 'md' => 'b' ], 'sm' ) )->toBe( 'a' );
	} );

	it( 'compresses device overrides down the max-width chain', function (): void {
		expect( $this->deviceResolver->distinctOverrides( [ 'base' => 'a', 'tablet' => 'b', 'mobile' => 'b' ] ) )
			->toBe( [ 'base' => 'a', 'tablet' => 'b' ] );
		expect( $this->deviceResolver->distinctOverrides( [ 'base' => 'a', 'tablet' => 'a', 'mobile' => 'c' ] ) )
			->toBe( [ 'base' => 'a', 'mobile' => 'c' ] );
	} );

	it( 'compresses legacy content exactly as before', function (): void {
		expect( $this->deviceResolver->distinctOverrides( [ 'base' => 'a', 'sm' => 'a', 'md' => 'b', 'lg' => 'b' ] ) )
			->toBe( [ 'base' => 'a', 'md' => 'b' ] );
	} );
} );

describe( 'mixed legacy and device keys (#820)', function (): void {
	it( 'keeps a device value equal to base so it can override a legacy rule', function (): void {
		$resolver = new ResponsiveValueResolver( BreakpointRegistry::fromLayers( [], [] ) );

		// Saved `{base:a, md:b}`; the author resets Tablet to `a`.
		expect( $resolver->distinctOverrides( [ 'base' => 'a', 'md' => 'b', 'tablet' => 'a' ] ) )
			->toBe( [ 'base' => 'a', 'md' => 'b', 'tablet' => 'a' ] );
	} );
} );
