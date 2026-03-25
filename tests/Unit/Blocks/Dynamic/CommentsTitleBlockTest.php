<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\CommentsTitle\CommentsTitleBlock;

test( 'comments title block has correct type', function (): void {
	$block = new CommentsTitleBlock();

	expect( $block->getType() )->toBe( 'comments-title' );
} );

test( 'comments title block has correct category', function (): void {
	$block = new CommentsTitleBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'comments title block has content schema with all fields', function (): void {
	$block  = new CommentsTitleBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'level', 'showCount', 'singularLabel', 'pluralLabel' ] )
		->and( $schema['level']['type'] )->toBe( 'select' )
		->and( $schema['level']['default'] )->toBe( 'h2' )
		->and( $schema['showCount']['type'] )->toBe( 'toggle' )
		->and( $schema['showCount']['default'] )->toBeTrue()
		->and( $schema['singularLabel']['type'] )->toBe( 'text' )
		->and( $schema['singularLabel']['default'] )->toBe( '' )
		->and( $schema['pluralLabel']['type'] )->toBe( 'text' )
		->and( $schema['pluralLabel']['default'] )->toBe( '' );
} );

test( 'comments title block level options include all heading levels', function (): void {
	$block   = new CommentsTitleBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['level']['options'] );

	expect( $options )->toContain( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
} );

test( 'comments title block default content has correct values', function (): void {
	$block    = new CommentsTitleBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['level'] )->toBe( 'h2' )
		->and( $defaults['showCount'] )->toBeTrue()
		->and( $defaults['singularLabel'] )->toBe( '' )
		->and( $defaults['pluralLabel'] )->toBe( '' );
} );

test( 'comments title block has keywords', function (): void {
	$block = new CommentsTitleBlock();

	expect( $block->getKeywords() )->toContain( 'comments' )
		->and( $block->getKeywords() )->toContain( 'title' );
} );

test( 'comments title block supports typography', function (): void {
	$block    = new CommentsTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'comments title block supports color', function (): void {
	$block    = new CommentsTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'comments title block supports spacing', function (): void {
	$block    = new CommentsTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'comments title block is marked as dynamic', function (): void {
	$block = new CommentsTitleBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
