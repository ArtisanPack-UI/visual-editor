<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Icon\IconSvgResolver;

beforeEach( function (): void {
	test()->base = sys_get_temp_dir() . '/icon-svg-resolver-' . bin2hex( random_bytes( 4 ) );
	mkdir( test()->base . '/fab', 0o755, true );
	file_put_contents( test()->base . '/fab/github.svg', '<svg id="github"/>' );

	test()->resolver = new IconSvgResolver( [ 'fab' => test()->base . '/fab' ] );
} );

afterEach( function (): void {
	$base = test()->base ?? null;
	if ( is_string( $base ) && is_dir( $base ) ) {
		foreach ( new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST,
		) as $path ) {
			$path->isDir() ? rmdir( $path->getRealPath() ) : unlink( $path->getRealPath() );
		}
		rmdir( $base );
	}
} );

it( 'returns the raw svg markup for a registered (set, name)', function () {
	expect( test()->resolver->resolve( 'fab', 'github' ) )->toBe( '<svg id="github"/>' );
} );

it( 'returns null when the set is not registered', function () {
	expect( test()->resolver->resolve( 'fas', 'github' ) )->toBeNull();
} );

it( 'returns null when the icon file is missing', function () {
	expect( test()->resolver->resolve( 'fab', 'does-not-exist' ) )->toBeNull();
} );

it( 'returns null when no sets are registered at all', function () {
	$resolver = new IconSvgResolver();

	expect( $resolver->resolve( 'fab', 'github' ) )->toBeNull();
} );

it( 'evaluates a closure source lazily — not at construction time', function () {
	$callCount = 0;
	$paths     = [ 'fab' => test()->base . '/fab' ];

	$resolver = new IconSvgResolver( function () use ( &$callCount, $paths ): array {
		$callCount++;

		return $paths;
	} );

	expect( $callCount )->toBe( 0 );

	$resolver->resolve( 'fab', 'github' );
	$resolver->resolve( 'fab', 'github' );

	// Cached after the first call — the closure is the path-discovery
	// gate, not a per-lookup hook.
	expect( $callCount )->toBe( 1 );
} );
