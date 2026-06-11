<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSet;
use ArtisanPackUI\VisualEditor\Services\Icon\UploadedIconSetRegistry;

beforeEach( function (): void {
	$base = sys_get_temp_dir() . '/icon-set-registry-' . bin2hex( random_bytes( 4 ) );
	mkdir( $base, 0o755, true );

	test()->baseDir  = $base;
	test()->registry = new UploadedIconSetRegistry( $base );
} );

afterEach( function (): void {
	$rrm = static function ( string $dir ) use ( &$rrm ): void {
		foreach ( scandir( $dir ) ?: [] as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $entry;
			is_dir( $path ) ? $rrm( $path ) : @unlink( $path );
		}
		@rmdir( $dir );
	};

	if ( isset( test()->baseDir ) && is_dir( test()->baseDir ) ) {
		$rrm( test()->baseDir );
	}
} );

it( 'returns no sets when the manifest is missing', function (): void {
	expect( test()->registry->all() )->toBe( [] );
} );

it( 'persists a registered set across instances', function (): void {
	test()->registry->register( new UploadedIconSet( 'fa-pro', 'Font Awesome Pro', '2026-01-01T00:00:00Z' ) );

	$fresh = new UploadedIconSetRegistry( test()->baseDir );

	expect( $fresh->has( 'fa-pro' ) )->toBeTrue()
		->and( $fresh->find( 'fa-pro' )->label )->toBe( 'Font Awesome Pro' );
} );

it( 'updates an existing set when re-registered with the same prefix', function (): void {
	test()->registry->register( new UploadedIconSet( 'brand', 'Old', '2026-01-01T00:00:00Z' ) );
	test()->registry->register( new UploadedIconSet( 'brand', 'New', '2026-01-02T00:00:00Z' ) );

	expect( test()->registry->all() )->toHaveCount( 1 )
		->and( test()->registry->find( 'brand' )->label )->toBe( 'New' );
} );

it( 'renames a set without touching the prefix or createdAt', function (): void {
	test()->registry->register( new UploadedIconSet( 'brand', 'Original', '2026-01-01T00:00:00Z' ) );

	$updated = test()->registry->rename( 'brand', 'Renamed' );

	expect( $updated->label )->toBe( 'Renamed' )
		->and( $updated->createdAt )->toBe( '2026-01-01T00:00:00Z' );
} );

it( 'rejects an empty label on rename', function (): void {
	test()->registry->register( new UploadedIconSet( 'brand', 'Original', '2026-01-01T00:00:00Z' ) );

	expect( fn (): UploadedIconSet => test()->registry->rename( 'brand', '   ' ) )
		->toThrow( RuntimeException::class );
} );

it( 'forgets a registered set', function (): void {
	test()->registry->register( new UploadedIconSet( 'brand', 'Original', '2026-01-01T00:00:00Z' ) );
	test()->registry->forget( 'brand' );

	expect( test()->registry->has( 'brand' ) )->toBeFalse();
} );

it( 'rejects path traversal in the prefix', function (): void {
	expect( fn (): string => test()->registry->pathFor( '../etc' ) )
		->toThrow( RuntimeException::class );
} );

it( 'validates prefix shape via the public matcher', function (): void {
	expect( UploadedIconSetRegistry::isValidPrefix( 'fa-pro' ) )->toBeTrue()
		->and( UploadedIconSetRegistry::isValidPrefix( 'Fa-Pro' ) )->toBeFalse()
		->and( UploadedIconSetRegistry::isValidPrefix( 'a' ) )->toBeFalse()
		->and( UploadedIconSetRegistry::isValidPrefix( '../etc' ) )->toBeFalse();
} );
