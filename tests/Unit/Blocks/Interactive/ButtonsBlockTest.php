<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\ButtonsBlock;

test( 'buttons block has correct type and category', function (): void {
	$block = new ButtonsBlock();

	expect( $block->getType() )->toBe( 'buttons' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'buttons block is public and button block is its only allowed child', function (): void {
	$block = new ButtonsBlock();

	expect( $block->isPublic() )->toBeTrue();
	expect( $block->getAllowedChildren() )->toBe( [ 'button' ] );
} );

test( 'buttons block supports inner blocks with horizontal orientation', function (): void {
	$block = new ButtonsBlock();

	expect( $block->supportsInnerBlocks() )->toBeTrue();
	expect( $block->getInnerBlocksOrientation() )->toBe( 'horizontal' );
} );

test( 'buttons block content schema has justification orientation and flex wrap fields', function (): void {
	$block  = new ButtonsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'justification' );
	expect( $schema )->toHaveKey( 'orientation' );
	expect( $schema )->toHaveKey( 'flexWrap' );
	expect( $schema['justification']['type'] )->toBe( 'select' );
	expect( $schema['orientation']['type'] )->toBe( 'select' );
	expect( $schema['flexWrap']['type'] )->toBe( 'toggle' );
} );

test( 'buttons block defaults to left justification and horizontal orientation', function (): void {
	$block    = new ButtonsBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['justification'] )->toBe( 'left' );
	expect( $defaults['orientation'] )->toBe( 'horizontal' );
	expect( $defaults['flexWrap'] )->toBeTrue();
} );

test( 'buttons block renders with flex layout', function (): void {
	$block  = new ButtonsBlock();
	$output = $block->render(
		[ 'justification' => 'center', 'orientation' => 'horizontal', 'flexWrap' => true ],
		[],
	);

	expect( $output )->toContain( 've-block-buttons' );
	expect( $output )->toContain( 'display: flex' );
	expect( $output )->toContain( 'justify-content: center' );
	expect( $output )->toContain( 'flex-direction: row' );
	expect( $output )->toContain( 'flex-wrap: wrap' );
} );

test( 'buttons block renders vertical orientation', function (): void {
	$block  = new ButtonsBlock();
	$output = $block->render(
		[ 'justification' => 'left', 'orientation' => 'vertical', 'flexWrap' => false ],
		[],
	);

	expect( $output )->toContain( 'flex-direction: column' );
} );

test( 'buttons block has default inner blocks with one button', function (): void {
	$block         = new ButtonsBlock();
	$defaultInner  = $block->getDefaultInnerBlocks();

	expect( $defaultInner )->toHaveCount( 1 );
	expect( $defaultInner[0]['type'] )->toBe( 'button' );
} );

test( 'buttons block has keywords', function (): void {
	$block = new ButtonsBlock();

	expect( $block->getKeywords() )->toContain( 'button' );
	expect( $block->getKeywords() )->toContain( 'buttons' );
	expect( $block->getKeywords() )->toContain( 'cta' );
} );

test( 'buttons block supports alignment and anchor', function (): void {
	$block = new ButtonsBlock();

	expect( $block->supportsFeature( 'anchor' ) )->toBeTrue();
	expect( $block->supportsFeature( 'className' ) )->toBeTrue();
	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );

test( 'buttons block has custom toolbar', function (): void {
	$block = new ButtonsBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );

test( 'buttons block does not have custom inspector', function (): void {
	$block = new ButtonsBlock();

	expect( $block->hasCustomInspector() )->toBeFalse();
} );

test( 'buttons block to array includes default inner blocks', function (): void {
	$block = new ButtonsBlock();
	$array = $block->toArray();

	expect( $array['defaultInnerBlocks'] )->toHaveCount( 1 );
	expect( $array['defaultInnerBlocks'][0]['type'] )->toBe( 'button' );
} );
