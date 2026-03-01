<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\Heading\HeadingBlock;
use Tests\Unit\Blocks\Stubs\StubBlock;

test( 'getActiveStyleSupports returns active style supports', function (): void {
	$block = new HeadingBlock();

	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'color.text' );
	expect( $active )->toContain( 'color.background' );
	expect( $active )->toContain( 'typography.fontSize' );
} );

test( 'getActiveStyleSupports excludes disabled supports', function (): void {
	$block = new HeadingBlock();

	$active = $block->getActiveStyleSupports();

	expect( $active )->not->toContain( 'typography.fontFamily' );
	expect( $active )->toContain( 'spacing.margin' );
	expect( $active )->toContain( 'spacing.padding' );
	expect( $active )->toContain( 'border' );
	expect( $active )->not->toContain( 'shadow' );
} );

test( 'stub block has correct active style supports', function (): void {
	$block = new StubBlock();

	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'color.text' );
	expect( $active )->toContain( 'typography.fontSize' );
	expect( $active )->not->toContain( 'color.background' );
} );

test( 'heading block supports from block.json include new default types', function (): void {
	$block    = new HeadingBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'shadow' );
	expect( $supports )->toHaveKey( 'dimensions' );
	expect( $supports )->toHaveKey( 'background' );
	expect( $supports['shadow'] )->toBeFalse();
	expect( $supports['dimensions']['aspectRatio'] )->toBeFalse();
	expect( $supports['dimensions']['minHeight'] )->toBeFalse();
	expect( $supports['background']['backgroundImage'] )->toBeFalse();
	expect( $supports['spacing']['blockSpacing'] )->toBeFalse();
} );
