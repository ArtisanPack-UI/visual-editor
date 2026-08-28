<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Http\Requests\Fonts\InstallFontRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Validate an install payload against the request's own rules and messages.
 *
 * @param  array<string, mixed>  $payload
 */
function validateInstall( array $payload ): Illuminate\Contracts\Validation\Validator
{
	$request = new InstallFontRequest();

	return Validator::make( $payload, $request->rules(), $request->messages() );
}

/**
 * A list of $count face entries, each a distinct weight/style pair.
 *
 * @return array<int, array{weight: int, style: string}>
 */
function fakeFaces( int $count ): array
{
	$faces = [];

	for ( $i = 0; $i < $count; $i++ ) {
		$faces[] = [ 'weight' => 100 + $i, 'style' => 'normal' ];
	}

	return $faces;
}

it( 'passes with a provider, slug, and one face', function (): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'slug'     => 'inter',
		'faces'    => [ [ 'weight' => 400, 'style' => 'normal' ] ],
	] );

	expect( $validator->passes() )->toBeTrue();
} );

it( 'requires a provider', function (): void {
	$validator = validateInstall( [
		'slug'  => 'inter',
		'faces' => [ [ 'weight' => 400 ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'provider' ) )->toBeTrue();
} );

it( 'rejects a malformed provider key', function ( string $provider ): void {
	$validator = validateInstall( [
		'provider' => $provider,
		'slug'     => 'inter',
		'faces'    => [ [ 'weight' => 400 ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'provider' ) )->toBeTrue();
} )->with( [ '1google', 'Google', 'goo gle', 'google!' ] );

it( 'requires a slug', function (): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'faces'    => [ [ 'weight' => 400 ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'slug' ) )->toBeTrue();
} );

it( 'requires at least one face', function (): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'slug'     => 'inter',
		'faces'    => [],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces' ) )->toBeTrue();
} );

it( 'accepts up to 18 faces', function (): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'slug'     => 'inter',
		'faces'    => fakeFaces( 18 ),
	] );

	expect( $validator->passes() )->toBeTrue();
} );

it( 'rejects more than 18 faces so a synchronous install cannot overrun', function (): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'slug'     => 'inter',
		'faces'    => fakeFaces( 19 ),
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces' ) )->toBeTrue();
} );

it( 'requires a weight on each face', function (): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'slug'     => 'inter',
		'faces'    => [ [ 'style' => 'normal' ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces.0.weight' ) )->toBeTrue();
} );

it( 'rejects a weight outside 1..1000', function ( int $weight ): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'slug'     => 'inter',
		'faces'    => [ [ 'weight' => $weight ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces.0.weight' ) )->toBeTrue();
} )->with( [ 0, -100, 1001 ] );

it( 'rejects an unknown style', function (): void {
	$validator = validateInstall( [
		'provider' => 'google',
		'slug'     => 'inter',
		'faces'    => [ [ 'weight' => 400, 'style' => 'oblique' ] ],
	] );

	expect( $validator->passes() )->toBeFalse()
		->and( $validator->errors()->has( 'faces.0.style' ) )->toBeTrue();
} );
