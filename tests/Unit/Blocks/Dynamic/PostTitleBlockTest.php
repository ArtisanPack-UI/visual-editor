<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTitle\PostTitleBlock;

test( 'post title block has correct type', function (): void {
	$block = new PostTitleBlock();

	expect( $block->getType() )->toBe( 'post-title' );
} );

test( 'post title block has correct category', function (): void {
	$block = new PostTitleBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post title block has content schema with level link and rel fields', function (): void {
	$block  = new PostTitleBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'level', 'isLink', 'linkTarget', 'rel' ] )
		->and( $schema['level']['type'] )->toBe( 'select' )
		->and( $schema['level']['default'] )->toBe( 'h1' )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeFalse()
		->and( $schema['linkTarget']['type'] )->toBe( 'select' )
		->and( $schema['linkTarget']['default'] )->toBe( '_self' )
		->and( $schema['rel']['type'] )->toBe( 'text' )
		->and( $schema['rel']['default'] )->toBe( '' );
} );

test( 'post title block level options include h1 through h6 plus p and span', function (): void {
	$block   = new PostTitleBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['level']['options'] );

	expect( $options )->toContain( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span' );
} );

test( 'post title block default content has correct values', function (): void {
	$block    = new PostTitleBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['level'] )->toBe( 'h1' )
		->and( $defaults['isLink'] )->toBeFalse()
		->and( $defaults['linkTarget'] )->toBe( '_self' )
		->and( $defaults['rel'] )->toBe( '' );
} );

test( 'post title block has toolbar controls', function (): void {
	$block    = new PostTitleBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'level' );
} );

test( 'post title block has keywords', function (): void {
	$block = new PostTitleBlock();

	expect( $block->getKeywords() )->toContain( 'post' )
		->and( $block->getKeywords() )->toContain( 'title' );
} );

test( 'post title block supports typography', function (): void {
	$block    = new PostTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post title block supports color', function (): void {
	$block    = new PostTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post title block supports spacing', function (): void {
	$block    = new PostTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post title block is marked as dynamic', function (): void {
	$block = new PostTitleBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
