<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\Responsive\ResponsiveValueResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Responsive\ResponsiveClassResolver;

function makeBladeResponsiveResolver( array $configOverrides = [] ): ResponsiveClassResolver
{
	$registry = BreakpointRegistry::fromLayers( $configOverrides, [] );
	$resolver = new ResponsiveValueResolver( $registry );

	return new ResponsiveClassResolver( $registry, $resolver );
}

it( 'returns empty class+css when given a null attribute', function () {
	$resolver = makeBladeResponsiveResolver();

	expect( $resolver->emit( null, 'padding' ) )->toBe( [ 'class' => '', 'css' => '' ] );
} );

it( 'emits Tailwind class strings when every value maps to a token', function () {
	$resolver = makeBladeResponsiveResolver();

	$attribute = [ 'base' => 4, 'sm' => 1, 'md' => 2 ];
	$tokenMap  = [
		'4' => 'grid-cols-4',
		'1' => 'grid-cols-1',
		'2' => 'grid-cols-2',
	];

	$result = $resolver->emit( $attribute, 'grid-template-columns', $tokenMap );

	expect( $result['css'] )->toBe( '' );
	expect( $result['class'] )->toBe( 'grid-cols-4 sm:grid-cols-1 md:grid-cols-2' );
} );

it( 'skips redundant inherited values when tokenizing', function () {
	$resolver = makeBladeResponsiveResolver();

	$attribute = [ 'base' => 4, 'sm' => 4, 'md' => 6, 'lg' => 6 ];
	$tokenMap  = [
		'4' => 'px-4',
		'6' => 'px-6',
	];

	$result = $resolver->emit( $attribute, 'padding', $tokenMap );

	expect( $result['class'] )->toBe( 'px-4 md:px-6' );
} );

it( 'falls back to @media rules when a value cannot be tokenized', function () {
	$resolver = makeBladeResponsiveResolver();

	$attribute = [ 'base' => '13px', 'md' => '18px' ];

	$result = $resolver->emit( $attribute, 'font-size', [] );

	expect( $result['class'] )->toStartWith( 've-r-' );
	expect( $result['css'] )->toContain( 'font-size:13px' );
	expect( $result['css'] )->toContain( '@media (min-width:768px)' );
	expect( $result['css'] )->toContain( 'font-size:18px' );
} );

it( 'accepts a callable token map', function () {
	$resolver = makeBladeResponsiveResolver();

	$attribute = [ 'base' => 1, 'md' => 3 ];

	$result = $resolver->emit(
		$attribute,
		'grid-template-columns',
		static fn ( $value ): ?string => is_int( $value ) ? 'cols-' . $value : null,
	);

	expect( $result['class'] )->toBe( 'cols-1 md:cols-3' );
	expect( $result['css'] )->toBe( '' );
} );

it( 'uses the active registry prefixes for class output', function () {
	// Adding a `3xl` breakpoint via config should make `3xl:` show up
	// in the emitted class string.
	$resolver = makeBladeResponsiveResolver( [ '3xl' => 1920 ] );

	$attribute = [ 'base' => 1, '3xl' => 5 ];
	$tokenMap  = [
		'1' => 'cols-1',
		'5' => 'cols-5',
	];

	$result = $resolver->emit( $attribute, 'grid-template-columns', $tokenMap );

	expect( $result['class'] )->toBe( 'cols-1 3xl:cols-5' );
} );
