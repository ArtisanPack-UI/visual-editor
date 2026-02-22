<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\VideoBlock;

test( 'video block has correct type and category', function (): void {
	$block = new VideoBlock();

	expect( $block->getType() )->toBe( 'video' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'video block content schema has url caption poster', function (): void {
	$block  = new VideoBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'caption' );
	expect( $schema )->toHaveKey( 'poster' );
} );

test( 'video block style schema has playback controls', function (): void {
	$block  = new VideoBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toHaveKey( 'autoplay' );
	expect( $schema )->toHaveKey( 'loop' );
	expect( $schema )->toHaveKey( 'muted' );
	expect( $schema )->toHaveKey( 'controls' );
} );

test( 'video block defaults to controls enabled', function (): void {
	$block    = new VideoBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['controls'] )->toBeTrue();
	expect( $defaults['autoplay'] )->toBeFalse();
} );

test( 'video block renders video element', function (): void {
	$block  = new VideoBlock();
	$output = $block->render( [ 'url' => 'video.mp4' ], [ 'controls' => true ] );

	expect( $output )->toContain( '<video' );
	expect( $output )->toContain( 'video.mp4' );
	expect( $output )->toContain( 'controls' );
} );
