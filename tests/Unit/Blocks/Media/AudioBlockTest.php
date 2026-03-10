<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\AudioBlock;

test( 'audio block has correct type and category', function (): void {
	$block = new AudioBlock();

	expect( $block->getType() )->toBe( 'audio' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'audio block content schema has url, caption, and settings', function (): void {
	$block  = new AudioBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'caption' );
	expect( $schema )->toHaveKey( 'autoplay' );
	expect( $schema )->toHaveKey( 'loop' );
	expect( $schema )->toHaveKey( 'preload' );
} );

test( 'audio block defaults to browser default preload', function (): void {
	$block    = new AudioBlock();
	$defaults = $block->getDefaultContent();

	expect( $defaults['preload'] )->toBe( '' );
	expect( $defaults['autoplay'] )->toBeFalse();
} );

test( 'audio block style schema is empty', function (): void {
	$block  = new AudioBlock();
	$schema = $block->getStyleSchema();

	expect( $schema )->toBeEmpty();
} );

test( 'audio block renders audio element', function (): void {
	$block  = new AudioBlock();
	$output = $block->render( [ 'url' => 'song.mp3', 'preload' => 'metadata' ], [] );

	expect( $output )->toContain( '<audio' );
	expect( $output )->toContain( 'song.mp3' );
} );

test( 'audio block autoplay field has hint text', function (): void {
	$block  = new AudioBlock();
	$schema = $block->getContentSchema();

	expect( $schema['autoplay'] )->toHaveKey( 'hint' );
	expect( $schema['autoplay']['hint'] )->toBeString();
	expect( $schema['autoplay']['hint'] )->not->toBeEmpty();
} );

test( 'audio block settings have panel grouping', function (): void {
	$block  = new AudioBlock();
	$schema = $block->getContentSchema();

	expect( $schema['autoplay'] )->toHaveKey( 'panel' );
	expect( $schema['loop'] )->toHaveKey( 'panel' );
	expect( $schema['preload'] )->toHaveKey( 'panel' );
} );

test( 'audio block preload has browser default option', function (): void {
	$block   = new AudioBlock();
	$schema  = $block->getContentSchema();
	$options = $schema['preload']['options'];

	expect( $options )->toHaveKey( '' );
	expect( $options )->toHaveKey( 'auto' );
	expect( $options )->toHaveKey( 'metadata' );
	expect( $options )->toHaveKey( 'none' );
} );
