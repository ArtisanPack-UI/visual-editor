<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTerms\PostTermsBlock;

test( 'post terms block has correct type', function (): void {
	$block = new PostTermsBlock();

	expect( $block->getType() )->toBe( 'post-terms' );
} );

test( 'post terms block has correct category', function (): void {
	$block = new PostTermsBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post terms block has content schema with all fields', function (): void {
	$block  = new PostTermsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'term', 'separator', 'prefix', 'suffix' ] )
		->and( $schema['term']['type'] )->toBe( 'text' )
		->and( $schema['term']['default'] )->toBe( 'category' )
		->and( $schema['separator']['type'] )->toBe( 'text' )
		->and( $schema['separator']['default'] )->toBe( ', ' )
		->and( $schema['prefix']['type'] )->toBe( 'text' )
		->and( $schema['prefix']['default'] )->toBe( '' )
		->and( $schema['suffix']['type'] )->toBe( 'text' )
		->and( $schema['suffix']['default'] )->toBe( '' );
} );

test( 'post terms block default content has correct values', function (): void {
	$block    = new PostTermsBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['term'] )->toBe( 'category' )
		->and( $defaults['separator'] )->toBe( ', ' )
		->and( $defaults['prefix'] )->toBe( '' )
		->and( $defaults['suffix'] )->toBe( '' );
} );

test( 'post terms block has keywords', function (): void {
	$block = new PostTermsBlock();

	expect( $block->getKeywords() )->toContain( 'terms' )
		->and( $block->getKeywords() )->toContain( 'category' )
		->and( $block->getKeywords() )->toContain( 'tag' );
} );

test( 'post terms block supports typography', function (): void {
	$block    = new PostTermsBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post terms block supports color', function (): void {
	$block    = new PostTermsBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post terms block supports spacing', function (): void {
	$block    = new PostTermsBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post terms block is marked as dynamic', function (): void {
	$block = new PostTermsBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
