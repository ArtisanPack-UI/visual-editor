<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ThemeJsonTokensCompiler;

beforeEach( function () {
	$this->compiler = new ThemeJsonTokensCompiler();
} );

it( 'compiles color, gradient, fontSize and spacing presets', function () {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'primary', 'color' => '#0f172a' ],
				],
				'gradient' => [
					[ 'slug' => 'sunset', 'gradient' => 'linear-gradient(45deg, #f00, #fa0)' ],
				],
			],
			'typography' => [
				'fontSizes' => [
					[ 'slug' => 'small', 'size' => '0.875rem' ],
				],
			],
			'spacing' => [
				'spacingSizes' => [
					[ 'slug' => 'sm', 'size' => '0.5rem' ],
				],
			],
		],
	] );

	expect( $css )
		->toContain( ':root {' )
		->toContain( '--wp--preset--color--primary: #0f172a;' )
		->toContain( '--wp--preset--gradient--sunset: linear-gradient(45deg, #f00, #fa0);' )
		->toContain( '--wp--preset--font-size--small: 0.875rem;' )
		->toContain( '--wp--preset--spacing--sm: 0.5rem;' );
} );

it( 'returns an empty string when no recognised tokens are present', function () {
	expect( $this->compiler->compile( [] ) )->toBe( '' );
	expect( $this->compiler->compile( [ 'settings' => [] ] ) )->toBe( '' );
	expect( $this->compiler->compile( [
		'settings' => [ 'color' => [ 'palette' => [] ] ],
	] ) )->toBe( '' );
} );

it( 'normalises slugs to lowercase hyphen-safe identifiers', function () {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'Primary Brand!', 'color' => '#000' ],
				],
			],
		],
	] );

	expect( $css )->toContain( '--wp--preset--color--primary-brand-: #000;' );
} );

it( 'skips entries missing slug or value', function () {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'good', 'color' => '#abc' ],
					[ 'slug' => '', 'color' => '#def' ],
					[ 'color' => '#fff' ],
					[ 'slug' => 'no-value' ],
				],
			],
		],
	] );

	expect( $css )
		->toContain( '--wp--preset--color--good: #abc;' )
		->not->toContain( '#def' )
		->not->toContain( '#fff' )
		->not->toContain( 'no-value' );
} );

it( 'ignores non-array entries gracefully', function () {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					'this should not be here',
					[ 'slug' => 'ok', 'color' => '#123' ],
					null,
				],
			],
		],
	] );

	expect( $css )->toContain( '--wp--preset--color--ok: #123;' );
} );

describe( 'Keystone #50 — layout-size custom properties + alignment rules', function (): void {
	it( 'emits content-size + wide-size custom properties from settings.layout', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'layout' => [
					'contentSize' => '720px',
					'wideSize'    => '1200px',
				],
			],
		] );

		expect( $css )->toContain( '--wp--style--global--content-size: 720px;' );
		expect( $css )->toContain( '--wp--style--global--wide-size: 1200px;' );
	} );

	it( 'emits the alignwide layout rule referencing the wide-size custom property', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'wideSize' => '1200px' ] ],
		] );

		expect( $css )->toContain( '.wp-block-group.is-layout-constrained.alignwide' );
		expect( $css )->toContain( 'max-width: var(--wp--style--global--wide-size);' );
		expect( $css )->toContain( 'margin-left: auto;' );
		expect( $css )->toContain( 'margin-right: auto;' );
	} );

	it( 'emits the alignfull layout rule whenever any layout is configured', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'layout' => [ 'contentSize' => '720px' ] ],
		] );

		expect( $css )->toContain( '.wp-block-group.is-layout-constrained.alignfull' );
		expect( $css )->toContain( 'max-width: none;' );
	} );

	it( 'omits layout rules entirely when no layout is configured', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [ 'color' => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ] ],
		] );

		expect( $css )->not->toContain( 'is-layout-constrained.alignwide' );
		expect( $css )->not->toContain( 'is-layout-constrained.alignfull' );
	} );

	it( 'emits .wp-block-post-content child rules so root-level default alignment uses content-size', function (): void {
		// Themes opt into WP-FSE-style page layout by adding
		// `class="wp-block-post-content is-layout-constrained"` to
		// their `<main>` wrapper. The renderer's CSS rules then
		// give the children the canonical "default = content-size,
		// wide = wide-size, full = no max" behavior. Without these
		// rules a section with `align="none"` stretches full-width
		// because nothing sizes its parent.
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'layout' => [ 'contentSize' => '720px', 'wideSize' => '1200px' ],
			],
		] );

		expect( $css )
			->toContain( '.wp-block-post-content.is-layout-constrained > :where(:not(.alignwide):not(.alignfull):not(.alignleft):not(.alignright))' )
			->toContain( '.wp-block-post-content.is-layout-constrained > .alignwide' )
			->toContain( '.wp-block-post-content.is-layout-constrained > .alignfull' );
	} );

	it( 'composes root presets with layout rules separated by a blank line', function (): void {
		$css = ( new ThemeJsonTokensCompiler() )->compile( [
			'settings' => [
				'color'  => [ 'palette' => [ [ 'slug' => 'primary', 'color' => '#000' ] ] ],
				'layout' => [ 'contentSize' => '720px', 'wideSize' => '1200px' ],
			],
		] );

		// `:root { … }` block first, layout rules second.
		expect( $css )->toMatch( '/:root \{[^}]*\}\n\n\.wp-block-group/' );
	} );
} );
