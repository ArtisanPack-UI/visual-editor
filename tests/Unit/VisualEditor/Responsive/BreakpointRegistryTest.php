<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

it( 'falls back to Tailwind v4 defaults when nothing overrides them', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->all() )->toBe( [
		'sm'  => 640,
		'md'  => 768,
		'lg'  => 1024,
		'xl'  => 1280,
		'2xl' => 1536,
	] );
} );

it( 'merges config overrides on top of defaults', function () {
	$registry = BreakpointRegistry::fromLayers( [ 'lg' => 1100 ], [] );

	expect( $registry->get( 'lg' ) )->toBe( 1100 );
	expect( $registry->get( 'md' ) )->toBe( 768 );
} );

it( 'merges theme.json overrides on top of config', function () {
	$registry = BreakpointRegistry::fromLayers(
		[ 'lg' => 1100 ],
		[ 'lg' => '1200px', '3xl' => 1920 ]
	);

	expect( $registry->get( 'lg' ) )->toBe( 1200 );
	expect( $registry->get( '3xl' ) )->toBe( 1920 );
	expect( $registry->prefixes() )->toContain( '3xl' );
} );

it( 'sorts the registry ascending by min-width', function () {
	$registry = BreakpointRegistry::fromLayers(
		[],
		[ '3xl' => 1920, 'xxs' => 320 ]
	);

	expect( array_keys( $registry->all() ) )->toBe( [
		'xxs', 'sm', 'md', 'lg', 'xl', '2xl', '3xl',
	] );
} );

it( 'returns 0 for the implicit base slot and exposes it in keysWithBase()', function () {
	$registry = BreakpointRegistry::fromLayers( [], [] );

	expect( $registry->get( 'base' ) )->toBe( 0 );
	expect( $registry->keysWithBase() )->toBe( [ 'base', 'sm', 'md', 'lg', 'xl', '2xl' ] );
	expect( $registry->has( 'base' ) )->toBeTrue();
} );

it( 'rejects the reserved `base` key during validation', function () {
	BreakpointRegistry::fromLayers( [ 'base' => 0 ], [] );
} )->throws( InvalidArgumentException::class, 'reserved' );

it( 'rejects breakpoints with non-positive widths', function () {
	BreakpointRegistry::fromLayers( [ 'sm' => 0 ], [] );
} )->throws( InvalidArgumentException::class, 'positive pixel value' );

it( 'rejects breakpoints with duplicate widths', function () {
	BreakpointRegistry::fromLayers( [], [ 'foo' => 640 ] );
} )->throws( InvalidArgumentException::class, 'same min-width' );

it( 'rejects breakpoints with non-numeric strings', function () {
	BreakpointRegistry::fromLayers( [], [ 'foo' => '10rem' ] );
} )->throws( InvalidArgumentException::class, 'invalid value' );

it( 'rejects breakpoints with invalid key characters', function () {
	BreakpointRegistry::fromLayers( [], [ 'big screen!' => 1900 ] );
} )->throws( InvalidArgumentException::class, 'letters, numbers' );
