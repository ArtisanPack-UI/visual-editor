<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'emits the block-library and theme stylesheet links by default', function () {
	$rendered = Blade::render( '<x-ve-blocks-styles />' );

	expect( $rendered )
		->toContain( '<link rel="stylesheet" href="/vendor/visual-editor-renderer-blade/style.css"' )
		->toContain( 'data-ve-block-library' )
		->toContain( '<link rel="stylesheet" href="/vendor/visual-editor-renderer-blade/theme.css"' )
		->toContain( 'data-ve-block-library-theme' );
} );

it( 'omits the block-library links when bundle is false', function () {
	$rendered = Blade::render( '<x-ve-blocks-styles :bundle="false" />' );

	expect( $rendered )
		->not->toContain( 'data-ve-block-library' );
} );

it( 'emits the grid + marquee frontend stylesheet links independently of $emitInteractive', function () {
	$rendered = Blade::render( '<x-ve-blocks-styles :interactive="false" />' );

	expect( $rendered )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/grid.css' )
		->toContain( 'data-ve-grid' )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/marquee.css' )
		->toContain( 'data-ve-marquee' )
		->not->toContain( 'data-ve-accordion' )
		->not->toContain( 'data-ve-tabs' );
} );

it( 'rebases the link href when assetBase is supplied', function () {
	$rendered = Blade::render(
		'<x-ve-blocks-styles asset-base="https://cdn.example.com/ve" />'
	);

	expect( $rendered )
		->toContain( 'https://cdn.example.com/ve/style.css' )
		->toContain( 'https://cdn.example.com/ve/theme.css' )
		->not->toContain( '/vendor/visual-editor-renderer-blade/style.css' );
} );

it( 'compiles theme.json palette + fontSizes into --wp--preset--* declarations', function () {
	$themeJson = [
		'settings' => [
			'color' => [
				'palette' => [
					[ 'slug' => 'primary', 'name' => 'Primary', 'color' => '#0f172a' ],
					[ 'slug' => 'accent',  'name' => 'Accent',  'color' => '#2563eb' ],
				],
			],
			'typography' => [
				'fontSizes' => [
					[ 'slug' => 'small', 'size' => '0.875rem' ],
					[ 'slug' => 'large', 'size' => '1.25rem' ],
				],
			],
		],
	];

	$rendered = Blade::render(
		'<x-ve-blocks-styles :theme-json="$themeJson" />',
		[ 'themeJson' => $themeJson ]
	);

	expect( $rendered )
		->toContain( 'data-ve-theme-tokens' )
		->toContain( '--wp--preset--color--primary: #0f172a;' )
		->toContain( '--wp--preset--color--accent: #2563eb;' )
		->toContain( '--wp--preset--font-size--small: 0.875rem;' )
		->toContain( '--wp--preset--font-size--large: 1.25rem;' );
} );

it( 'omits the tokens style block when theme.json carries no recognised tokens', function () {
	// `settings.layout` is now a recognized category (Keystone #50 — it
	// produces layout-size custom properties + alignwide/alignfull
	// rules), so use an unrecognized section to exercise the
	// "no tokens" code path.
	$rendered = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
		'themeJson' => [ 'settings' => [ 'border' => [ 'color' => true ] ] ],
	] );

	expect( $rendered )->not->toContain( 'data-ve-theme-tokens' );
} );

it( 'silently skips theme.json entries missing slug or value', function () {
	$rendered = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
		'themeJson' => [
			'settings' => [
				'color' => [
					'palette' => [
						[ 'slug' => 'good',           'color' => '#abcdef' ],
						[ 'slug' => '',               'color' => '#000000' ],
						[ 'name' => 'Anonymous',      'color' => '#111111' ],
						[ 'slug' => 'missing-value' ],
					],
				],
			],
		],
	] );

	expect( $rendered )
		->toContain( '--wp--preset--color--good: #abcdef;' )
		->not->toContain( '#000000' )
		->not->toContain( '#111111' )
		->not->toContain( 'missing-value' );
} );

it( 'emits the layout-flow block-gap baseline rule for sibling spacing', function () {
	// Issue #539 — paragraphs (and any flow-layout children) had no
	// vertical spacing because the canonical
	// `:where(.is-layout-flow) > * + * { margin-block-start:
	// var(--wp--style--block-gap, …) }` rule was not emitted anywhere.
	// Assert the rule is present in the renderer-static baseline.
	$rendered = Blade::render( '<x-ve-blocks-styles />' );

	expect( $rendered )
		->toContain( 'data-ve-layout-baseline' )
		->toContain( ':where(.is-layout-flow) > * + *' )
		->toContain( ':where(.is-layout-constrained) > * + *' )
		->toContain( 'margin-block-start: var(--wp--style--block-gap, 24px)' );
} );

it( 'publishes block-library assets under the visual-editor-renderer-blade-assets tag', function () {
	$artisan = $this->artisan( 'vendor:publish', [
		'--tag'   => 'visual-editor-renderer-blade-assets',
		'--force' => true,
	] );

	$artisan->assertExitCode( 0 );
} );
