<?php

declare( strict_types=1 );

use Symfony\Component\Yaml\Yaml;

/**
 * Regression tests for `.github/workflows/release.yml`.
 *
 * The release workflow is the ONE place where `dist/editor/` and
 * `dist/lib/` are baked into the release tag so Composer consumers
 * (Keystone CMS etc. — see #678) get pre-built bundles under
 * `vendor/artisanpack-ui/visual-editor/dist/editor/`. Without these
 * tests a future edit could silently drop the dist-baking step and
 * ship another `1.5.1`-shaped release where the tarball has no
 * `dist/editor/` at all.
 *
 * Each test asserts a single invariant of the workflow — the shape
 * of the guard, not its exact wording — so cosmetic edits to
 * comments or shell formatting don't break the suite, but removing
 * the guard itself does.
 */

/**
 * Load the release workflow YAML into a fresh array per test.
 *
 * @return array{
 *     workflow: array<string, mixed>,
 *     buildDist: array<string, mixed>,
 *     release: array<string, mixed>
 * }
 */
function loadReleaseWorkflow(): array
{
	$path = dirname( __DIR__, 3 ) . '/.github/workflows/release.yml';

	if ( ! file_exists( $path ) ) {
		throw new RuntimeException( 'release.yml missing at ' . $path );
	}

	$workflow = Yaml::parseFile( $path );

	return [
		'workflow' => $workflow,
		'buildDist' => $workflow['jobs']['build-dist'] ?? [],
		'release' => $workflow['jobs']['release'] ?? [],
	];
}

/**
 * Concatenate every `run:` script in a job into a single haystack
 * for substring assertions. Steps that use an action (no `run` key)
 * are skipped.
 */
function releaseWorkflowRunScripts( array $job ): string
{
	return collect( $job['steps'] ?? [] )
		->pluck( 'run' )
		->filter()
		->implode( "\n" );
}

test( 'build-dist job exists and depends on both test jobs', function () {
	$w = loadReleaseWorkflow();

	expect( $w['buildDist'] )->not->toBeEmpty(
		'build-dist job missing — dist/ will not be baked into the release tag'
	);
	expect( $w['buildDist']['needs'] )
		->toContain( 'test-php' )
		->toContain( 'test-js' );
} );

test( 'build-dist runs both build:lib and build so dist/lib and dist/editor are produced', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildDist'] );

	expect( $runs )->toContain( 'npm run build:lib' );
	expect( $runs )->toContain( 'npm run build' );
} );

test( 'build-dist strips sourcemaps before committing', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildDist'] );

	// Sourcemaps must be found, packaged, and removed. Without this
	// the composer tarball grows by ~30 MB per install (#678).
	expect( $runs )
		->toContain( '*.map' )
		->toContain( 'tar' )
		->toContain( 'rm' );
} );

test( 'build-dist verifies the required build outputs exist', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildDist'] );

	// The verify step guards against silent build breakage that
	// would ship a release with missing bundles.
	foreach ( [
		'dist/editor/visual-editor.js',
		'dist/editor/site-editor.js',
		'dist/editor/sandbox.js',
		'dist/editor/chunks',
		'dist/lib/visual-editor.js',
	] as $expected ) {
		expect( $runs )->toContain( $expected );
	}
} );

test( 'build-dist force-adds dist/editor and dist/lib onto the release commit', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildDist'] );

	// `dist/` stays .gitignored on every working branch (see the
	// comment in .gitignore); the release workflow force-adds so
	// the bundles land in the tarball anyway.
	expect( $runs )
		->toContain( 'git add --force' )
		->toContain( 'dist/editor' )
		->toContain( 'dist/lib' );
} );

test( 'build-dist re-points the version tag at the dist-baked commit', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildDist'] );

	// Composer resolves tags to SHAs — the tag must move to the
	// new commit or consumers keep getting the pre-build SHA.
	expect( $runs )
		->toContain( 'git tag -f' )
		->toContain( 'git push --force' );
} );

test( 'release job checks out the re-tagged commit before extracting notes', function () {
	$release = loadReleaseWorkflow()['release'];

	$checkout = collect( $release['steps'] ?? [] )
		->first( fn ( array $step ) => str_starts_with( (string) ( $step['uses'] ?? '' ), 'actions/checkout' ) );

	expect( $checkout )->not->toBeNull();
	expect( $checkout['with']['ref'] ?? '' )
		->toContain( 'refs/tags/v' )
		->toContain( 'build-dist.outputs.version' );
} );

test( 'release job downloads the sourcemap artefact and attaches it to the GitHub Release', function () {
	$release = loadReleaseWorkflow()['release'];
	$steps = collect( $release['steps'] ?? [] );

	$download = $steps->first(
		fn ( array $step ) => str_starts_with( (string) ( $step['uses'] ?? '' ), 'actions/download-artifact' )
	);
	expect( $download )->not->toBeNull(
		'release job must download the sourcemap archive from build-dist'
	);

	$ghRelease = $steps->first(
		fn ( array $step ) => str_starts_with( (string) ( $step['uses'] ?? '' ), 'softprops/action-gh-release' )
	);
	expect( $ghRelease )->not->toBeNull();
	expect( $ghRelease['with']['files'] ?? '' )->toContain( 'release-artifacts' );
} );

test( 'build-dist declares contents:write permission (required to push the re-tag)', function () {
	$buildDist = loadReleaseWorkflow()['buildDist'];

	expect( $buildDist['permissions']['contents'] ?? '' )->toBe( 'write' );
} );
