<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostAuthorBiography\PostAuthorBiographyBlock;

test( 'post author biography block has correct type', function (): void {
	$block = new PostAuthorBiographyBlock();

	expect( $block->getType() )->toBe( 'post-author-biography' );
} );

test( 'post author biography block has correct category', function (): void {
	$block = new PostAuthorBiographyBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post author biography block has empty content schema', function (): void {
	$block  = new PostAuthorBiographyBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'post author biography block has keywords', function (): void {
	$block = new PostAuthorBiographyBlock();

	expect( $block->getKeywords() )->toContain( 'author' )
		->and( $block->getKeywords() )->toContain( 'biography' );
} );

test( 'post author biography block supports typography', function (): void {
	$block    = new PostAuthorBiographyBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post author biography block supports color', function (): void {
	$block    = new PostAuthorBiographyBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post author biography block supports spacing', function (): void {
	$block    = new PostAuthorBiographyBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post author biography block is marked as dynamic', function (): void {
	$block = new PostAuthorBiographyBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
