<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostExcerpt\PostExcerptBlock;

test( 'post excerpt block has correct type', function (): void {
	$block = new PostExcerptBlock();

	expect( $block->getType() )->toBe( 'post-excerpt' );
} );

test( 'post excerpt block has correct category', function (): void {
	$block = new PostExcerptBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post excerpt block has content schema with excerpt fields', function (): void {
	$block  = new PostExcerptBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'excerptLength', 'moreText', 'showMoreOnNewLine' ] )
		->and( $schema['excerptLength']['type'] )->toBe( 'range' )
		->and( $schema['excerptLength']['default'] )->toBe( 55 )
		->and( $schema['excerptLength']['min'] )->toBe( 5 )
		->and( $schema['excerptLength']['max'] )->toBe( 200 )
		->and( $schema['moreText']['type'] )->toBe( 'text' )
		->and( $schema['moreText']['default'] )->toBe( '' )
		->and( $schema['showMoreOnNewLine']['type'] )->toBe( 'toggle' )
		->and( $schema['showMoreOnNewLine']['default'] )->toBeTrue();
} );

test( 'post excerpt block default content has correct values', function (): void {
	$block    = new PostExcerptBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['excerptLength'] )->toBe( 55 )
		->and( $defaults['moreText'] )->toBe( '' )
		->and( $defaults['showMoreOnNewLine'] )->toBeTrue();
} );

test( 'post excerpt block has keywords', function (): void {
	$block = new PostExcerptBlock();

	expect( $block->getKeywords() )->toContain( 'excerpt' )
		->and( $block->getKeywords() )->toContain( 'summary' );
} );

test( 'post excerpt block supports typography', function (): void {
	$block    = new PostExcerptBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post excerpt block supports color', function (): void {
	$block    = new PostExcerptBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post excerpt block supports spacing', function (): void {
	$block    = new PostExcerptBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post excerpt block is marked as dynamic', function (): void {
	$block = new PostExcerptBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
