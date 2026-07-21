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

it( 'fires ap.visualEditor.blockRegistered on successful registration', function (): void {
	$registry = new BlockTypeRegistry();
	$seen     = [];

	addAction( 'ap.visualEditor.blockRegistered', function ( string $name, array $config ) use ( &$seen ): void {
		$seen[] = [ 'name' => $name, 'config' => $config ];
	}, 10, 2 );

	$registry->register( 'acme/hero', [ 'title' => 'Hero', 'category' => 'design' ] );

	removeAllActions( 'ap.visualEditor.blockRegistered' );

	expect( $seen )->toHaveCount( 1 )
		->and( $seen[0]['name'] )->toBe( 'acme/hero' )
		->and( $seen[0]['config']['title'] )->toBe( 'Hero' )
		->and( $seen[0]['config']['name'] )->toBe( 'acme/hero' );
} );

it( 'does not fire ap.visualEditor.blockRegistered when the name is invalid', function (): void {
	$registry = new BlockTypeRegistry();
	$fired    = false;

	addAction( 'ap.visualEditor.blockRegistered', function () use ( &$fired ): void {
		$fired = true;
	} );

	try {
		$registry->register( 'BAD NAME', [] );
	} catch ( InvalidArgumentException $e ) {
		// expected
	}

	removeAllActions( 'ap.visualEditor.blockRegistered' );

	expect( $fired )->toBeFalse();
} );
