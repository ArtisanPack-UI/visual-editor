<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryPagination\QueryPaginationBlock;

test( 'query pagination block has correct type', function (): void {
	$block = new QueryPaginationBlock();

	expect( $block->getType() )->toBe( 'query-pagination' );
} );

test( 'query pagination block has correct category', function (): void {
	$block = new QueryPaginationBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'query pagination block has content schema with all fields', function (): void {
	$block  = new QueryPaginationBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'showNumbers', 'showPrevNext', 'previousLabel', 'nextLabel', 'midSize' ] )
		->and( $schema['showNumbers']['type'] )->toBe( 'toggle' )
		->and( $schema['showNumbers']['default'] )->toBeTrue()
		->and( $schema['showPrevNext']['type'] )->toBe( 'toggle' )
		->and( $schema['showPrevNext']['default'] )->toBeTrue()
		->and( $schema['previousLabel']['type'] )->toBe( 'text' )
		->and( $schema['previousLabel']['default'] )->toBe( '' )
		->and( $schema['nextLabel']['type'] )->toBe( 'text' )
		->and( $schema['nextLabel']['default'] )->toBe( '' )
		->and( $schema['midSize']['type'] )->toBe( 'number' )
		->and( $schema['midSize']['default'] )->toBe( 2 );
} );

test( 'query pagination block default content has correct values', function (): void {
	$block    = new QueryPaginationBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['showNumbers'] )->toBeTrue()
		->and( $defaults['showPrevNext'] )->toBeTrue()
		->and( $defaults['previousLabel'] )->toBe( '' )
		->and( $defaults['nextLabel'] )->toBe( '' )
		->and( $defaults['midSize'] )->toBe( 2 );
} );

test( 'query pagination block has keywords', function (): void {
	$block = new QueryPaginationBlock();

	expect( $block->getKeywords() )->toContain( 'query' )
		->and( $block->getKeywords() )->toContain( 'pagination' );
} );

test( 'query pagination block supports typography', function (): void {
	$block    = new QueryPaginationBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'query pagination block supports color', function (): void {
	$block    = new QueryPaginationBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'query pagination block supports spacing', function (): void {
	$block    = new QueryPaginationBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'query pagination block has parent restriction', function (): void {
	$block = new QueryPaginationBlock();
	$meta  = $block->toArray();

	expect( $meta['allowedParents'] )->toContain( 'query-loop' );
} );

test( 'query pagination block is marked as dynamic', function (): void {
	$block = new QueryPaginationBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
