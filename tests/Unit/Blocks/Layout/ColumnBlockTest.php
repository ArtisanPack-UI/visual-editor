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

test( 'column block style schema is empty since width and alignment are in settings and toolbar', function (): void {
	$block  = new ColumnBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toBeEmpty();
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

test( 'column block supports all background sub-keys but not shadow or dimensions', function (): void {
	$block = new ColumnBlock();

	expect( $block->supportsFeature( 'background.backgroundImage' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundSize' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundPosition' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundGradient' ) )->toBeTrue();
	expect( $block->supportsFeature( 'shadow' ) )->toBeFalse();
	expect( $block->supportsFeature( 'dimensions.aspectRatio' ) )->toBeFalse();
	expect( $block->supportsFeature( 'dimensions.minHeight' ) )->toBeFalse();
} );

test( 'column block active style supports include background sub-keys', function (): void {
	$block  = new ColumnBlock();
	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'background.backgroundImage' );
	expect( $active )->toContain( 'background.backgroundSize' );
	expect( $active )->toContain( 'background.backgroundPosition' );
	expect( $active )->toContain( 'background.backgroundGradient' );
	expect( $active )->not->toContain( 'shadow' );
} );

test( 'column block has custom toolbar', function (): void {
	$block = new ColumnBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );
