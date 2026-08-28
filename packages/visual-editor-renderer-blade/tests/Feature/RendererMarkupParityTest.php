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
 * Delimiter separating the canonical markup from the canonical
 * per-instance CSS section in the golden. Mirrors `CSS_SECTION_DELIMITER`
 * in blade-parity.test.ts.
 */
function markupParityCssDelimiter(): string
{
	return '@@ renderer-instance-css @@';
}

/**
 * Renderer `<style data-ve-*>` attributes carrying the baseline /
 * global-styles / theme layer. That layer is a known, documented
 * divergence — Blade compiles it from theme.json
 * (`ThemeJsonTokensCompiler::compileLayoutRules()`), the JS renderers ship
 * a static `LAYOUT_BASELINE_CSS` — so it is dropped rather than compared,
 * to avoid encoding the same difference twice. Mirrors `GLOBAL_STYLE_ATTRS`
 * in blade-parity.test.ts.
 *
 * @return array<int, string>
 */
function markupParityGlobalStyleAttrs(): array
{
	return [
		'data-ve-global-styles',
		'data-ve-layout-baseline',
		'data-ve-theme',
		'data-ve-theme-tokens',
		'data-ve-block-library',
		'data-ve-block-library-theme',
	];
}

/**
 * Splits the renderer-injected style tags off the markup and returns the
 * markup (every `<style>/<link>/<script data-ve-*>` tag removed) plus the
 * captured per-instance CSS bodies. Blade folds column-width, photo-grid,
 * visibility, and flex-arbitrary rules into one `<style data-ve-responsive>`
 * block; the React/Vue renderers split them across several tags. Capturing
 * the bodies (minus the global/baseline layer) lets the rule *bodies* be
 * compared regardless of which tag each renderer delivers them in. Mirrors
 * `extractRendererCss()` in blade-parity.test.ts.
 *
 * @return array{markup: string, css: string}
 */
function markupParityExtractCss( string $html ): array
{
	$global   = markupParityGlobalStyleAttrs();
	$captured = [];

	$markup = (string) preg_replace_callback(
		'#<style\s+(data-ve-[a-z-]+)(?:="[^"]*")?\s*>(.*?)</style>#s',
		function ( array $matches ) use ( &$captured, $global ): string {
			if ( ! in_array( $matches[1], $global, true ) ) {
				$captured[] = $matches[2];
			}

			return '';
		},
		$html
	);

	$markup = (string) preg_replace( '#<link\b[^>]*\sdata-ve-[a-z-]+[^>]*>#', '', $markup );
	$markup = (string) preg_replace( '#<script\b[^>]*\sdata-ve-[a-z-]+[^>]*>.*?</script>#s', '', $markup );

	return [ 'markup' => $markup, 'css' => implode( '', $captured ) ];
}

/**
 * Splits a CSS string into top-level rules, tracking brace depth so an
 * `@media (...) { ... }` block stays a single rule. Mirrors
 * `splitCssRules()` in blade-parity.test.ts.
 *
 * @return array<int, string>
 */
function markupParitySplitCssRules( string $css ): array
{
	$rules = [];
	$depth = 0;
	$start = 0;
	$len   = strlen( $css );

	for ( $i = 0; $i < $len; $i++ ) {
		$ch = $css[ $i ];

		if ( '{' === $ch ) {
			$depth++;
		} elseif ( '}' === $ch ) {
			$depth--;

			if ( 0 === $depth ) {
				$rules[] = substr( $css, $start, $i - $start + 1 );
				$start   = $i + 1;
			}
		}
	}

	$tail = substr( $css, $start );

	if ( '' !== trim( $tail ) ) {
		$rules[] = $tail;
	}

	return $rules;
}

/**
 * Canonicalizes the captured per-instance CSS: split into top-level rules,
 * collapse insignificant whitespace, drop empties, and sort so delivery
 * differences (Blade folds every rule into one `<style data-ve-responsive>`
 * in push order; the JS renderers split them across tags in tree order)
 * never register as divergence — only a differing rule body does. Mirrors
 * `canonicalRendererCss()` in blade-parity.test.ts.
 */
function markupParityCanonicalCss( string $css ): string
{
	$rules = array_map(
		static fn ( string $rule ): string => trim( (string) preg_replace( '/[ \t\r\n\f\x0B]+/', ' ', $rule ) ),
		markupParitySplitCssRules( $css )
	);

	$rules = array_values( array_filter( $rules, static fn ( string $rule ): bool => '' !== $rule ) );

	sort( $rules, SORT_STRING );

	return implode( "\n", $rules );
}

it( 'matches the golden markup shared with the React and Vue renderers', function ( string $name, array $tree ) {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	$extracted = markupParityExtractCss( $rendered );

	$canonicalMarkup = CanonicalMarkup::fromHtml(
		$extracted['markup'],
		markupParityDropClassPatterns()
	);

	$canonicalCss = markupParityCanonicalCss( $extracted['css'] );

	$canonical = '' === $canonicalCss
		? $canonicalMarkup
		: $canonicalMarkup . "\n" . markupParityCssDelimiter() . "\n" . $canonicalCss;

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
 * instead, on both sides. The manifest may legitimately be empty once every
 * renderer has converged (as it is after #714), so this asserts "every
 * declared pattern compiles", not that any are declared.
 */
it( 'compiles every declared divergence pattern', function () {
	$patterns = markupParityDropClassPatterns();

	expect( $patterns )->toBeArray();

	foreach ( $patterns as $pattern ) {
		expect( CanonicalMarkup::compileDropClassPattern( $pattern ) )->toBeString();
	}
} );
