<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\SiteLogo\SiteLogoBlock;

test( 'site logo block has correct type', function (): void {
	$block = new SiteLogoBlock();

	expect( $block->getType() )->toBe( 'site-logo' );
} );

test( 'site logo block has correct category', function (): void {
	$block = new SiteLogoBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'site logo block has content schema with link fields', function (): void {
	$block  = new SiteLogoBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'isLink', 'linkTarget' ] )
		->and( $schema['isLink']['type'] )->toBe( 'toggle' )
		->and( $schema['isLink']['default'] )->toBeTrue()
		->and( $schema['linkTarget']['type'] )->toBe( 'select' )
		->and( $schema['linkTarget']['default'] )->toBe( '_self' );
} );

test( 'site logo block has style schema with width and height', function (): void {
	$block  = new SiteLogoBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKeys( [ 'width', 'height' ] )
		->and( $schema['width']['type'] )->toBe( 'unit' )
		->and( $schema['height']['type'] )->toBe( 'unit' );
} );

test( 'site logo block default content has correct values', function (): void {
	$block    = new SiteLogoBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['isLink'] )->toBeTrue()
		->and( $defaults['linkTarget'] )->toBe( '_self' );
} );

test( 'site logo block has keywords', function (): void {
	$block = new SiteLogoBlock();

	expect( $block->getKeywords() )->toContain( 'site' )
		->and( $block->getKeywords() )->toContain( 'logo' );
} );

test( 'site logo block supports spacing', function (): void {
	$block    = new SiteLogoBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'site logo block supports border', function (): void {
	$block    = new SiteLogoBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'border' )
		->and( $supports['border'] )->toBeTrue();
} );
