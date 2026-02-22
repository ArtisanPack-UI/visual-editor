<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\ButtonBlock;

test( 'button block has correct type and category', function (): void {
	$block = new ButtonBlock();

	expect( $block->getType() )->toBe( 'button' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'button block content schema has text url and link fields', function (): void {
	$block  = new ButtonBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'text' );
	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'linkTarget' );
	expect( $schema )->toHaveKey( 'icon' );
	expect( $schema )->toHaveKey( 'iconPosition' );
} );

test( 'button block style schema has size variant and color fields', function (): void {
	$block  = new ButtonBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'color' );
	expect( $schema )->toHaveKey( 'backgroundColor' );
	expect( $schema )->toHaveKey( 'size' );
	expect( $schema )->toHaveKey( 'variant' );
	expect( $schema )->toHaveKey( 'borderRadius' );
	expect( $schema )->toHaveKey( 'width' );
} );

test( 'button block defaults to medium size and filled variant', function (): void {
	$block    = new ButtonBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['size'] )->toBe( 'md' );
	expect( $defaults['variant'] )->toBe( 'filled' );
	expect( $defaults['width'] )->toBe( 'auto' );
} );

test( 'button block renders as anchor element', function (): void {
	$block  = new ButtonBlock();
	$output = $block->render(
		[ 'text' => 'Click Me', 'url' => 'https://example.com', 'linkTarget' => '_self' ],
		[ 'size' => 'md', 'variant' => 'filled' ],
	);

	expect( $output )->toContain( '<a' );
	expect( $output )->toContain( 'href="https://example.com"' );
	expect( $output )->toContain( 'Click Me' );
	expect( $output )->toContain( 've-block-button' );
} );

test( 'button block renders blank target with noopener', function (): void {
	$block  = new ButtonBlock();
	$output = $block->render(
		[ 'text' => 'External', 'url' => 'https://example.com', 'linkTarget' => '_blank' ],
		[ 'size' => 'md', 'variant' => 'filled' ],
	);

	expect( $output )->toContain( 'target="_blank"' );
	expect( $output )->toContain( 'rel="noopener noreferrer"' );
} );

test( 'button block has keywords', function (): void {
	$block = new ButtonBlock();

	expect( $block->getKeywords() )->toContain( 'button' );
	expect( $block->getKeywords() )->toContain( 'cta' );
} );
