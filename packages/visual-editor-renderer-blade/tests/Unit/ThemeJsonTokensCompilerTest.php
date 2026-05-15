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
