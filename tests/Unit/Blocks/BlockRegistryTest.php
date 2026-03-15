<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use Tests\Unit\Blocks\Stubs\StubBlock;
use Tests\Unit\Blocks\Stubs\StubContainerBlock;
use Tests\Unit\Blocks\Stubs\StubDynamicBlock;

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

test( 'registry rejects blocks with empty type', function (): void {
	$registry = new BlockRegistry();
	$block    = new class extends ArtisanPackUI\VisualEditor\Blocks\BaseBlock {
		public function getContentSchema(): array
		{
		return [];
		}

		public function getStyleSchema(): array
		{
		return [];
		}
	};

	$registry->register( $block );
} )->throws( InvalidArgumentException::class, 'Block type must be a non-empty string.' );

test( 'registry clear removes all blocks', function (): void {
	$registry = new BlockRegistry();

	$registry->register( new StubBlock() );
	$registry->register( new StubContainerBlock() );

	expect( $registry->all() )->toHaveCount( 2 );

	$registry->clear();

	expect( $registry->all() )->toBeEmpty();
} );

test( 'registry get container blocks filters correctly', function (): void {
	$registry = new BlockRegistry();

	$registry->register( new StubBlock() );
	$registry->register( new StubContainerBlock() );

	$containers = $registry->getContainerBlocks();

	expect( $containers )->toHaveCount( 1 )
		->and( $containers )->toHaveKey( 'stub-container' );
} );

test( 'registry get dynamic blocks filters correctly', function (): void {
	$registry = new BlockRegistry();

	$registry->register( new StubBlock() );
	$registry->register( new StubContainerBlock() );
	$registry->register( new StubDynamicBlock() );

	$dynamic = $registry->getDynamicBlocks();

	expect( $dynamic )->toHaveCount( 1 )
		->and( $dynamic )->toHaveKey( 'stub-dynamic' );
} );

test( 'registry get js renderer blocks filters correctly', function (): void {
	$registry = new BlockRegistry();

	$registry->register( new StubBlock() );
	$registry->register( new StubContainerBlock() );
	$registry->register( new StubDynamicBlock() );

	$jsRendered = $registry->getJsRendererBlocks();

	expect( $jsRendered )->toHaveCount( 1 )
		->and( $jsRendered )->toHaveKey( 'stub-container' );
} );

test( 'registry to array serializes all blocks', function (): void {
	$registry = new BlockRegistry();

	$registry->register( new StubBlock() );
	$registry->register( new StubContainerBlock() );

	$array = $registry->toArray();

	expect( $array )->toHaveKeys( [ 'stub', 'stub-container' ] )
		->and( $array['stub']['type'] )->toBe( 'stub' )
		->and( $array['stub']['supportsInnerBlocks'] )->toBeFalse()
		->and( $array['stub-container']['supportsInnerBlocks'] )->toBeTrue()
		->and( $array['stub-container']['hasJsRenderer'] )->toBeTrue()
		->and( $array['stub-container']['innerBlocksOrientation'] )->toBe( 'horizontal' );
} );
