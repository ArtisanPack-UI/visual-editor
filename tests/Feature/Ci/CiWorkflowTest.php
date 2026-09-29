<?php

declare( strict_types=1 );

use Symfony\Component\Yaml\Yaml;

/**
 * Regression tests for `.github/workflows/ci.yml`.
 *
 * `packages/renderer-parity.json` documents that CI runs
 * `scripts/verify-renderer-parity.mjs` to fail the build on renderer
 * drift. The step was missing until 1.12.0 and the manifest drifted
 * unnoticed from v1.9; this locks it into the JS job.
 *
 * @since 1.12.0
 */
it( 'runs the renderer parity check in the JS job', function (): void {
	$workflow = Yaml::parseFile( dirname( __DIR__, 3 ) . '/.github/workflows/ci.yml' );

	$scripts = implode( "\n", array_map(
		static fn ( array $step ): string => (string) ( $step['run'] ?? '' ),
		$workflow['jobs']['test-js']['steps'] ?? [],
	) );

	expect( $scripts )->toContain( 'npm run verify:parity' );
} );

it( 'keeps the verify:parity npm script wired to the parity checker', function (): void {
	$package = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/package.json' ), true );

	expect( $package['scripts']['verify:parity'] ?? null )->toContain( 'scripts/verify-renderer-parity.mjs' );
} );
