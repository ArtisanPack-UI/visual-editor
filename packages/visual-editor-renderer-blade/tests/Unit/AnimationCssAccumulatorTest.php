<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Animations\KeyframeRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Services\AnimationCssAccumulator;

function makeAnimAccumulator(): AnimationCssAccumulator
{
	return new AnimationCssAccumulator( new KeyframeRegistry() );
}

it( 'emits an empty result when nothing is pushed', function () {
	$flushed = makeAnimAccumulator()->flush();

	expect( $flushed['styleTag'] )->toBe( '' );
	expect( $flushed['noscriptTag'] )->toBe( '' );
	expect( $flushed['runtimeNeeded'] )->toBeFalse();
} );

it( 'wraps accumulated rules in a single <style> tag with built-in keyframes', function () {
	$accumulator = makeAnimAccumulator();
	$accumulator->push( '.ap-block-a', '.ap-block-a { animation: apFadeIn 600ms; }', '', false );
	$accumulator->push( '.ap-block-b', '.ap-block-b { animation: apPulse 2s infinite; }', '', false );

	$flushed = $accumulator->flush();

	expect( $flushed['styleTag'] )->toStartWith( '<style data-ve-animations>' );
	expect( $flushed['styleTag'] )->toEndWith( '</style>' );
	expect( $flushed['styleTag'] )->toContain( '@keyframes apFadeIn' );
	expect( $flushed['styleTag'] )->toContain( '.ap-block-a' );
	expect( $flushed['styleTag'] )->toContain( '.ap-block-b' );
} );

it( 'collects noscript fallbacks only for entrance blocks', function () {
	$accumulator = makeAnimAccumulator();
	$accumulator->push(
		'.ap-block-a',
		'.ap-block-a.ap-anim-pre { opacity: 0; }',
		'.ap-block-a.ap-anim-pre { opacity: 1; transform: none; }',
		true,
	);

	$flushed = $accumulator->flush();

	expect( $flushed['noscriptTag'] )->toContain( '<noscript>' );
	expect( $flushed['noscriptTag'] )->toContain( 'opacity: 1' );
	expect( $flushed['runtimeNeeded'] )->toBeTrue();
} );

it( 'deduplicates pushes for the same scope', function () {
	$accumulator = makeAnimAccumulator();
	$accumulator->push( '.ap-block-a', '.ap-block-a { animation: apFadeIn; }', '', false );
	$accumulator->push( '.ap-block-a', '.ap-block-a { animation: apPulse; }', '', false );

	$state = $accumulator->all();

	expect( count( $state['rules'] ) )->toBe( 1 );
} );

it( 'resets after flushing', function () {
	$accumulator = makeAnimAccumulator();
	$accumulator->push( '.ap-block-a', '.ap-block-a { x: y; }', '', false );
	$accumulator->flush();

	$state = $accumulator->all();

	expect( $state['rules'] )->toBe( [] );
} );
