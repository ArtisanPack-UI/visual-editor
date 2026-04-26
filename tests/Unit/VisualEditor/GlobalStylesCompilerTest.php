<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\GlobalStylesCompiler;

beforeEach( function (): void {
	$this->compiler = new GlobalStylesCompiler();
} );

it( 'returns an empty string when settings and styles are empty', function (): void {
	expect( $this->compiler->compile( [ 'version' => 3, 'settings' => [], 'styles' => [] ] ) )
		->toBe( '' );
} );

it( 'returns an empty string when payload is missing settings/styles entirely', function (): void {
	expect( $this->compiler->compile( [] ) )->toBe( '' );
} );

it( 'emits color palette presets as :root --wp--preset--color--{slug} variables', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'primary', 'name' => 'Primary', 'color' => '#3b82f6' ],
					[ 'slug' => 'accent', 'name' => 'Accent', 'color' => '#10b981' ],
				],
			],
		],
	] );

	expect( $css )->toContain( ':root' )
		->toContain( '--wp--preset--color--primary: #3b82f6' )
		->toContain( '--wp--preset--color--accent: #10b981' );
} );

it( 'emits font-family and font-size presets', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'typography' => [
				'fontFamilies' => [
					[ 'slug' => 'sans', 'name' => 'Sans', 'fontFamily' => "'Inter', sans-serif" ],
				],
				'fontSizes' => [
					[ 'slug' => 'medium', 'name' => 'Medium', 'size' => '1rem' ],
				],
			],
		],
	] );

	expect( $css )->toContain( "--wp--preset--font-family--sans: 'Inter', sans-serif" )
		->toContain( '--wp--preset--font-size--medium: 1rem' );
} );

it( 'emits layout content/wide sizes as --wp--style--global variables', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'layout' => [
				'contentSize' => '720px',
				'wideSize'    => '1120px',
			],
		],
	] );

	expect( $css )->toContain( '--wp--style--global--content-size: 720px' )
		->toContain( '--wp--style--global--wide-size: 1120px' );
} );

it( 'emits body styles for color and typography', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'color'      => [
				'background' => 'var(--wp--preset--color--base)',
				'text'       => 'var(--wp--preset--color--contrast)',
			],
			'typography' => [
				'fontFamily' => 'var(--wp--preset--font-family--sans)',
				'fontSize'   => 'var(--wp--preset--font-size--medium)',
				'lineHeight' => '1.6',
			],
		],
	] );

	expect( $css )->toContain( 'body {' )
		->toContain( 'background-color: var(--wp--preset--color--base)' )
		->toContain( 'color: var(--wp--preset--color--contrast)' )
		->toContain( 'font-family: var(--wp--preset--font-family--sans)' )
		->toContain( 'font-size: var(--wp--preset--font-size--medium)' )
		->toContain( 'line-height: 1.6' );
} );

it( 'emits link element rules wrapped in :where()', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'elements' => [
				'link' => [ 'color' => [ 'text' => 'var(--wp--preset--color--primary)' ] ],
			],
		],
	] );

	expect( $css )->toContain( ':where(a) {' )
		->toContain( 'color: var(--wp--preset--color--primary)' );
} );

it( 'emits heading element rules covering h1 through h6', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'elements' => [
				'heading' => [
					'typography' => [
						'fontFamily' => 'var(--wp--preset--font-family--serif)',
						'fontWeight' => '600',
					],
				],
			],
		],
	] );

	expect( $css )->toContain( ':where(h1, h2, h3, h4, h5, h6)' )
		->toContain( "font-family: var(--wp--preset--font-family--serif)" )
		->toContain( 'font-weight: 600' );
} );

it( 'emits per-block overrides as .wp-block-{name} rules', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'blocks' => [
				'core/button' => [
					'color'  => [
						'background' => 'var(--wp--preset--color--primary)',
						'text'       => 'var(--wp--preset--color--base)',
					],
					'border' => [ 'radius' => '0.5rem' ],
				],
			],
		],
	] );

	expect( $css )->toContain( '.wp-block-button {' )
		->toContain( 'background-color: var(--wp--preset--color--primary)' )
		->toContain( 'color: var(--wp--preset--color--base)' )
		->toContain( 'border-radius: 0.5rem' );
} );

it( 'namespaces non-core blocks in the selector', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'blocks' => [
				'artisanpack/callout' => [
					'color' => [ 'background' => '#f0f0f0' ],
				],
			],
		],
	] );

	expect( $css )->toContain( '.wp-block-artisanpack-callout' );
} );

it( 'skips block names without a namespace separator', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'blocks' => [
				'malformed-name' => [
					'color' => [ 'background' => '#f0f0f0' ],
				],
			],
		],
	] );

	expect( $css )->not->toContain( '.wp-block-malformed-name' )
		->not->toContain( 'malformed' );
} );

it( 'expands per-side padding/margin maps into individual declarations', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'spacing' => [
				'padding' => [
					'top'    => '1rem',
					'right'  => '2rem',
					'bottom' => '1rem',
					'left'   => '2rem',
				],
				'margin'  => '0 auto',
			],
		],
	] );

	expect( $css )->toContain( 'padding-top: 1rem' )
		->toContain( 'padding-right: 2rem' )
		->toContain( 'padding-bottom: 1rem' )
		->toContain( 'padding-left: 2rem' )
		->toContain( 'margin: 0 auto' );
} );

it( 'maps spacing.blockGap to gap', function (): void {
	$css = $this->compiler->compile( [
		'styles' => [
			'blocks' => [
				'core/columns' => [
					'spacing' => [ 'blockGap' => '2rem' ],
				],
			],
		],
	] );

	expect( $css )->toContain( '.wp-block-columns' )
		->toContain( 'gap: 2rem' );
} );

it( 'strips characters that could break out of the style block', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'evil', 'name' => 'Evil', 'color' => '#fff;</style><script>alert(1)' ],
				],
			],
		],
	] );

	expect( $css )->not->toContain( '</style>' )
		->not->toContain( '<script>' )
		->not->toContain( ';' . PHP_EOL )
		->toContain( '--wp--preset--color--evil' );
} );

it( 'sanitizes preset slugs to a safe charset', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'evil!@#$%', 'name' => 'Evil', 'color' => '#000000' ],
				],
			],
		],
	] );

	expect( $css )->toContain( '--wp--preset--color--evil:' )
		->not->toContain( '!@#' );
} );

it( 'skips palette entries missing a slug or color', function (): void {
	$css = $this->compiler->compile( [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'good', 'color' => '#000000' ],
					[ 'slug' => 'no-color' ],
					[ 'color' => '#ffffff' ],
				],
			],
		],
	] );

	expect( $css )->toContain( '--wp--preset--color--good' )
		->not->toContain( '--wp--preset--color--no-color' );
} );

it( 'compiles the bundled default-base payload to a non-empty CSS string', function (): void {
	$payload = require __DIR__ . '/../../../resources/theme-json/default-base.php';

	$css = $this->compiler->compile( $payload );

	expect( $css )->toContain( ':root' )
		->toContain( '--wp--preset--color--primary' )
		->toContain( '--wp--preset--font-family--sans' )
		->toContain( 'body {' )
		->toContain( '.wp-block-button' );
} );
