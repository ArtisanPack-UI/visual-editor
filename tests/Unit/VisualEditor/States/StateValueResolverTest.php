<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\States\StateRegistry;
use ArtisanPackUI\VisualEditor\States\StateValueResolver;

function makeStateResolver(): StateValueResolver
{
	return new StateValueResolver( StateRegistry::fromLayers( [], [] ) );
}

it( 'returns scalars unchanged', function () {
	expect( makeStateResolver()->resolve( 'red', 'hover' ) )->toBe( 'red' );
	expect( makeStateResolver()->resolve( 4, 'hover' ) )->toBe( 4 );
} );

it( 'returns idle when no per-state overrides exist', function () {
	$attribute = [ 'idle' => 'red' ];

	expect( makeStateResolver()->resolve( $attribute, 'hover' ) )->toBe( 'red' );
} );

it( 'walks the inheritance chain through null slots', function () {
	$attribute = [ 'idle' => 'red', 'hover' => 'blue', 'active' => null ];

	expect( makeStateResolver()->resolve( $attribute, 'active' ) )->toBe( 'blue' );
} );

it( 'returns the override at the active state when one exists', function () {
	$attribute = [ 'idle' => 'red', 'hover' => 'blue' ];
	$resolver  = makeStateResolver();

	expect( $resolver->resolve( $attribute, 'idle' ) )->toBe( 'red' );
	expect( $resolver->resolve( $attribute, 'hover' ) )->toBe( 'blue' );
	expect( $resolver->resolve( $attribute, 'active' ) )->toBe( 'blue' );
	expect( $resolver->resolve( $attribute, 'focus' ) )->toBe( 'red' );
} );

it( 'returns null when no slot in the chain is defined', function () {
	$attribute = [ 'hover' => 'blue' ];

	expect( makeStateResolver()->resolve( $attribute, 'focus' ) )->toBeNull();
} );

it( 'falls back to idle when active state is unknown', function () {
	$attribute = [ 'idle' => 'red', 'hover' => 'blue' ];

	expect( makeStateResolver()->resolve( $attribute, 'made-up' ) )->toBe( 'red' );
} );

it( 'recognises stateful shape via idle or any registry key', function () {
	$resolver = makeStateResolver();

	expect( $resolver->isStatefulAttribute( [ 'idle' => 'a' ] ) )->toBeTrue();
	expect( $resolver->isStatefulAttribute( [ 'hover' => 'a' ] ) )->toBeTrue();
	expect( $resolver->isStatefulAttribute( 'red' ) )->toBeFalse();
	expect( $resolver->isStatefulAttribute( [ 'a', 'b' ] ) )->toBeFalse();
	expect( $resolver->isStatefulAttribute( [] ) )->toBeFalse();
} );

it( 'collapses distinctOverrides to only states that differ from their inheritance parent', function () {
	$attribute = [ 'idle' => 'red', 'hover' => 'red', 'active' => 'red', 'focus' => 'blue' ];

	$result = makeStateResolver()->distinctOverrides( $attribute );

	expect( $result )->toBe( [ 'idle' => 'red', 'focus' => 'blue' ] );
} );

it( 'includes idle in distinctOverrides whenever it has a non-null value', function () {
	$attribute = [ 'idle' => 'red' ];

	expect( makeStateResolver()->distinctOverrides( $attribute ) )->toBe( [ 'idle' => 'red' ] );
} );

it( 'returns orphaned keys for states not present in the registry', function () {
	$attribute = [ 'idle' => 'a', 'hover' => 'b', 'legacy-state' => 'c' ];

	expect( makeStateResolver()->orphanedKeys( $attribute ) )->toBe( [ 'legacy-state' ] );
} );

it( 'resolveAll returns the cascaded value for every registered state', function () {
	$attribute = [ 'idle' => 'red', 'hover' => 'blue' ];

	$all = makeStateResolver()->resolveAll( $attribute );

	expect( $all['idle'] )->toBe( 'red' );
	expect( $all['hover'] )->toBe( 'blue' );
	expect( $all['active'] )->toBe( 'blue' );
	expect( $all['focus'] )->toBe( 'red' );
	expect( $all['focus-visible'] )->toBe( 'red' );
	expect( $all['disabled'] )->toBe( 'red' );
} );
