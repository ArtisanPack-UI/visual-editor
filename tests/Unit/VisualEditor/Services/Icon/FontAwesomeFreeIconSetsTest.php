<?php

declare( strict_types=1 );

use ArtisanPackUI\Icons\Registries\IconSetRegistration;
use ArtisanPackUI\VisualEditor\Services\Icon\FontAwesomeFreeIconSets;

beforeEach( function (): void {
	test()->fixtureBase = sys_get_temp_dir() . '/fa-free-' . bin2hex( random_bytes( 4 ) );
	mkdir( test()->fixtureBase, 0o755, true );
} );

afterEach( function (): void {
	$base = test()->fixtureBase;
	if ( is_dir( $base ) ) {
		// Fixture trees are at most 4 entries deep; a manual walk is enough
		// and avoids pulling Symfony Finder just for the cleanup.
		foreach ( new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST,
		) as $path ) {
			$path->isDir() ? rmdir( $path->getRealPath() ) : unlink( $path->getRealPath() );
		}
		rmdir( $base );
	}
} );

it( 'discovers fas, far, and fab when all three set directories exist', function () {
	mkdir( test()->fixtureBase . '/fas' );
	mkdir( test()->fixtureBase . '/far' );
	mkdir( test()->fixtureBase . '/fab' );

	$found = FontAwesomeFreeIconSets::discover( test()->fixtureBase );

	expect( $found )->toHaveKeys( [ 'fas', 'far', 'fab' ] )
		->and( $found['fas'] )->toBe( test()->fixtureBase . '/fas' )
		->and( $found['fab'] )->toBe( test()->fixtureBase . '/fab' );
} );

it( 'silently skips sets whose directory has not been synced yet', function () {
	mkdir( test()->fixtureBase . '/fas' );
	mkdir( test()->fixtureBase . '/fab' );

	$found = FontAwesomeFreeIconSets::discover( test()->fixtureBase );

	expect( $found )->toHaveKeys( [ 'fas', 'fab' ] )
		->and( $found )->not->toHaveKey( 'far' );
} );

it( 'returns an empty array when the base directory is absent', function () {
	$found = FontAwesomeFreeIconSets::discover( test()->fixtureBase . '/missing' );

	expect( $found )->toBe( [] );
} );

it( 'registers each discovered set on the IconSetRegistration', function () {
	mkdir( test()->fixtureBase . '/fas' );
	mkdir( test()->fixtureBase . '/far' );
	mkdir( test()->fixtureBase . '/fab' );

	$registry = new IconSetRegistration();
	$result   = FontAwesomeFreeIconSets::register( $registry, test()->fixtureBase );
	$sets     = $result->getSets();

	expect( $result )->toBe( $registry )
		->and( $sets )->toHaveKeys( [ 'fas', 'far', 'fab' ] )
		->and( $sets['fas']['path'] )->toBe( test()->fixtureBase . '/fas' );
} );

it( 'does not throw when the base directory is missing', function () {
	$registry = new IconSetRegistration();

	expect( fn () => FontAwesomeFreeIconSets::register( $registry, test()->fixtureBase . '/missing' ) )
		->not->toThrow( Throwable::class );

	expect( $registry->getSets() )->toBe( [] );
} );
