<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Inspector\BlockMetadataService;

test( 'returns metadata for all registered blocks', function (): void {
	$service = app( BlockMetadataService::class );
	$meta    = $service->getAllBlockMeta();

	expect( $meta )->toHaveKey( 'heading' );
	expect( $meta )->toHaveKey( 'paragraph' );
	expect( $meta )->toHaveKey( 'image' );
} );

test( 'block metadata includes all required keys', function (): void {
	$service = app( BlockMetadataService::class );
	$meta    = $service->getAllBlockMeta();

	$heading = $meta['heading'];

	expect( $heading )->toHaveKey( 'type' );
	expect( $heading )->toHaveKey( 'name' );
	expect( $heading )->toHaveKey( 'description' );
	expect( $heading )->toHaveKey( 'icon' );
	expect( $heading )->toHaveKey( 'category' );
	expect( $heading )->toHaveKey( 'supports' );
	expect( $heading )->toHaveKey( 'attributes' );
	expect( $heading )->toHaveKey( 'contentSchema' );
	expect( $heading )->toHaveKey( 'styleSchema' );
	expect( $heading )->toHaveKey( 'advancedSchema' );
	expect( $heading )->toHaveKey( 'transforms' );
	expect( $heading )->toHaveKey( 'toolbarControls' );
	expect( $heading )->toHaveKey( 'supportsPanels' );
	expect( $heading )->toHaveKey( 'defaults' );
	expect( $heading )->toHaveKey( 'hasCustomInspector' );
	expect( $heading )->toHaveKey( 'hasCustomToolbar' );
} );

test( 'heading block metadata has correct type and category', function (): void {
	$service = app( BlockMetadataService::class );
	$meta    = $service->getAllBlockMeta();

	expect( $meta['heading']['type'] )->toBe( 'heading' );
	expect( $meta['heading']['category'] )->toBe( 'text' );
} );

test( 'heading block metadata includes supports panels', function (): void {
	$service = app( BlockMetadataService::class );
	$meta    = $service->getAllBlockMeta();

	$panels = $meta['heading']['supportsPanels'];

	expect( $panels )->not->toBeEmpty();

	$keys = array_column( $panels, 'key' );
	expect( $keys )->toContain( 'color' );
	expect( $keys )->toContain( 'typography' );
} );

test( 'heading block metadata includes defaults', function (): void {
	$service = app( BlockMetadataService::class );
	$meta    = $service->getAllBlockMeta();

	expect( $meta['heading']['defaults']['content'] )->toHaveKey( 'level' );
	expect( $meta['heading']['defaults']['content']['level'] )->toBe( 'h2' );
	expect( $meta['heading']['defaults']['styles'] )->toHaveKey( 'alignment' );
} );
