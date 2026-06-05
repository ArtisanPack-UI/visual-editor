<?php

/**
 * Unit tests for {@see ElementsSupport::compile}. Pins the Elements
 * API rendering path used by the `core/navigation` block's Link
 * color picker (Keystone #56) — preset slug, custom hex, pseudo-state
 * nesting, and absence-of-style no-ops.
 *
 * @since 1.1.0
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

	it( 'ignores a link node that has no usable color.text declaration', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'background' => '#fff' ] ],
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
		expect( $result['style'] )->toContain( '.' . $result['class'] . ' a{color: var(--wp--preset--color--accent) !important;}' );
	} );

	it( 'emits a custom hex value verbatim with !important', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [ 'color' => [ 'text' => '#0f172a' ] ],
			] ],
		] );

		expect( $result['style'] )->toContain( ' a{color: #0f172a !important;}' );
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

		expect( $result['style'] )->toContain( ' a{color: #000 !important;}' );
		expect( $result['style'] )->toContain( ' a:hover{color: var(--wp--preset--color--accent) !important;}' );
	} );

	it( 'emits a :focus rule independently of base / hover', function (): void {
		$result = ElementsSupport::compile( [
			'style' => [ 'elements' => [
				'link' => [
					':focus' => [ 'color' => [ 'text' => '#abcdef' ] ],
				],
			] ],
		] );

		expect( $result['style'] )->toContain( ' a:focus{color: #abcdef !important;}' );
		expect( $result['style'] )->not->toContain( ' a{' );
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

		$hoverPos = strpos( $result['style'], ' a:hover{' );
		$focusPos = strpos( $result['style'], ' a:focus{' );
		$basePos  = strpos( $result['style'], ' a{' );

		expect( $basePos )->not->toBeFalse();
		expect( $hoverPos )->not->toBeFalse();
		expect( $focusPos )->not->toBeFalse();
		expect( $basePos )->toBeLessThan( $hoverPos );
		expect( $hoverPos )->toBeLessThan( $focusPos );
	} );
} );
