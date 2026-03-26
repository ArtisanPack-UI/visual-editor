<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\QueryTitle\QueryTitleBlock;

test( 'query title block has correct type', function (): void {
	$block = new QueryTitleBlock();

	expect( $block->getType() )->toBe( 'query-title' );
} );

test( 'query title block has correct category', function (): void {
	$block = new QueryTitleBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'query title block has content schema with all fields', function (): void {
	$block  = new QueryTitleBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [ 'level', 'showPrefix', 'prefixType' ] )
		->and( $schema['level']['type'] )->toBe( 'select' )
		->and( $schema['level']['default'] )->toBe( 'h1' )
		->and( $schema['showPrefix']['type'] )->toBe( 'toggle' )
		->and( $schema['showPrefix']['default'] )->toBeTrue()
		->and( $schema['prefixType']['type'] )->toBe( 'select' )
		->and( $schema['prefixType']['default'] )->toBe( 'archive' );
} );

test( 'query title block level options include all heading levels', function (): void {
	$block   = new QueryTitleBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['level']['options'] );

	expect( $options )->toContain( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' );
} );

test( 'query title block prefix type options include archive and search', function (): void {
	$block   = new QueryTitleBlock();
	$schema  = $block->getContentSchema();
	$options = array_keys( $schema['prefixType']['options'] );

	expect( $options )->toContain( 'archive', 'search' );
} );

test( 'query title block default content has correct values', function (): void {
	$block    = new QueryTitleBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['level'] )->toBe( 'h1' )
		->and( $defaults['showPrefix'] )->toBeTrue()
		->and( $defaults['prefixType'] )->toBe( 'archive' );
} );

test( 'query title block has keywords', function (): void {
	$block = new QueryTitleBlock();

	expect( $block->getKeywords() )->toContain( 'query' )
		->and( $block->getKeywords() )->toContain( 'title' );
} );

test( 'query title block supports typography', function (): void {
	$block    = new QueryTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'typography' )
		->and( $supports['typography']['fontSize'] )->toBeTrue();
} );

test( 'query title block supports color', function (): void {
	$block    = new QueryTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'color' )
		->and( $supports['color']['text'] )->toBeTrue()
		->and( $supports['color']['background'] )->toBeTrue();
} );

test( 'query title block supports spacing', function (): void {
	$block    = new QueryTitleBlock();
	$supports = $block->getSupports();

	expect( $supports )->toHaveKey( 'spacing' )
		->and( $supports['spacing']['margin'] )->toBeTrue()
		->and( $supports['spacing']['padding'] )->toBeTrue();
} );

test( 'query title block has toolbar controls', function (): void {
	$block    = new QueryTitleBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->not->toBeEmpty()
		->and( $controls[0]['group'] )->toBe( 'block' )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'level' );
} );

test( 'query title block is marked as dynamic', function (): void {
	$block = new QueryTitleBlock();
	$meta  = $block->toArray();

	expect( $meta['dynamic'] )->toBeTrue();
} );
