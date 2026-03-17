<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\AccordionBlock;

test( 'accordion block has correct type and category', function (): void {
	$block = new AccordionBlock();

	expect( $block->getType() )->toBe( 'accordion' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'accordion block is public and accordion-section is its only allowed child', function (): void {
	$block = new AccordionBlock();

	expect( $block->isPublic() )->toBeTrue();
	expect( $block->getAllowedChildren() )->toBe( [ 'accordion-section' ] );
} );

test( 'accordion block supports inner blocks with vertical orientation', function (): void {
	$block = new AccordionBlock();

	expect( $block->supportsInnerBlocks() )->toBeTrue();
	expect( $block->getInnerBlocksOrientation() )->toBe( 'vertical' );
} );

test( 'accordion block content schema has allow multiple field', function (): void {
	$block  = new AccordionBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'allowMultiple' );
	expect( $schema['allowMultiple']['type'] )->toBe( 'toggle' );
} );

test( 'accordion block style schema has icon and style fields', function (): void {
	$block  = new AccordionBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'iconStyle' );
	expect( $schema )->toHaveKey( 'iconPosition' );
	expect( $schema )->toHaveKey( 'bordered' );
	expect( $schema )->toHaveKey( 'accordionStyle' );
	expect( $schema )->toHaveKey( 'headerBackground' );
	expect( $schema )->toHaveKey( 'contentBackground' );
	expect( $schema )->toHaveKey( 'borderColor' );
	expect( $schema )->toHaveKey( 'activeHeaderColor' );
} );

test( 'accordion block icon style options include all variants', function (): void {
	$block  = new AccordionBlock();
	$schema = $block->getStyleSchema();

	expect( $schema['iconStyle']['options'] )->toHaveKey( 'chevron' );
	expect( $schema['iconStyle']['options'] )->toHaveKey( 'plus-minus' );
	expect( $schema['iconStyle']['options'] )->toHaveKey( 'caret' );
	expect( $schema['iconStyle']['options'] )->toHaveKey( 'none' );
} );

test( 'accordion block style options include all variants', function (): void {
	$block  = new AccordionBlock();
	$schema = $block->getStyleSchema();

	expect( $schema['accordionStyle']['options'] )->toHaveKey( 'default' );
	expect( $schema['accordionStyle']['options'] )->toHaveKey( 'joined' );
	expect( $schema['accordionStyle']['options'] )->toHaveKey( 'separated' );
} );

test( 'accordion block defaults to single open mode with chevron icon', function (): void {
	$block           = new AccordionBlock();
	$contentDefaults = $block->getDefaultContent();
	$styleDefaults   = $block->getDefaultStyles();

	expect( $contentDefaults['allowMultiple'] )->toBeFalse();
	expect( $styleDefaults['iconStyle'] )->toBe( 'chevron' );
	expect( $styleDefaults['iconPosition'] )->toBe( 'right' );
	expect( $styleDefaults['bordered'] )->toBeTrue();
	expect( $styleDefaults['accordionStyle'] )->toBe( 'default' );
} );

test( 'accordion block has default inner blocks with two sections', function (): void {
	$block        = new AccordionBlock();
	$defaultInner = $block->getDefaultInnerBlocks();

	expect( $defaultInner )->toHaveCount( 2 );
	expect( $defaultInner[0]['type'] )->toBe( 'accordion-section' );
	expect( $defaultInner[1]['type'] )->toBe( 'accordion-section' );
} );

test( 'accordion block first default section is open', function (): void {
	$block        = new AccordionBlock();
	$defaultInner = $block->getDefaultInnerBlocks();

	expect( $defaultInner[0]['attributes']['isOpen'] )->toBeTrue();
} );

test( 'accordion block has transforms to tabs', function (): void {
	$block      = new AccordionBlock();
	$transforms = $block->getTransforms();

	expect( $transforms )->toHaveKey( 'tabs' );
	expect( $transforms['tabs'] )->toBe( [ 'accordion-section' => 'tab-panel' ] );
} );

test( 'accordion block has keywords', function (): void {
	$block = new AccordionBlock();

	expect( $block->getKeywords() )->toContain( 'accordion' );
	expect( $block->getKeywords() )->toContain( 'collapse' );
	expect( $block->getKeywords() )->toContain( 'faq' );
} );

test( 'accordion block supports alignment and anchor', function (): void {
	$block = new AccordionBlock();

	expect( $block->supportsFeature( 'anchor' ) )->toBeTrue();
	expect( $block->supportsFeature( 'className' ) )->toBeTrue();
	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );

test( 'accordion block has custom toolbar', function (): void {
	$block = new AccordionBlock();

	expect( $block->hasCustomToolbar() )->toBeTrue();
} );

test( 'accordion block renders with accordion classes', function (): void {
	$block  = new AccordionBlock();
	$output = $block->render(
		[ 'allowMultiple' => false ],
		[ 'iconStyle' => 'chevron', 'iconPosition' => 'right', 'bordered' => true, 'accordionStyle' => 'default' ],
	);

	expect( $output )->toContain( 've-block-accordion' );
	expect( $output )->toContain( 've-accordion-default' );
	expect( $output )->toContain( 've-accordion-icon-chevron' );
	expect( $output )->toContain( 've-accordion-bordered' );
} );

test( 'accordion block renders separated style', function (): void {
	$block  = new AccordionBlock();
	$output = $block->render(
		[ 'allowMultiple' => true ],
		[ 'iconStyle' => 'plus-minus', 'iconPosition' => 'left', 'bordered' => false, 'accordionStyle' => 'separated' ],
	);

	expect( $output )->toContain( 've-accordion-separated' );
	expect( $output )->toContain( 've-accordion-icon-plus-minus' );
	expect( $output )->toContain( 've-accordion-icon-left' );
	expect( $output )->not->toContain( 've-accordion-bordered' );
} );

test( 'accordion block to array includes default inner blocks', function (): void {
	$block = new AccordionBlock();
	$array = $block->toArray();

	expect( $array['defaultInnerBlocks'] )->toHaveCount( 2 );
	expect( $array['defaultInnerBlocks'][0]['type'] )->toBe( 'accordion-section' );
} );
