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

it( 'fromLayers skips and logs invalid entries instead of throwing', function () {
	// Mixed: one valid + two invalid. Boot must never crash on a
	// malformed host config — the scoped service-provider binding
	// resolves on every request, so a throw here would 500 the editor.
	$registry = KeyframeRegistry::fromLayers(
		[
			[
				'name'  => 'confetti',
				'stops' => [
					[ 'at' => '0%',   'transform' => 'translateY(0)' ],
					[ 'at' => '100%', 'transform' => 'translateY(0)' ],
				],
			],
			[ 'stops' => [] ],
			[ 'name' => 'broken', 'stops' => [ [ 'at' => '0%', 'opacity' => '0' ] ] ],
		],
		[]
	);

	expect( $registry->customNames() )->toBe( [ 'confetti' ] );
} );

it( 'does not let an invalid global-styles entry overwrite a valid theme entry of the same name', function () {
	// Regression for the validate-then-merge fix: if global-styles
	// stomps the merge dict pre-validation, the (valid) theme entry
	// vanishes because the (invalid) global entry replaces it and is
	// then dropped at validate time. Both layers reference the same
	// name so we can isolate the precedence behaviour.
	$registry = KeyframeRegistry::fromLayers(
		[
			[
				'name'  => 'confetti',
				'stops' => [
					[ 'at' => '0%',   'transform' => 'translateY(0)' ],
					[ 'at' => '100%', 'transform' => 'translateY(0)' ],
				],
			],
		],
		[
			// Invalid: only one stop. Must NOT overwrite the theme
			// entry above.
			[ 'name' => 'confetti', 'stops' => [ [ 'at' => '0%', 'opacity' => '0' ] ] ],
		]
	);

	expect( $registry->customNames() )->toBe( [ 'confetti' ] );
	expect( $registry->emitOne( 'confetti' ) )->toContain( 'translateY(0)' );
} );

it( 'global-styles wins over theme when both layers are valid', function () {
	$registry = KeyframeRegistry::fromLayers(
		[
			[
				'name'  => 'confetti',
				'stops' => [
					[ 'at' => '0%',   'opacity' => '0' ],
					[ 'at' => '100%', 'opacity' => '1' ],
				],
			],
		],
		[
			[
				'name'  => 'confetti',
				'stops' => [
					[ 'at' => '0%',   'transform' => 'translateY(0)' ],
					[ 'at' => '100%', 'transform' => 'translateY(-12px)' ],
				],
			],
		]
	);

	$emitted = $registry->emitOne( 'confetti' );
	// The Global Styles version (transforms) wins, not the theme one
	// (opacity).
	expect( $emitted )->toContain( 'translateY(-12px)' );
	expect( $emitted )->not->toContain( 'opacity' );
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

it( 'rejects case-variant collisions with a built-in keyframe name', function () {
	new KeyframeRegistry( [
		[
			'name'  => 'apfadein',
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
