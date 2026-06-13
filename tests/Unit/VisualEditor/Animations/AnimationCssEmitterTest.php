<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Animations\AnimationAttributeResolver;
use ArtisanPackUI\VisualEditor\Animations\AnimationCssEmitter;
use ArtisanPackUI\VisualEditor\Animations\AnimationRegistry;
use ArtisanPackUI\VisualEditor\Animations\KeyframeRegistry;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

function makeAnimEmitter(): AnimationCssEmitter
{
	$breakpoints = BreakpointRegistry::fromLayers( [], [] );

	return new AnimationCssEmitter(
		AnimationRegistry::fromLayers( [], [] ),
		new KeyframeRegistry(),
		$breakpoints,
		new AnimationAttributeResolver( $breakpoints ),
	);
}

it( 'emits nothing for an empty attribute bag', function () {
	expect( makeAnimEmitter()->emit( '.ap-block-x', [] ) )->toBe( '' );
} );

it( 'emits the entrance pre-state and play rule', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'entrance' => [ 'name' => 'fade-in-up' ],
	] );

	expect( $css )->toContain( '.ap-block-x.ap-anim-pre { opacity: 0; }' );
	expect( $css )->toContain( 'animation: apFadeInUp' );
	expect( $css )->toContain( '600ms' );
} );

it( 'lets a per-breakpoint null disable the entrance animation', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'entrance' => [ 'name' => [ 'base' => 'fade-in', 'md' => null ] ],
	] );

	expect( $css )->toContain( '@media (min-width: 768px)' );
	expect( $css )->toContain( 'animation: none' );
} );

it( 'emits a hover preset rule wrapped in a hover media query', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'hover' => [ 'name' => 'lift' ],
	] );

	expect( $css )->toContain( '@media (hover: hover)' );
	expect( $css )->toContain( 'translateY(-3px)' );
	expect( $css )->toContain( 'transition' );
} );

it( 'emits a continuous animation with infinite iteration by default', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'continuous' => [ 'name' => 'pulse' ],
	] );

	expect( $css )->toContain( 'animation: apPulse' );
	expect( $css )->toContain( 'infinite' );
} );

it( 'guards entrance + continuous against prefers-reduced-motion by default', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'entrance' => [ 'name' => 'fade-in' ],
	] );

	expect( $css )->toContain( '@media (prefers-reduced-motion: reduce)' );
	expect( $css )->toContain( 'animation: none !important' );
} );

it( 'skips the reduced-motion guard when the block opts out', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'entrance'      => [ 'name' => 'fade-in' ],
		'reducedMotion' => 'allow',
	] );

	expect( $css )->not->toContain( 'prefers-reduced-motion' );
} );

it( 'reports whether any family has an animation set', function () {
	$emitter = makeAnimEmitter();

	expect( $emitter->hasAny( [] ) )->toBeFalse();
	expect( $emitter->hasAny( [ 'entrance' => [ 'name' => 'fade-in' ] ] ) )->toBeTrue();
	expect( $emitter->hasAny( [ 'hover' => [ 'name' => 'lift' ] ] ) )->toBeTrue();
	expect( $emitter->hasAny( [ 'continuous' => [ 'name' => 'pulse' ] ] ) )->toBeTrue();
} );

it( 'reports whether any entrance is configured for runtime gating', function () {
	$emitter = makeAnimEmitter();

	expect( $emitter->hasEntrance( [ 'continuous' => [ 'name' => 'pulse' ] ] ) )->toBeFalse();
	expect( $emitter->hasEntrance( [ 'entrance' => [ 'name' => 'fade-in' ] ] ) )->toBeTrue();
} );

it( 'computes the wrapper class list', function () {
	$classes = makeAnimEmitter()->wrapperClasses( [
		'entrance' => [ 'name' => 'fade-in' ],
	] );

	expect( $classes )->toContain( 'ap-anim' );
	expect( $classes )->toContain( 'ap-anim-pre' );
} );

it( 'computes data-* attributes the runtime keys off', function () {
	$data = makeAnimEmitter()->dataAttributes( [
		'entrance' => [ 'name' => 'fade-in', 'threshold' => 0.5, 'once' => false ],
	] );

	expect( $data['data-ap-anim-entrance'] )->toBe( 'fade-in' );
	expect( $data['data-ap-anim-threshold'] )->toBe( '0.5' );
	expect( $data['data-ap-anim-once'] )->toBe( 'false' );
} );

it( 'returns the noscript fallback CSS for entrance blocks', function () {
	expect( makeAnimEmitter()->noscriptCss( '.ap-block-x' ) )
		->toContain( '.ap-block-x.ap-anim-pre' )
		->toContain( 'opacity: 1' );
} );

it( 'composes entrance + continuous so the continuous loop survives the play class swap', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'entrance'   => [ 'name' => 'fade-in-up' ],
		'continuous' => [ 'name' => 'pulse' ],
	] );

	// Both keyframes must appear comma-joined on the same play-class
	// rule so adding `.ap-anim-play` doesn't clobber the continuous
	// pulse loop emitted on the base scope.
	expect( $css )->toMatch( '/\.ap-block-x\.ap-anim-play\s*\{\s*animation:\s*apFadeInUp[^,]*,\s*apPulse/' );

	// Continuous still emits its own base-scope rule so it starts
	// running before the entrance fires.
	expect( $css )->toMatch( '/\.ap-block-x\s*\{\s*animation:\s*apPulse/' );
} );

it( 'emits the pre-state and data attr for responsive-only entrance configs', function () {
	$emitter = makeAnimEmitter();

	$attributes = [
		'entrance' => [ 'name' => [ 'md' => 'fade-in-up' ] ],
	];

	$css  = $emitter->emit( '.ap-block-x', $attributes );
	$data = $emitter->dataAttributes( $attributes );

	expect( $css )->toContain( '.ap-block-x.ap-anim-pre { opacity: 0; }' );
	expect( $data['data-ap-anim-entrance'] )->toBe( 'fade-in-up' );
} );

it( 'rejects easing values containing CSS-injection characters', function () {
	$css = makeAnimEmitter()->emit( '.ap-block-x', [
		'entrance' => [ 'name' => 'fade-in', 'easing' => 'ease; } body {' ],
	] );

	// Falls back to the registered default easing rather than echoing
	// the attacker-controlled value into the animation shorthand.
	expect( $css )->toContain( 'ease-out' );
	expect( $css )->not->toContain( 'body {' );
} );
