<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostNavigationLink\PostNavigationLinkBlock;

test( 'post navigation link block has correct type', function (): void {
	$block = new PostNavigationLinkBlock();

	expect( $block->getType() )->toBe( 'post-navigation-link' );
} );

test( 'post navigation link block has correct category', function (): void {
	$block = new PostNavigationLinkBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post navigation link block has content schema with type label show title arrow and taxonomy fields', function (): void {
	$block  = new PostNavigationLinkBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'type', 'label', 'showTitle', 'arrow', 'taxonomy' ] )
		->and( $schema['type']['type'] )->toBe( 'select' )
		->and( $schema['type']['default'] )->toBe( 'previous' )
		->and( $schema['label']['type'] )->toBe( 'text' )
		->and( $schema['label']['default'] )->toBe( '' )
		->and( $schema['showTitle']['type'] )->toBe( 'toggle' )
		->and( $schema['showTitle']['default'] )->toBeTrue()
		->and( $schema['arrow']['type'] )->toBe( 'select' )
		->and( $schema['arrow']['default'] )->toBe( 'none' )
		->and( $schema['taxonomy']['type'] )->toBe( 'text' )
		->and( $schema['taxonomy']['default'] )->toBe( '' );
} );

test( 'post navigation link block type options include previous and next', function (): void {
	$block   = new PostNavigationLinkBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['type']['options'] );

	expect( $options )->toContain( 'previous', 'next' );
} );

test( 'post navigation link block arrow options include none arrow and chevron', function (): void {
	$block   = new PostNavigationLinkBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['arrow']['options'] );

	expect( $options )->toContain( 'none', 'arrow', 'chevron' );
} );

test( 'post navigation link block default content has correct values', function (): void {
	$block    = new PostNavigationLinkBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['type'] )->toBe( 'previous' )
		->and( $defaults['label'] )->toBe( '' )
		->and( $defaults['showTitle'] )->toBeTrue()
		->and( $defaults['arrow'] )->toBe( 'none' )
		->and( $defaults['taxonomy'] )->toBe( '' );
} );

test( 'post navigation link block has toolbar controls', function (): void {
	$block    = new PostNavigationLinkBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'type' );
} );

test( 'post navigation link block has keywords', function (): void {
	$block = new PostNavigationLinkBlock();

	expect( $block->getKeywords() )->toContain( 'navigation' )
		->and( $block->getKeywords() )->toContain( 'previous' )
		->and( $block->getKeywords() )->toContain( 'next' );
} );

test( 'post navigation link block supports typography', function (): void {
	$block    = new PostNavigationLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'post navigation link block supports color', function (): void {
	$block    = new PostNavigationLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'post navigation link block supports spacing', function (): void {
	$block    = new PostNavigationLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post navigation link block supports border', function (): void {
	$block    = new PostNavigationLinkBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'border' )
		->and( $supports['border'] )->toBeTrue();
} );

test( 'post navigation link block is marked as dynamic', function (): void {
	$block = new PostNavigationLinkBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
