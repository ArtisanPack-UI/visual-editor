<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\States\StateRegistry;

it( 'falls back to built-in defaults when nothing overrides them', function () {
	$registry = StateRegistry::fromLayers( [], [] );

	expect( $registry->keys() )->toBe( [
		'idle',
		'hover',
		'focus',
		'focus-visible',
		'active',
		'disabled',
	] );
} );

it( 'merges config overrides on top of defaults', function () {
	$registry = StateRegistry::fromLayers(
		[ 'hover' => [ 'label' => 'Hovered' ] ],
		[]
	);

	$hover = $registry->get( 'hover' );

	expect( $hover )->not->toBeNull();
	expect( $hover['label'] )->toBe( 'Hovered' );
	expect( $hover['selector'] )->toBe( '&:hover' );
} );

it( 'merges theme.json overrides on top of config', function () {
	$registry = StateRegistry::fromLayers(
		[ 'hover' => [ 'label' => 'Config label' ] ],
		[ 'aria-current' => [
			'label'        => 'Current',
			'selector'     => '&[aria-current="page"]',
			'inheritsFrom' => 'idle',
		] ]
	);

	expect( $registry->has( 'aria-current' ) )->toBeTrue();
	expect( $registry->get( 'aria-current' )['selector'] )->toBe( '&[aria-current="page"]' );
} );

it( 'hoists idle to the front so iteration order is stable', function () {
	$registry = StateRegistry::fromLayers(
		[],
		[ 'aria-current' => [
			'label'        => 'Current',
			'selector'     => '&[aria-current]',
			'inheritsFrom' => 'idle',
		] ]
	);

	expect( $registry->keys()[0] )->toBe( 'idle' );
} );

it( 'returns the inheritance chain ending at idle', function () {
	$registry = StateRegistry::fromLayers( [], [] );

	expect( $registry->inheritanceChain( 'active' ) )->toBe( [ 'active', 'hover', 'idle' ] );
	expect( $registry->inheritanceChain( 'focus-visible' ) )->toBe( [ 'focus-visible', 'focus', 'idle' ] );
	expect( $registry->inheritanceChain( 'idle' ) )->toBe( [ 'idle' ] );
	expect( $registry->inheritanceChain( 'made-up' ) )->toBe( [ 'idle' ] );
} );

it( 'rejects a registry missing the reserved idle slot', function () {
	StateRegistry::fromLayers(
		[ 'idle' => null ],
		[]
	);
} )->throws( InvalidArgumentException::class, 'idle' );

it( 'rejects an idle slot with a non-empty selector', function () {
	StateRegistry::fromLayers(
		[ 'idle' => [ 'label' => 'Idle', 'selector' => '&:idle' ] ],
		[]
	);
} )->throws( InvalidArgumentException::class, 'idle' );

it( 'rejects a non-idle state with a missing or empty selector', function () {
	StateRegistry::fromLayers(
		[],
		[ 'broken' => [ 'label' => 'Broken', 'selector' => '', 'inheritsFrom' => 'idle' ] ]
	);
} )->throws( InvalidArgumentException::class, 'selector' );

it( 'rejects a state whose inheritsFrom is not registered', function () {
	StateRegistry::fromLayers(
		[],
		[ 'orphan' => [
			'label'        => 'Orphan',
			'selector'     => '&[data-orphan]',
			'inheritsFrom' => 'made-up',
		] ]
	);
} )->throws( InvalidArgumentException::class, 'inherits' );

it( 'allows removing a built-in state by setting it to null', function () {
	$registry = StateRegistry::fromLayers( [ 'disabled' => null ], [] );

	expect( $registry->has( 'disabled' ) )->toBeFalse();
	expect( $registry->has( 'hover' ) )->toBeTrue();
} );

it( 'flags hover with hoverMediaWrap=true by default', function () {
	$registry = StateRegistry::fromLayers( [], [] );

	expect( $registry->get( 'hover' )['hoverMediaWrap'] )->toBeTrue();
	expect( $registry->get( 'focus' )['hoverMediaWrap'] )->toBeFalse();
} );
