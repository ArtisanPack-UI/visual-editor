<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\SiteTagline\SiteTaglineBlock;

test( 'site tagline block has correct type', function (): void {
	$block = new SiteTaglineBlock();

	expect( $block->getType() )->toBe( 'site-tagline' );
} );

test( 'site tagline block has correct category', function (): void {
	$block = new SiteTaglineBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'site tagline block has content schema with level field', function (): void {
	$block  = new SiteTaglineBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'level' ] )
		->and( $schema['level']['type'] )->toBe( 'select' )
		->and( $schema['level']['default'] )->toBe( 'p' );
} );

test( 'site tagline block level options include h1 through h6 plus p and span', function (): void {
	$block   = new SiteTaglineBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['level']['options'] );

	expect( $options )->toContain( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span' );
} );

test( 'site tagline block default content has correct values', function (): void {
	$block    = new SiteTaglineBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['level'] )->toBe( 'p' );
} );

test( 'site tagline block has toolbar controls', function (): void {
	$block    = new SiteTaglineBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'level' );
} );

test( 'site tagline block has keywords', function (): void {
	$block = new SiteTaglineBlock();

	expect( $block->getKeywords() )->toContain( 'site' )
		->and( $block->getKeywords() )->toContain( 'tagline' );
} );

test( 'site tagline block supports typography', function (): void {
	$block    = new SiteTaglineBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'site tagline block supports spacing', function (): void {
	$block    = new SiteTaglineBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'site tagline block supports color', function (): void {
	$block    = new SiteTaglineBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );
