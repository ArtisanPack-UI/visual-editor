<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PresetRegistry;

it( 'returns empty append-mode lists when no presets are configured', function (): void {
	config()->set( 'artisanpack.visual-editor.presets', [] );

	expect( PresetRegistry::fromConfig() )->toBe( [
		'palette'      => [ 'mode' => 'append', 'entries' => [] ],
		'fontSizes'    => [ 'mode' => 'append', 'entries' => [] ],
		'fontFamilies' => [ 'mode' => 'append', 'entries' => [] ],
		'spacingSizes' => [ 'mode' => 'append', 'entries' => [] ],
	] );
} );

it( 'normalises a bare palette entry list as append mode', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'brand-navy', 'name' => 'Brand Navy', 'color' => '#0a2540' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette'] )->toBe( [
		'mode'    => 'append',
		'entries' => [
			[ 'slug' => 'brand-navy', 'name' => 'Brand Navy', 'color' => '#0a2540' ],
		],
	] );
} );

it( 'honours the explicit replace mode wrapper', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'mode'    => 'replace',
		'entries' => [
			[ 'slug' => 'ink', 'name' => 'Ink', 'color' => '#111' ],
		],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['mode'] )->toBe( 'replace' )
		->and( $presets['palette']['entries'] )->toBe( [
			[ 'slug' => 'ink', 'name' => 'Ink', 'color' => '#111' ],
		] );
} );

it( 'falls back to append when the mode value is unknown', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'mode'    => 'merge',
		'entries' => [
			[ 'slug' => 'brand', 'color' => '#000' ],
		],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['mode'] )->toBe( 'append' );
} );

it( 'derives a title-cased name from the slug when none is given', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'brand_navy', 'color' => '#0a2540' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [
		[ 'slug' => 'brand_navy', 'name' => 'Brand Navy', 'color' => '#0a2540' ],
	] );
} );

it( 'drops palette entries with an invalid slug', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'bad slug', 'color' => '#000' ],
		[ 'slug' => 'good', 'color' => '#111' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [
		[ 'slug' => 'good', 'name' => 'Good', 'color' => '#111' ],
	] );
} );

it( 'drops palette entries whose color contains an HTML-attribute-breakout character', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'x', 'color' => 'red<script>' ],
		[ 'slug' => 'y', 'color' => 'red"and-quote' ],
		[ 'slug' => 'z', 'color' => 'back`tick' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [] );
} );

it( 'preserves internal whitespace so multi-argument CSS color functions survive', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'a', 'color' => 'rgb(0, 0, 0)' ],
		[ 'slug' => 'b', 'color' => 'oklch(0.5 0.1 200)' ],
		[ 'slug' => 'c', 'color' => 'hsl(210deg 100% 50%)' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( array_column( $presets['palette']['entries'], 'color' ) )->toBe( [
		'rgb(0, 0, 0)',
		'oklch(0.5 0.1 200)',
		'hsl(210deg 100% 50%)',
	] );
} );

it( 'accepts hex-only colors and non-whitespace CSS values', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'a', 'color' => '#abc' ],
		[ 'slug' => 'b', 'color' => '#aabbcc' ],
		[ 'slug' => 'c', 'color' => '#aabbccdd' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( array_column( $presets['palette']['entries'], 'slug' ) )
		->toBe( [ 'a', 'b', 'c' ] );
} );

it( 'deduplicates palette entries whose slug collapses to the same value', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		[ 'slug' => 'Brand', 'color' => '#111' ],
		[ 'slug' => ' brand ', 'color' => '#222' ],
		[ 'slug' => 'other', 'color' => '#333' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( array_column( $presets['palette']['entries'], 'slug' ) )
		->toBe( [ 'brand', 'other' ] );
} );

it( 'normalises font_sizes, font_families, and spacing_sizes entries', function (): void {
	config()->set( 'artisanpack.visual-editor.presets', [
		'font_sizes'    => [
			[ 'slug' => 'display', 'name' => 'Display', 'size' => '48px' ],
		],
		'font_families' => [
			[ 'slug' => 'brand', 'name' => 'Brand', 'fontFamily' => 'Inter, sans-serif' ],
		],
		'spacing_sizes' => [
			[ 'slug' => 'gutter', 'name' => 'Gutter', 'size' => '2rem' ],
		],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['fontSizes']['entries'] )->toBe( [
		[ 'slug' => 'display', 'name' => 'Display', 'size' => '48px' ],
	] );
	expect( $presets['fontFamilies']['entries'] )->toBe( [
		[ 'slug' => 'brand', 'name' => 'Brand', 'fontFamily' => 'Inter, sans-serif' ],
	] );
	expect( $presets['spacingSizes']['entries'] )->toBe( [
		[ 'slug' => 'gutter', 'name' => 'Gutter', 'size' => '2rem' ],
	] );
} );

it( 'accepts snake_case font_family alongside camelCase for font families', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.font_families', [
		[ 'slug' => 'brand', 'font_family' => 'Inter, sans-serif' ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['fontFamilies']['entries'] )->toBe( [
		[ 'slug' => 'brand', 'name' => 'Brand', 'fontFamily' => 'Inter, sans-serif' ],
	] );
} );

it( 'drops entries missing the value key required for their list', function (): void {
	config()->set( 'artisanpack.visual-editor.presets', [
		'palette'       => [ [ 'slug' => 'a' ] ],
		'font_sizes'    => [ [ 'slug' => 'a' ] ],
		'font_families' => [ [ 'slug' => 'a' ] ],
		'spacing_sizes' => [ [ 'slug' => 'a' ] ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [] );
	expect( $presets['fontSizes']['entries'] )->toBe( [] );
	expect( $presets['fontFamilies']['entries'] )->toBe( [] );
	expect( $presets['spacingSizes']['entries'] )->toBe( [] );
} );

it( 'ignores non-array entries inside a list', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'not-an-array',
		[ 'slug' => 'good', 'color' => '#111' ],
		42,
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette']['entries'] )->toBe( [
		[ 'slug' => 'good', 'name' => 'Good', 'color' => '#111' ],
	] );
} );

it( 'returns empty append lists when a preset key is not an array', function (): void {
	config()->set( 'artisanpack.visual-editor.presets.palette', 'nope' );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette'] )->toBe( [ 'mode' => 'append', 'entries' => [] ] );
} );

it( 'preserves a replace wrapper with an empty entries list as an explicit clear', function (): void {
	// The PHP layer emits the mode verbatim so the JS merge helper can
	// treat this shape as an explicit "no presets for this list"
	// instruction (per the "host wins outright" contract in
	// `config/visual-editor.php`). A regression here silently reverts
	// the seam to defaults-preserving.
	config()->set( 'artisanpack.visual-editor.presets.palette', [
		'mode'    => 'replace',
		'entries' => [],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( $presets['palette'] )->toBe( [ 'mode' => 'replace', 'entries' => [] ] );
} );

it( 'returns the whole config record even when only one list is populated', function (): void {
	config()->set( 'artisanpack.visual-editor.presets', [
		'palette' => [ [ 'slug' => 'x', 'color' => '#111' ] ],
	] );

	$presets = PresetRegistry::fromConfig();

	expect( array_keys( $presets ) )
		->toBe( [ 'palette', 'fontSizes', 'fontFamilies', 'spacingSizes' ] );
	expect( $presets['fontSizes'] )->toBe( [ 'mode' => 'append', 'entries' => [] ] );
} );

describe( 'effectiveSpacingSizes() (#814)', function (): void {
	beforeEach( function (): void {
		config()->set( 'artisanpack.visual-editor.presets', [] );
	} );

	it( 'falls back to the package defaults when the theme ships no spacing sizes', function ( mixed $themeSizes ): void {
		expect( PresetRegistry::effectiveSpacingSizes( $themeSizes ) )
			->toBe( PresetRegistry::DEFAULT_SPACING_SIZES );
	} )->with( [
		'null'              => [ null ],
		'empty list'        => [ [] ],
		'non-array'         => [ 'nope' ],
		'no usable entries' => [ [ [ 'slug' => '', 'size' => '1rem' ], [ 'slug' => 'x' ], 'junk' ] ],
	] );

	it( 'uses the theme list instead of the defaults, first slug winning', function (): void {
		expect( PresetRegistry::effectiveSpacingSizes( [
			[ 'slug' => ' SM ', 'size' => '4px', 'name' => 'Small' ],
			[ 'slug' => 'sm', 'size' => '99px' ],
			[ 'slug' => 'lg', 'size' => '2rem' ],
		] ) )->toBe( [
			[ 'slug' => 'sm', 'size' => '4px' ],
			[ 'slug' => 'lg', 'size' => '2rem' ],
		] );
	} );

	it( 'merges host append entries into the defaults, overriding colliding slugs in place', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.spacing_sizes', [
			[ 'slug' => '40', 'size' => '2rem' ],
			[ 'slug' => 'gutter', 'size' => '18px' ],
		] );

		$sizes = PresetRegistry::effectiveSpacingSizes( null );

		expect( $sizes[2] )->toBe( [ 'slug' => '40', 'size' => '2rem' ] );
		expect( end( $sizes ) )->toBe( [ 'slug' => 'gutter', 'size' => '18px' ] );
		expect( $sizes )->toHaveCount( 7 );
	} );

	it( 'lets host replace mode wipe the defaults, even with no entries', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.spacing_sizes', [ 'mode' => 'replace', 'entries' => [] ] );

		expect( PresetRegistry::effectiveSpacingSizes( null ) )->toBe( [] );
	} );

	it( 'keeps the theme list under an empty host replace, mirroring the editor', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.spacing_sizes', [ 'mode' => 'replace', 'entries' => [] ] );

		expect( PresetRegistry::effectiveSpacingSizes( [ [ 'slug' => 'sm', 'size' => '4px' ] ] ) )
			->toBe( [ [ 'slug' => 'sm', 'size' => '4px' ] ] );
	} );

	it( 'merges host entries on top of a theme list', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.spacing_sizes', [
			[ 'slug' => 'sm', 'size' => '6px' ],
		] );

		expect( PresetRegistry::effectiveSpacingSizes( [
			[ 'slug' => 'sm', 'size' => '4px' ],
			[ 'slug' => 'lg', 'size' => '2rem' ],
		] ) )->toBe( [
			[ 'slug' => 'sm', 'size' => '6px' ],
			[ 'slug' => 'lg', 'size' => '2rem' ],
		] );
	} );
} );

describe( 'spacingPresetsCss() (#814)', function (): void {
	beforeEach( function (): void {
		config()->set( 'artisanpack.visual-editor.presets', [] );
	} );

	it( 'declares every default spacing preset on :root when the theme has none', function (): void {
		expect( PresetRegistry::spacingPresetsCss( null ) )->toBe(
			":root {\n"
			. "\t--wp--preset--spacing--20: 0.5rem;\n"
			. "\t--wp--preset--spacing--30: 1rem;\n"
			. "\t--wp--preset--spacing--40: 1.5rem;\n"
			. "\t--wp--preset--spacing--50: 3rem;\n"
			. "\t--wp--preset--spacing--60: 5rem;\n"
			. "\t--wp--preset--spacing--70: 7rem;\n"
			. '}',
		);
	} );

	it( 'kebabs slugs and skips values that could break out of the style block', function (): void {
		$css = PresetRegistry::spacingPresetsCss( [
			[ 'slug' => 'Big Gap', 'size' => '2rem' ],
			[ 'slug' => 'evil', 'size' => '1px; } body { display: none' ],
			[ 'slug' => 'tag', 'size' => '1px</style>' ],
			[ 'slug' => 'comment', 'size' => '1px /* swallow' ],
			[ 'slug' => 'escape', 'size' => '1px\\3b' ],
		] );

		expect( $css )->toContain( '--wp--preset--spacing--big-gap: 2rem;' )
			->not->toContain( 'evil' )
			->not->toContain( '</style>' )
			->not->toContain( 'comment' )
			->not->toContain( 'escape' );
	} );

	it( 'returns an empty string when no preset survives', function (): void {
		config()->set( 'artisanpack.visual-editor.presets.spacing_sizes', [ 'mode' => 'replace', 'entries' => [] ] );

		expect( PresetRegistry::spacingPresetsCss( null ) )->toBe( '' );
	} );
} );

describe( 'presetSlug() (#814)', function (): void {
	it( 'lowercases and maps every character outside [a-z0-9-] to a dash, without collapsing', function ( string $in, string $out ): void {
		expect( PresetRegistry::presetSlug( $in ) )->toBe( $out );
	} )->with( [
		'plain'        => [ '40', '40' ],
		'underscore'   => [ 'big_gap', 'big-gap' ],
		'double dash'  => [ 'a--b', 'a--b' ],
		'double under' => [ 'a__b', 'a--b' ],
		'space + case' => [ 'Big Gap', 'big-gap' ],
		'edge dashes'  => [ '-x-', '-x-' ],
		'digits'       => [ '2xl', '2xl' ],
		'camel case'   => [ 'bigGap', 'big-gap' ],
	] );

	it( 'declares underscore and double-dash slugs exactly as the references expand them', function (): void {
		$css = PresetRegistry::spacingPresetsCss( [
			[ 'slug' => 'big_gap', 'size' => '2rem' ],
			[ 'slug' => 'x--y', 'size' => '3rem' ],
		] );

		expect( $css )
			->toContain( '--wp--preset--spacing--big-gap: 2rem;' )
			->toContain( '--wp--preset--spacing--x--y: 3rem;' );
	} );
} );

describe( 'isSafeCssValue() (#814)', function (): void {
	it( 'accepts ordinary lengths and balanced functions', function ( string $value ): void {
		expect( PresetRegistry::isSafeCssValue( $value ) )->toBeTrue();
	} )->with( [
		'rem'         => [ '1.5rem' ],
		'calc'        => [ 'calc(1rem + 2px)' ],
		'nested'      => [ 'clamp(1rem, calc(2vw + 1rem), 3rem)' ],
		'custom prop' => [ 'var(--wp--custom--gap)' ],
	] );

	it( 'rejects values that could unbalance or break out of the style block', function ( string $value ): void {
		expect( PresetRegistry::isSafeCssValue( $value ) )->toBeFalse();
	} )->with( [
		'empty'          => [ '' ],
		'semicolon'      => [ '1px; color: red' ],
		'brace'          => [ '1px }' ],
		'tag'            => [ '1px</style>' ],
		'comment open'   => [ '1px /* x' ],
		'comment close'  => [ '1px */' ],
		'backslash'      => [ '1px\\3b' ],
		'unclosed paren' => [ 'calc(1rem' ],
		'stray close'    => [ '1rem)' ],
		'misordered'     => [ ')1rem(' ],
		'double quote'   => [ '"1rem"' ],
		'single quote'   => [ "'1rem'" ],
		'newline'        => [ "1rem\n2rem" ],
		'tab'            => [ "1rem\t" ],
		'nul'            => [ "1rem\0" ],
		'del'            => [ "1rem\x7F" ],
	] );

	it( 'drops an unbalanced value from the declared block', function (): void {
		$css = PresetRegistry::spacingPresetsCss( [
			[ 'slug' => 'broken', 'size' => 'calc(1rem' ],
			[ 'slug' => 'ok', 'size' => '1rem' ],
		] );

		expect( $css )->not->toContain( 'broken' )->toContain( '--wp--preset--spacing--ok: 1rem;' );
	} );
} );

it( 'keeps the React / Vue renderers\' DEFAULT_SPACING_SIZES in sync with the package defaults (#814)', function ( string $renderer ): void {
	$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/packages/visual-editor-renderer-' . $renderer . '/src/support/layoutBaselineCss.ts' );

	$list = static function ( string $constant, string $valueKey ) use ( $source ): array {
		preg_match( '/export const ' . $constant . '\\b[^=]*= \\[(.*?)\\];/s', $source, $block );
		preg_match_all( "/\\{ slug: '([^']+)', {$valueKey}: '([^']+)' \\}/", $block[1] ?? '', $matches, PREG_SET_ORDER );

		return array_map( static fn ( array $match ): array => [ 'slug' => $match[1], $valueKey => $match[2] ], $matches );
	};

	expect( $list( 'DEFAULT_SPACING_SIZES', 'size' ) )->toBe( PresetRegistry::DEFAULT_SPACING_SIZES );
	// #821 — and the font-size / palette defaults.
	expect( $list( 'DEFAULT_FONT_SIZES', 'size' ) )->toBe( PresetRegistry::DEFAULT_FONT_SIZES );
	expect( $list( 'DEFAULT_PALETTE', 'color' ) )->toBe( PresetRegistry::DEFAULT_PALETTE );
} )->with( [ 'react', 'vue' ] );
