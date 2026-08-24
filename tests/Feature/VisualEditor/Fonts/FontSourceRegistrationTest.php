<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Registries\FontSourceRegistry;

function makeRegistrationTestProvider( string $key ): FontProvider
{
	return new class( $key ) implements FontProvider {
		public function __construct( private string $key ) {}

		public function key(): string
		{
			return $this->key;
		}

		public function label(): string
		{
			return 'Registration Test';
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

it( 'binds the font source registry as a singleton', function () {
	$first  = app( FontSourceRegistry::class );
	$second = app( FontSourceRegistry::class );

	expect( $first )->toBeInstanceOf( FontSourceRegistry::class )
		->and( $first )->toBe( $second );
} );

it( 'seeds providers registered via the ap.visualEditor.registerFontSources filter', function () {
	$provider = makeRegistrationTestProvider( 'acme' );

	addFilter(
		'ap.visualEditor.registerFontSources',
		static function ( FontSourceRegistry $registry ) use ( $provider ): FontSourceRegistry {
			$registry->register( $provider );

			return $registry;
		}
	);

	$registry = app( FontSourceRegistry::class );

	expect( $registry->has( 'acme' ) )->toBeTrue()
		->and( $registry->get( 'acme' ) )->toBe( $provider );
} );
