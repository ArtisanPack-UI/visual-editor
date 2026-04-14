<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry;

it( 'seeds core/paragraph and core/heading by default', function () {
	$registry = new BlockTypeRegistry();

	$names = array_column( $registry->all(), 'name' );

	expect( $names )->toContain( 'core/paragraph', 'core/heading' );
} );

it( 'registers a valid namespaced block name', function () {
	$registry = new BlockTypeRegistry();

	$registry->register( 'acme/callout', ['title' => 'Callout'] );

	$names = array_column( $registry->all(), 'name' );

	expect( $names )->toContain( 'acme/callout' );
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
