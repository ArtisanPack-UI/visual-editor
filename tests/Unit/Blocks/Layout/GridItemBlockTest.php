<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\GridItemBlock;

test( 'grid item block has correct type and category', function (): void {
	$block = new GridItemBlock();

	expect( $block->getType() )->toBe( 'grid-item' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'grid item block has empty content schema', function (): void {
	$block  = new GridItemBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'grid item block has empty style schema', function (): void {
	$block  = new GridItemBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'grid item block attributes include columnSpan rowSpan and verticalAlignment', function (): void {
	$block      = new GridItemBlock();
	$attributes = $block->getAttributes();

	expect( $attributes )->toHaveKey( 'columnSpan' );
	expect( $attributes )->toHaveKey( 'rowSpan' );
	expect( $attributes )->toHaveKey( 'verticalAlignment' );
	expect( $attributes['columnSpan']['source'] )->toBe( 'style' );
	expect( $attributes['rowSpan']['source'] )->toBe( 'style' );
	expect( $attributes['verticalAlignment']['source'] )->toBe( 'style' );
} );

test( 'grid item block only allows grid parent', function (): void {
	$block = new GridItemBlock();

	expect( $block->getAllowedParents() )->toBe( [ 'grid' ] );
} );

test( 'grid item block is not public', function (): void {
	$block = new GridItemBlock();

	expect( $block->isPublic() )->toBeFalse();
} );

test( 'grid item block with columnSpan 2 renders grid-column span', function (): void {
	$block  = new GridItemBlock();
	$output = $block->render(
		[],
		[ 'columnSpan' => [ 'mode' => 'global', 'global' => 2, 'desktop' => 2, 'tablet' => 2, 'mobile' => 2 ], 'rowSpan' => [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ], 'verticalAlignment' => 'stretch' ],
	);

	expect( $output )->toContain( 'grid-column: span 2' );
} );

test( 'grid item block with default span 1 does not contain grid-column span', function (): void {
	$block  = new GridItemBlock();
	$output = $block->render(
		[],
		[ 'columnSpan' => [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ], 'rowSpan' => [ 'mode' => 'global', 'global' => 1, 'desktop' => 1, 'tablet' => 1, 'mobile' => 1 ], 'verticalAlignment' => 'stretch' ],
	);

	expect( $output )->not->toContain( 'grid-column: span' );
} );

test( 'grid item block supports all background sub-keys', function (): void {
	$block = new GridItemBlock();

	expect( $block->supportsFeature( 'background.backgroundImage' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundSize' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundPosition' ) )->toBeTrue();
	expect( $block->supportsFeature( 'background.backgroundGradient' ) )->toBeTrue();
} );

test( 'grid item block active style supports include background sub-keys', function (): void {
	$block  = new GridItemBlock();
	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'background.backgroundImage' );
	expect( $active )->toContain( 'background.backgroundSize' );
	expect( $active )->toContain( 'background.backgroundPosition' );
	expect( $active )->toContain( 'background.backgroundGradient' );
} );
