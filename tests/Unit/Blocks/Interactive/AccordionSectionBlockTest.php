<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\AccordionSectionBlock;

test( 'accordion section block has correct type and category', function (): void {
	$block = new AccordionSectionBlock();

	expect( $block->getType() )->toBe( 'accordion-section' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'accordion section block is not public and requires accordion parent', function (): void {
	$block = new AccordionSectionBlock();

	expect( $block->isPublic() )->toBeFalse();
	expect( $block->getAllowedParents() )->toBe( [ 'accordion' ] );
} );

test( 'accordion section block supports inner blocks with vertical orientation', function (): void {
	$block = new AccordionSectionBlock();

	expect( $block->supportsInnerBlocks() )->toBeTrue();
	expect( $block->getInnerBlocksOrientation() )->toBe( 'vertical' );
} );

test( 'accordion section block content schema has title is open and heading level fields', function (): void {
	$block  = new AccordionSectionBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'title' );
	expect( $schema )->toHaveKey( 'isOpen' );
	expect( $schema )->toHaveKey( 'headingLevel' );
	expect( $schema['title']['type'] )->toBe( 'text' );
	expect( $schema['isOpen']['type'] )->toBe( 'toggle' );
	expect( $schema['headingLevel']['type'] )->toBe( 'select' );
} );

test( 'accordion section block heading level options include h2 through h6', function (): void {
	$block  = new AccordionSectionBlock();
	$schema = $block->getContentSchema();

	expect( $schema['headingLevel']['options'] )->toHaveKey( 'h2' );
	expect( $schema['headingLevel']['options'] )->toHaveKey( 'h3' );
	expect( $schema['headingLevel']['options'] )->toHaveKey( 'h4' );
	expect( $schema['headingLevel']['options'] )->toHaveKey( 'h5' );
	expect( $schema['headingLevel']['options'] )->toHaveKey( 'h6' );
} );

test( 'accordion section block defaults to closed with h3 heading', function (): void {
	$block    = new AccordionSectionBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['isOpen'] )->toBeFalse();
	expect( $defaults['headingLevel'] )->toBe( 'h3' );
} );

test( 'accordion section block renders with correct class', function (): void {
	$block  = new AccordionSectionBlock();
	$output = $block->render(
		[ 'title' => 'Section 1', 'isOpen' => false, 'headingLevel' => 'h3' ],
		[],
	);

	expect( $output )->toContain( 've-block-accordion-section' );
} );

test( 'accordion section block supports background color', function (): void {
	$block = new AccordionSectionBlock();

	expect( $block->supportsFeature( 'color.background' ) )->toBeTrue();
} );

test( 'accordion section block allows any children', function (): void {
	$block = new AccordionSectionBlock();

	expect( $block->getAllowedChildren() )->toBeNull();
} );
