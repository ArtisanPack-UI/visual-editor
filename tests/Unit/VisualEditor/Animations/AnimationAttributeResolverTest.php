<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Animations\AnimationAttributeResolver;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

function makeAnimResolver(): AnimationAttributeResolver
{
	return new AnimationAttributeResolver( BreakpointRegistry::fromLayers( [], [] ) );
}

it( 'returns a scalar value unchanged at base', function () {
	expect( makeAnimResolver()->resolve( 'fade-in', 'base' ) )->toBe( 'fade-in' );
} );

it( 'treats a scalar value as the base shape at every breakpoint', function () {
	// Mirrors `ResponsiveValueResolver::resolve()`: a scalar applies
	// uniformly across the cascade.
	expect( makeAnimResolver()->resolve( 'fade-in', 'md' ) )->toBe( 'fade-in' );
	expect( makeAnimResolver()->resolve( 'fade-in', 'xl' ) )->toBe( 'fade-in' );
} );

it( 'returns the base value when no breakpoint overrides exist', function () {
	$value = [ 'base' => 'fade-in' ];

	expect( makeAnimResolver()->resolve( $value, 'lg' ) )->toBe( 'fade-in' );
} );

it( 'returns null when the breakpoint explicitly disables the animation', function () {
	$value = [ 'base' => 'fade-in', 'md' => null ];

	expect( makeAnimResolver()->resolve( $value, 'md' ) )->toBeNull();
	expect( makeAnimResolver()->resolve( $value, 'base' ) )->toBe( 'fade-in' );
	expect( makeAnimResolver()->resolve( $value, 'sm' ) )->toBe( 'fade-in' );
} );

it( 'returns the override at the requested breakpoint', function () {
	$value = [ 'base' => 'fade-in', 'md' => 'zoom-in' ];

	expect( makeAnimResolver()->resolve( $value, 'md' ) )->toBe( 'zoom-in' );
} );

it( 'resolves the full cascade at once', function () {
	$value = [ 'base' => 'fade-in', 'md' => 'zoom-in', 'xl' => null ];
	$all   = makeAnimResolver()->resolveAll( $value );

	expect( $all['base'] )->toBe( 'fade-in' );
	expect( $all['sm'] )->toBe( 'fade-in' );
	expect( $all['md'] )->toBe( 'zoom-in' );
	expect( $all['lg'] )->toBe( 'zoom-in' );
	expect( $all['xl'] )->toBeNull();
} );
