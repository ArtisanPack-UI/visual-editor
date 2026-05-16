<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\GlobalStylesEmissionTracker;
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

it( 'does not emit a global-styles <style> block without cms-framework', function () {
	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $rendered )->not->toContain( '<style data-ve-global-styles>' );
} );

it( 'still tracks emission state so the dedupe path works once cms-framework lands', function () {
	$tracker = $this->app->make( GlobalStylesEmissionTracker::class );

	expect( $tracker->hasEmitted() )->toBeFalse();

	// First render of <x-ve-blocks> calls into the resolver, which calls
	// `markEmitted()` even when the CSS is empty — that's the contract
	// downstream consumers rely on for the "emit at most once per
	// request" guarantee.
	Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => [] ] );

	expect( $tracker->hasEmitted() )->toBeTrue();
} );
