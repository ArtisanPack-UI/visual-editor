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
 * The `drop*` keys a `knownDivergences` entry may carry — exactly one per
 * entry. Mirrors `DROP_KEYS` in blade-parity.test.ts.
 *
 * @return array<int, string>
 */
function markupParityDropKeys(): array
{
	return [
		'dropClassTokensMatching',
		'dropAttributesMatching',
		'dropElementsMatching',
		'dropRendererStyleTag',
	];
}

/**
 * The declared, documented renderer divergences.
 *
 * @return array<int, array<string, string>>
 */
function markupParityDivergences(): array
{
	return markupParityManifest()['knownDivergences'] ?? [];
}

/**
 * The declared divergences that apply to one fixture, grouped by `drop*`
 * key. An entry's optional `fixturesMatching` regex (tested against the
 * fixture name) scopes it, so a divergence declared for one block cannot
 * mask the same drift anywhere else. Mirrors `divergencesFor()` in
 * blade-parity.test.ts.
 *
 * @since 1.13.0
 *
 * @param  string  $name  Fixture name.
 *
 * @return array<string, array<int, string>> Drop key => declared values.
 */
function markupParityDivergencesFor( string $name ): array
{
	$grouped = array_fill_keys( markupParityDropKeys(), [] );

	foreach ( markupParityDivergences() as $divergence ) {
		$scope = $divergence['fixturesMatching'] ?? null;

		if ( null !== $scope && 1 !== preg_match( CanonicalMarkup::compileDropClassPattern( $scope ), $name ) ) {
			continue;
		}

		foreach ( markupParityDropKeys() as $key ) {
			if ( isset( $divergence[ $key ] ) && is_string( $divergence[ $key ] ) ) {
				$grouped[ $key ][] = $divergence[ $key ];
			}
		}
	}

	return $grouped;
}

/**
 * Loads the shared, language-neutral fixture set.
 *
 * A fixture may carry an optional `templateParts` list (`slug`, `area`,
 * `blocks`); the JS side hands it to `BlockTree`'s `templateParts` prop
 * and this suite serves it through a TemplatePartResolver stub (see
 * markupParityBindTemplateParts()).
 *
 * @return array<string, array{0: string, 1: array<int, mixed>, 2: array<int, array<string, mixed>>}>
 */
function markupParityFixtures(): array
{
	$json = markupParityManifest();

	$dataset = [];

	foreach ( $json['fixtures'] as $fixture ) {
		$dataset[ $fixture['name'] ] = [ $fixture['name'], $fixture['tree'], $fixture['templateParts'] ?? [] ];
	}

	return $dataset;
}

/**
 * Serves a fixture's `templateParts` through a stub bound under
 * cms-framework's TemplatePartResolver FQCN — the resolver the Blade
 * navigation partial asks for an `overlay` part. Mirrors the JS side
 * passing the same records to `BlockTree`'s `templateParts` prop.
 *
 * @since 1.13.0
 *
 * @param  array<int, array<string, mixed>>  $templateParts  Fixture template-part records.
 */
function markupParityBindTemplateParts( array $templateParts ): void
{
	$parts = [];

	foreach ( $templateParts as $part ) {
		$parts[ (string) $part['slug'] ] = $part;
	}

	$stub = new class( $parts ) {
		/**
		 * @param  array<string, array<string, mixed>>  $parts  Slug => template-part record.
		 */
		public function __construct( private array $parts ) {}

		public function resolve( string $slug ): ?object
		{
			if ( ! array_key_exists( $slug, $this->parts ) ) {
				return null;
			}

			return (object) [
				'area'   => $this->parts[ $slug ]['area'] ?? null,
				'blocks' => $this->parts[ $slug ]['blocks'] ?? [],
			];
		}
	};

	app()->bind(
		'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Resolution\\TemplatePartResolver',
		fn () => $stub,
	);
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
 * @param  string              $html              Rendered HTML.
 * @param  array<int, string>  $droppedStyleTags  Declared `dropRendererStyleTag`
 *                                                attributes, dropped like the
 *                                                global layer rather than compared.
 *
 * @return array{markup: string, css: string}
 */
function markupParityExtractCss( string $html, array $droppedStyleTags = [] ): array
{
	$global   = array_merge( markupParityGlobalStyleAttrs(), $droppedStyleTags );
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

it( 'matches the golden markup shared with the React and Vue renderers', function ( string $name, array $tree, array $templateParts ) {
	if ( [] !== $templateParts ) {
		markupParityBindTemplateParts( $templateParts );
	}

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	$divergences = markupParityDivergencesFor( $name );

	$extracted = markupParityExtractCss( $rendered, $divergences['dropRendererStyleTag'] );

	$canonicalMarkup = CanonicalMarkup::fromHtml(
		$extracted['markup'],
		$divergences['dropClassTokensMatching'],
		$divergences['dropAttributesMatching'],
		$divergences['dropElementsMatching'],
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
	$divergences = markupParityDivergences();

	expect( $divergences )->toBeArray();

	foreach ( $divergences as $divergence ) {
		foreach ( [ 'dropClassTokensMatching', 'dropAttributesMatching', 'dropElementsMatching', 'fixturesMatching' ] as $key ) {
			if ( isset( $divergence[ $key ] ) ) {
				expect( CanonicalMarkup::compileDropClassPattern( $divergence[ $key ] ) )->toBeString();
			}
		}
	}
} );

/**
 * A malformed entry would otherwise silently drop nothing (or everything)
 * on one side only. Mirrors the vitest twin.
 */
it( 'declares every divergence with a reason and exactly one drop rule', function () {
	foreach ( markupParityDivergences() as $divergence ) {
		expect( $divergence['id'] ?? null )->toBeString()
			->and( $divergence['issue'] ?? '' )->not->toBe( '' )
			->and( $divergence['reason'] ?? '' )->not->toBe( '' )
			->and( array_intersect( markupParityDropKeys(), array_keys( $divergence ) ) )->toHaveCount( 1 );
	}
} );

/**
 * A `fixturesMatching` scope that matches no fixture is a stale entry: the
 * divergence it documents is no longer exercised. Mirrors the vitest twin.
 */
it( 'scopes every divergence to at least one fixture', function () {
	$names = array_keys( markupParityFixtures() );

	foreach ( markupParityDivergences() as $divergence ) {
		if ( ! isset( $divergence['fixturesMatching'] ) ) {
			continue;
		}

		$pattern = CanonicalMarkup::compileDropClassPattern( $divergence['fixturesMatching'] );

		expect( preg_grep( $pattern, $names ) )->not->toBeEmpty( $divergence['id'] . ' matches no fixture' );
	}
} );
