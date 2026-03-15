<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Dynamic\TableOfContents\TableOfContentsBlock;
use ArtisanPackUI\VisualEditor\Livewire\Blocks\TableOfContentsBlockComponent;

test( 'table of contents block has correct type', function (): void {
	$block = new TableOfContentsBlock();

	expect( $block->getType() )->toBe( 'table-of-contents' );
} );

test( 'table of contents block is dynamic', function (): void {
	$block = new TableOfContentsBlock();

	expect( $block->isDynamic() )->toBeTrue();
} );

test( 'table of contents block has correct category', function (): void {
	$block = new TableOfContentsBlock();

	expect( $block->getCategory() )->toBe( 'dynamic' );
} );

test( 'table of contents block returns correct component', function (): void {
	$block = new TableOfContentsBlock();

	expect( $block->getComponent() )->toBe( TableOfContentsBlockComponent::class );
} );

test( 'table of contents block has content schema with toc fields', function (): void {
	$block  = new TableOfContentsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKeys( [
		'headingLevels', 'listStyle', 'hierarchical',
		'maxDepth', 'title', 'collapsible', 'smoothScroll',
	] );
} );

test( 'table of contents block default content has correct values', function (): void {
	$block    = new TableOfContentsBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['headingLevels'] )->toBe( [ 2, 3 ] )
		->and( $defaults['listStyle'] )->toBe( 'numbered' )
		->and( $defaults['hierarchical'] )->toBeTrue()
		->and( $defaults['maxDepth'] )->toBe( 3 )
		->and( $defaults['collapsible'] )->toBeFalse()
		->and( $defaults['smoothScroll'] )->toBeTrue();
} );

test( 'table of contents block toArray includes dynamic metadata', function (): void {
	$block = new TableOfContentsBlock();
	$array = $block->toArray();

	expect( $array['dynamic'] )->toBeTrue()
		->and( $array['component'] )->toBe( TableOfContentsBlockComponent::class )
		->and( $array['type'] )->toBe( 'table-of-contents' );
} );

test( 'table of contents block has keywords', function (): void {
	$block = new TableOfContentsBlock();

	expect( $block->getKeywords() )->toContain( 'toc' )
		->and( $block->getKeywords() )->toContain( 'headings' );
} );
