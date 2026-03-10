<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Interactive\ButtonBlock;

test( 'button block has correct type and category', function (): void {
	$block = new ButtonBlock();

	expect( $block->getType() )->toBe( 'button' );
	expect( $block->getCategory() )->toBe( 'interactive' );
} );

test( 'button block content schema has icon fields only', function (): void {
	$block  = new ButtonBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'icon' );
	expect( $schema )->toHaveKey( 'iconPosition' );
	expect( $schema )->not->toHaveKey( 'text' );
	expect( $schema )->not->toHaveKey( 'url' );
	expect( $schema )->not->toHaveKey( 'linkTarget' );
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
	expect( $output )->toContain( 'rel="noopener"' );
} );

test( 'button block renders nofollow and sponsored rel attributes', function (): void {
	$block  = new ButtonBlock();
	$output = $block->render(
		[ 'text' => 'Sponsored', 'url' => 'https://example.com', 'linkTarget' => '_blank', 'nofollow' => true, 'sponsored' => true ],
		[ 'size' => 'md', 'variant' => 'filled' ],
	);

	expect( $output )->toContain( 'rel="noopener nofollow sponsored"' );
} );

test( 'button block renders nofollow without blank target', function (): void {
	$block  = new ButtonBlock();
	$output = $block->render(
		[ 'text' => 'Nofollow', 'url' => 'https://example.com', 'linkTarget' => '_self', 'nofollow' => true ],
		[ 'size' => 'md', 'variant' => 'filled' ],
	);

	expect( $output )->toContain( 'rel="nofollow"' );
	expect( $output )->not->toContain( 'target=' );
} );

test( 'button block has keywords', function (): void {
	$block = new ButtonBlock();

	expect( $block->getKeywords() )->toContain( 'button' );
	expect( $block->getKeywords() )->toContain( 'cta' );
} );

test( 'button block supports shadow but not dimensions or background', function (): void {
	$block = new ButtonBlock();

	expect( $block->supportsFeature( 'shadow' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.aspectRatio' ) )->toBeFalse();
	expect( $block->supportsFeature( 'dimensions.minHeight' ) )->toBeFalse();
	expect( $block->supportsFeature( 'background.backgroundImage' ) )->toBeFalse();
} );

test( 'button block active style supports include shadow', function (): void {
	$block  = new ButtonBlock();
	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'shadow' );
	expect( $active )->not->toContain( 'dimensions.aspectRatio' );
	expect( $active )->not->toContain( 'dimensions.minHeight' );
	expect( $active )->not->toContain( 'background.backgroundImage' );
} );

test( 'button block is not public and requires buttons parent', function (): void {
	$block = new ButtonBlock();

	expect( $block->isPublic() )->toBeFalse();
	expect( $block->getAllowedParents() )->toBe( [ 'buttons' ] );
} );

test( 'button block width options include percentage values', function (): void {
	$block  = new ButtonBlock();
	$schema = $block->getStyleSchema();

	expect( $schema['width']['options'] )->toHaveKey( 'auto' );
	expect( $schema['width']['options'] )->toHaveKey( '25' );
	expect( $schema['width']['options'] )->toHaveKey( '50' );
	expect( $schema['width']['options'] )->toHaveKey( '75' );
	expect( $schema['width']['options'] )->toHaveKey( '100' );
} );

test( 'button block renders with percentage width', function (): void {
	$block  = new ButtonBlock();
	$output = $block->render(
		[ 'text' => 'Wide Button', 'url' => '#', 'linkTarget' => '_self' ],
		[ 'size' => 'md', 'variant' => 'filled', 'width' => '50' ],
	);

	expect( $output )->toContain( 'width: 50%' );
} );

test( 'button block does not render width style when auto', function (): void {
	$block  = new ButtonBlock();
	$output = $block->render(
		[ 'text' => 'Normal Button', 'url' => '#', 'linkTarget' => '_self' ],
		[ 'size' => 'md', 'variant' => 'filled', 'width' => 'auto' ],
	);

	expect( $output )->not->toContain( 'width:' );
} );
