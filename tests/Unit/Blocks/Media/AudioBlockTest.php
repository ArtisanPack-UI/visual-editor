<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Media\AudioBlock;

test( 'audio block has correct type and category', function (): void {
	$block = new AudioBlock();

	expect( $block->getType() )->toBe( 'audio' );
	expect( $block->getCategory() )->toBe( 'media' );
} );

test( 'audio block content schema has url and caption', function (): void {
	$block  = new AudioBlock();
	$schema = $block->getContentSchema();

	expect( $schema )->toHaveKey( 'url' );
	expect( $schema )->toHaveKey( 'caption' );
} );

test( 'audio block defaults to metadata preload', function (): void {
	$block    = new AudioBlock();
	$defaults = $block->getDefaultStyles();

	expect( $defaults['preload'] )->toBe( 'metadata' );
	expect( $defaults['autoplay'] )->toBeFalse();
} );

test( 'audio block renders audio element', function (): void {
	$block  = new AudioBlock();
	$output = $block->render( [ 'url' => 'song.mp3' ], [ 'preload' => 'metadata' ] );

	expect( $output )->toContain( '<audio' );
	expect( $output )->toContain( 'song.mp3' );
} );
