<?php

/**
 * Integration tests for the gradient-border slot in
 * {@see BlockSupports::compile} (#490).
 *
 * Verifies the compile() pipeline wires the resolver + emitter
 * correctly: a wrapper class is added, the scope class is content-
 * stable, and the rules end up on the accumulator-bound return key.
 *
 * @since 1.1.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

describe( 'BlockSupports::compile (gradient border)', function (): void {
	it( 'does nothing when no gradient is configured', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'border' => [ 'width' => '2px', 'color' => '#000' ] ],
		] );

		expect( $result['gradientBorderClass'] )->toBe( '' );
		expect( $result['gradientBorderRules'] )->toBe( '' );
		expect( $result['classes'] )->not->toContain( 'ApplyGradientBorder' );
	} );

	it( 'attaches a `ve-gb-*` scope class when a gradient is set', function (): void {
		$result = BlockSupports::compile( [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		] );

		expect( $result['gradientBorderClass'] )->toStartWith( 've-gb-' );
		expect( $result['classes'] )->toContain( $result['gradientBorderClass'] );
		expect( $result['gradientBorderRules'] )
			->toContain( 'var(--wp--preset--gradient--primary-glow)' );
	} );

	it( 'produces a content-stable scope class for the same payload', function (): void {
		$payload = [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow', 'width' => '2px' ] ],
		];

		$a = BlockSupports::compile( $payload );
		$b = BlockSupports::compile( $payload );

		expect( $a['gradientBorderClass'] )->toBe( $b['gradientBorderClass'] );
	} );

	it( 'produces different scope classes for different payloads', function (): void {
		$a = BlockSupports::compile( [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		] );

		$b = BlockSupports::compile( [
			'style' => [ 'border' => [ 'gradient' => 'accent-glow' ] ],
		] );

		expect( $a['gradientBorderClass'] )->not->toBe( $b['gradientBorderClass'] );
	} );

	it( 'composes with per-state overrides from attributes.states', function (): void {
		$result = BlockSupports::compile( [
			'style'  => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
			'states' => [
				'style.border.gradient' => [ 'hover' => 'accent-glow' ],
			],
		] );

		expect( $result['gradientBorderRules'] )
			->toContain( 'var(--wp--preset--gradient--primary-glow)' );
		expect( $result['gradientBorderRules'] )
			->toContain( 'var(--wp--preset--gradient--accent-glow)' );
		// Hover wrapped in @media (hover: hover) so touch devices
		// don't sticky-state.
		expect( $result['gradientBorderRules'] )->toContain( '@media (hover: hover)' );
	} );

	it( 'composes with per-breakpoint overrides from attributes.responsive', function (): void {
		$result = BlockSupports::compile( [
			'style'      => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
			'responsive' => [
				'style.border.gradient' => [ 'md' => 'vertical' ],
			],
		] );

		expect( $result['gradientBorderRules'] )
			->toContain( '@media (min-width:768px)' );
		expect( $result['gradientBorderRules'] )
			->toContain( 'var(--wp--preset--gradient--vertical)' );
	} );

	it( 'survives even when only a per-state override is present (no idle)', function (): void {
		// Gradient border configured only on hover (no idle gradient) —
		// the hover still gets emitted, idle ::before falls back to
		// `transparent` so the cascade has a base to override.
		$result = BlockSupports::compile( [
			'states' => [
				'style.border.gradient' => [ 'hover' => 'accent-glow' ],
			],
		] );

		expect( $result['gradientBorderClass'] )->toStartWith( 've-gb-' );
		expect( $result['gradientBorderRules'] )->toContain( 'background:transparent' );
		expect( $result['gradientBorderRules'] )
			->toContain( 'var(--wp--preset--gradient--accent-glow)' );
	} );
} );

describe( 'BlockSupports::applyGradientBorder', function (): void {
	it( 'returns the HTML unchanged when no gradient is configured', function (): void {
		$html       = '<div class="wp-block-foo">child</div>';
		$attributes = [
			'style' => [ 'border' => [ 'color' => '#000', 'width' => '2px' ] ],
		];

		expect( BlockSupports::applyGradientBorder( $html, $attributes ) )->toBe( $html );
	} );

	it( 'injects the scope class onto an existing class attribute', function (): void {
		$html       = '<div class="wp-block-icon">inner</div>';
		$attributes = [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		];

		$out = BlockSupports::applyGradientBorder( $html, $attributes );

		expect( $out )->toContain( 'class="wp-block-icon ve-gb-' );
		expect( $out )->toContain( '>inner</div>' );
	} );

	it( 'adds a class attribute when the first tag has none', function (): void {
		$html       = '<div data-x="y">inner</div>';
		$attributes = [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		];

		$out = BlockSupports::applyGradientBorder( $html, $attributes );

		expect( $out )->toMatch( '/<div [^>]*class="ve-gb-[a-z0-9]+"/' );
		expect( $out )->toContain( 'data-x="y"' );
	} );

	it( 'is idempotent — calling twice does not double the scope class', function (): void {
		$html       = '<div class="wp-block-icon">inner</div>';
		$attributes = [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		];

		$once  = BlockSupports::applyGradientBorder( $html, $attributes );
		$twice = BlockSupports::applyGradientBorder( $once, $attributes );

		expect( $twice )->toBe( $once );
	} );

	it( 'merges into a single-quoted class attribute instead of duplicating it', function (): void {
		$html       = "<div class='wp-block-icon'>inner</div>";
		$attributes = [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		];

		$out = BlockSupports::applyGradientBorder( $html, $attributes );

		// Token order: existing first, then injected; quotes normalised
		// to double on rewrite so downstream parsers don't trip on the
		// mixed-quote shape.
		expect( $out )->toMatch( '/<div class="wp-block-icon ve-gb-[a-z0-9]+">inner<\/div>/' );
		// Guard against the regression where a second `class` attribute
		// gets appended because the regex missed the single-quoted form.
		expect( substr_count( $out, 'class=' ) )->toBe( 1 );
	} );

	it( 'handles case-variant `CLASS=` attribute names', function (): void {
		$html       = '<div CLASS="wp-block-icon">inner</div>';
		$attributes = [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		];

		$out = BlockSupports::applyGradientBorder( $html, $attributes );

		expect( substr_count( strtolower( $out ), 'class=' ) )->toBe( 1 );
		expect( $out )->toMatch( '/wp-block-icon ve-gb-[a-z0-9]+/' );
	} );

	it( 'respects an editor-minted `_gradientScopeId` so editor and renderer agree', function (): void {
		$html       = '<div class="wp-block-icon">inner</div>';
		$attributes = [
			'style' => [
				'border' => [
					'gradient'         => 'primary-glow',
					'_gradientScopeId' => 'pn8qudcya',
				],
			],
		];

		$out = BlockSupports::applyGradientBorder( $html, $attributes );

		expect( $out )->toContain( 've-gb-pn8qudcya' );
	} );
} );
