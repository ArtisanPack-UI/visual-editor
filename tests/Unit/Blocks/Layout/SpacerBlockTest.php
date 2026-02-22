<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Layout\SpacerBlock;

test( 'spacer block has correct type and category', function (): void {
	$block = new SpacerBlock();

	expect( $block->getType() )->toBe( 'spacer' );
	expect( $block->getCategory() )->toBe( 'layout' );
} );

test( 'spacer block content schema has height and unit fields', function (): void {
	$block  = new SpacerBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'height' );
	expect( $schema )->toHaveKey( 'unit' );
	expect( $schema['unit']['type'] )->toBe( 'select' );
} );

test( 'spacer block has empty style schema', function (): void {
	$block  = new SpacerBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'spacer block defaults to 40px height', function (): void {
	$block    = new SpacerBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['height'] )->toBe( '40' );
	expect( $defaults['unit'] )->toBe( 'px' );
} );

test( 'spacer block renders with correct height', function (): void {
	$block  = new SpacerBlock();
	$output = $block->render( [ 'height' => '60', 'unit' => 'px' ], [] );

	expect( $output )->toContain( 've-block-spacer' );
	expect( $output )->toContain( 'height: 60px' );
	expect( $output )->toContain( 'aria-hidden="true"' );
} );

test( 'spacer block renders with rem unit', function (): void {
	$block  = new SpacerBlock();
	$output = $block->render( [ 'height' => '2', 'unit' => 'rem' ], [] );

	expect( $output )->toContain( 'height: 2rem' );
} );

test( 'spacer block has keywords', function (): void {
	$block = new SpacerBlock();

	expect( $block->getKeywords() )->toContain( 'space' );
	expect( $block->getKeywords() )->toContain( 'gap' );
} );
