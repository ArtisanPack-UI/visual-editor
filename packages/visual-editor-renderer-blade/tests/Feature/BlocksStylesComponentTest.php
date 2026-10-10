<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'emits the block-library and theme stylesheet links by default', function (): void {
	$rendered = Blade::render( '<x-ve-blocks-styles />' );

	expect( $rendered )
		->toContain( '<link rel="stylesheet" href="/vendor/visual-editor-renderer-blade/style.css"' )
		->toContain( 'data-ve-block-library' )
		->toContain( '<link rel="stylesheet" href="/vendor/visual-editor-renderer-blade/theme.css"' )
		->toContain( 'data-ve-block-library-theme' );
} );

it( 'omits the block-library links when bundle is false', function (): void {
	$rendered = Blade::render( '<x-ve-blocks-styles :bundle="false" />' );

	expect( $rendered )
		->not->toContain( 'data-ve-block-library' );
} );

it( 'emits the grid + marquee frontend stylesheet links independently of $emitInteractive', function (): void {
	$rendered = Blade::render( '<x-ve-blocks-styles :interactive="false" />' );

	expect( $rendered )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/grid.css' )
		->toContain( 'data-ve-grid' )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/marquee.css' )
		->toContain( 'data-ve-marquee' )
		->not->toContain( 'data-ve-accordion' )
		->not->toContain( 'data-ve-tabs' );
} );

it( 'emits the breadcrumbs frontend stylesheet link independently of $emitInteractive', function (): void {
	$rendered = Blade::render( '<x-ve-blocks-styles :interactive="false" />' );

	expect( $rendered )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/breadcrumbs.css' )
		->toContain( 'data-ve-breadcrumbs' );
} );

it( 'emits the query-pagination frontend stylesheet link independently of $emitInteractive', function (): void {
	$rendered = Blade::render( '<x-ve-blocks-styles :interactive="false" />' );

	expect( $rendered )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/query-pagination.css' )
		->toContain( 'data-ve-query-pagination' );
} );

it( 'rebases the link href when assetBase is supplied', function (): void {
	$rendered = Blade::render(
		'<x-ve-blocks-styles asset-base="https://cdn.example.com/ve" />',
	);

	expect( $rendered )
		->toContain( 'https://cdn.example.com/ve/style.css' )
		->toContain( 'https://cdn.example.com/ve/theme.css' )
		->not->toContain( '/vendor/visual-editor-renderer-blade/style.css' );
} );

it( 'compiles theme.json palette + fontSizes into --wp--preset--* declarations', function (): void {
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
		[ 'themeJson' => $themeJson ],
	);

	expect( $rendered )
		->toContain( 'data-ve-theme-tokens' )
		->toContain( '--wp--preset--color--primary: #0f172a;' )
		->toContain( '--wp--preset--color--accent: #2563eb;' )
		->toContain( '--wp--preset--font-size--small: 0.875rem;' )
		->toContain( '--wp--preset--font-size--large: 1.25rem;' );
} );

it( 'omits the tokens style block when theme.json carries no recognised tokens', function (): void {
	// `settings.layout` is now a recognized category (Keystone #50 — it
	// produces layout-size custom properties + alignwide/alignfull
	// rules), so use an unrecognized section to exercise the
	// "no tokens" code path.
	$rendered = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
		'themeJson' => [ 'settings' => [ 'border' => [ 'color' => true ] ] ],
	] );

	expect( $rendered )->not->toContain( 'data-ve-theme-tokens' );
} );

it( 'silently skips theme.json entries missing slug or value', function (): void {
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

it( 'emits the layout-flow block-gap baseline rule for sibling spacing', function (): void {
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

it( 'publishes block-library assets under the visual-editor-renderer-blade-assets tag', function (): void {
	$artisan = $this->artisan( 'vendor:publish', [
		'--tag'   => 'visual-editor-renderer-blade-assets',
		'--force' => true,
	] );

	$artisan->assertExitCode( 0 );
} );

it( 'declares the default spacing presets even without a theme.json (#814)', function (): void {
	config()->set( 'artisanpack.visual-editor.presets', [] );

	$rendered = Blade::render( '<x-ve-blocks-styles />' );

	expect( $rendered )
		->toContain( '<style data-ve-spacing-presets>' )
		->toContain( '--wp--preset--spacing--40: 1.5rem;' );
} );

it( 'declares the theme\'s spacing presets in place of the defaults (#814)', function (): void {
	config()->set( 'artisanpack.visual-editor.presets', [] );

	$rendered = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
		'themeJson' => [ 'settings' => [ 'spacing' => [ 'spacingSizes' => [ [ 'slug' => 'sm', 'size' => '4px' ] ] ] ] ],
	] );

	expect( $rendered )
		->toContain( '--wp--preset--spacing--sm: 4px;' )
		->not->toContain( '--wp--preset--spacing--40' );
} );

describe( 'theme-less defaults (#821)', function (): void {
	beforeEach( function (): void {
		config()->set( 'artisanpack.visual-editor.presets', [] );
	} );

	it( 'declares every default font-size preset and its utility class with no theme', function (): void {
		$rendered = Blade::render( '<x-ve-blocks-styles />' );

		foreach ( [ 'small' => '13px', 'regular' => '16px', 'medium' => '20px', 'large' => '28px', 'huge' => '36px' ] as $slug => $size ) {
			expect( $rendered )
				->toContain( "--wp--preset--font-size--{$slug}: {$size};" )
				->toContain( ".has-{$slug}-font-size { font-size: var(--wp--preset--font-size--{$slug}) !important; }" );
		}
	} );

	it( 'declares the default palette with its utility classes', function (): void {
		$rendered = Blade::render( '<x-ve-blocks-styles />' );

		expect( $rendered )
			->toContain( '--wp--preset--color--primary: #2563eb;' )
			->toContain( '.has-primary-background-color { background-color: var(--wp--preset--color--primary) !important; }' );
	} );

	it( 'declares layout tokens at zero specificity and emits default layout rules', function (): void {
		$rendered = Blade::render( '<x-ve-blocks-styles />' );

		expect( $rendered )
			->toContain( '<style data-ve-default-tokens>' )
			->toContain( ':where(:root) {' )
			->toContain( '--wp--style--global--content-size: 720px;' )
			->toContain( '--wp--style--global--wide-size: 1080px;' )
			->toContain( '--wp--style--block-gap: 24px;' )
			->toContain( '--wp--style--root--padding-left: 1.5rem;' )
			->toContain( '.wp-block-post-content.is-layout-constrained > .alignwide' )
			->toContain( ':where(.wp-block-gallery.has-nested-images) { gap: var(--wp--style--unstable-gallery-gap, 16px); }' );
	} );

	it( 'uses the theme presets in place of the defaults when the theme ships them', function (): void {
		$rendered = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
			'themeJson' => [
				'settings' => [
					'typography' => [ 'fontSizes' => [ [ 'slug' => 'large', 'size' => '3rem' ] ] ],
					'layout'     => [ 'contentSize' => '640px', 'wideSize' => '1200px' ],
				],
			],
		] );

		expect( $rendered )
			->toContain( '--wp--preset--font-size--large: 3rem;' )
			->not->toContain( '--wp--preset--font-size--large: 28px;' )
			->not->toContain( '--wp--preset--font-size--huge' )
			->toContain( '--wp--style--global--content-size: 640px;' );
	} );

	it( 'emits the baseline stylesheet in auto mode only without a theme', function (): void {
		expect( Blade::render( '<x-ve-blocks-styles />' ) )->toContain( '<style data-ve-default-styles>' );

		expect( Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
			'themeJson' => [ 'settings' => [ 'layout' => [ 'contentSize' => '640px' ] ] ],
		] ) )->not->toContain( 'data-ve-default-styles' );
	} );

	it( 'honours an explicit default_styles flag', function (): void {
		config()->set( 'artisanpack.visual-editor.default_styles', false );
		expect( Blade::render( '<x-ve-blocks-styles />' ) )->not->toContain( 'data-ve-default-styles' );

		config()->set( 'artisanpack.visual-editor.default_styles', true );
		expect( Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" />', [
			'themeJson' => [ 'settings' => [ 'layout' => [ 'contentSize' => '640px' ] ] ],
		] ) )->toContain( 'data-ve-default-styles' );
	} );
} );

describe( 'stacking at the mobile breakpoint (#820)', function (): void {
	it( 'stacks columns and media-text at the registry mobile max-width', function (): void {
		$rendered = Blade::render( '<x-ve-blocks-styles />' );

		expect( $rendered )
			->toContain( '<style data-ve-responsive-stacking>' )
			->toContain( "@media (max-width: 767px) {\n\t.wp-block-columns:not(.is-not-stacked-on-mobile) { flex-wrap: wrap !important; }" )
			->toContain( '.wp-block-media-text.is-stacked-on-mobile { grid-template-columns: 100% !important; }' )
			->toContain( '@media (min-width: 768px) {' );
	} );

	it( 'follows a custom mobile threshold', function (): void {
		app()->instance(
			ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry::class,
			ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry::fromLayers( [ 'mobile' => [ 'maxWidthPx' => 599, 'previewWidthPx' => 375 ] ] ),
		);

		expect( Blade::render( '<x-ve-blocks-styles />' ) )
			->toContain( '@media (max-width: 599px) {' )
			->toContain( '@media (min-width: 600px) {' );
	} );

	it( 'is skipped with the block library', function (): void {
		expect( Blade::render( '<x-ve-blocks-styles :bundle="false" />' ) )->not->toContain( 'data-ve-responsive-stacking' );
	} );
} );

it( 'floats alignleft / alignright under any flow or constrained layout (#819)', function (): void {
	$rendered = Blade::render( '<x-ve-blocks-styles />' );

	expect( $rendered )
		->toContain( ':where(.is-layout-constrained, .is-layout-flow) > .alignleft { float: left; margin-inline-start: 0; margin-inline-end: 2em; }' )
		->toContain( ':where(.is-layout-constrained, .is-layout-flow) > .alignright { float: right;' )
		// Tailwind's `size-full` utility would otherwise stretch the figure.
		->toContain( ':where(.wp-block-image.size-full) { width: auto; height: auto; }' )
		// The float starts on the same line as the text beside it.
		->toContain( ':where(.is-layout-constrained, .is-layout-flow) > :is(.alignleft, .alignright) { margin-block-start: var(--wp--style--block-gap, 24px); }' )
		->toContain( '@media (min-width: 768px) { :where(.is-layout-constrained, .is-layout-flow) > :is(.alignleft, .alignright):first-child + * { margin-block-start: 0; } }' );
} );

describe( 'default styles scope (#821)', function (): void {
	it( 'scopes the baseline to block output, not the host page', function (): void {
		$css = ArtisanPackUI\VisualEditorRendererBlade\Support\DefaultStyles::CSS;

		expect( $css )
			->toContain( ':where(.wp-block-post-content) {' )
			->toContain( ':where(h2.wp-block-heading) { font-size: 2rem; }' )
			->not->toContain( ':where(body)' )
			->not->toMatch( '/:where\(h[1-6]\)/' );
	} );

	it( 'stays off in auto mode when a theme is active even without a theme.json prop', function (): void {
		$resolver = Mockery::mock( ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver::class );
		$resolver->shouldReceive( 'resolve' )->andReturn( new ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\ResolvedGlobalStyles(
			theme               : 'demo',
			settings            : [],
			styles              : [],
			variation           : null,
			hasUserCustomization: false,
			model               : null,
		) );
		app()->instance( ArtisanPackUI\CMSFramework\Modules\SiteEditor\Resolution\GlobalStylesResolver::class, $resolver );

		expect( Blade::render( '<x-ve-blocks-styles />' ) )->not->toContain( 'data-ve-default-styles' );
	} );
} );
