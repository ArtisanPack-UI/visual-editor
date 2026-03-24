<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\BlockDiscoveryService;

beforeEach( function (): void {
	$service = app( BlockDiscoveryService::class );
	$path    = $service->manifestPath();

	if ( file_exists( $path ) ) {
		unlink( $path );
	}
} );

afterEach( function (): void {
	$service = app( BlockDiscoveryService::class );
	$path    = $service->manifestPath();

	if ( file_exists( $path ) ) {
		unlink( $path );
	}
} );

test( 've:cache creates manifest file', function (): void {
	$service = app( BlockDiscoveryService::class );

	$this->artisan( 've:cache' )->assertSuccessful();

	expect( file_exists( $service->manifestPath() ) )->toBeTrue();
} );

test( 've:cache manifest contains all 39 blocks', function (): void {
	$service = app( BlockDiscoveryService::class );

	$this->artisan( 've:cache' )->assertSuccessful();

	$manifest = $service->loadManifest();

	expect( $manifest )->toBeArray();
	expect( $manifest )->toHaveCount( 44 );
} );

test( 've:cache manifest entries have correct structure', function (): void {
	$service = app( BlockDiscoveryService::class );

	$this->artisan( 've:cache' )->assertSuccessful();

	$manifest = $service->loadManifest();

	foreach ( $manifest as $entry ) {
		expect( $entry )->toHaveKeys( [ 'type', 'class', 'dir', 'metadata' ] );
	}
} );

test( 've:clear removes manifest file', function (): void {
	$service = app( BlockDiscoveryService::class );

	$this->artisan( 've:cache' )->assertSuccessful();
	expect( $service->manifestExists() )->toBeTrue();

	$this->artisan( 've:clear' )->assertSuccessful();
	expect( $service->manifestExists() )->toBeFalse();
} );

test( 've:clear succeeds even when no manifest exists', function (): void {
	$service = app( BlockDiscoveryService::class );

	expect( $service->manifestExists() )->toBeFalse();

	$this->artisan( 've:clear' )->assertSuccessful();
} );
