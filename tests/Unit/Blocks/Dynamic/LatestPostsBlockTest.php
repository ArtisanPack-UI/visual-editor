<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\LatestPosts\LatestPostsBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\LatestPostsBlockComponent;

test( 'latest posts block has correct type', function (): void {
	$block = new LatestPostsBlock();

	expect( $block->getType() )->toBe( 'latest-posts' );
} );

test( 'latest posts block is dynamic', function (): void {
	$block = new LatestPostsBlock();

	expect( $block->isDynamic() )->toBeTrue();
} );

test( 'latest posts block has correct category', function (): void {
	$block = new LatestPostsBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'latest posts block returns correct component', function (): void {
	$block = new LatestPostsBlock();

	expect( $block->getComponent() )->toBe( LatestPostsBlockComponent::class );
} );

test( 'latest posts block has content schema with query fields', function (): void {
	$block  = new LatestPostsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [
		'postType', 'numberOfPosts', 'orderBy', 'order',
		'displayTemplate', 'showFeaturedImage', 'showExcerpt',
		'showDate', 'showAuthor', 'excerptLength', 'columns',
		'offset', 'excludeCurrentPost',
	] )
		->and( $schema['columns']['type'] )->toBe( 'responsive_range' );
} );

test( 'latest posts block has style schema with gap and aspect ratio', function (): void {
	$block  = new LatestPostsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKeys( [ 'gap', 'imageAspectRatio' ] );
} );

test( 'latest posts block has toolbar controls', function (): void {
	$block    = new LatestPostsBlock();
	$controls = $block->getToolbarControls();

	expect( $controls )->toHaveCount( 1 )
		->and( $controls[0]['controls'][0]['field'] )->toBe( 'displayTemplate' );
} );

test( 'latest posts block default content has correct values', function (): void {
	$block    = new LatestPostsBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['postType'] )->toBe( 'post' )
		->and( $defaults['numberOfPosts'] )->toBe( 5 )
		->and( $defaults['orderBy'] )->toBe( 'date' )
		->and( $defaults['order'] )->toBe( 'desc' )
		->and( $defaults['displayTemplate'] )->toBe( 'list' )
		->and( $defaults['showFeaturedImage'] )->toBeTrue()
		->and( $defaults['showExcerpt'] )->toBeTrue()
		->and( $defaults['showDate'] )->toBeTrue()
		->and( $defaults['showAuthor'] )->toBeFalse()
		->and( $defaults['excludeCurrentPost'] )->toBeFalse();
} );

test( 'latest posts block toArray includes dynamic metadata', function (): void {
	$block = new LatestPostsBlock();
	$array = $block->toArray();

	expect( $array['dynamic'] )->toBeTrue()
		->and( $array['component'] )->toBe( LatestPostsBlockComponent::class )
		->and( $array['type'] )->toBe( 'latest-posts' );
} );

test( 'latest posts block has keywords', function (): void {
	$block = new LatestPostsBlock();

	expect( $block->getKeywords() )->toContain( 'posts' )
		->and( $block->getKeywords() )->toContain( 'latest' );
} );
