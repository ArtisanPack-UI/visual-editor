<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Animations\AnimationRegistry;

it( 'falls back to built-in defaults when nothing overrides them', function () {
	$registry = AnimationRegistry::fromLayers( [], [] );

	expect( $registry->families() )->toEqualCanonicalizing( [ 'entrance', 'hover', 'continuous' ] );
	expect( $registry->has( 'entrance', 'fade-in-up' ) )->toBeTrue();
	expect( $registry->has( 'continuous', 'pulse' ) )->toBeTrue();
	expect( $registry->has( 'hover', 'lift' ) )->toBeTrue();
} );

it( 'merges config overrides on top of defaults', function () {
	$registry = AnimationRegistry::fromLayers(
		[ 'entrance' => [ 'fade-in' => [ 'label' => 'Soft fade' ] ] ],
		[]
	);

	$def = $registry->get( 'entrance', 'fade-in' );

	expect( $def )->not->toBeNull();
	expect( $def['label'] )->toBe( 'Soft fade' );
	// Defaults flow through.
	expect( $def['keyframe'] )->toBe( 'apFadeIn' );
	expect( $def['duration'] )->toBe( 600 );
} );

it( 'lets a theme override drop a built-in by setting it to null', function () {
	$registry = AnimationRegistry::fromLayers(
		[],
		[ 'entrance' => [ 'flip-x' => null ] ]
	);

	expect( $registry->has( 'entrance', 'flip-x' ) )->toBeFalse();
	expect( $registry->has( 'entrance', 'fade-in' ) )->toBeTrue();
} );

it( 'lets a theme register a new animation in the entrance family', function () {
	$registry = AnimationRegistry::fromLayers(
		[],
		[ 'entrance' => [ 'fade-in-blur' => [
			'label'    => 'Fade in (blur)',
			'keyframe' => 'apFadeInBlur',
			'duration' => 700,
			'easing'   => 'ease-out',
		] ] ]
	);

	expect( $registry->has( 'entrance', 'fade-in-blur' ) )->toBeTrue();
	expect( $registry->get( 'entrance', 'fade-in-blur' )['keyframe'] )->toBe( 'apFadeInBlur' );
} );

it( 'rejects an unknown family slug', function () {
	AnimationRegistry::fromLayers(
		[],
		[ 'parallax' => [ 'scroll' => [ 'label' => 'Scroll', 'keyframe' => 'x', 'duration' => 1000, 'easing' => 'ease' ] ] ]
	);
} )->throws( InvalidArgumentException::class, 'family' );

it( 'rejects an entry missing the keyframe', function () {
	AnimationRegistry::fromLayers(
		[],
		[ 'entrance' => [ 'broken' => [ 'label' => 'Broken', 'duration' => 600, 'easing' => 'ease' ] ] ]
	);
} )->throws( InvalidArgumentException::class, 'keyframe' );

it( 'rejects a hover entry missing the preset', function () {
	AnimationRegistry::fromLayers(
		[],
		[ 'hover' => [ 'broken' => [ 'label' => 'Broken', 'duration' => 200, 'easing' => 'ease' ] ] ]
	);
} )->throws( InvalidArgumentException::class, 'preset' );

it( 'rejects a non-positive duration', function () {
	AnimationRegistry::fromLayers(
		[],
		[ 'entrance' => [ 'broken' => [
			'label' => 'Broken', 'keyframe' => 'x', 'duration' => 0, 'easing' => 'ease',
		] ] ]
	);
} )->throws( InvalidArgumentException::class, 'duration' );

it( 'rejects a key with invalid characters', function () {
	AnimationRegistry::fromLayers(
		[],
		[ 'entrance' => [ 'fade in' => [
			'label' => 'Bad', 'keyframe' => 'x', 'duration' => 100, 'easing' => 'ease',
		] ] ]
	);
} )->throws( InvalidArgumentException::class );
