<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\ColumnBlock;

test( 'column block has correct type and category', function (): void {
	$block = new ColumnBlock();

	expect( $block->getType() )->toBe( 'column' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'column block has empty content schema', function (): void {
	$block  = new ColumnBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'column block style schema has width and vertical alignment fields', function (): void {
	$block  = new ColumnBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'width' );
	expect( $schema )->toHaveKey( 'verticalAlignment' );
} );

test( 'column block only allows columns parent', function (): void {
	$block = new ColumnBlock();

	expect( $block->getAllowedParents() )->toBe( [ 'columns' ] );
} );

test( 'column block is not public', function (): void {
	$block = new ColumnBlock();

	expect( $block->isPublic() )->toBeFalse();
} );

test( 'column block renders with flex column layout', function (): void {
	$block  = new ColumnBlock();
	$output = $block->render( [], [ 'width' => '50%', 'verticalAlignment' => 'center' ] );

	expect( $output )->toContain( 've-block-column' );
	expect( $output )->toContain( 'flex-basis: 50%' );
	expect( $output )->toContain( 'center' );
} );

test( 'column block renders with flex 1 when no width set', function (): void {
	$block  = new ColumnBlock();
	$output = $block->render( [], [ 'width' => '', 'verticalAlignment' => 'top' ] );

	expect( $output )->toContain( 'flex: 1' );
} );
