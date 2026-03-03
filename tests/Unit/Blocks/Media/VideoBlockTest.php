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

test( 'video block content schema has playback controls', function (): void {
	$block  = new VideoBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'autoplay' );
	expect( $schema )->toHaveKey( 'loop' );
	expect( $schema )->toHaveKey( 'muted' );
	expect( $schema )->toHaveKey( 'controls' );
	expect( $schema )->toHaveKey( 'preload' );
	expect( $schema )->toHaveKey( 'playInline' );
} );

test( 'video block defaults to controls enabled', function (): void {
	$block    = new VideoBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['controls'] )->toBeTrue();
	expect( $defaults['autoplay'] )->toBeFalse();
	expect( $defaults['preload'] )->toBe( 'metadata' );
	expect( $defaults['playInline'] )->toBeFalse();
} );

test( 'video block renders video element', function (): void {
	$block  = new VideoBlock();
	$output = $block->render( [ 'url' => 'video.mp4', 'controls' => true ], [] );

	expect( $output )->toContain( '<video' );
	expect( $output )->toContain( 'video.mp4' );
	expect( $output )->toContain( 'controls' );
} );

test( 'video block renders preload attribute', function (): void {
	$block  = new VideoBlock();
	$output = $block->render( [ 'url' => 'video.mp4', 'controls' => true, 'preload' => 'auto' ], [] );

	expect( $output )->toContain( 'preload="auto"' );
} );

test( 'video block renders playsinline when enabled', function (): void {
	$block  = new VideoBlock();
	$output = $block->render( [ 'url' => 'video.mp4', 'controls' => true, 'playInline' => true ], [] );

	expect( $output )->toContain( 'playsinline' );
} );

test( 'video block omits playsinline when disabled', function (): void {
	$block  = new VideoBlock();
	$output = $block->render( [ 'url' => 'video.mp4', 'controls' => true, 'playInline' => false ], [] );

	expect( $output )->not->toContain( 'playsinline' );
} );

test( 'video block content schema uses media_picker for url and poster', function (): void {
	$block  = new VideoBlock();
	$schema = $block->getContentSchema();

	expect( $schema['url']['type'] )->toBe( 'media_picker' );
	expect( $schema['poster']['type'] )->toBe( 'media_picker' );
} );

test( 'video block supports dimensions but not shadow', function (): void {
	$block = new VideoBlock();

	expect( $block->supportsFeature( 'dimensions.aspectRatio' ) )->toBeTrue();
	expect( $block->supportsFeature( 'dimensions.minHeight' ) )->toBeTrue();
	expect( $block->supportsFeature( 'shadow' ) )->toBeFalse();
} );

test( 'video block active style supports include dimensions', function (): void {
	$block  = new VideoBlock();
	$active = $block->getActiveStyleSupports();

	expect( $active )->toContain( 'dimensions.aspectRatio' );
	expect( $active )->toContain( 'dimensions.minHeight' );
	expect( $active )->not->toContain( 'shadow' );
} );
