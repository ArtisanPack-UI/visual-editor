<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;

it( 'starts empty with no seeded blocks', function () {
	$registry = new BlockTypeRegistry();

	expect( $registry->all() )->toBeEmpty();
} );

it( 'registers a valid namespaced block name', function () {
	$registry = new BlockTypeRegistry();

	$registry->register( 'acme/callout', ['title' => 'Callout'] );

	$names = array_column( $registry->all(), 'name' );

	expect( $names )->toContain( 'acme/callout' );
} );

it( 'retrieves a single block by name', function () {
	$registry = new BlockTypeRegistry();

	$registry->register( 'acme/callout', ['title' => 'Callout', 'category' => 'text'] );

	$block = $registry->get( 'acme/callout' );

	expect( $block )->not->toBeNull()
		->and( $block['title'] )->toBe( 'Callout' )
		->and( $block['category'] )->toBe( 'text' );
} );

it( 'returns null for unregistered block names', function () {
	$registry = new BlockTypeRegistry();

	expect( $registry->get( 'acme/missing' ) )->toBeNull();
} );

it( 'rejects empty block names', function () {
	$registry = new BlockTypeRegistry();

	$registry->register( '   ', [] );
} )->throws( InvalidArgumentException::class );

it( 'rejects malformed block names', function () {
	$registry = new BlockTypeRegistry();

	$registry->register( 'Bad Name!', [] );
} )->throws( InvalidArgumentException::class );

it( 'rejects block names missing a namespace', function () {
	$registry = new BlockTypeRegistry();

	$registry->register( 'paragraph', [] );
} )->throws( InvalidArgumentException::class );
