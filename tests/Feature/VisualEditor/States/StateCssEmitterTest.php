<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\States\StateCssEmitter;
use ArtisanPackUI\VisualEditor\States\StateRegistry;
use ArtisanPackUI\VisualEditor\States\StateValueResolver;

function makeEmitter( array $configOverrides = [], array $themeOverrides = [] ): StateCssEmitter
{
	$registry = StateRegistry::fromLayers( $configOverrides, $themeOverrides );
	$resolver = new StateValueResolver( $registry );

	return new StateCssEmitter( $registry, $resolver );
}

it( 'emits nothing for empty inputs', function () {
	$emitter = makeEmitter();

	expect( $emitter->emit( '.ap-block-abc', [] ) )->toBe( '' );
	expect( $emitter->emit( '', [ 'color' => 'red' ] ) )->toBe( '' );
} );

it( 'emits the idle rule only when no per-state overrides exist', function () {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => 'red',
	] );

	expect( $css )->toBe( '.ap-block-abc { background-color: red !important; }' );
} );

it( 'emits hover styles inside a hover-media wrap', function () {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'blue' ],
	] );

	expect( $css )->toContain( '.ap-block-abc { background-color: red !important;' );
	expect( $css )->toContain( '@media (hover: hover) { .ap-block-abc:hover { background-color: blue !important; }' );
} );

it( 'adds a default transition to idle when any non-idle state is set', function () {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'blue' ],
	] );

	expect( $css )->toContain( 'transition: all 150ms ease;' );
} );

it( 'respects an editor-authored transition value', function () {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'blue' ],
		'transition'       => 'transform 200ms ease-out',
	] );

	// `transition` is in the NEVER_IMPORTANT set — author-supplied
	// values stay overridable by host CSS, unlike colors/borders.
	expect( $css )->toContain( 'transition: transform 200ms ease-out;' );
	expect( $css )->not->toContain( 'transition: transform 200ms ease-out !important;' );
	expect( $css )->not->toContain( 'transition: all 150ms ease;' );
} );

it( 'does not wrap non-hover states in the hover media query', function () {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'color' => [ 'idle' => 'red', 'focus-visible' => 'blue' ],
	] );

	expect( $css )->toContain( '.ap-block-abc:focus-visible { color: blue !important; }' );
	expect( $css )->not->toContain( '@media (hover: hover) { .ap-block-abc:focus-visible' );
} );

it( 'skips emitting a state rule when the resolved value equals the inheritance parent', function () {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'red' ],
	] );

	expect( $css )->toContain( '.ap-block-abc { background-color: red !important;' );
	expect( $css )->not->toContain( ':hover { background-color: red' );
} );

it( 'supports custom states with attribute selectors', function () {
	$emitter = makeEmitter( [], [
		'aria-current' => [
			'label'        => 'Current',
			'selector'     => '&[aria-current="page"]',
			'inheritsFrom' => 'idle',
		],
	] );

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'aria-current' => 'navy' ],
	] );

	expect( $css )->toContain( '.ap-block-abc[aria-current="page"] { background-color: navy !important; }' );
} );

it( 'supports comma-separated selector lists', function () {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'opacity' => [ 'idle' => '1', 'disabled' => '0.5' ],
	] );

	expect( $css )->toContain( '.ap-block-abc:disabled, .ap-block-abc[aria-disabled="true"]' );
} );
