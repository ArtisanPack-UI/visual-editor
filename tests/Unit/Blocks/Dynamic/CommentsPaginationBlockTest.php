<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentsPagination\CommentsPaginationBlock;

test( 'comments pagination block has correct type', function (): void {
	$block = new CommentsPaginationBlock();

	expect( $block->getType() )->toBe( 'comments-pagination' );
} );

test( 'comments pagination block has correct category', function (): void {
	$block = new CommentsPaginationBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comments pagination block has content schema with all fields', function (): void {
	$block  = new CommentsPaginationBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'perPage', 'previousLabel', 'nextLabel', 'showNumbers' ] )
		->and( $schema['perPage']['type'] )->toBe( 'number' )
		->and( $schema['perPage']['default'] )->toBe( 20 )
		->and( $schema['previousLabel']['type'] )->toBe( 'text' )
		->and( $schema['previousLabel']['default'] )->toBe( '' )
		->and( $schema['nextLabel']['type'] )->toBe( 'text' )
		->and( $schema['nextLabel']['default'] )->toBe( '' )
		->and( $schema['showNumbers']['type'] )->toBe( 'toggle' )
		->and( $schema['showNumbers']['default'] )->toBeFalse();
} );

test( 'comments pagination block default content has correct values', function (): void {
	$block    = new CommentsPaginationBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['perPage'] )->toBe( 20 )
		->and( $defaults['previousLabel'] )->toBe( '' )
		->and( $defaults['nextLabel'] )->toBe( '' )
		->and( $defaults['showNumbers'] )->toBeFalse();
} );

test( 'comments pagination block has keywords', function (): void {
	$block = new CommentsPaginationBlock();

	expect( $block->getKeywords() )->toContain( 'comments' )
		->and( $block->getKeywords() )->toContain( 'pagination' );
} );

test( 'comments pagination block supports typography', function (): void {
	$block    = new CommentsPaginationBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'comments pagination block supports color', function (): void {
	$block    = new CommentsPaginationBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'comments pagination block supports spacing', function (): void {
	$block    = new CommentsPaginationBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'comments pagination block is marked as dynamic', function (): void {
	$block = new CommentsPaginationBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
