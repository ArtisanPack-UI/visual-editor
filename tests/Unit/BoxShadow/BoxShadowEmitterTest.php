<?php

/**
 * Unit tests for {@see BoxShadowEmitter} (#607).
 *
 * Pins the three emission modes (preset / solid / gradient) plus the
 * state and breakpoint cascade behaviors. Byte-sensitive against the
 * TS mirror in `box-shadows/emitter.ts` — the editor preview and
 * server render must produce equivalent CSS.
 *
 * @since 1.2.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BoxShadow\BoxShadowEmitter;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\States\StateRegistry;

beforeEach( function (): void {
	$this->emitter = new BoxShadowEmitter(
		StateRegistry::fromLayers( [], [] ),
		BreakpointRegistry::fromLayers( [], [] ),
	);
} );

/**
 * @return array<string, mixed>
 */
function bsLayer( array $overrides = [] ): array
{
	return array_merge( [
		'offsetX'  => '0',
		'offsetY'  => '0',
		'blur'     => '0',
		'spread'   => '0',
		'color'    => null,
		'gradient' => null,
		'inset'    => false,
		'preset'   => null,
	], $overrides );
}

describe( 'BoxShadowEmitter::emit', function (): void {
	it( 'returns empty when scope is blank or payload has no values', function (): void {
		expect( $this->emitter->emit( '', [ 'idle' => bsLayer( [ 'color' => '#000' ] ) ] ) )->toBe( '' );

		expect( $this->emitter->emit( '.scope', [
			'idle'        => null,
			'states'      => [],
			'breakpoints' => [],
		] ) )->toBe( '' );
	} );

	it( 'emits a stock box-shadow for a solid outer layer', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle' => bsLayer( [ 'offsetX' => '2px', 'offsetY' => '4px', 'blur' => '8px', 'color' => '#000' ] ),
		] );

		expect( $css )->toContain( '.scope{box-shadow:2px 4px 8px 0 #000}' );
		expect( $css )->not->toContain( '::before' );
		expect( $css )->not->toContain( 'position:relative' );
	} );

	it( 'emits inset prefix for a solid inset layer', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle' => bsLayer( [ 'blur' => '6px', 'color' => '#333', 'inset' => true ] ),
		] );

		expect( $css )->toContain( '.scope{box-shadow:inset 0 0 6px 0 #333}' );
	} );

	it( 'emits var() for a preset layer', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle' => bsLayer( [ 'preset' => 'shadow-md' ] ),
		] );

		expect( $css )->toContain( '.scope{box-shadow:var(--wp--preset--shadow--shadow-md)}' );
	} );

	it( 'appends inset suffix on preset layers when inset is true', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle' => bsLayer( [ 'preset' => 'shadow-lg', 'inset' => true ] ),
		] );

		expect( $css )->toContain( '.scope{box-shadow:var(--wp--preset--shadow--shadow-lg) inset}' );
	} );

	it( 'emits a ::before pseudo for an outer gradient layer with position relative on the wrapper', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle' => bsLayer( [
				'offsetX'  => '4px',
				'offsetY'  => '6px',
				'blur'     => '12px',
				'spread'   => '2px',
				'gradient' => 'linear-gradient(135deg, #ff0000, #0000ff)',
			] ),
		] );

		expect( $css )->toContain( '.scope{position:relative;isolation:isolate}' );
		expect( $css )->toContain( '.scope::before{' );
		expect( $css )->toContain( 'background:linear-gradient(135deg, #ff0000, #0000ff)' );
		expect( $css )->toContain( 'filter:blur(12px)' );
		expect( $css )->toContain( 'transform:translate(4px,6px)' );
		expect( $css )->toContain( 'z-index:-1' );
	} );

	it( 'emits a ::after pseudo with mask-composite for an inset gradient layer', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle' => bsLayer( [
				'offsetX'  => '4px',
				'offsetY'  => '6px',
				'blur'     => '8px',
				'spread'   => '4px',
				'gradient' => 'linear-gradient(180deg, #fff, #000)',
				'inset'    => true,
			] ),
		] );

		expect( $css )->toContain( '.scope{position:relative;isolation:isolate}' );
		expect( $css )->toContain( '.scope::after{' );
		expect( $css )->toContain( 'mask-composite:exclude' );
		expect( $css )->toContain( 'transform:translate(calc(-1 * 4px),calc(-1 * 6px))' );
		expect( $css )->toContain( 'padding:4px' );
	} );

	it( 'emits transition declaration when non-idle layers exist', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => bsLayer( [ 'blur' => '4px', 'color' => '#000' ] ),
			'states' => [],
		] );

		expect( $css )->not->toContain( 'transition:' );

		$css = $this->emitter->emit( '.scope', [
			'idle'        => bsLayer( [ 'blur' => '4px', 'color' => '#000' ] ),
			'breakpoints' => [ 'md' => bsLayer( [ 'blur' => '12px', 'color' => '#000' ] ) ],
		] );

		// The breakpoint registry above has no entries, so the wrap
		// won't fire, but the transition rule still emits because the
		// payload reports non-idle entries exist. Both behaviors are
		// internally consistent.
		expect( $css )->toContain( 'transition:' );
	} );

	it( 'sanitises hostile characters in CSS values', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle' => bsLayer( [
				'offsetX' => '4px"; }</style><script>alert(1)</script>',
				'color'   => '#000',
			] ),
		] );

		expect( $css )->not->toContain( '<script>' );
		expect( $css )->not->toContain( '"' );
	} );
} );
