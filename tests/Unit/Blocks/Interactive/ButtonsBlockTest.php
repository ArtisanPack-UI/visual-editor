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

test( 'buttons block content schema has bulk styling fields', function (): void {
	$block  = new ButtonsBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'bulkColor' );
	expect( $schema )->toHaveKey( 'bulkSize' );
	expect( $schema )->toHaveKey( 'bulkVariant' );
	expect( $schema['bulkColor']['type'] )->toBe( 'select' );
	expect( $schema['bulkSize']['type'] )->toBe( 'select' );
	expect( $schema['bulkVariant']['type'] )->toBe( 'select' );
} );

test( 'buttons block style schema has gap and stack on mobile fields', function (): void {
	$block  = new ButtonsBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'gap' );
	expect( $schema )->toHaveKey( 'stackOnMobile' );
	expect( $schema['gap']['type'] )->toBe( 'select' );
	expect( $schema['stackOnMobile']['type'] )->toBe( 'toggle' );
} );

test( 'buttons block defaults to left justification and horizontal orientation', function (): void {
	$block    = new ButtonsBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['justification'] )->toBe( 'left' );
	expect( $defaults['orientation'] )->toBe( 'horizontal' );
	expect( $defaults['flexWrap'] )->toBeTrue();
} );

test( 'buttons block defaults to half rem gap and no mobile stacking', function (): void {
	$block    = new ButtonsBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['gap'] )->toBe( '0.5rem' );
	expect( $defaults['stackOnMobile'] )->toBeFalse();
} );

test( 'buttons block renders with flex layout and role group', function (): void {
	$block  = new ButtonsBlock();
	$output = $block->render(
		[ 'justification' => 'center', 'orientation' => 'horizontal', 'flexWrap' => true ],
		[ 'gap' => '0.5rem', 'stackOnMobile' => false ],
	);

	expect( $output )->toContain( 've-block-buttons' );
	expect( $output )->toContain( 'display: flex' );
	expect( $output )->toContain( 'justify-content: center' );
	expect( $output )->toContain( 'flex-direction: row' );
	expect( $output )->toContain( 'flex-wrap: wrap' );
	expect( $output )->toContain( 'role="group"' );
	expect( $output )->toContain( 'aria-label=' );
} );

test( 'buttons block renders vertical orientation', function (): void {
	$block  = new ButtonsBlock();
	$output = $block->render(
		[ 'justification' => 'left', 'orientation' => 'vertical', 'flexWrap' => false ],
		[ 'gap' => '0.5rem', 'stackOnMobile' => false ],
	);

	expect( $output )->toContain( 'flex-direction: column' );
} );

test( 'buttons block renders with custom gap', function (): void {
	$block  = new ButtonsBlock();
	$output = $block->render(
		[ 'justification' => 'left', 'orientation' => 'horizontal', 'flexWrap' => true ],
		[ 'gap' => '1rem', 'stackOnMobile' => false ],
	);

	expect( $output )->toContain( 'gap: 1rem' );
} );

test( 'buttons block renders stack on mobile class', function (): void {
	$block  = new ButtonsBlock();
	$output = $block->render(
		[ 'justification' => 'left', 'orientation' => 'horizontal', 'flexWrap' => true ],
		[ 'gap' => '0.5rem', 'stackOnMobile' => true ],
	);

	expect( $output )->toContain( 've-buttons-stack-mobile' );
} );

test( 'buttons block has default inner blocks with two buttons', function (): void {
	$block         = new ButtonsBlock();
	$defaultInner  = $block->getDefaultInnerBlocks();

	expect( $defaultInner )->toHaveCount( 2 );
	expect( $defaultInner[0]['type'] )->toBe( 'button' );
	expect( $defaultInner[1]['type'] )->toBe( 'button' );
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

	expect( $array['defaultInnerBlocks'] )->toHaveCount( 2 );
	expect( $array['defaultInnerBlocks'][0]['type'] )->toBe( 'button' );
} );
