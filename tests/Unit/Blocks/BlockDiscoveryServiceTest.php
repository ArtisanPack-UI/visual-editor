<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockDiscoveryService;

test( 'discovery finds all 33 core blocks', function (): void {
	$service = new BlockDiscoveryService();
	$blocks  = $service->discover();

	expect( $blocks )->toHaveCount( 33 );
} );

test( 'discovery returns correct structure for each block', function (): void {
	$service = new BlockDiscoveryService();
	$blocks  = $service->discover();

	foreach ( $blocks as $block ) {
		expect( $block )->toHaveKeys( [ 'type', 'class', 'dir', 'metadata' ] );
		expect( $block['type'] )->toBeString()->not->toBeEmpty();
		expect( $block['class'] )->toBeString()->not->toBeEmpty();
		expect( $block['dir'] )->toBeString()->not->toBeEmpty();
		expect( $block['metadata'] )->toBeArray()->not->toBeEmpty();
		expect( class_exists( $block['class'] ) )->toBeTrue();
	}
} );

test( 'discovery returns all expected block types', function (): void {
	$service = new BlockDiscoveryService();
	$blocks  = $service->discover();
	$types   = array_column( $blocks, 'type' );

	$expected = [
		'heading', 'paragraph', 'list', 'quote', 'preformatted', 'details', 'table',
		'image', 'gallery', 'video', 'audio', 'file',
		'columns', 'column', 'group', 'grid', 'grid-item', 'spacer', 'divider',
		'button', 'buttons', 'code', 'tabs', 'tab-panel', 'accordion', 'accordion-section',
		'latest-posts', 'table-of-contents', 'search',
	];

	foreach ( $expected as $type ) {
		expect( $types )->toContain( $type );
	}
} );

test( 'discovery metadata contains required fields', function (): void {
	$service = new BlockDiscoveryService();
	$blocks  = $service->discover();

	foreach ( $blocks as $block ) {
		expect( $block['metadata'] )->toHaveKey( 'type' );
		expect( $block['metadata'] )->toHaveKey( 'name' );
		expect( $block['metadata'] )->toHaveKey( 'category' );
	}
} );

test( 'manifest path points to bootstrap cache', function (): void {
	$service = new BlockDiscoveryService();
	$path    = $service->manifestPath();

	expect( $path )->toContain( 'bootstrap' );
	expect( $path )->toContain( 'cache' );
	expect( $path )->toEndWith( 'visual-editor-blocks.php' );
} );

test( 'manifest does not exist by default', function (): void {
	$service = new BlockDiscoveryService();
	$path    = $service->manifestPath();

	if ( file_exists( $path ) ) {
		unlink( $path );
	}

	expect( $service->manifestExists() )->toBeFalse();
} );

test( 'load manifest returns null when no manifest exists', function (): void {
	$service = new BlockDiscoveryService();
	$path    = $service->manifestPath();

	if ( file_exists( $path ) ) {
		unlink( $path );
	}

	expect( $service->loadManifest() )->toBeNull();
} );
