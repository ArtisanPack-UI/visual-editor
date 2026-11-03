<?php

/**
 * Desktop-first device overrides across the PHP emitters (#820).
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BoxShadow\BoxShadowEmitter;
use ArtisanPackUI\VisualEditor\GradientBorder\GradientBorderEmitter;
use ArtisanPackUI\VisualEditor\Position\PositionEmitter;
use ArtisanPackUI\VisualEditor\Position\PositionResolver;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\States\StateRegistry;

beforeEach( function (): void {
	$this->registry = BreakpointRegistry::fromLayers( [], [] );
} );

it( 'emits gradient-border device overrides as max-width, tablet before mobile', function (): void {
	$emitter = new GradientBorderEmitter( StateRegistry::fromLayers( [], [] ), $this->registry );

	$css = $emitter->emit( '.scope', [
		'idle'        => 'var(--red)',
		// Stored smallest-first; emission must still be descending.
		'breakpoints' => [ 'mobile' => 'var(--green)', 'tablet' => 'var(--blue)', 'md' => 'var(--gold)' ],
	] );

	expect( $css )
		->toContain( '@media (max-width:767px){.scope::before{background:var(--green)}}' )
		->toContain( '@media (max-width:1023px){.scope::before{background:var(--blue)}}' )
		->toContain( '@media (min-width:768px){.scope::before{background:var(--gold)}}' );

	expect( strpos( $css, 'min-width:768px' ) )->toBeLessThan( strpos( $css, 'max-width:1023px' ) );
	expect( strpos( $css, 'max-width:1023px' ) )->toBeLessThan( strpos( $css, 'max-width:767px' ) );
} );

it( 'emits box-shadow device overrides as max-width in descending order', function (): void {
	$emitter = new BoxShadowEmitter( StateRegistry::fromLayers( [], [] ), $this->registry );
	$layer   = static fn ( string $blur ): array => [
		'offsetX' => '0', 'offsetY' => '0', 'blur' => $blur, 'spread' => '0', 'color' => '#000', 'inset' => false,
	];

	$css = $emitter->emit( '.scope', [
		'idle'        => $layer( '1px' ),
		'breakpoints' => [ 'mobile' => $layer( '3px' ), 'tablet' => $layer( '2px' ) ],
	] );

	expect( strpos( $css, '@media (max-width:1023px)' ) )->toBeLessThan( strpos( $css, '@media (max-width:767px)' ) );
} );

it( 'cascades position device layers downward from base', function (): void {
	$payload = PositionResolver::resolve( [
		'style'      => [ 'position' => [ 'value' => 'relative' ] ],
		'responsive' => [
			'style.position' => [
				'tablet' => [ 'value' => 'sticky', 'offsets' => [ 'top' => [ 'value' => 10, 'unit' => 'px' ] ] ],
				'mobile' => [ 'value' => 'static' ],
			],
		],
	] );

	$css = ( new PositionEmitter( $this->registry ) )->emit( '.scope', $payload );

	expect( $css )
		->toContain( '@media (max-width:1023px){.scope{position:sticky !important;top:10px !important}}' )
		->not->toContain( 'min-width' );
} );
