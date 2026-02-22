<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Text\ListBlock;

test( 'list block has correct type', function (): void {
	$block = new ListBlock();

	expect( $block->getType() )->toBe( 'list' );
} );

test( 'list block has correct category', function (): void {
	$block = new ListBlock();

	expect( $block->getCategory() )->toBe( 'text' );
} );

test( 'list block content schema has type and items fields', function (): void {
	$block  = new ListBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'type' );
	expect( $schema )->toHaveKey( 'items' );
	expect( $schema )->toHaveKey( 'start' );
	expect( $schema )->toHaveKey( 'reversed' );
} );

test( 'list block defaults to unordered type', function (): void {
	$block    = new ListBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['type'] )->toBe( 'unordered' );
} );

test( 'list block transforms to paragraph', function (): void {
	$block      = new ListBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'paragraph' );
} );

test( 'list block renders unordered list', function (): void {
	$block  = new ListBlock();
	$items  = [
		[ 'text' => 'Item 1' ],
		[ 'text' => 'Item 2' ],
	];
	$output = $block->render( [ 'type' => 'unordered', 'items' => $items ], [] );

	expect( $output )->toContain( '<ul' );
	expect( $output )->toContain( '<li>' );
	expect( $output )->toContain( 'Item 1' );
	expect( $output )->toContain( 'Item 2' );
} );

test( 'list block renders ordered list', function (): void {
	$block  = new ListBlock();
	$items  = [ [ 'text' => 'First' ] ];
	$output = $block->render( [ 'type' => 'ordered', 'items' => $items, 'start' => '1' ], [] );

	expect( $output )->toContain( '<ol' );
} );

test( 'list block renders reversed ordered list', function (): void {
	$block  = new ListBlock();
	$items  = [ [ 'text' => 'Last' ] ];
	$output = $block->render( [ 'type' => 'ordered', 'items' => $items, 'start' => '1', 'reversed' => true ], [] );

	expect( $output )->toContain( 'reversed' );
} );
