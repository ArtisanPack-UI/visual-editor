<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockRegistry;
use ArtisanPackUI\VisualEditor\Blocks\BlockTransformService;
use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'transform service returns available transforms', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	$service = new BlockTransformService( $registry );

	expect( $service->getAvailableTransforms( 'stub' ) )->toBe( [ 'paragraph' ] );
} );

test( 'transform service returns empty array for nonexistent block', function (): void {
	$registry = new BlockRegistry();
	$service  = new BlockTransformService( $registry );

	expect( $service->getAvailableTransforms( 'nonexistent' ) )->toBe( [] );
} );

test( 'transform service returns null when target block not registered', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	$service = new BlockTransformService( $registry );
	$result  = $service->transform( $block, 'paragraph', [ 'text' => 'Hello' ] );

	expect( $result )->toBeNull();
} );

test( 'transform service returns null for unavailable transform', function (): void {
	$registry = new BlockRegistry();
	$block    = new StubBlock();

	$registry->register( $block );

	$service = new BlockTransformService( $registry );
	$result  = $service->transform( $block, 'image', [ 'text' => 'Hello' ] );

	expect( $result )->toBeNull();
} );
