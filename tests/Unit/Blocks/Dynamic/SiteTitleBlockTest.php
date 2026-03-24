<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\SiteTitle\SiteTitleBlock;

test( 'site title block has correct type', function (): void {
	$block = new SiteTitleBlock();

	expect( $block->getType() )->toBe( 'site-title' );
} );

test( 'site title block has correct category', function (): void {
	$block = new SiteTitleBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'site title block has content schema with level and link fields', function (): void {
	$block  = new SiteTitleBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'level', 'isLink', 'linkTarget' ] )
		->and( $schema['level']['type'] )->toBe( 'select' )
		->and( $schema['level']['default'] )->toBe( 'h1' )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeTrue()
		->and( $schema['linkTarget']['type'] )->toBe( 'select' )
		->and( $schema['linkTarget']['default'] )->toBe( '_self' );
} );

test( 'site title block level options include h1 through h6 plus p and span', function (): void {
	$block   = new SiteTitleBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['level']['options'] );

	expect( $options )->toContain( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span' );
} );

test( 'site title block default content has correct values', function (): void {
	$block    = new SiteTitleBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['level'] )->toBe( 'h1' )
		->and( $defaults['isLink'] )->toBeTrue()
		->and( $defaults['linkTarget'] )->toBe( '_self' );
} );

test( 'site title block has toolbar controls', function (): void {
	$block    = new SiteTitleBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'level' );
} );

test( 'site title block has keywords', function (): void {
	$block = new SiteTitleBlock();

	expect( $block->getKeywords() )->toContain( 'site' )
		->and( $block->getKeywords() )->toContain( 'title' );
} );

test( 'site title block supports typography', function (): void {
	$block    = new SiteTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'site title block supports color', function (): void {
	$block    = new SiteTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'site title block supports spacing', function (): void {
	$block    = new SiteTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );
