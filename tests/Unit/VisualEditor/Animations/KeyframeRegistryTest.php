<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Animations\KeyframeRegistry;

it( 'exposes built-in keyframe names', function () {
	$registry = new KeyframeRegistry();

	expect( $registry->isBuiltIn( 'apFadeIn' ) )->toBeTrue();
	expect( $registry->isBuiltIn( 'apPulse' ) )->toBeTrue();
	expect( $registry->isBuiltIn( 'apBounce' ) )->toBeTrue();
	expect( $registry->has( 'apFadeIn' ) )->toBeTrue();
} );

it( 'emits CSS for every built-in keyframe', function () {
	$registry = new KeyframeRegistry();
	$css      = $registry->emitCss();

	expect( $css )->toContain( '@keyframes apFadeIn' );
	expect( $css )->toContain( '@keyframes apPulse' );
	expect( $css )->toContain( '@keyframes apSpin' );
} );

it( 'accepts and emits a custom keyframe', function () {
	$registry = new KeyframeRegistry( [
		[
			'name'  => 'confetti',
			'stops' => [
				[ 'at' => '0%',   'transform' => 'translateY(0)' ],
				[ 'at' => '50%',  'transform' => 'translateY(-12px) rotate(10deg)' ],
				[ 'at' => '100%', 'transform' => 'translateY(0)' ],
			],
		],
	] );

	expect( $registry->customNames() )->toBe( [ 'confetti' ] );
	expect( $registry->emitOne( 'confetti' ) )->toContain( '@keyframes confetti' );
	expect( $registry->emitOne( 'confetti' ) )->toContain( 'translateY(-12px)' );
} );

it( 'rejects a custom keyframe whose name collides with a built-in', function () {
	new KeyframeRegistry( [
		[
			'name'  => 'apFadeIn',
			'stops' => [
				[ 'at' => '0%',   'opacity' => '0' ],
				[ 'at' => '100%', 'opacity' => '1' ],
			],
		],
	] );
} )->throws( InvalidArgumentException::class, 'reserved' );

it( 'rejects fewer than two stops', function () {
	new KeyframeRegistry( [
		[ 'name' => 'oneStop', 'stops' => [ [ 'at' => '0%', 'opacity' => '0' ] ] ],
	] );
} )->throws( InvalidArgumentException::class, 'two' );

it( 'rejects an unsupported stop property', function () {
	new KeyframeRegistry( [
		[
			'name'  => 'bad',
			'stops' => [
				[ 'at' => '0%',   'position' => 'absolute' ],
				[ 'at' => '100%', 'opacity' => '1' ],
			],
		],
	] );
} )->throws( InvalidArgumentException::class, 'property' );

it( 'rejects an at value outside 0-100 percent', function () {
	new KeyframeRegistry( [
		[
			'name'  => 'bad',
			'stops' => [
				[ 'at' => '150%', 'opacity' => '0' ],
				[ 'at' => '100%', 'opacity' => '1' ],
			],
		],
	] );
} )->throws( InvalidArgumentException::class );

it( 'rejects a CSS-injection attempt in a value', function () {
	new KeyframeRegistry( [
		[
			'name'  => 'bad',
			'stops' => [
				[ 'at' => '0%',   'opacity' => '0; } body { display: none' ],
				[ 'at' => '100%', 'opacity' => '1' ],
			],
		],
	] );
} )->throws( InvalidArgumentException::class, 'disallowed' );
