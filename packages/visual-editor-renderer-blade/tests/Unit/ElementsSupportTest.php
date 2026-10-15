<?php

/**
 * Unit tests for {@see ElementsSupport::compile}. Pins the Elements
 * API rendering path used by the `core/navigation` block's Link
 * color picker (Keystone #56) — preset slug, custom hex, pseudo-state
 * nesting, and absence-of-style no-ops.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\ElementsSupport;

describe( 'ElementsSupport::compile (no-op cases)', function (): void {
	it( 'returns empty class + style when no style.elements tree is set', function (): void {
		expect( ElementsSupport::compile( [] ) )
			->toBe( [ 'class' => '', 'style' => '' ] );
	} );

	it( 'returns empty when style.elements is an empty array', function (): void {
		expect( ElementsSupport::compile( [ 'style' => [ 'elements' => [] ] ] ) )
			->toBe( [ 'class' => '', 'style' => '' ] );
	} );

	it( 'ignores unrecognized element keys (e.g. an unknown "footer" element)', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'footer' => [ 'color' => [ 'text' => '#ff0000' ] ],
			] ],
		] );

		expect( $result )->toBe( [ 'class' => '', 'style' => '' ] );
	} );

	it( 'ignores a link node that has no usable color declaration', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'typography' => [ 'fontWeight' => '700' ], 'color' => [ 'text' => '' ] ],
			] ],
		] );

		expect( $result )->toBe( [ 'class' => '', 'style' => '' ] );
	} );
} );

describe( 'ElementsSupport::compile (link color)', function (): void {
	it( 'expands a preset reference to a CSS var() scoped under the per-block class', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
			] ],
		] );

		expect( $result['class'] )->toStartWith( 'wp-elements-' );
		expect( $result['style'] )->toContain( '.' . $result['class'] . ' a:where(:not(.wp-element-button)){color: var(--wp--preset--color--accent) !important;}' );
	} );

	it( 'emits a custom hex value verbatim with !important', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => '#0f172a' ] ],
			] ],
		] );

		expect( $result['style'] )->toContain( ' a:where(:not(.wp-element-button)){color: #0f172a !important;}' );
	} );

	it( 'kebab-cases camelCase preset slugs (e.g. brandPrimary → brand-primary)', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => 'var:preset|color|brandPrimary' ] ],
			] ],
		] );

		expect( $result['style'] )->toContain( 'var(--wp--preset--color--brand-primary)' );
	} );

	it( 'produces a stable hash for identical attribute trees (cache-friendly)', function (): void {
		$attributes = [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
			] ],
		];

		$first  = ElementsSupport::compile( $attributes );
		$second = ElementsSupport::compile( $attributes );

		expect( $first['class'] )->toBe( $second['class'] );
	} );

	it( 'produces a different hash for different attribute trees (block-instance scoping)', function (): void {
		$accent = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
			] ],
		] );
		$primary = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => 'var:preset|color|primary' ] ],
			] ],
		] );

		expect( $accent['class'] )->not->toBe( $primary['class'] );
	} );
} );

describe( 'ElementsSupport::compile (color sanitization)', function (): void {
	it( 'drops a color value with CSS-injection characters rather than emitting it raw', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => '#000;}body{display:none' ] ],
			] ],
		] );

		expect( $result )->toBe( [ 'class' => '', 'style' => '' ] );
	} );

	it( 'accepts CSS color keywords (e.g. currentColor, transparent)', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => 'currentColor' ] ],
			] ],
		] );

		expect( $result['style'] )->toContain( 'color: currentColor !important' );
	} );

	it( 'accepts rgba() values', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => 'rgba(15, 23, 42, 0.8)' ] ],
			] ],
		] );

		expect( $result['style'] )->toContain( 'color: rgba(15, 23, 42, 0.8) !important' );
	} );
} );

describe( 'ElementsSupport::compile (link pseudo-states)', function (): void {
	it( 'emits a :hover rule when style.elements.link[":hover"].color.text is set', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [
					'color'  => [ 'text' => '#000' ],
					':hover' => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
				],
			] ],
		] );

		expect( $result['style'] )->toContain( ' a:where(:not(.wp-element-button)){color: #000 !important;}' );
		expect( $result['style'] )->toContain( ' a:where(:not(.wp-element-button)):hover{color: var(--wp--preset--color--accent) !important;}' );
	} );

	it( 'emits a :focus rule independently of base / hover', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [
					':focus' => [ 'color' => [ 'text' => '#abcdef' ] ],
				],
			] ],
		] );

		expect( $result['style'] )->toContain( ' a:where(:not(.wp-element-button)):focus{color: #abcdef !important;}' );
		expect( $result['style'] )->not->toContain( ' a:where(:not(.wp-element-button)){' );
	} );

	it( 'preserves base → :hover → :focus order so the cascade matches author intent', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [
					'color'  => [ 'text' => '#000' ],
					':focus' => [ 'color' => [ 'text' => '#222' ] ],
					':hover' => [ 'color' => [ 'text' => '#111' ] ],
				],
			] ],
		] );

		$hoverPos = strpos( $result['style'], ' a:where(:not(.wp-element-button)):hover{' );
		$focusPos = strpos( $result['style'], ' a:where(:not(.wp-element-button)):focus{' );
		$basePos  = strpos( $result['style'], ' a:where(:not(.wp-element-button)){' );

		expect( $basePos )->not->toBeFalse();
		expect( $hoverPos )->not->toBeFalse();
		expect( $focusPos )->not->toBeFalse();
		expect( $basePos )->toBeLessThan( $hoverPos );
		expect( $hoverPos )->toBeLessThan( $focusPos );
	} );
} );

describe( 'ElementsSupport::compile (#852 selectors and states)', function (): void {
	it( 'never targets button links with the link selector', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => '#ff0000' ] ],
			] ],
		] );

		expect( $result['style'] )
			->toBe( '.' . $result['class'] . ' a:where(:not(.wp-element-button)){color: #ff0000 !important;}' );
	} );

	it( 'emits a :focus-visible rule between :focus and :active', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [
					':active'        => [ 'color' => [ 'text' => '#333' ] ],
					':focus-visible' => [ 'color' => [ 'text' => '#222' ] ],
					':focus'         => [ 'color' => [ 'text' => '#111' ] ],
				],
			] ],
		] );

		$focus        = strpos( $result['style'], ':focus{' );
		$focusVisible = strpos( $result['style'], ':focus-visible{color: #222 !important;}' );
		$active       = strpos( $result['style'], ':active{' );

		expect( $focusVisible )->not->toBeFalse()
			->and( $focus )->toBeLessThan( $focusVisible )
			->and( $focusVisible )->toBeLessThan( $active );
	} );

	it( 'emits background colors alongside text colors', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'button' => [ 'color' => [ 'text' => 'var:preset|color|base-content', 'background' => '#00ffff' ] ],
			] ],
		] );

		$scope = '.' . $result['class'];

		expect( $result['style'] )->toBe(
			"{$scope} .wp-element-button,{$scope} .wp-block-button__link"
			. '{color: var(--wp--preset--color--base-content) !important;background-color: #00ffff !important;}',
		);
	} );

	it( 'scopes every heading selector', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'heading' => [ 'color' => [ 'text' => '#010203' ] ],
			] ],
		] );

		$scope = '.' . $result['class'];

		expect( $result['style'] )->toBe( "{$scope} h1,{$scope} h2,{$scope} h3,{$scope} h4,{$scope} h5,{$scope} h6{color: #010203 !important;}" );
	} );

	it( 'ignores pseudo states on elements other than link and button, as WordPress does', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'heading' => [ ':hover' => [ 'color' => [ 'text' => '#010203' ] ] ],
				'h2'      => [ ':focus' => [ 'color' => [ 'text' => '#010203' ] ] ],
			] ],
		] );

		expect( $result )->toBe( [ 'class' => '', 'style' => '' ] );
	} );

	it( 'appends the button pseudo to each button selector', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'button' => [ ':hover' => [ 'color' => [ 'text' => '#010203' ] ] ],
			] ],
		] );

		$scope = '.' . $result['class'];

		expect( $result['style'] )->toBe( "{$scope} .wp-element-button:hover,{$scope} .wp-block-button__link:hover{color: #010203 !important;}" );
	} );

	it( 'emits WordPress\'s link states in its order', function (): void {
		$states = [ ':active', ':focus-visible', ':focus', ':hover', ':visited', ':any-link', ':link' ];
		$node   = [];

		foreach ( $states as $index => $state ) {
			$node[ $state ] = [ 'color' => [ 'text' => '#00000' . $index ] ];
		}

		$result = ElementsSupport::compile( [ 'style' => [ 'elements' => [ 'link' => $node ] ] ] );

		$positions = array_map(
			static fn ( string $state ): int|false => strpos( $result['style'], ' a:where(:not(.wp-element-button))' . $state . '{' ),
			ElementsSupport::PSEUDO_STATES,
		);

		expect( ElementsSupport::PSEUDO_STATES )->toBe( [ ':link', ':any-link', ':visited', ':hover', ':focus', ':focus-visible', ':active' ] )
			->and( $positions )->not->toContain( false );

		$sorted = $positions;
		sort( $sorted );

		expect( $positions )->toBe( $sorted );
	} );

	it( 'drops an unsafe background value', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'background' => 'red;}body{display:none' ] ],
			] ],
		] );

		expect( $result )->toBe( [ 'class' => '', 'style' => '' ] );
	} );
} );
