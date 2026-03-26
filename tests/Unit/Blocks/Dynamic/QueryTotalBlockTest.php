<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryTotal\QueryTotalBlock;

test( 'query total block has correct type', function (): void {
	$block = new QueryTotalBlock();

	expect( $block->getType() )->toBe( 'query-total' );
} );

test( 'query total block has correct category', function (): void {
	$block = new QueryTotalBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'query total block has content schema with all fields', function (): void {
	$block  = new QueryTotalBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'format', 'template' ] )
		->and( $schema['format']['type'] )->toBe( 'select' )
		->and( $schema['format']['default'] )->toBe( 'text' )
		->and( $schema['template']['type'] )->toBe( 'text' )
		->and( $schema['template']['default'] )->toBe( '' );
} );

test( 'query total block format options include number and text', function (): void {
	$block   = new QueryTotalBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['format']['options'] );

	expect( $options )->toContain( 'number', 'text' );
} );

test( 'query total block default content has correct values', function (): void {
	$block    = new QueryTotalBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['format'] )->toBe( 'text' )
		->and( $defaults['template'] )->toBe( '' );
} );

test( 'query total block has keywords', function (): void {
	$block = new QueryTotalBlock();

	expect( $block->getKeywords() )->toContain( 'query' )
		->and( $block->getKeywords() )->toContain( 'total' );
} );

test( 'query total block supports typography', function (): void {
	$block    = new QueryTotalBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'query total block supports color', function (): void {
	$block    = new QueryTotalBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'query total block supports spacing', function (): void {
	$block    = new QueryTotalBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'query total block is marked as dynamic', function (): void {
	$block = new QueryTotalBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
