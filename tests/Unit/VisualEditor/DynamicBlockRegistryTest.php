<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\ClosureDynamicBlock;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;

function makeClosureBlock( string $name ): ClosureDynamicBlock
{
	return new ClosureDynamicBlock(
		blockName: $name,
		renderCallback: static fn ( array $attrs ): string => '<p>' . ( $attrs['text'] ?? '' ) . '</p>',
	);
}

it( 'starts empty', function () {
	$registry = new DynamicBlockRegistry();

	expect( $registry->all() )->toBeEmpty();
} );

it( 'registers a dynamic block by its declared name', function () {
	$registry = new DynamicBlockRegistry();

	$registry->register( makeClosureBlock( 'acme/thing' ) );

	expect( $registry->has( 'acme/thing' ) )->toBeTrue()
		->and( $registry->get( 'acme/thing' ) )->not->toBeNull();
} );

it( 'returns null for unregistered names', function () {
	$registry = new DynamicBlockRegistry();

	expect( $registry->get( 'acme/missing' ) )->toBeNull()
		->and( $registry->has( 'acme/missing' ) )->toBeFalse();
} );

it( 'unregisters a block by name', function () {
	$registry = new DynamicBlockRegistry();
	$registry->register( makeClosureBlock( 'acme/thing' ) );

	$registry->unregister( 'acme/thing' );

	expect( $registry->has( 'acme/thing' ) )->toBeFalse();
} );

it( 'overwrites the previous registration when the same name is reused', function () {
	$registry = new DynamicBlockRegistry();
	$first    = makeClosureBlock( 'acme/thing' );
	$second   = makeClosureBlock( 'acme/thing' );

	$registry->register( $first );
	$registry->register( $second );

	expect( $registry->get( 'acme/thing' ) )->toBe( $second );
} );
