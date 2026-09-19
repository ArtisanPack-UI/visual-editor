<?php

declare( strict_types=1 );

/**
 * Regression guard for #800: the Font Awesome Free payload must be committed
 * to the repo so it ships in the Composer tarball. Downstream consumers never
 * run `npm run build`, so an ignored/unshipped payload silently empties the
 * icon picker in every install.
 */

$payloadRoot = dirname( __DIR__, 5 ) . '/resources/icons/font-awesome';

it( 'ships the FA index manifest in the package tree', function () use ( $payloadRoot ) {
	$manifestPath = $payloadRoot . '/index.json';

	expect( is_file( $manifestPath ) )->toBeTrue(
		'resources/icons/font-awesome/index.json must be committed — see #800.',
	);

	$manifest = json_decode( file_get_contents( $manifestPath ), true );

	expect( $manifest )->toBeArray()
		->and( $manifest['sets'] ?? [] )->not->toBeEmpty()
		->and( $manifest['icons'] ?? [] )->not->toBeEmpty();
} );

it( 'ships at least one SVG per registered FA set', function () use ( $payloadRoot ) {
	foreach ( [ 'fas', 'far', 'fab' ] as $prefix ) {
		$dir = $payloadRoot . '/' . $prefix;

		expect( is_dir( $dir ) )->toBeTrue(
			"resources/icons/font-awesome/{$prefix} must be committed — see #800.",
		);

		$svgs = glob( $dir . '/*.svg' ) ?: [];
		expect( $svgs )->not->toBeEmpty();
	}
} );
