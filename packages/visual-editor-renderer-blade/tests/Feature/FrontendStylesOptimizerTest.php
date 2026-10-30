<?php

/**
 * Covers trimming the block stylesheets from a finished page (#1032).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\OptimizeBlockStyles;
use ArtisanPackUI\VisualEditorRendererBlade\Services\FrontendStylesOptimizer;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GeneratedStylesheets;
use ArtisanPackUI\VisualEditorRendererBlade\Support\FrontendAssets;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

function fileCacheForStyles(): void
{
	config()->set( 'cache.default', 'file' );
	config()->set( 'cache.stores.file.path', storage_path( 'framework/cache/ve-styles-test' ) );
}

function pageWithStyles( string $body ): string
{
	return '<!doctype html><html><head><title>t</title>'
		. Blade::render( '<x-ve-blocks-styles />' )
		. '<link rel="stylesheet" href="/theme/style.css"></head><body>' . $body . '</body></html>';
}

function optimizedPage( string $body ): string
{
	return app( FrontendStylesOptimizer::class )->optimize( pageWithStyles( $body ) );
}

function compiledLinkPath( string $html ): ?string
{
	return 1 === preg_match( '/<link rel="stylesheet" href="([^"]+)" data-ve-compiled-styles>/', $html, $matches )
		? $matches[1]
		: null;
}

/**
 * Selectors of a stylesheet, without keyframe steps.
 *
 * @return array<int, string>
 */
function stylesheetSelectors( string $css ): array
{
	$css = (string) preg_replace( '/\/\*.*?\*\//s', '', $css );
	$css = (string) preg_replace( '/@keyframes[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $css );

	preg_match_all( '/([^{};]+)\{/', $css, $matches );

	$selectors = [];

	foreach ( $matches[1] as $prelude ) {
		$prelude = trim( $prelude );

		if ( str_starts_with( $prelude, '@' ) ) {
			continue;
		}

		// Split on top-level commas only: `:is(.a, .b)` is one selector.
		foreach ( preg_split( '/,(?![^()]*\))/', $prelude ) ?: [] as $selector ) {
			$selectors[] = trim( $selector );
		}
	}

	return $selectors;
}

beforeEach( function (): void {
	File::deleteDirectory( public_path( FrontendAssets::PUBLIC_DIRECTORY ) );
} );

afterEach( function (): void {
	File::deleteDirectory( public_path( FrontendAssets::PUBLIC_DIRECTORY ) );
	File::deleteDirectory( storage_path( 'framework/cache/ve-styles-test' ) );
	File::deleteDirectory( storage_path( 'app/visual-editor' ) );
} );

it( 'has markers covering every selector of each conditional stylesheet', function ( string $attribute, string $file ): void {
	$markers = FrontendStylesOptimizer::STYLESHEET_MARKERS[ $attribute ];
	$css     = (string) file_get_contents( FrontendAssets::root() . '/frontend/' . $file );

	$hasMarker = static function ( string $selector ) use ( $markers ): bool {
		foreach ( $markers as $marker ) {
			if ( str_contains( $selector, $marker ) ) {
				return true;
			}
		}

		return false;
	};

	// A leading `:is(.a, .b)` matches on either class alone, so each
	// alternative needs a marker of its own.
	$unmatched = array_filter( stylesheetSelectors( $css ), static function ( string $selector ) use ( $hasMarker ): bool {
		if ( 1 === preg_match( '/^:is\(([^()]*)\)/', $selector, $union ) ) {
			return [] !== array_filter( explode( ',', $union[1] ), static fn ( string $alternative ): bool => ! $hasMarker( $alternative ) );
		}

		return ! $hasMarker( $selector );
	} );

	expect( array_values( $unmatched ) )->toBe( [] );
} )->with( [
	[ 'data-ve-accordion', 'accordion.css' ],
	[ 'data-ve-tabs', 'tabs.css' ],
	[ 'data-ve-grid', 'grid.css' ],
	[ 'data-ve-marquee', 'marquee.css' ],
	[ 'data-ve-social-icons', 'social-icons.css' ],
	[ 'data-ve-inline-icon', 'inline-icon.css' ],
	[ 'data-ve-breadcrumbs', 'breadcrumbs.css' ],
	[ 'data-ve-query-pagination', 'query-pagination.css' ],
	[ 'data-ve-flex-layout', 'flex-layout.css' ],
	[ 'data-ve-photo-grid', 'photo-grid.css' ],
	[ 'data-ve-post-template', 'post-template.css' ],
	[ 'data-ve-post-variant', 'post-variant.css' ],
	[ 'data-ve-masonry', 'masonry.css' ],
] );

it( 'removes the stylesheets a page has no markup for', function (): void {
	$html = optimizedPage( '<p>Plain text.</p>' );

	foreach ( array_keys( FrontendStylesOptimizer::STYLESHEET_MARKERS ) as $attribute ) {
		expect( $html )->not->toContain( '<link rel="stylesheet" href="/vendor/visual-editor-renderer-blade/frontend/' . substr( $attribute, 8 ) . '.css"' );
	}
} );

it( 'always keeps the block library and the host stylesheet', function (): void {
	expect( optimizedPage( '<p>Plain text.</p>' ) )
		->toContain( 'data-ve-block-library>' )
		->toContain( 'data-ve-block-library-theme>' )
		->toContain( 'href="/theme/style.css"' );
} );

it( 'keeps a stylesheet when its markup is in the body', function ( string $body, string $attribute ): void {
	expect( optimizedPage( $body ) )->toContain( $attribute );
} )->with( [
	'accordion'      => [ '<div class="ap-accordions"></div>', 'data-ve-accordion>' ],
	'tabs'           => [ '<div class="ap-tabs"></div>', 'data-ve-tabs>' ],
	'inline icon'    => [ '<p>Hi <span class="ap-inline-icon"></span></p>', 'data-ve-inline-icon>' ],
	'flex support'   => [ '<div class="wp-block-group ap-flex-row"></div>', 'data-ve-flex-layout>' ],
	'photo grid'     => [ '<div class="wp-block-columns has-photo-grid"></div>', 'data-ve-photo-grid>' ],
	'masonry'        => [ '<div class="wp-block-post-template is-layout-masonry"></div>', 'data-ve-masonry>' ],
	'related posts'  => [ '<div class="ap-related-posts is-layout-grid columns-3"></div>', 'data-ve-post-template>' ],
] );

it( 'keeps only the stylesheet the page uses', function (): void {
	expect( optimizedPage( '<div class="ap-tabs"></div>' ) )
		->toContain( 'data-ve-tabs>' )
		->not->toContain( 'data-ve-accordion>' )
		->not->toContain( 'data-ve-marquee>' );
} );

it( 'finds markers in markup the renderer never produced, as on a cached fragment', function (): void {
	expect( optimizedPage( '<div class="ap-marquee"><p class="ap-marquee__text">Cached</p></div>' ) )
		->toContain( 'data-ve-marquee>' );
} );

it( 'ignores a class name that only appears in the head', function (): void {
	$html = '<html><head><link rel="stylesheet" href="/a.css" data-ve-tabs><style>.ap-tabs { color: red; }</style></head><body></body></html>';

	expect( app( FrontendStylesOptimizer::class )->optimize( $html ) )->not->toContain( 'data-ve-tabs' );
} );

it( 'does not let a shorter attribute match a longer one', function (): void {
	$html = '<html><head><link rel="stylesheet" href="/a.css" data-ve-grid-extra></head><body></body></html>';

	expect( app( FrontendStylesOptimizer::class )->optimize( $html ) )->toContain( 'data-ve-grid-extra' );
} );

it( 'keeps stylesheets a host marks as required', function (): void {
	addFilter( 'ap.visualEditor.requiredStylesheets', fn (): array => [ 'data-ve-tabs' ] );

	expect( optimizedPage( '<p>Plain.</p>' ) )->toContain( 'data-ve-tabs>' );

	removeAllFilters( 'ap.visualEditor.requiredStylesheets' );
} );

it( 'removes the inline flex rules a moved breakpoint prints', function (): void {
	$html = '<html><head><style data-ve-flex-layout>.ap-flex{display:flex}</style></head><body><p>x</p></body></html>';

	expect( app( FrontendStylesOptimizer::class )->optimize( $html ) )->not->toContain( 'data-ve-flex-layout' );
} );

it( 'leaves a document without a head alone', function (): void {
	$html = '<div data-ve-tabs>fragment</div>';

	expect( app( FrontendStylesOptimizer::class )->optimize( $html ) )->toBe( $html );
} );

it( 'keeps every stylesheet when the conditional step is off', function (): void {
	config()->set( 'artisanpack.visual-editor.frontend_styles.conditional', false );

	expect( optimizedPage( '<p>Plain.</p>' ) )
		->toContain( 'data-ve-tabs>' )
		->toContain( 'data-ve-marquee>' );
} );

it( 'moves the inline styles into one linked file, in order', function (): void {
	fileCacheForStyles();

	$html = optimizedPage( '<p>Plain.</p>' );
	$path = compiledLinkPath( $html );

	expect( $path )->toMatch( '#^/vendor/visual-editor-renderer-blade/generated/[a-f0-9]{20}\.css$#' )
		->and( $html )->not->toContain( '<style data-ve-layout-baseline>' )
		->and( strpos( $html, 'data-ve-compiled-styles' ) )->toBeLessThan( strpos( $html, 'href="/theme/style.css"' ) );

	$response = $this->get( $path );

	$response->assertOk();

	expect( $response->headers->get( 'Cache-Control' ) )->toContain( 'immutable' )->toContain( 'max-age=31536000' )
		->and( (string) $response->headers->get( 'Content-Type' ) )->toStartWith( 'text/css' )
		->and( $response->getContent() )->toContain( '.is-layout-flex' );
} );

it( 'changes the file name when the styles change', function (): void {
	fileCacheForStyles();

	$stylesheets = app( GeneratedStylesheets::class );

	expect( $stylesheets->urlFor( '.a{color:red}' ) )->not->toBe( $stylesheets->urlFor( '.a{color:blue}' ) );
} );

it( 'merges only adjacent style tags, so a later tag keeps its cascade position', function (): void {
	fileCacheForStyles();

	$html = '<html><head>'
		. '<style data-ve-theme-tokens>:root{--a:1}</style>'
		. "\n" . '<style data-ve-layout-baseline>.b{color:red}</style>'
		. '<link rel="stylesheet" href="/theme.css">'
		. '<style data-ve-global-styles>.c{color:blue}</style>'
		. '</head><body></body></html>';

	$optimized = app( FrontendStylesOptimizer::class )->optimize( $html );

	preg_match_all( '/data-ve-compiled-styles/', $optimized, $links );

	expect( $links[0] )->toHaveCount( 2 )
		->and( strpos( $optimized, 'href="/theme.css"' ) )->toBeLessThan( strrpos( $optimized, 'data-ve-compiled-styles' ) );
} );

it( 'keeps styles inline when moving them would change what they mean', function ( string $css ): void {
	fileCacheForStyles();

	expect( app( GeneratedStylesheets::class )->urlFor( $css ) )->toBeNull();
} )->with( [
	'relative font'   => [ '@font-face{src:url(fonts/a.woff2)}' ],
	'dotted relative' => [ '.a{background:url("../a.png")}' ],
	'import'          => [ '@import "other.css";.a{color:red}' ],
	'empty'           => [ '' ],
] );

it( 'moves styles whose URLs are absolute', function ( string $css ): void {
	fileCacheForStyles();

	expect( app( GeneratedStylesheets::class )->urlFor( $css ) )->not->toBeNull();
} )->with( [
	'root relative' => [ '.a{background:url(/a.png)}' ],
	'https'         => [ '.a{background:url("https://x.test/a.png")}' ],
	'data'          => [ '.a{background:url(data:image/gif;base64,AAAA)}' ],
	'fragment'      => [ '.a{filter:url(#duotone)}' ],
] );

it( 'stays inline on a cache store that forgets between requests', function (): void {
	config()->set( 'cache.default', 'array' );

	$html = optimizedPage( '<p>Plain.</p>' );

	expect( $html )->toContain( '<style data-ve-layout-baseline>' )
		->and( compiledLinkPath( $html ) )->toBeNull();
} );

it( 'stays inline when the compiled file step is off', function (): void {
	fileCacheForStyles();
	config()->set( 'artisanpack.visual-editor.frontend_styles.compiled_file', false );

	expect( optimizedPage( '<p>Plain.</p>' ) )->toContain( '<style data-ve-layout-baseline>' );
} );

it( 'writes a static copy for a host that published its assets', function (): void {
	config()->set( 'cache.default', 'array' );

	$this->artisan( 'visual-editor:publish-assets' );

	$path = compiledLinkPath( optimizedPage( '<p>Plain.</p>' ) );

	expect( public_path( ltrim( (string) $path, '/' ) ) )->toBeFile();
} );

it( 'writes nothing to public for a host that never published', function (): void {
	fileCacheForStyles();

	optimizedPage( '<p>Plain.</p>' );

	expect( public_path( FrontendAssets::PUBLIC_DIRECTORY ) )->not->toBeDirectory();
} );

it( 'still serves a generated stylesheet after the cache is cleared', function (): void {
	fileCacheForStyles();

	$path = (string) app( GeneratedStylesheets::class )->urlFor( '.survives{color:red}' );

	Cache::flush();

	$response = $this->get( $path );

	$response->assertOk();

	expect( $response->getContent() )->toBe( '.survives{color:red}' );
} );

it( 'answers an unknown generated stylesheet with a 404', function (): void {
	fileCacheForStyles();

	$this->get( '/vendor/visual-editor-renderer-blade/generated/00000000000000000000.css' )->assertNotFound();
} );

it( 'trims a real response through the web group', function (): void {
	Route::middleware( 'web' )->get( '/styles-page', fn (): string => pageWithStyles( '<p>Plain.</p>' ) );

	$this->get( '/styles-page' )
		->assertOk()
		->assertDontSee( 'data-ve-tabs', false )
		->assertSee( 'data-ve-block-library', false );
} );

it( 'leaves the JSON and non-HTML responses alone', function (): void {
	Route::middleware( 'web' )->get( '/styles-json', fn (): array => [ 'html' => '<head data-ve-tabs></head>' ] );
	Route::middleware( 'web' )->get( '/styles-text', fn () => response( '<head><link data-ve-tabs></head>', 200, [ 'Content-Type' => 'text/plain' ] ) );

	$this->get( '/styles-json' )->assertOk()->assertJsonPath( 'html', '<head data-ve-tabs></head>' );
	$this->get( '/styles-text' )->assertOk()->assertSee( 'data-ve-tabs', false );
} );

it( 'runs after the queued-styles middleware', function (): void {
	$group    = app( Illuminate\Contracts\Http\Kernel::class )->getMiddlewareGroups()['web'];
	$optimize = array_search( OptimizeBlockStyles::class, $group, true );
	$inject   = array_search( ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\InjectQueuedGlobalStyles::class, $group, true );

	expect( $optimize )->toBeLessThan( $inject );
} );

it( 'keeps every output path of the component unchanged outside the middleware', function (): void {
	expect( Blade::render( '<x-ve-blocks-styles />' ) )
		->toContain( 'data-ve-tabs>' )
		->toContain( '<style data-ve-layout-baseline>' );
} );
