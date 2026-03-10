<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BaseBlock;
use ArtisanPackUI\VisualEditor\Blocks\BlockDiscoveryService;
use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock;
use Tests\Unit\Blocks\Stubs\StubBlock;

beforeEach( function (): void {
	BaseBlock::resetCachedManifest();

	$service = app( BlockDiscoveryService::class );
	$path    = $service->manifestPath();

	if ( file_exists( $path ) ) {
		unlink( $path );
	}
} );

afterEach( function (): void {
	BaseBlock::resetCachedManifest();

	$service = app( BlockDiscoveryService::class );
	$path    = $service->manifestPath();

	if ( file_exists( $path ) ) {
		unlink( $path );
	}
} );

test( 'block loads metadata from disk when no cache exists', function (): void {
	$block = new HeadingBlock();

	expect( $block->getMetadata() )->not->toBeNull();
	expect( $block->getType() )->toBe( 'heading' );
	expect( $block->getName() )->toBe( 'Heading' );
} );

test( 'block loads metadata from cache when manifest exists', function (): void {
	$this->artisan( 've:cache' )->assertSuccessful();

	BaseBlock::resetCachedManifest();

	$block = new HeadingBlock();

	expect( $block->getMetadata() )->not->toBeNull();
	expect( $block->getType() )->toBe( 'heading' );
	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'stub block without block.json still works with cache', function (): void {
	$this->artisan( 've:cache' )->assertSuccessful();

	BaseBlock::resetCachedManifest();

	$block = new StubBlock();

	expect( $block->getType() )->toBe( 'stub' );
	expect( $block->getName() )->toBe( 'Stub Block' );
	expect( $block->getMetadata() )->toBeNull();
} );

test( 'stub block without block.json works without cache', function (): void {
	$block = new StubBlock();

	expect( $block->getType() )->toBe( 'stub' );
	expect( $block->getName() )->toBe( 'Stub Block' );
	expect( $block->getDescription() )->toBe( 'A stub block for testing' );
	expect( $block->getMetadata() )->toBeNull();
} );

test( 'cached metadata matches disk metadata', function (): void {
	$diskBlock = new HeadingBlock();
	$diskMeta  = $diskBlock->getMetadata();

	$this->artisan( 've:cache' )->assertSuccessful();

	BaseBlock::resetCachedManifest();

	$cachedBlock = new HeadingBlock();
	$cachedMeta  = $cachedBlock->getMetadata();

	expect( $cachedMeta )->toBe( $diskMeta );
} );
