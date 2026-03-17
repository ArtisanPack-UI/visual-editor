<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\TabPanelBlock;

test( 'tab panel block has correct type and category', function (): void {
	$block = new TabPanelBlock();

	expect( $block->getType() )->toBe( 'tab-panel' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'tab panel block is not public and requires tabs parent', function (): void {
	$block = new TabPanelBlock();

	expect( $block->isPublic() )->toBeFalse();
	expect( $block->getAllowedParents() )->toBe( [ 'tabs' ] );
} );

test( 'tab panel block supports inner blocks with vertical orientation', function (): void {
	$block = new TabPanelBlock();

	expect( $block->supportsInnerBlocks() )->toBeTrue();
	expect( $block->getInnerBlocksOrientation() )->toBe( 'vertical' );
} );

test( 'tab panel block content schema has label and icon fields', function (): void {
	$block  = new TabPanelBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'label' );
	expect( $schema )->toHaveKey( 'icon' );
	expect( $schema['label']['type'] )->toBe( 'text' );
	expect( $schema['icon']['type'] )->toBe( 'text' );
} );

test( 'tab panel block renders with correct class', function (): void {
	$block  = new TabPanelBlock();
	$output = $block->render(
		[ 'label' => 'Tab 1' ],
		[],
	);

	expect( $output )->toContain( 've-block-tab-panel' );
} );

test( 'tab panel block supports background color', function (): void {
	$block = new TabPanelBlock();

	expect( $block->supportsFeature( 'color.background' ) )->toBeTrue();
} );

test( 'tab panel block allows any children', function (): void {
	$block = new TabPanelBlock();

	expect( $block->getAllowedChildren() )->toBeNull();
} );
