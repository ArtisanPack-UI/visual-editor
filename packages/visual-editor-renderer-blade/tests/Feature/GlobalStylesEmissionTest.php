<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleCssCompiler;
use ArtisanPackUI\VisualEditor\BlockStyles\BlockStyleVariationParser;
use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesPlacement;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

beforeEach( function (): void {
	Cache::flush();
	$this->app->make( GlobalStylesEmissionTracker::class )->reset();
} );

/*
 * After #434, the renderer-blade package's `<x-ve-blocks>` /
 * `<x-ve-template>` delegate global-styles CSS emission to
 * cms-framework's `GlobalStylesEmitter` via `GlobalStylesEmissionResolver`.
 * In this Testbench environment cms-framework isn't installed, so the
 * resolver short-circuits to an empty string and no `<style data-ve-
 * global-styles>` block is emitted.
 *
 * The populated emission path is exercised in the consuming app's test
 * suite (Keystone CMS), where a real cms-framework env, a theme, and a
 * `global_styles` record are all available.
 */

it( 'does not emit a global-styles <style> block without cms-framework', function (): void {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $rendered )->not->toContain( '<style data-ve-global-styles>' );
} );

it( 'still tracks emission state so the dedupe path works once cms-framework lands', function (): void {
	$tracker = $this->app->make( GlobalStylesEmissionTracker::class );

	expect( $tracker->hasEmitted() )->toBeFalse();

	// First render of <x-ve-blocks> calls into the resolver, which calls
	// `markEmitted()` even when the CSS is empty — that's the contract
	// downstream consumers rely on for the "emit at most once per
	// request" guarantee.
	Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $tracker->hasEmitted() )->toBeTrue();
} );

/*
 * #879 — `<x-ve-blocks-styles>` claims the emission so the global styles
 * land in `<head>` before the theme's `style.css`, the order the editor
 * canvas uses. The resolver is faked here because cms-framework isn't
 * installed in this suite.
 */
function fakeGlobalStylesForEmissionTest(): void
{
	app()->instance( GlobalStylesEmissionResolver::class, new class extends GlobalStylesEmissionResolver {
		public function emit(): string
		{
			return 'h2 { color: var(--wp--preset--color--accent); }';
		}
	} );
}

it( 'prints the global styles from <x-ve-blocks-styles> and skips the body copy (#879)', function (): void {
	fakeGlobalStylesForEmissionTest();

	$head = Blade::render( '<x-ve-blocks-styles />' );
	$body = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $head )->toContain( '<style data-ve-global-styles>h2 { color: var(--wp--preset--color--accent); }</style>' )
		->and( strpos( $head, 'data-ve-global-styles' ) )->toBeGreaterThan( strpos( $head, 'data-ve-layout-baseline' ) )
		->and( $body )->not->toContain( 'data-ve-global-styles' );
} );

it( 'leaves the global styles to <x-ve-blocks> when global-styles is false (#879)', function (): void {
	fakeGlobalStylesForEmissionTest();

	$head = Blade::render( '<x-ve-blocks-styles :global-styles="false" />' );
	$body = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $head )->not->toContain( 'data-ve-global-styles' )
		->and( $body )->toContain( '<style data-ve-global-styles>' );
} );

it( 'does not print the global styles twice when <x-ve-blocks> rendered first (#879)', function (): void {
	fakeGlobalStylesForEmissionTest();

	$body = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );
	$late = Blade::render( '<x-ve-blocks-styles />' );

	expect( $body )->toContain( 'data-ve-global-styles' )
		->and( $late )->not->toContain( 'data-ve-global-styles' );
} );

/*
 * M-4 (#879) — Blade renders an `@extends` section and a component
 * layout's slot before the layout's `<head>`, so `<x-ve-blocks>` runs
 * first there. It queues the styles on the `ve-global-styles` stack and
 * `<x-ve-blocks-styles>` prints them in the head.
 */
function registerGlobalStylesLayoutFixtures(): void
{
	View::addNamespace( 've-test', __DIR__ . '/../Fixtures/views' );
	Blade::anonymousComponentPath( __DIR__ . '/../Fixtures/components', 've-test' );
}

/**
 * Assert the global styles print once, inside `<head>`, before the
 * theme stylesheet `<link>`.
 */
function expectGlobalStylesOnceInHead( string $html ): void
{
	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 )
		->and( strpos( $html, 'data-ve-global-styles' ) )->toBeLessThan( strpos( $html, '</head>' ) )
		->and( strpos( $html, 'data-ve-global-styles' ) )->toBeLessThan( strpos( $html, 'data-theme-stylesheet' ) );
}

it( 'prints the global styles in <head> for an @extends layout (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();
	registerGlobalStylesLayoutFixtures();

	$html = view( 've-test::pages.extends-app', [ 'tree' => [] ] )->render();

	expectGlobalStylesOnceInHead( $html );
} );

it( 'prints the global styles in <head> for an anonymous component layout (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();
	registerGlobalStylesLayoutFixtures();

	$html = Blade::render( '<x-ve-test::shell><main><x-ve-blocks :tree="$tree" /></main></x-ve-test::shell>', [ 'tree' => [] ] );

	expectGlobalStylesOnceInHead( $html );
} );

it( 'prints the global styles in <head> for a single-file view whose head renders first (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();

	$html = Blade::render(
		'<html><head><x-ve-blocks-styles /><link rel="stylesheet" href="/s.css" data-theme-stylesheet></head>'
		. '<body><x-ve-blocks :tree="$tree" /><x-ve-blocks :tree="$tree" /></body></html>',
		[ 'tree' => [] ],
	);

	expectGlobalStylesOnceInHead( $html );
} );

it( 'still prints the global styles once in a single-file view with no <x-ve-blocks-styles> (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();

	$html = Blade::render(
		'<html><head><link rel="stylesheet" href="/s.css"></head><body><div><x-ve-blocks :tree="$tree" /></div><x-ve-blocks :tree="$tree" /></body></html>',
		[ 'tree' => [] ],
	);

	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 )
		->and( strpos( $html, 'data-ve-global-styles' ) )->toBeGreaterThan( strpos( $html, '<body>' ) );
} );

it( 'prints the global styles once from a host @stack when the layout has no <x-ve-blocks-styles> (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();
	registerGlobalStylesLayoutFixtures();

	$html = view( 've-test::pages.extends-bare-stack', [ 'tree' => [] ] )->render();

	expectGlobalStylesOnceInHead( $html );
} );

it( 'prints the global styles once in the body with global_styles_in_head off (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();
	registerGlobalStylesLayoutFixtures();
	config()->set( 'artisanpack.visual-editor.global_styles_in_head', false );

	$withHead = view( 've-test::pages.extends-app', [ 'tree' => [] ] )->render();

	$this->app->make( GlobalStylesEmissionTracker::class )->reset();
	$this->app->make( GlobalStylesPlacement::class )->reset();

	$withoutHead = view( 've-test::pages.extends-bare', [ 'tree' => [] ] )->render();

	foreach ( [ $withHead, $withoutHead ] as $html ) {
		expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 )
			->and( strpos( $html, 'data-ve-global-styles' ) )->toBeGreaterThan( strpos( $html, '</head>' ) );
	}
} );

it( 'keeps the body placement for :global-styles="false" with global_styles_in_head off (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();
	registerGlobalStylesLayoutFixtures();
	config()->set( 'artisanpack.visual-editor.global_styles_in_head', false );

	$html = view( 've-test::pages.extends-legacy', [ 'tree' => [] ] )->render();

	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 )
		->and( strpos( $html, 'data-ve-global-styles' ) )->toBeGreaterThan( strpos( $html, '<main>' ) );
} );

it( 'does not lose the queued global styles when a later head has :global-styles="false" (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();
	registerGlobalStylesLayoutFixtures();

	$html = view( 've-test::pages.extends-legacy', [ 'tree' => [] ] )->render();

	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 );
} );

it( 'prints the queued global styles once when two <x-ve-blocks-styles> render after the body (M-4)', function (): void {
	fakeGlobalStylesForEmissionTest();
	registerGlobalStylesLayoutFixtures();

	$html = Blade::render(
		'<x-ve-test::shell><x-ve-blocks :tree="$tree" /><x-ve-blocks-styles /></x-ve-test::shell>',
		[ 'tree' => [] ],
	);

	expect( substr_count( $html, 'data-ve-global-styles' ) )->toBe( 1 );
} );

/*
 * R-12 — the global styles already carry every block style variation, so
 * the tokens `<style>` must not compile the theme's variations again.
 */
it( 'emits a theme.json block style variation once alongside the global styles (R-12)', function (): void {
	$themeJson = [
		'styles' => [
			'blocks' => [
				'core/group' => [
					'variations' => [
						'card' => [ 'color' => [ 'background' => '#fafafa' ] ],
					],
				],
			],
		],
	];

	$variationCss = ( new BlockStyleCssCompiler() )->compile( ( new BlockStyleVariationParser() )->fromStyles( $themeJson['styles'] ) );

	expect( $variationCss )->toContain( 'is-style-card' );

	app()->instance( GlobalStylesEmissionResolver::class, new class( $variationCss ) extends GlobalStylesEmissionResolver {
		public function __construct( protected string $css )
		{
		}

		public function emit(): string
		{
			return $this->css;
		}
	} );

	$headFirst = Blade::render( '<x-ve-blocks-styles :theme-json="$themeJson" /><x-ve-blocks :tree="$tree" />', [
		'themeJson' => $themeJson,
		'tree'      => [],
	] );

	expect( substr_count( $headFirst, '.is-style-card' ) )->toBe( 1 );

	$this->app->make( GlobalStylesEmissionTracker::class )->reset();
	$this->app->make( GlobalStylesPlacement::class )->reset();
	registerGlobalStylesLayoutFixtures();

	$bodyFirst = Blade::render( '<x-ve-test::shell><x-ve-blocks :tree="$tree" /></x-ve-test::shell>', [ 'tree' => [] ] );

	expect( substr_count( $bodyFirst, '.is-style-card' ) )->toBe( 1 );
} );
