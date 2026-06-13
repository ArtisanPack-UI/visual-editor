<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Animations\AnimationCssEmitter;
use ArtisanPackUI\VisualEditorRendererBlade\Animations\AnimationMarkupResolver;

it( 'resolves the markup pieces a Blade partial needs for an entrance block', function () {
	$emitter  = $this->app->make( AnimationCssEmitter::class );
	$resolver = new AnimationMarkupResolver( $emitter );

	$markup = $resolver->resolve( '.ap-block-abc', [
		'entrance' => [ 'name' => 'fade-in-up', 'threshold' => 0.3 ],
	] );

	expect( $markup['hasAnimations'] )->toBeTrue();
	expect( $markup['hasEntrance'] )->toBeTrue();
	expect( $markup['classes'] )->toContain( 'ap-anim' );
	expect( $markup['classes'] )->toContain( 'ap-anim-pre' );
	expect( $markup['data']['data-ap-anim-entrance'] )->toBe( 'fade-in-up' );
	expect( $markup['data']['data-ap-anim-threshold'] )->toBe( '0.3' );
	expect( $markup['css'] )->toContain( 'apFadeInUp' );
	expect( $markup['css'] )->not->toBeEmpty();
	expect( $markup['noscriptCss'] )->toContain( 'opacity: 1' );
} );

it( 'returns an empty result for a block without animations', function () {
	$emitter  = $this->app->make( AnimationCssEmitter::class );
	$resolver = new AnimationMarkupResolver( $emitter );

	$markup = $resolver->resolve( '.ap-block-abc', [] );

	expect( $markup['hasAnimations'] )->toBeFalse();
	expect( $markup['classes'] )->toBe( [] );
	expect( $markup['data'] )->toBe( [] );
	expect( $markup['css'] )->toBe( '' );
} );

it( 'escapes attribute values when serialising to a string', function () {
	$emitter  = $this->app->make( AnimationCssEmitter::class );
	$resolver = new AnimationMarkupResolver( $emitter );

	$string = $resolver->dataString( [ 'data-ap-x' => 'foo"bar' ] );

	expect( $string )->toContain( '&quot;' );
} );
