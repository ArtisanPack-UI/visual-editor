<?php

/**
 * Unit tests for {@see PositionEmitter} (#640).
 *
 * @since 1.4.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Position\PositionEmitter;
use ArtisanPackUI\VisualEditor\Position\PositionResolver;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;

function positionMakeRegistry(): BreakpointRegistry
{
	// Pass explicit `[]` for both overrides so `fromLayers()` doesn't
	// reach for `config()` (unavailable in unit-test isolation).
	return BreakpointRegistry::fromLayers( [], [] );
}

describe( 'PositionEmitter::emit', function (): void {
	it( 'returns empty when the scope is blank', function (): void {
		$payload = PositionResolver::resolve( [ 'style' => [ 'position' => [ 'value' => 'relative' ] ] ] );
		$emitter = new PositionEmitter( positionMakeRegistry() );

		expect( $emitter->emit( '   ', $payload ) )->toBe( '' );
	} );

	it( 'emits nothing for a static-only base layer', function (): void {
		$payload = PositionResolver::resolve( [ 'style' => [ 'position' => [ 'value' => 'static' ] ] ] );
		$emitter = new PositionEmitter( positionMakeRegistry() );

		expect( $emitter->emit( '.foo', $payload ) )->toBe( '' );
	} );

	it( 'emits base position + offsets + z-index in one rule', function (): void {
		$payload = PositionResolver::resolve( [
			'style' => [
				'position' => [
					'value'   => 'absolute',
					'offsets' => [
						'top'    => [ 'value' => 10, 'unit' => 'px' ],
						'right'  => [ 'unit' => 'auto' ],
					],
					'zIndex'  => 2,
				],
			],
		] );

		$emitter = new PositionEmitter( positionMakeRegistry() );

		expect( $emitter->emit( '.wrap', $payload ) )
			->toBe( '.wrap{position:absolute !important;top:10px !important;right:auto !important;z-index:2 !important}' );
	} );

	it( 'wraps breakpoint overrides in @media rules with inherited fields', function (): void {
		$payload = PositionResolver::resolve( [
			'style' => [
				'position' => [
					'value'   => 'relative',
					'offsets' => [ 'top' => [ 'value' => 10, 'unit' => 'px' ] ],
				],
			],
			'responsive' => [
				'style.position' => [
					'md' => [ 'zIndex' => 3 ],
					'lg' => [ 'value' => 'sticky', 'offsets' => [ 'top' => [ 'value' => 0, 'unit' => 'px' ] ] ],
				],
			],
		] );

		$css = ( new PositionEmitter( positionMakeRegistry() ) )->emit( '.wrap', $payload );

		expect( $css )->toContain( '.wrap{position:relative !important;top:10px !important}' );
		expect( $css )->toContain( '@media (min-width:768px){.wrap{position:relative !important;top:10px !important;z-index:3 !important}}' );
		expect( $css )->toContain( '@media (min-width:1024px){.wrap{position:sticky !important;top:0px !important;z-index:3 !important}}' );
	} );

	it( 'legacy sticky (bare string) round-trips to a single sticky rule', function (): void {
		$payload = PositionResolver::resolve( [ 'style' => [ 'position' => 'sticky' ] ] );

		expect( ( new PositionEmitter( positionMakeRegistry() ) )->emit( '.wrap', $payload ) )
			->toBe( '.wrap{position:sticky !important}' );
	} );
} );
