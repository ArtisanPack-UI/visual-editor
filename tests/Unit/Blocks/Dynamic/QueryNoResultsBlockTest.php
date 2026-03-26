<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryNoResults\QueryNoResultsBlock;

test( 'query no results block has correct type', function (): void {
	$block = new QueryNoResultsBlock();

	expect( $block->getType() )->toBe( 'query-no-results' );
} );

test( 'query no results block has correct category', function (): void {
	$block = new QueryNoResultsBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'query no results block has empty content schema', function (): void {
	$block  = new QueryNoResultsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'query no results block has keywords', function (): void {
	$block = new QueryNoResultsBlock();

	expect( $block->getKeywords() )->toContain( 'query' )
		->and( $block->getKeywords() )->toContain( 'no results' );
} );

test( 'query no results block supports spacing', function (): void {
	$block    = new QueryNoResultsBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'query no results block supports inner blocks', function (): void {
	$block = new QueryNoResultsBlock();
	$meta  = $block->toArray();

	expect( $meta['supportsInnerBlocks'] )->toBeTrue();
} );

test( 'query no results block has parent restriction', function (): void {
	$block = new QueryNoResultsBlock();
	$meta  = $block->toArray();

	expect( $meta['allowedParents'] )->toContain( 'query-loop' );
} );

test( 'query no results block is marked as dynamic', function (): void {
	$block = new QueryNoResultsBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
