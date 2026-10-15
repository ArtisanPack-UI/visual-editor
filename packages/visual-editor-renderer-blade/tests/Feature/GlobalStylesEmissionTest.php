<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
use ArtisanPackUI\VisualEditorRendererBlade\Services\GlobalStylesEmissionResolver;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

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
