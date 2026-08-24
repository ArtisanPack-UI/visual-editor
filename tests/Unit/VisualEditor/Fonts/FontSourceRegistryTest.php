<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;

function makeFontProvider( string $key, string $label = 'Test Fonts' ): FontProvider
{
	return new class( $key, $label ) implements FontProvider {
		public function __construct(
			private string $key,
			private string $label,
		) {}

		public function key(): string
		{
			return $this->key;
		}

		public function label(): string
		{
			return $this->label;
		}

		public function isSelfHostable(): bool
		{
			return true;
		}

		public function searchCatalog( string $query, int $page = 1 ): array
		{
			return ['families' => [], 'page' => $page, 'has_more' => false];
		}

		public function getFamily( string $slug ): ?array
		{
			return null;
		}

		public function fetchFace( string $slug, string $weight, string $style ): string
		{
			return '';
		}
	};
}

it( 'starts empty', function () {
	$registry = new FontSourceRegistry();

	expect( $registry->all() )->toBeEmpty();
} );

it( 'registers a provider under its declared key', function () {
	$registry = new FontSourceRegistry();
	$provider = makeFontProvider( 'google' );

	$registry->register( $provider );

	expect( $registry->has( 'google' ) )->toBeTrue()
		->and( $registry->get( 'google' ) )->toBe( $provider );
} );

it( 'returns null and false for unregistered keys', function () {
	$registry = new FontSourceRegistry();

	expect( $registry->get( 'missing' ) )->toBeNull()
		->and( $registry->has( 'missing' ) )->toBeFalse();
} );

it( 'returns every provider keyed by provider key', function () {
	$registry = new FontSourceRegistry();
	$registry->register( makeFontProvider( 'google' ) );
	$registry->register( makeFontProvider( 'bunny' ) );

	expect( $registry->all() )->toHaveCount( 2 )
		->and( array_keys( $registry->all() ) )->toEqual( ['google', 'bunny'] );
} );

it( 'overwrites the previous provider when the same key is reused', function () {
	$registry = new FontSourceRegistry();
	$first    = makeFontProvider( 'google', 'First' );
	$second   = makeFontProvider( 'google', 'Second' );

	$registry->register( $first );
	$registry->register( $second );

	expect( $registry->all() )->toHaveCount( 1 )
		->and( $registry->get( 'google' ) )->toBe( $second );
} );

it( 'unregisters a provider by key', function () {
	$registry = new FontSourceRegistry();
	$registry->register( makeFontProvider( 'google' ) );

	$registry->unregister( 'google' );

	expect( $registry->has( 'google' ) )->toBeFalse();
} );

it( 'unregistering an unknown key is a no-op', function () {
	$registry = new FontSourceRegistry();

	$registry->unregister( 'missing' );

	expect( $registry->all() )->toBeEmpty();
} );

it( 'rejects a provider whose key is empty', function () {
	$registry = new FontSourceRegistry();

	$registry->register( makeFontProvider( '   ' ) );
} )->throws( InvalidArgumentException::class, 'cannot be empty' );

it( 'rejects a provider whose key does not match the key pattern', function ( string $key ) {
	$registry = new FontSourceRegistry();

	$registry->register( makeFontProvider( $key ) );
} )->throws( InvalidArgumentException::class, 'is invalid' )->with( [
	'uppercase'         => ['Google'],
	'namespace slash'   => ['acme/google'],
	'leading digit'     => ['1google'],
	'spaces'            => ['google fonts'],
] );
