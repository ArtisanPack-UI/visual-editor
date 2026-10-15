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

it( 'emits nothing for empty inputs', function (): void {
	$emitter = makeEmitter();

	expect( $emitter->emit( '.ap-block-abc', [] ) )->toBe( '' );
	expect( $emitter->emit( '', [ 'color' => 'red' ] ) )->toBe( '' );
} );

it( 'emits the idle rule only when no per-state overrides exist', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => 'red',
	] );

	expect( $css )->toBe( '.ap-block-abc { background-color: red !important; }' );
} );

it( 'emits hover styles inside a hover-media wrap', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'blue' ],
	] );

	expect( $css )->toContain( '.ap-block-abc { background-color: red !important;' );
	expect( $css )->toContain( '@media (hover: hover) { .ap-block-abc:hover { background-color: blue !important; }' );
} );

it( 'adds a default transition to idle when any non-idle state is set', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'blue' ],
	] );

	expect( $css )->toContain( 'transition: all 150ms ease;' );
} );

it( 'respects an editor-authored transition value', function (): void {
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

it( 'does not wrap non-hover states in the hover media query', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'color' => [ 'idle' => 'red', 'focus-visible' => 'blue' ],
	] );

	expect( $css )->toContain( '.ap-block-abc:focus-visible { color: blue !important; }' );
	expect( $css )->not->toContain( '@media (hover: hover) { .ap-block-abc:focus-visible' );
} );

it( 'skips emitting a state rule when the resolved value equals the inheritance parent', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'red' ],
	] );

	expect( $css )->toContain( '.ap-block-abc { background-color: red !important;' );
	expect( $css )->not->toContain( ':hover { background-color: red' );
} );

it( 'supports custom states with attribute selectors', function (): void {
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

it( 'supports comma-separated selector lists', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'opacity' => [ 'idle' => '1', 'disabled' => '0.5' ],
	] );

	expect( $css )->toContain( '.ap-block-abc:disabled, .ap-block-abc[aria-disabled="true"]' );
} );

it( 'drops boolean overrides without emitting `0` / `1` declarations', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => true, 'hover' => false ],
	] );

	expect( $css )->toBe( '' );
} );

it( 'drops non-scalar overrides instead of casting them to garbage strings', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.ap-block-abc', [
		'background-color' => [ 'idle' => [ 'nested' => 'wrong' ] ],
	] );

	expect( $css )->toBe( '' );
} );

it( 'appends a reduced-motion guard that stops transitions and hover transforms', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.s', [
		'transform' => [ 'hover' => 'scale(1.05)' ],
	] );

	expect( $css )->toBe(
		'.s { transition: all 150ms ease; } '
		. '@media (hover: hover) { .s:hover { transform: scale(1.05) !important; } } '
		. '@media (prefers-reduced-motion: reduce) { .s { transition: none !important; } '
		. '.s:hover { transform: none !important; } }',
	);
} );

it( 'holds a state transform at the authored idle transform for reduced motion', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.s', [
		'transform' => [ 'idle' => 'rotate(2deg)', 'focus' => 'rotate(2deg) scale(1.1)' ],
	] );

	expect( $css )->toEndWith(
		'@media (prefers-reduced-motion: reduce) { .s { transition: none !important; } '
		. '.s:focus { transform: rotate(2deg) !important; } }',
	);
} );

it( 'turns off a transition authored on a non-idle state for reduced motion', function (): void {
	$emitter = makeEmitter();

	$css = $emitter->emit( '.s', [
		'background-color' => [ 'idle' => 'red', 'hover' => 'blue' ],
		'transition'       => [ 'idle' => 'color 1s', 'hover' => 'background-color 2s' ],
	] );

	expect( $css )->toEndWith(
		'@media (prefers-reduced-motion: reduce) { .s { transition: none !important; } '
		. '.s:hover { transition: none !important; } }',
	);
} );

it( 'emits no reduced-motion guard when nothing can animate or move', function (): void {
	$emitter = makeEmitter();

	expect( $emitter->emit( '.s', [ 'background-color' => 'red' ] ) )
		->toBe( '.s { background-color: red !important; }' );
} );
