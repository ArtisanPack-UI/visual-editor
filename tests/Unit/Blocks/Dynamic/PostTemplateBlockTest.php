<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\PostTemplate\PostTemplateBlock;

test( 'post template block has correct type', function (): void {
	$block = new PostTemplateBlock();

	expect( $block->getType() )->toBe( 'post-template' );
} );

test( 'post template block has correct category', function (): void {
	$block = new PostTemplateBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'post template block has content schema with all fields', function (): void {
	$block  = new PostTemplateBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'layout', 'columns' ] )
		->and( $schema['layout']['type'] )->toBe( 'select' )
		->and( $schema['layout']['default'] )->toBe( 'list' )
		->and( $schema['columns']['type'] )->toBe( 'range' )
		->and( $schema['columns']['min'] )->toBe( 1 )
		->and( $schema['columns']['max'] )->toBe( 6 )
		->and( $schema['columns']['default'] )->toBe( 3 );
} );

test( 'post template block layout options include list and grid', function (): void {
	$block   = new PostTemplateBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['layout']['options'] );

	expect( $options )->toContain( 'list', 'grid' );
} );

test( 'post template block default content has correct values', function (): void {
	$block    = new PostTemplateBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['layout'] )->toBe( 'list' )
		->and( $defaults['columns'] )->toBe( 3 );
} );

test( 'post template block has keywords', function (): void {
	$block = new PostTemplateBlock();

	expect( $block->getKeywords() )->toContain( 'post' )
		->and( $block->getKeywords() )->toContain( 'template' );
} );

test( 'post template block supports spacing', function (): void {
	$block    = new PostTemplateBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'post template block supports inner blocks', function (): void {
	$block = new PostTemplateBlock();
	$meta  = $block->toArray();

	expect( $meta['supportsInnerBlocks'] )->toBeTrue();
} );

test( 'post template block has parent restriction', function (): void {
	$block = new PostTemplateBlock();
	$meta  = $block->toArray();

	expect( $meta['allowedParents'] )->toContain( 'query-loop' );
} );

test( 'post template block has allowed children', function (): void {
	$block = new PostTemplateBlock();
	$meta  = $block->toArray();

	expect( $meta['allowedChildren'] )->toContain( 'post-title' )
		->and( $meta['allowedChildren'] )->toContain( 'post-excerpt' )
		->and( $meta['allowedChildren'] )->toContain( 'post-featured-image' )
		->and( $meta['allowedChildren'] )->toContain( 'post-date' )
		->and( $meta['allowedChildren'] )->toContain( 'read-more' );
} );

test( 'post template block has variations', function (): void {
	$block      = new PostTemplateBlock();
	$variations = $block->getVariations();

	expect( $variations )->not->toBeEmpty()
		->and( count( $variations ) )->toBe( 5 );
} );

test( 'post template block default variation is image title excerpt', function (): void {
	$block      = new PostTemplateBlock();
	$variations = $block->getVariations();
	$default    = collect( $variations )->firstWhere( 'isDefault', true );

	expect( $default )->not->toBeNull()
		->and( $default['name'] )->toBe( 'image-title-excerpt' )
		->and( $default['innerBlocks'] )->toHaveCount( 4 );
} );

test( 'post template block variations include all starter layouts', function (): void {
	$block = new PostTemplateBlock();
	$names = array_column( $block->getVariations(), 'name' );

	expect( $names )->toContain( 'image-title-excerpt' )
		->and( $names )->toContain( 'title-date' )
		->and( $names )->toContain( 'title-excerpt-author' )
		->and( $names )->toContain( 'image-title-date' )
		->and( $names )->toContain( 'title-only' );
} );

test( 'post template block variations have inner blocks', function (): void {
	$block      = new PostTemplateBlock();
	$variations = $block->getVariations();

	foreach ( $variations as $variation ) {
		expect( $variation )->toHaveKey( 'innerBlocks' )
			->and( $variation['innerBlocks'] )->not->toBeEmpty();
	}
} );

test( 'post template block has toolbar controls', function (): void {
	$block    = new PostTemplateBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->not->toBeEmpty()
		->and( $controls[0]['group'] )->toBe( 'block' )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'layout' );
} );

test( 'post template block is marked as dynamic', function (): void {
	$block = new PostTemplateBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
