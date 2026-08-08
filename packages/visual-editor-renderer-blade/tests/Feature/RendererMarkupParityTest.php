<?php

/**
 * Issue #704 — Blade-vs-JS markup parity.
 *
 * Renders every shared fixture in
 * `packages/renderer-markup-parity/fixtures.json` through `<x-ve-blocks>`,
 * canonicalizes the markup, and compares it against the checked-in golden
 * file. The vitest side
 * (`packages/renderer-markup-parity/tests/blade-parity.test.ts`) asserts
 * the React and Vue renderers produce the same golden, so a divergence in
 * any of the three renderers fails one of the two suites.
 *
 * Regenerate the goldens deliberately, after reviewing the diff:
 *
 *     composer test:update-markup-goldens
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Tests\Support\CanonicalMarkup;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

/**
 * Render the partials the package ships, never a published copy.
 *
 * `vendor:publish --force` (exercised by BlocksComponentTest) drops a
 * snapshot of the block views into testbench's
 * `resources/views/vendor/visual-editor-renderer-blade`, and
 * `loadViewsFrom()` gives that copy priority. A stale snapshot would let
 * the goldens lock in markup the package no longer emits, so prepend the
 * source directory for this suite.
 */
beforeEach( function () {
	View::getFinder()->prependNamespace(
		'visual-editor-renderer-blade',
		dirname( __DIR__, 2 ) . '/resources/views'
	);

	View::getFinder()->flush();
} );

/**
 * Absolute path to the shared parity directory.
 */
function markupParityPath( string $relative = '' ): string
{
	$root = dirname( __DIR__, 3 ) . '/renderer-markup-parity';

	return '' === $relative ? $root : $root . '/' . $relative;
}

/**
 * Decodes the shared parity manifest.
 *
 * @return array<string, mixed>
 */
function markupParityManifest(): array
{
	return json_decode(
		(string) file_get_contents( markupParityPath( 'fixtures.json' ) ),
		true,
		512,
		JSON_THROW_ON_ERROR
	);
}

/**
 * Class-token regexes for the declared, documented renderer divergences.
 *
 * @return array<int, string>
 */
function markupParityDropClassPatterns(): array
{
	return array_map(
		static fn ( array $divergence ): string => $divergence['dropClassTokensMatching'],
		markupParityManifest()['knownDivergences'] ?? []
	);
}

/**
 * Loads the shared, language-neutral fixture set.
 *
 * @return array<string, array{0: string, 1: array<int, mixed>}>
 */
function markupParityFixtures(): array
{
	$json = markupParityManifest();

	$dataset = [];

	foreach ( $json['fixtures'] as $fixture ) {
		$dataset[ $fixture['name'] ] = [ $fixture['name'], $fixture['tree'] ];
	}

	return $dataset;
}

/**
 * Strips the renderer-injected `<style data-ve-*>` blocks.
 *
 * This check covers block markup only. The layout baseline / global-styles
 * CSS is a known, documented divergence between Blade (which emits it from
 * `ThemeJsonTokensCompiler::compileLayoutRules()`, gated on theme.json
 * layout sizes) and the JS renderers (which ship `LAYOUT_BASELINE_CSS`), so
 * comparing it here would only encode that difference twice.
 */
function stripRendererStyleTags( string $html ): string
{
	return (string) preg_replace( '#<style\s+data-ve-[^>]*>.*?</style>#s', '', $html );
}

it( 'matches the golden markup shared with the React and Vue renderers', function ( string $name, array $tree ) {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	$canonical = CanonicalMarkup::fromHtml(
		stripRendererStyleTags( $rendered ),
		markupParityDropClassPatterns()
	);

	$goldenPath = markupParityPath( 'goldens/' . $name . '.txt' );

	if ( '1' === getenv( 'UPDATE_MARKUP_GOLDENS' ) ) {
		if ( ! is_dir( dirname( $goldenPath ) ) ) {
			mkdir( dirname( $goldenPath ), 0o755, true );
		}

		file_put_contents( $goldenPath, $canonical . "\n" );
	}

	expect( file_exists( $goldenPath ) )->toBeTrue(
		'Missing golden for fixture "' . $name . '". Run: composer test:update-markup-goldens'
	);

	// `\r\n` guard: the goldens are compared as exact strings, so a CRLF
	// checkout must not read as a divergence.
	$golden = str_replace( "\r\n", "\n", (string) file_get_contents( $goldenPath ) );

	expect( $canonical )->toBe( rtrim( $golden, "\n" ) );
} )->with( markupParityFixtures() );

it( 'has a golden for every fixture and no orphaned goldens', function () {
	$expected = array_keys( markupParityFixtures() );

	$actual = array_map(
		static fn ( string $path ): string => basename( $path, '.txt' ),
		glob( markupParityPath( 'goldens/*.txt' ) ) ?: []
	);

	sort( $expected );
	sort( $actual );

	expect( $actual )->toBe( $expected );
} );

/**
 * A `dropClassTokensMatching` source that compiles under `new RegExp()` but
 * not under PCRE is the worst kind of drift: `preg_match()` reports a failed
 * compile as `false`, which reads exactly like "no match", so the golden is
 * written *with* the token while the JS side drops it. Fail loudly here
 * instead, on both sides.
 */
it( 'compiles every declared divergence pattern', function () {
	$patterns = markupParityDropClassPatterns();

	expect( $patterns )->not->toBeEmpty();

	foreach ( $patterns as $pattern ) {
		expect( CanonicalMarkup::compileDropClassPattern( $pattern ) )->toBeString();
	}
} );
