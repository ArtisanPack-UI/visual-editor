<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Facades\VisualEditor;
use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;
use ArtisanPackUI\VisualEditor\Registries\DynamicBlockRegistry;

it( 'registers both the block type metadata and the server renderer', function () {
	VisualEditor::registerServerBlock(
		'tests/server-block',
		[
			'title'      => 'Server Block',
			'category'   => 'widgets',
			'attributes' => [ 'label' => [ 'type' => 'string', 'default' => 'Hi' ] ],
		],
		fn ( array $attrs ): string => '<p>' . ( $attrs['label'] ?? '' ) . '</p>',
	);

	$type = app( BlockTypeRegistry::class )->get( 'tests/server-block' );

	expect( $type )->not->toBeNull()
		->and( $type['name'] )->toBe( 'tests/server-block' )
		->and( $type['title'] )->toBe( 'Server Block' )
		->and( $type['attributes'] )->toBe( [ 'label' => [ 'type' => 'string', 'default' => 'Hi' ] ] );

	$dynamic = app( DynamicBlockRegistry::class )->get( 'tests/server-block' );

	expect( $dynamic )->not->toBeNull()
		->and( $dynamic->name() )->toBe( 'tests/server-block' )
		->and( (string) $dynamic->render( [ 'label' => 'World' ] ) )->toBe( '<p>World</p>' );
} );

it( 'returns the registered dynamic block instance', function () {
	$block = VisualEditor::registerServerBlock(
		'tests/returns-instance',
		[ 'title' => 'X' ],
		fn ( array $attrs ): string => 'x',
	);

	expect( $block )->toBe( app( DynamicBlockRegistry::class )->get( 'tests/returns-instance' ) );
} );

it( 'ignores a stray name in the metadata array', function () {
	VisualEditor::registerServerBlock(
		'tests/authoritative-name',
		[ 'name' => 'tests/other-name', 'title' => 'X' ],
		fn ( array $attrs ): string => 'x',
	);

	expect( app( BlockTypeRegistry::class )->get( 'tests/authoritative-name' ) )->not->toBeNull()
		->and( app( BlockTypeRegistry::class )->get( 'tests/other-name' ) )->toBeNull();
} );

it( 'wires optional callbacks through to the dynamic block', function () {
	VisualEditor::registerServerBlock(
		'tests/with-search',
		[ 'title' => 'X' ],
		fn ( array $attrs ): string => 'x',
		[ 'searchableText' => fn ( array $attrs ): string => (string) ( $attrs['q'] ?? '' ) ],
	);

	$block = app( DynamicBlockRegistry::class )->get( 'tests/with-search' );

	expect( $block->searchableText( [ 'q' => 'findable' ] ) )->toBe( 'findable' );
} );

it( 'registers neither the type nor the renderer when a callback is invalid', function () {
	try {
		VisualEditor::registerServerBlock(
			'tests/bad-callback',
			[ 'title' => 'X' ],
			fn ( array $attrs ): string => 'x',
			[ 'searchableText' => 'not a callable' ],
		);

		$this->fail( 'Expected an InvalidArgumentException for the non-callable callback.' );
	} catch ( InvalidArgumentException $e ) {
		// The block type must not be left registered without its renderer —
		// that would reach the editor unflagged and un-synthesizable.
		expect( app( BlockTypeRegistry::class )->get( 'tests/bad-callback' ) )->toBeNull()
			->and( app( DynamicBlockRegistry::class )->has( 'tests/bad-callback' ) )->toBeFalse();
	}
} );

it( 'throws when the block name is invalid', function () {
	VisualEditor::registerServerBlock( 'not-namespaced', [ 'title' => 'X' ], fn ( array $attrs ): string => 'x' );
} )->throws( InvalidArgumentException::class );
