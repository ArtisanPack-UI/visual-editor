<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'registry can register a block', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	expect( $registry->exists( 'stub' ) )->toBeTrue();
} );

test( 'registry can retrieve a registered block', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	expect( $registry->get( 'stub' ) )->toBe( $block );
} );

test( 'registry returns null for unregistered block', function (): void {
	$registry = new BlockRegistry();

	expect( $registry->get( 'nonexistent' ) )->toBeNull();
} );

test( 'registry exists returns false for unregistered block', function (): void {
	$registry = new BlockRegistry();

	expect( $registry->exists( 'nonexistent' ) )->toBeFalse();
} );

test( 'registry can unregister a block by string', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );
	$registry->unregister( 'stub' );

	expect( $registry->exists( 'stub' ) )->toBeFalse();
} );

test( 'registry can unregister multiple blocks by array', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );
	$registry->unregister( [ 'stub' ] );

	expect( $registry->exists( 'stub' ) )->toBeFalse();
} );

test( 'registry can unregister by category', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );
	$registry->unregisterCategory( 'text' );

	expect( $registry->exists( 'stub' ) )->toBeFalse();
} );

test( 'registry unregister category preserves other categories', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );
	$registry->unregisterCategory( 'media' );

	expect( $registry->exists( 'stub' ) )->toBeTrue();
} );

test( 'registry all returns all registered blocks', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	$all = $registry->all();

	expect( $all )->toHaveKey( 'stub' );
	expect( $all['stub'] )->toBe( $block );
} );

test( 'registry get by category filters correctly', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	expect( $registry->getByCategory( 'text' ) )->toHaveCount( 1 );
	expect( $registry->getByCategory( 'media' ) )->toHaveCount( 0 );
} );

test( 'registry get categories returns unique categories', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	$categories = $registry->getCategories();

	expect( $categories )->toContain( 'text' );
	expect( $categories )->toHaveCount( 1 );
} );
