<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryLoop\QueryLoopBlock;

test( 'query loop block has correct type', function (): void {
	$block = new QueryLoopBlock();

	expect( $block->getType() )->toBe( 'query-loop' );
} );

test( 'query loop block has correct category', function (): void {
	$block = new QueryLoopBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'query loop block has content schema with all fields', function (): void {
	$block  = new QueryLoopBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'queryType', 'perPage', 'orderBy', 'order', 'author', 'search', 'offset', 'sticky', 'inherit' ] )
		->and( $schema['queryType']['type'] )->toBe( 'select' )
		->and( $schema['queryType']['default'] )->toBe( 'post' )
		->and( $schema['perPage']['type'] )->toBe( 'number' )
		->and( $schema['perPage']['default'] )->toBe( 10 )
		->and( $schema['orderBy']['type'] )->toBe( 'select' )
		->and( $schema['orderBy']['default'] )->toBe( 'date' )
		->and( $schema['order']['type'] )->toBe( 'select' )
		->and( $schema['order']['default'] )->toBe( 'desc' )
		->and( $schema['author']['type'] )->toBe( 'text' )
		->and( $schema['author']['default'] )->toBe( '' )
		->and( $schema['search']['type'] )->toBe( 'text' )
		->and( $schema['search']['default'] )->toBe( '' )
		->and( $schema['offset']['type'] )->toBe( 'number' )
		->and( $schema['offset']['default'] )->toBe( 0 )
		->and( $schema['sticky']['type'] )->toBe( 'select' )
		->and( $schema['sticky']['default'] )->toBe( 'include' )
		->and( $schema['inherit']['type'] )->toBe( 'toggle' )
		->and( $schema['inherit']['default'] )->toBeFalse();
} );

test( 'query loop block order by options include all sort fields', function (): void {
	$block   = new QueryLoopBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['orderBy']['options'] );

	expect( $options )->toContain( 'date', 'title', 'modified', 'random' );
} );

test( 'query loop block sticky options include all modes', function (): void {
	$block   = new QueryLoopBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['sticky']['options'] );

	expect( $options )->toContain( 'include', 'exclude', 'only' );
} );

test( 'query loop block default content has correct values', function (): void {
	$block    = new QueryLoopBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['queryType'] )->toBe( 'post' )
		->and( $defaults['perPage'] )->toBe( 10 )
		->and( $defaults['orderBy'] )->toBe( 'date' )
		->and( $defaults['order'] )->toBe( 'desc' )
		->and( $defaults['offset'] )->toBe( 0 )
		->and( $defaults['sticky'] )->toBe( 'include' )
		->and( $defaults['inherit'] )->toBeFalse();
} );

test( 'query loop block has keywords', function (): void {
	$block = new QueryLoopBlock();

	expect( $block->getKeywords() )->toContain( 'query' )
		->and( $block->getKeywords() )->toContain( 'loop' );
} );

test( 'query loop block supports spacing', function (): void {
	$block    = new QueryLoopBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'query loop block has allowed children', function (): void {
	$block = new QueryLoopBlock();
	$meta  = $block->toArray();

	expect( $meta['allowedChildren'] )->toContain( 'post-template' )
		->and( $meta['allowedChildren'] )->toContain( 'query-pagination' )
		->and( $meta['allowedChildren'] )->toContain( 'query-no-results' );
} );

test( 'query loop block content type options include post and page by default', function (): void {
	$block   = new QueryLoopBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['queryType']['options'] );

	expect( $options )->toContain( 'post', 'page' );
} );

test( 'query loop block supports inner blocks', function (): void {
	$block = new QueryLoopBlock();
	$meta  = $block->toArray();

	expect( $meta['supportsInnerBlocks'] )->toBeTrue();
} );

test( 'query loop block has default inner blocks with post template', function (): void {
	$block       = new QueryLoopBlock();
	$innerBlocks = $block->getDefaultInnerBlocks();

	expect( $innerBlocks )->toHaveCount( 1 )
		->and( $innerBlocks[0]['type'] )->toBe( 'post-template' );
} );

test( 'query loop block is marked as dynamic', function (): void {
	$block = new QueryLoopBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
