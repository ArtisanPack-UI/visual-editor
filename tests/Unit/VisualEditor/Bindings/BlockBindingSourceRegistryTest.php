<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockBindingSourceRegistry;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingContext;
use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;

function makeStubSource( string $name, mixed $value = null, array $eager = [] ): BlockBindingSource
{
	return new class( $name, $value, $eager ) implements BlockBindingSource
	{
		public function __construct(
			protected string $sourceName,
			protected mixed $value,
			protected array $eager
		) {
		}

		public function name(): string
		{
			return $this->sourceName;
		}

		public function resolve( BindingContext $context, array $args ): mixed
		{
			return $this->value;
		}

		public function eagerLoadRelations( array $bindingArgs ): array
		{
			return $this->eager;
		}

		public function availableFields( string $resource, ?string $modelClass = null ): array
		{
			return [];
		}
	};
}

it( 'starts empty', function () {
	$registry = new BlockBindingSourceRegistry();

	expect( $registry->all() )->toBeEmpty();
} );

it( 'registers a source by its declared name', function () {
	$registry = new BlockBindingSourceRegistry();
	$source   = makeStubSource( 'acme_field' );

	$registry->register( $source );

	expect( $registry->has( 'acme_field' ) )->toBeTrue()
		->and( $registry->get( 'acme_field' ) )->toBe( $source );
} );

it( 'returns null for unregistered names', function () {
	$registry = new BlockBindingSourceRegistry();

	expect( $registry->get( 'missing' ) )->toBeNull()
		->and( $registry->has( 'missing' ) )->toBeFalse();
} );

it( 'unregisters a source by name', function () {
	$registry = new BlockBindingSourceRegistry();
	$registry->register( makeStubSource( 'temp_source' ) );

	$registry->unregister( 'temp_source' );

	expect( $registry->has( 'temp_source' ) )->toBeFalse();
} );

it( 'overwrites the previous registration when the same name is reused', function () {
	$registry = new BlockBindingSourceRegistry();
	$first    = makeStubSource( 'shared' );
	$second   = makeStubSource( 'shared' );

	$registry->register( $first );
	$registry->register( $second );

	expect( $registry->get( 'shared' ) )->toBe( $second );
} );

it( 'rejects an empty source name', function () {
	$registry = new BlockBindingSourceRegistry();

	$registry->register( makeStubSource( '   ' ) );
} )->throws( InvalidArgumentException::class, 'cannot be empty' );

it( 'rejects a source name with invalid characters', function () {
	$registry = new BlockBindingSourceRegistry();

	$registry->register( makeStubSource( 'Bad-Name!' ) );
} )->throws( InvalidArgumentException::class, 'snake_case' );

it( 'rejects a source name that uses a slash like a block name', function () {
	$registry = new BlockBindingSourceRegistry();

	$registry->register( makeStubSource( 'acme/source' ) );
} )->throws( InvalidArgumentException::class, 'snake_case' );
