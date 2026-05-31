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
