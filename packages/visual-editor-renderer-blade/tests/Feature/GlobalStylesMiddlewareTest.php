<?php

/**
 * {@see InjectQueuedGlobalStyles} — the safety net that delivers global
 * styles a layout queued but never printed (#879, M-4).
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditorRendererBlade\Http\Middleware\InjectQueuedGlobalStyles;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesPlacement;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

const MIDDLEWARE_TEST_CSS = 'h2 { color: var(--wp--preset--color--accent); }';
const MIDDLEWARE_TEST_TAG = '<style data-ve-global-styles>' . MIDDLEWARE_TEST_CSS . '</style>';

beforeEach( function (): void {
	Cache::flush();
	$this->app->make( GlobalStylesEmissionTracker::class )->reset();

	app()->instance( GlobalStylesEmissionResolver::class, new class extends GlobalStylesEmissionResolver {
		public function emit(): string
		{
			return MIDDLEWARE_TEST_CSS;
		}
	} );

	View::addNamespace( 've-test', __DIR__ . '/../Fixtures/views' );
	Blade::anonymousComponentPath( __DIR__ . '/../Fixtures/components', 've-test' );
} );

/**
 * Register a `web` route that returns `$respond()`.
 */
function globalStylesRoute( string $uri, Closure $respond ): void
{
	Route::middleware( 'web' )->get( $uri, $respond );
}

/**
 * Queue the styles the way an `@extends` layout without a head component
 * does, and return the rendered page body.
 */
function renderBlocksInsideLayoutCapture(): string
{
	return Blade::render( '<x-ve-test::fragment><x-ve-blocks :tree="$tree" /></x-ve-test::fragment>', [ 'tree' => [] ] );
}

it( 'is pushed onto the web middleware group', function (): void {
	expect( app( 'router' )->getMiddlewareGroups()['web'] ?? [] )->toContain( InjectQueuedGlobalStyles::class );
} );

it( 'injects queued styles before </head> when the layout has no <x-ve-blocks-styles>', function (): void {
	globalStylesRoute( '/m4/bare', fn () => view( 've-test::pages.extends-bare', [ 'tree' => [] ] ) );

	$html = $this->get( '/m4/bare' )->assertOk()->getContent();

	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 )
		->and( $html )->toContain( MIDDLEWARE_TEST_TAG . '<link rel="stylesheet" href="/themes/demo/style.css" data-theme-stylesheet>' )
		->and( strpos( $html, 'data-ve-global-styles' ) )->toBeLessThan( strpos( $html, 'data-theme-stylesheet' ) );
} );

it( 'injects right after the opening <body> tag when there is no </head>', function (): void {
	globalStylesRoute( '/m4/body', fn () => Blade::render( '<x-ve-test::body-only><x-ve-blocks :tree="$tree" /></x-ve-test::body-only>', [ 'tree' => [] ] ) );

	$html = $this->get( '/m4/body' )->assertOk()->getContent();

	expect( $html )->toContain( '<body class="page" data-x="a>b">' . MIDDLEWARE_TEST_TAG )
		->and( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 );
} );

it( 'appends when there is neither </head> nor <body>', function (): void {
	globalStylesRoute( '/m4/fragment', fn () => renderBlocksInsideLayoutCapture() );

	$html = $this->get( '/m4/fragment' )->assertOk()->getContent();

	expect( $html )->toEndWith( MIDDLEWARE_TEST_TAG )
		->and( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 );
} );

it( 'never double-prints when the layout printed the styles', function ( string $page ): void {
	globalStylesRoute( '/m4/' . $page, fn () => view( 've-test::pages.' . $page, [ 'tree' => [] ] ) );

	$html = $this->get( '/m4/' . $page )->assertOk()->getContent();

	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 )
		->and( strpos( $html, 'data-ve-global-styles' ) )->toBeLessThan( strpos( $html, '</head>' ) );
} )->with( [
	'<x-ve-blocks-styles> in the head' => 'extends-app',
	'host @stack in the head'          => 'extends-bare-stack',
] );

it( 'leaves an inline body copy alone', function (): void {
	globalStylesRoute( '/m4/inline', fn () => Blade::render( '<html><head></head><body><x-ve-blocks :tree="$tree" /></body></html>', [ 'tree' => [] ] ) );

	$html = $this->get( '/m4/inline' )->assertOk()->getContent();

	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 )
		->and( strpos( $html, 'data-ve-global-styles' ) )->toBeGreaterThan( strpos( $html, '<body>' ) );
} );

it( 'skips responses that are not buffered HTML', function ( Closure $respond, ?string $expected ): void {
	globalStylesRoute( '/m4/skip', function () use ( $respond ) {
		renderBlocksInsideLayoutCapture();

		return $respond();
	} );

	$response = $this->get( '/m4/skip' );

	if ( null !== $expected ) {
		expect( $response->getContent() )->toBe( $expected );
	}

	expect( (string) $response->getContent() )->not->toContain( 'data-ve-global-styles' )
		->and( app( GlobalStylesPlacement::class )->pendingTag() )->toBe( MIDDLEWARE_TEST_TAG );
} )->with( [
	'json'        => [ fn () => response()->json( [ 'html' => '<head></head>' ] ), '{"html":"<head><\/head>"}' ],
	'redirect'    => [ fn () => redirect( '/elsewhere' ), null ],
	'streamed'    => [ fn () => response()->stream( function (): void {
		echo '<head></head>';
	}, 200, [ 'Content-Type' => 'text/html' ] ), null ],
	'binary file' => [ fn () => response()->file( __DIR__ . '/../Fixtures/components/fragment.blade.php', [ 'Content-Type' => 'text/html' ] ), null ],
	'plain text'  => [ fn () => response( '<head></head>', 200, [ 'Content-Type' => 'text/plain' ] ), '<head></head>' ],
	'gzipped'     => [ fn () => response( '<head></head>', 200, [ 'Content-Type' => 'text/html', 'Content-Encoding' => 'gzip' ] ), '<head></head>' ],
] );

it( 'updates Content-Length when it injects', function (): void {
	globalStylesRoute( '/m4/length', function () {
		$html = renderBlocksInsideLayoutCapture();

		return response( '<html><head></head><body>' . $html . '</body></html>', 200, [ 'Content-Length' => '1' ] );
	} );

	$response = $this->get( '/m4/length' );

	expect( $response->headers->get( 'Content-Length' ) )->toBe( (string) strlen( (string) $response->getContent() ) );
} );

it( 'starts every request clean under a long-lived worker (Octane)', function (): void {
	globalStylesRoute( '/m4/first', fn () => renderBlocksInsideLayoutCapture() );
	globalStylesRoute( '/m4/second', fn () => '<html><head></head><body>no blocks</body></html>' );

	$this->get( '/m4/first' )->assertOk();

	// What Octane does between requests.
	$this->app->forgetScopedInstances();
	$this->app->make( GlobalStylesEmissionTracker::class )->reset();

	expect( $this->get( '/m4/second' )->getContent() )->not->toContain( 'data-ve-global-styles' );
} );

it( 'delivers the queued styles only once even if the pipeline runs twice', function (): void {
	renderBlocksInsideLayoutCapture();

	$middleware = new InjectQueuedGlobalStyles();
	$request    = request();
	$first      = $middleware->handle( $request, fn () => response( '<html><head></head></html>' ) );
	$second     = $middleware->handle( $request, fn () => response( '<html><head></head></html>' ) );

	expect( substr_count( $first->getContent(), 'data-ve-global-styles' ) )->toBe( 1 )
		->and( $second->getContent() )->toBe( '<html><head></head></html>' );
} );

it( 'places the styles in <head> before its first stylesheet, else before </head>', function ( string $html, string $expected ): void {
	expect( InjectQueuedGlobalStyles::inject( $html, '<S>' ) )->toBe( $expected );
} )->with( [
	'before the first stylesheet link' => [ '<head><title>t</title><link rel="icon" href="/i"><link href="/a.css" rel=\'stylesheet\'></head>', '<head><title>t</title><link rel="icon" href="/i"><S><link href="/a.css" rel=\'stylesheet\'></head>' ],
	'before an inline style'           => [ '<HEAD><meta charset="utf-8"><style>a{}</style></HEAD>', '<HEAD><meta charset="utf-8"><S><style>a{}</style></HEAD>' ],
	'commented stylesheet ignored'     => [ '<head><!-- <link rel="stylesheet" href="/x.css"> --></head>', '<head><!-- <link rel="stylesheet" href="/x.css"> --><S></head>' ],
	'no stylesheet'                    => [ '<head><title>t</title></head><body></body>', '<head><title>t</title><S></head><body></body>' ],
	'body only'                        => [ '<body class="a">x</body>', '<body class="a"><S>x</body>' ],
	'stylesheet in body ignored'       => [ '<body><link rel="stylesheet" href="/b.css"></body>', '<body><S><link rel="stylesheet" href="/b.css"></body>' ],
	'fragment'                         => [ '<p>x</p>', '<p>x</p><S>' ],
] );
