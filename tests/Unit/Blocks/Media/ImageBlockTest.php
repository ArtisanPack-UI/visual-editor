<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\ImageBlock;

test( 'image block has correct type and category', function (): void {
	$block = new ImageBlock();

	expect( $block->getType() )->toBe( 'image' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'image block content schema has url alt caption link fields', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'alt' );
	expect( $schema )->toHaveKey( 'caption' );
	expect( $schema )->toHaveKey( 'link' );
	expect( $schema )->toHaveKey( 'linkTarget' );
} );

test( 'image block style schema has size alignment rounded shadow fields', function (): void {
	$block  = new ImageBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'size' );
	expect( $schema )->toHaveKey( 'alignment' );
	expect( $schema )->toHaveKey( 'rounded' );
	expect( $schema )->toHaveKey( 'shadow' );
	expect( $schema )->toHaveKey( 'objectFit' );
} );

test( 'image block defaults to large size and center alignment', function (): void {
	$block    = new ImageBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['size'] )->toBe( 'large' );
	expect( $defaults['alignment'] )->toBe( 'center' );
} );

test( 'image block renders figure with img', function (): void {
	$block  = new ImageBlock();
	$output = $block->render( [ 'url' => 'test.jpg', 'alt' => 'Test' ], [ 'size' => 'large', 'alignment' => 'center' ] );

	expect( $output )->toContain( '<figure' );
	expect( $output )->toContain( '<img' );
	expect( $output )->toContain( 'test.jpg' );
} );

test( 'image block renders link wrapper when link is set', function (): void {
	$block  = new ImageBlock();
	$output = $block->render(
		[ 'url' => 'test.jpg', 'alt' => 'Test', 'link' => 'https://example.com', 'linkTarget' => '_blank' ],
		[ 'size' => 'large', 'alignment' => 'center' ],
	);

	expect( $output )->toContain( '<a href="https://example.com"' );
	expect( $output )->toContain( 'target="_blank"' );
} );

test( 'image block supports alignment', function (): void {
	$block = new ImageBlock();

	expect( $block->supportsFeature( 'align' ) )->toBeTrue();
} );
