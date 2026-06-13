<?php

/**
 * Unit tests for {@see BlockSupports::compile}. Each `describe` block
 * pins one WordPress core block-supports module against our
 * implementation. The fixtures use the exact attribute shapes
 * Gutenberg serializes into `block_content` JSON — captured from the
 * jmwd-default seed and from the WP block.json reference.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

describe( 'BlockSupports::compile (align)', function (): void {
	it( 'maps wide / full to alignwide / alignfull classes', function (): void {
		expect( BlockSupports::compile( [ 'align' => 'wide' ] )['classes'] )->toBe( [ 'alignwide' ] );
		expect( BlockSupports::compile( [ 'align' => 'full' ] )['classes'] )->toBe( [ 'alignfull' ] );
	} );

	it( 'maps left / center / right to alignX AND has-text-align-X (mirrors WP)', function (): void {
		// Two classes per side: `align{value}` for container blocks
		// (group, columns) and `has-text-align-{value}` for text blocks
		// (paragraph, heading). Both ride along regardless of block
		// type; the partial's base class disambiguates downstream.
		expect( BlockSupports::compile( [ 'align' => 'center' ] )['classes'] )
			->toBe( [ 'aligncenter', 'has-text-align-center' ] );
		expect( BlockSupports::compile( [ 'align' => 'left' ] )['classes'] )
			->toBe( [ 'alignleft', 'has-text-align-left' ] );
	} );

	it( 'drops unrecognized align values rather than emitting arbitrary classes', function (): void {
		expect( BlockSupports::compile( [ 'align' => 'sideways' ] )['classes'] )->toBe( [] );
		expect( BlockSupports::compile( [ 'align' => '' ] )['classes'] )->toBe( [] );
		expect( BlockSupports::compile( [ 'align' => 123 ] )['classes'] )->toBe( [] );
	} );
} );

describe( 'BlockSupports::compile (textAlign)', function (): void {
	it( 'emits has-text-align-{value} class — not inline style', function (): void {
		$result = BlockSupports::compile( [ 'textAlign' => 'center' ] );

		expect( $result['classes'] )->toContain( 'has-text-align-center' );
		expect( $result['style'] )->toBe( '' );
	} );

	it( 'accepts justify alongside left / center / right', function (): void {
		expect( BlockSupports::compile( [ 'textAlign' => 'justify' ] )['classes'] )
			->toContain( 'has-text-align-justify' );
	} );

	it( 'drops unknown text-align values', function (): void {
		expect( BlockSupports::compile( [ 'textAlign' => 'middle' ] )['classes'] )->toBe( [] );
	} );
} );

describe( 'BlockSupports::compile (color)', function (): void {
	it( 'palette slug → has-{slug}-background-color + has-background, custom value → inline + has-background', function (): void {
		$slug = BlockSupports::compile( [ 'backgroundColor' => 'accent' ] );

		expect( $slug['classes'] )->toContain( 'has-accent-background-color' );
		expect( $slug['classes'] )->toContain( 'has-background' );
		expect( $slug['style'] )->toBe( '' );

		$custom = BlockSupports::compile( [
			'style' => [ 'color' => [ 'background' => '#0f172a' ] ],
		] );

		expect( $custom['style'] )->toContain( 'background-color: #0f172a;' );
		expect( $custom['classes'] )->toContain( 'has-background' );
		expect( $custom['classes'] )->not->toContain( 'has-accent-background-color' );
	} );

	it( 'slug wins over custom when both are set (matches WP core priority)', function (): void {
		$result = BlockSupports::compile( [
			'backgroundColor' => 'accent',
			'style'           => [ 'color' => [ 'background' => '#000' ] ],
		] );

		expect( $result['classes'] )->toContain( 'has-accent-background-color' );
		expect( $result['style'] )->not->toContain( 'background-color: #000' );
	} );

	it( 'text color follows the same slug-vs-custom rules with the has-text-color marker', function (): void {
		$slug   = BlockSupports::compile( [ 'textColor' => 'primary' ] );
		$custom = BlockSupports::compile( [ 'style' => [ 'color' => [ 'text' => '#111827' ] ] ] );

		expect( $slug['classes'] )->toContain( 'has-primary-color' );
		expect( $slug['classes'] )->toContain( 'has-text-color' );

		expect( $custom['style'] )->toContain( 'color: #111827;' );
		expect( $custom['classes'] )->toContain( 'has-text-color' );
	} );

	it( 'gradient palette slug + custom both emit has-background; gradient inline uses `background:` shorthand', function (): void {
		$slug   = BlockSupports::compile( [ 'gradient' => 'vivid-cyan' ] );
		$custom = BlockSupports::compile( [
			'style' => [ 'color' => [ 'gradient' => 'linear-gradient(135deg, #000, #fff)' ] ],
		] );

		expect( $slug['classes'] )->toContain( 'has-vivid-cyan-gradient-background' );
		expect( $slug['classes'] )->toContain( 'has-background' );
		expect( $custom['style'] )->toContain( 'background: linear-gradient(135deg, #000, #fff);' );
	} );

	it( 'expands `var:preset|color|primary` references in custom values', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'color' => [ 'background' => 'var:preset|color|primary' ] ],
		] );

		expect( $result['style'] )->toContain( 'background-color: var(--wp--preset--color--primary);' );
	} );

	it( 'slugifies a palette slug that arrived as `Primary Accent`', function (): void {
		$result = BlockSupports::compile( [ 'backgroundColor' => 'Primary Accent' ] );

		expect( $result['classes'] )->toContain( 'has-primary-accent-background-color' );
	} );
} );

describe( 'BlockSupports::compile (spacing)', function (): void {
	it( 'accepts a single padding string for all four sides', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'spacing' => [ 'padding' => '1.5rem' ] ],
		] );

		expect( $result['style'] )->toContain( 'padding: 1.5rem;' );
	} );

	it( 'accepts per-side padding objects', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'spacing' => [
					'padding' => [
						'top'    => '1rem',
						'right'  => '2rem',
						'bottom' => '3rem',
						'left'   => '4rem',
					],
				],
			],
		] );

		expect( $result['style'] )->toContain( 'padding-top: 1rem;' );
		expect( $result['style'] )->toContain( 'padding-right: 2rem;' );
		expect( $result['style'] )->toContain( 'padding-bottom: 3rem;' );
		expect( $result['style'] )->toContain( 'padding-left: 4rem;' );
	} );

	it( 'maps blockGap string to the --wp--style--block-gap custom property', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'spacing' => [ 'blockGap' => '1.25rem' ] ],
		] );

		expect( $result['style'] )->toContain( '--wp--style--block-gap: 1.25rem;' );
	} );

	it( 'maps blockGap object to row-gap / column-gap', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'spacing' => [ 'blockGap' => [ 'top' => '1rem', 'left' => '2rem' ] ] ],
		] );

		expect( $result['style'] )->toContain( 'row-gap: 1rem;' );
		expect( $result['style'] )->toContain( 'column-gap: 2rem;' );
	} );

	it( 'expands preset references in spacing values', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'spacing' => [ 'padding' => 'var:preset|spacing|40' ] ],
		] );

		expect( $result['style'] )->toContain( 'padding: var(--wp--preset--spacing--40);' );
	} );
} );

describe( 'BlockSupports::compile (border)', function (): void {
	it( 'maps a single radius value to border-radius', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'border' => [ 'radius' => '0.5rem' ] ],
		] );

		expect( $result['style'] )->toContain( 'border-radius: 0.5rem;' );
	} );

	it( 'maps a per-corner radius object to border-{corner}-radius', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'border' => [
					'radius' => [
						'topLeft'     => '4px',
						'topRight'    => '8px',
						'bottomLeft'  => '12px',
						'bottomRight' => '16px',
					],
				],
			],
		] );

		expect( $result['style'] )->toContain( 'border-top-left-radius: 4px;' );
		expect( $result['style'] )->toContain( 'border-top-right-radius: 8px;' );
		expect( $result['style'] )->toContain( 'border-bottom-left-radius: 12px;' );
		expect( $result['style'] )->toContain( 'border-bottom-right-radius: 16px;' );
	} );

	it( 'forces border-style: solid when only width is declared so the border actually renders', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'border' => [ 'width' => '2px', 'color' => '#000' ] ],
		] );

		expect( $result['style'] )->toContain( 'border-width: 2px;' );
		expect( $result['style'] )->toContain( 'border-style: solid;' );
	} );

	it( 'respects a user-supplied border-style instead of overriding with solid', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'border' => [ 'width' => '2px', 'style' => 'dashed', 'color' => '#000' ] ],
		] );

		expect( $result['style'] )->toContain( 'border-style: dashed;' );
		expect( substr_count( $result['style'], 'border-style:' ) )->toBe( 1 );
	} );

	it( 'maps per-side border objects with a per-side style fallback (not a global one)', function (): void {
		// Side-only width must NOT trigger a global `border-style: solid`
		// fallback — that would turn on the untouched edges with the
		// browser-default width and render a full box instead of the
		// single requested top border (CodeRabbit on PR #457).
		$result = BlockSupports::compile( [
			'style' => [
				'border' => [
					'top' => [ 'color' => '#000', 'width' => '1px' ],
				],
			],
		] );

		expect( $result['style'] )->toContain( 'border-top-color: #000;' );
		expect( $result['style'] )->toContain( 'border-top-width: 1px;' );
		// Per-side fallback only.
		expect( $result['style'] )->toContain( 'border-top-style: solid;' );
		// NO global fallback that would activate the other three edges.
		expect( $result['style'] )->not->toContain( 'border-style: solid;' );
	} );

	it( 'emits per-side style fallbacks for every side that has a width', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'border' => [
					'top'    => [ 'width' => '1px' ],
					'bottom' => [ 'width' => '2px' ],
				],
			],
		] );

		expect( $result['style'] )->toContain( 'border-top-style: solid;' );
		expect( $result['style'] )->toContain( 'border-bottom-style: solid;' );
		// Untouched sides stay untouched.
		expect( $result['style'] )->not->toContain( 'border-right-style:' );
		expect( $result['style'] )->not->toContain( 'border-left-style:' );
		expect( $result['style'] )->not->toContain( 'border-style: solid;' );
	} );

	it( 'respects an explicit per-side style and skips the per-side fallback', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'border' => [
					'top' => [ 'width' => '2px', 'style' => 'dashed' ],
				],
			],
		] );

		expect( $result['style'] )->toContain( 'border-top-style: dashed;' );
		// No duplicate / no override from the fallback.
		expect( substr_count( $result['style'], 'border-top-style:' ) )->toBe( 1 );
		expect( $result['style'] )->not->toContain( 'border-top-style: solid;' );
	} );

	it( 'maps the borderColor palette slug', function (): void {
		$result = BlockSupports::compile( [ 'borderColor' => 'accent' ] );

		expect( $result['classes'] )->toContain( 'has-border-color' );
		expect( $result['classes'] )->toContain( 'has-accent-border-color' );
	} );
} );

describe( 'BlockSupports::compile (typography)', function (): void {
	it( 'maps palette slugs to has-{slug}-font-size + has-defined-font-size', function (): void {
		$result = BlockSupports::compile( [ 'fontSize' => 'large' ] );

		expect( $result['classes'] )->toContain( 'has-large-font-size' );
		expect( $result['classes'] )->toContain( 'has-defined-font-size' );
	} );

	it( 'maps fontFamily palette slug', function (): void {
		$result = BlockSupports::compile( [ 'fontFamily' => 'mono' ] );

		expect( $result['classes'] )->toContain( 'has-mono-font-family' );
	} );

	it( 'gives slug precedence over custom for fontSize / fontFamily (matches color path)', function (): void {
		$result = BlockSupports::compile( [
			'fontSize'   => 'large',
			'fontFamily' => 'mono',
			'style'      => [ 'typography' => [
				'fontSize'   => '2rem',
				'fontFamily' => 'serif',
				// Other keys still emit inline — they don't have a
				// slug counterpart so there's nothing to conflict.
				'fontWeight' => '700',
			] ],
		] );

		expect( $result['classes'] )->toContain( 'has-large-font-size' );
		expect( $result['classes'] )->toContain( 'has-mono-font-family' );
		expect( $result['style'] )->not->toContain( 'font-size: 2rem' );
		expect( $result['style'] )->not->toContain( 'font-family: serif' );
		expect( $result['style'] )->toContain( 'font-weight: 700' );
	} );

	it( 'maps every supported style.typography.* key', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'typography' => [
					'fontSize'       => '1.25rem',
					'fontFamily'     => 'system-ui',
					'fontWeight'     => '600',
					'fontStyle'      => 'italic',
					'lineHeight'     => '1.4',
					'letterSpacing'  => '0.02em',
					'textTransform'  => 'uppercase',
					'textDecoration' => 'underline',
				],
			],
		] );

		expect( $result['style'] )->toContain( 'font-size: 1.25rem;' );
		expect( $result['style'] )->toContain( 'font-family: system-ui;' );
		expect( $result['style'] )->toContain( 'font-weight: 600;' );
		expect( $result['style'] )->toContain( 'font-style: italic;' );
		expect( $result['style'] )->toContain( 'line-height: 1.4;' );
		expect( $result['style'] )->toContain( 'letter-spacing: 0.02em;' );
		expect( $result['style'] )->toContain( 'text-transform: uppercase;' );
		expect( $result['style'] )->toContain( 'text-decoration: underline;' );
	} );
} );

describe( 'BlockSupports::compile (className + anchor)', function (): void {
	it( 'appends user-supplied className tokens', function (): void {
		$result = BlockSupports::compile( [ 'className' => 'site-footer  brand-section' ] );

		expect( $result['classes'] )->toContain( 'site-footer' );
		expect( $result['classes'] )->toContain( 'brand-section' );
	} );

	it( 'lifts anchor into the id slot', function (): void {
		$result = BlockSupports::compile( [ 'anchor' => 'hero-section' ] );

		expect( $result['id'] )->toBe( 'hero-section' );
	} );

	it( 'sanitizes anchor characters that could escape the attribute', function (): void {
		$result = BlockSupports::compile( [ 'anchor' => 'hero"><script>x</script>' ] );

		expect( $result['id'] )->toBe( 'heroscriptxscript' );
	} );

	it( 'returns null id when no anchor is set', function (): void {
		expect( BlockSupports::compile( [] )['id'] )->toBeNull();
	} );
} );

describe( 'BlockSupports::wrapperAttrs', function (): void {
	it( 'merges baseClasses before compiled classes and escapes the values', function (): void {
		$rendered = BlockSupports::wrapperAttrs(
			[ 'align' => 'full', 'className' => 'site-footer' ],
			[ 'wp-block-group', 'is-layout-constrained' ],
		);

		expect( $rendered )->toContain( 'class="wp-block-group is-layout-constrained alignfull site-footer"' );
	} );

	it( 'emits style and id slots when present', function (): void {
		$rendered = BlockSupports::wrapperAttrs(
			[
				'anchor' => 'hero',
				'style'  => [ 'color' => [ 'background' => '#000' ] ],
			],
			[ 'wp-block-group' ],
		);

		expect( $rendered )->toContain( 'style="background-color: #000;"' );
		expect( $rendered )->toContain( 'id="hero"' );
	} );

	it( 'returns an empty string when there are no classes, no styles, no id', function (): void {
		expect( BlockSupports::wrapperAttrs( [], [] ) )->toBe( '' );
	} );

	it( 'starts with a single leading space so it splices into a tag without doubling whitespace', function (): void {
		$rendered = BlockSupports::wrapperAttrs( [], [ 'wp-block-group' ] );

		expect( $rendered )->toStartWith( ' ' );
		expect( $rendered )->not->toStartWith( '  ' );
	} );

	it( 'escapes special characters in className tokens', function (): void {
		$rendered = BlockSupports::wrapperAttrs( [ 'className' => 'a"<b' ], [] );

		// The compiled class list is shell-safe — `"` and `<` are
		// HTML-escaped by `e()` before they reach the wrapper output.
		expect( $rendered )->not->toContain( '"<b' );
		expect( $rendered )->toContain( 'a&quot;&lt;b' );
	} );
} );

describe( 'BlockSupports::compile (composition)', function (): void {
	it( 'compose multiple supports without duplicating has-background', function (): void {
		// Both backgroundColor (slug) and a gradient (slug) emit
		// has-background; the dedupe pass should keep only one.
		$result = BlockSupports::compile( [
			'backgroundColor' => 'primary',
			'gradient'        => 'vivid-cyan',
		] );

		expect( array_count_values( $result['classes'] )['has-background'] )->toBe( 1 );
	} );

	it( 'returns deterministic style output with declarations separated by `;`', function (): void {
		$result = BlockSupports::compile( [
			'style' => [
				'color'   => [ 'background' => '#000', 'text' => '#fff' ],
				'spacing' => [ 'padding' => '1rem' ],
			],
		] );

		// Each declaration is semicolon-terminated. No leading or
		// trailing whitespace.
		expect( $result['style'] )->toEndWith( ';' );
		expect( $result['style'] )->not->toStartWith( ' ' );
		expect( substr_count( $result['style'], 'background-color:' ) )->toBe( 1 );
	} );

	it( 'returns empty result for a block with no supported attributes', function (): void {
		$result = BlockSupports::compile( [] );

		expect( $result )->toBe( [
			'classes'             => [],
			'style'               => '',
			'id'                  => null,
			'responsiveCss'       => '',
			'responsiveClass'     => '',
			'responsiveRules'     => '',
			'statesClass'         => '',
			'statesRules'         => '',
			'gradientBorderClass' => '',
			'gradientBorderRules' => '',
		] );
	} );

	it( 'tolerates a `style` attribute that arrives as a string (legacy `core/separator` shape)', function (): void {
		// Older block schemas (notably `core/separator` pre-Gutenberg
		// 6.x) stored `style` as a variant id string rather than an
		// object. The compile pass must not error on `style: "default"`.
		$result = BlockSupports::compile( [
			'style'           => 'default',
			'backgroundColor' => 'accent',
		] );

		expect( $result['classes'] )->toContain( 'has-accent-background-color' );
		expect( $result['style'] )->toBe( '' );
	} );
} );

describe( 'compile() — state design tools (#488)', function (): void {
	it( 'returns empty state slots when no states bag is set', function (): void {
		$result = BlockSupports::compile( [ 'backgroundColor' => 'accent' ] );

		expect( $result['statesClass'] )->toBe( '' );
		expect( $result['statesRules'] )->toBe( '' );
	} );

	it( 'returns empty state slots when scopeId is missing even if overrides exist', function (): void {
		$result = BlockSupports::compile( [
			'backgroundColor' => 'accent',
			'states'          => [
				'backgroundColor' => [ 'idle' => 'accent', 'hover' => 'accent-700' ],
			],
		] );

		expect( $result['statesClass'] )->toBe( '' );
		expect( $result['statesRules'] )->toBe( '' );
	} );

	it( 'emits a scoped state class and CSS rules when scopeId + overrides are present', function (): void {
		$result = BlockSupports::compile( [
			'backgroundColor' => 'accent',
			'states'          => [
				'_scopeId'        => 'abc12345',
				'backgroundColor' => [ 'idle' => 'accent', 'hover' => 'accent-700' ],
			],
		] );

		expect( $result['statesClass'] )->toBe( 'ap-state-abc12345' );
		expect( $result['classes'] )->toContain( 'ap-state-abc12345' );
		expect( $result['statesRules'] )->toContain( '.ap-state-abc12345' );
		expect( $result['statesRules'] )->toContain( 'background-color: var(--wp--preset--color--accent) !important;' );
		expect( $result['statesRules'] )->toContain( '@media (hover: hover)' );
		expect( $result['statesRules'] )->toContain( 'background-color: var(--wp--preset--color--accent-700) !important;' );
		expect( $result['statesRules'] )->toContain( ':is(a, button)' );
	} );

	it( 'rejects a `_scopeId` that contains CSS-breaking characters', function (): void {
		$result = BlockSupports::compile( [
			'states' => [
				'_scopeId'        => 'abc"</style><script>x</script>',
				'backgroundColor' => [ 'idle' => 'accent', 'hover' => 'accent-700' ],
			],
		] );

		// The crafted scope id must be rejected outright — no class,
		// no rules — so the hostile string can never reach the
		// rendered `<style>` block.
		expect( $result['statesClass'] )->toBe( '' );
		expect( $result['statesRules'] )->toBe( '' );
	} );

	it( 'rejects a `_scopeId` containing whitespace', function (): void {
		$result = BlockSupports::compile( [
			'states' => [
				'_scopeId'        => 'has space',
				'backgroundColor' => [ 'idle' => 'accent', 'hover' => 'accent-700' ],
			],
		] );

		expect( $result['statesClass'] )->toBe( '' );
	} );

	it( 'rejects a `_scopeId` longer than the safe identifier cap', function (): void {
		$result = BlockSupports::compile( [
			'states' => [
				'_scopeId'        => str_repeat( 'a', 65 ),
				'backgroundColor' => [ 'idle' => 'accent' ],
			],
		] );

		expect( $result['statesClass'] )->toBe( '' );
	} );

	it( 'preserves raw hex / var() values without preset wrapping', function (): void {
		$result = BlockSupports::compile( [
			'style'  => [ 'color' => [ 'background' => '#abc123' ] ],
			'states' => [
				'_scopeId'              => 'def',
				'style.color.background' => [ 'idle' => '#abc123', 'hover' => '#fff' ],
			],
		] );

		expect( $result['statesRules'] )->toContain( 'background-color: #abc123 !important;' );
		expect( $result['statesRules'] )->toContain( 'background-color: #fff !important;' );
	} );
} );
