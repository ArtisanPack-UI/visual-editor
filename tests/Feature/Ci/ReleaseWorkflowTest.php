<?php

declare( strict_types=1 );

use Symfony\Component\Yaml\Yaml;

/**
 * Regression tests for `.github/workflows/release.yml`.
 *
 * The release workflow bakes `dist/editor/` + `dist/lib/` into the
 * release tag so Composer consumers (Keystone CMS etc. — see #678)
 * get pre-built bundles under
 * `vendor/artisanpack-ui/visual-editor/dist/editor/`. #678's original
 * design triggered on tag push and force-updated the tag mid-run,
 * which raced Packagist's version-immutability policy and lost
 * (v1.5.2 shipped without dist/ on Packagist — see #683). The
 * workflow now triggers on `workflow_dispatch`, builds first, and
 * pushes the tag exactly ONCE at the built commit.
 *
 * These tests lock the load-bearing invariants of that design: no
 * `on: push: tags` trigger, no `git push --force` on a version tag,
 * a guard that refuses to reship an existing tag, and the
 * verify/strip/commit/tag pipeline. Each test asserts the shape of
 * one guard so cosmetic edits to comments or formatting don't break
 * the suite, but removing the guard itself does.
 */

/**
 * Load the release workflow YAML into a fresh array per test.
 *
 * @return array{
 *     workflow: array<string, mixed>,
 *     buildAndTag: array<string, mixed>,
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
		'buildAndTag' => $workflow['jobs']['build-and-tag'] ?? [],
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

test( 'workflow triggers on workflow_dispatch and NEVER on tag push', function () {
	$w = loadReleaseWorkflow();

	// The YAML `on:` key is parsed to boolean `true` in PHP — access
	// the raw value via the special key. Prefer the parsed structure.
	$on = $w['workflow'][ true ] ?? $w['workflow']['on'] ?? [];

	expect( $on )
		->toHaveKey( 'workflow_dispatch' )
		->not->toHaveKey( 'push' );

	// The version input must be required — dispatching without a
	// version would tag with an empty string.
	$version = $on['workflow_dispatch']['inputs']['version'] ?? [];
	expect( $version['required'] ?? null )->toBeTrue();
} );

test( 'build-and-tag job exists and depends on both test jobs', function () {
	$w = loadReleaseWorkflow();

	expect( $w['buildAndTag'] )->not->toBeEmpty(
		'build-and-tag job missing — dist/ will not be baked into the release tag'
	);
	expect( $w['buildAndTag']['needs'] )
		->toContain( 'test-php' )
		->toContain( 'test-js' );
} );

test( 'build-and-tag refuses to overwrite an existing tag on origin', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildAndTag'] );

	// The guard against re-dispatching for an already-shipped
	// version — without it we could hit Packagist's immutability
	// wall a second time. See #683.
	expect( $runs )
		->toContain( 'git ls-remote --tags --exit-code origin' )
		->toContain( 'already exists on origin' );
} );

test( 'build-and-tag verifies the version input matches the manifests', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildAndTag'] );

	// A `-f version=1.5.3` dispatch against a main that still says
	// 1.5.2 in the manifests would produce a tag inconsistent with
	// the source's stated version.
	expect( $runs )
		->toContain( 'composer.json' )
		->toContain( 'package.json' );
} );

test( 'build-and-tag runs both build:lib and build so dist/lib and dist/editor are produced', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildAndTag'] );

	expect( $runs )->toContain( 'npm run build:lib' );
	expect( $runs )->toContain( 'npm run build' );
} );

test( 'build-and-tag strips sourcemaps before committing', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildAndTag'] );

	// Sourcemaps must be found, packaged, and removed. Without this
	// the composer tarball grows by ~30 MB per install (#678).
	expect( $runs )
		->toContain( '*.map' )
		->toContain( 'tar' )
		->toContain( 'rm' );
} );

test( 'build-and-tag verifies the required build outputs exist', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildAndTag'] );

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

test( 'build-and-tag force-adds dist/editor and dist/lib onto the release commit', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildAndTag'] );

	// `dist/` stays .gitignored on every working branch (see the
	// comment in .gitignore); the release workflow force-adds so
	// the bundles land in the tarball anyway.
	expect( $runs )
		->toContain( 'git add --force' )
		->toContain( 'dist/editor' )
		->toContain( 'dist/lib' );
} );

test( 'build-and-tag pushes the version tag exactly once — no force-push', function () {
	$runs = releaseWorkflowRunScripts( loadReleaseWorkflow()['buildAndTag'] );

	// Packagist immutability blocks any subsequent SHA change on a
	// stable tag — see #683. The workflow must NEVER force-push a
	// version tag or use `git tag -f`.
	expect( $runs )
		->toContain( 'git tag -a' )
		->toContain( 'git push origin' )
		->not->toContain( 'git tag -f' )
		->not->toContain( 'git push --force' )
		->not->toContain( 'git push -f' );
} );

test( 'release job checks out the tag created by build-and-tag', function () {
	$release = loadReleaseWorkflow()['release'];

	$checkout = collect( $release['steps'] ?? [] )
		->first( fn ( array $step ) => str_starts_with( (string) ( $step['uses'] ?? '' ), 'actions/checkout' ) );

	expect( $checkout )->not->toBeNull();
	expect( $checkout['with']['ref'] ?? '' )
		->toContain( 'refs/tags/v' )
		->toContain( 'build-and-tag.outputs.version' );
} );

test( 'release job downloads the sourcemap artefact and attaches it to the GitHub Release', function () {
	$release = loadReleaseWorkflow()['release'];
	$steps = collect( $release['steps'] ?? [] );

	$download = $steps->first(
		fn ( array $step ) => str_starts_with( (string) ( $step['uses'] ?? '' ), 'actions/download-artifact' )
	);
	expect( $download )->not->toBeNull(
		'release job must download the sourcemap archive from build-and-tag'
	);

	$ghRelease = $steps->first(
		fn ( array $step ) => str_starts_with( (string) ( $step['uses'] ?? '' ), 'softprops/action-gh-release' )
	);
	expect( $ghRelease )->not->toBeNull();
	expect( $ghRelease['with']['files'] ?? '' )->toContain( 'release-artifacts' );
} );

test( 'build-and-tag declares contents:write permission (required to push the tag)', function () {
	$job = loadReleaseWorkflow()['buildAndTag'];

	expect( $job['permissions']['contents'] ?? '' )->toBe( 'write' );
} );
