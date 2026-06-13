<?php

/**
 * Unit tests for {@see GradientBorderEmitter} (#490).
 *
 * Pins the mask-pseudo emission strategy against fixture payloads —
 * idle-only, per-state, per-breakpoint, with-radius, etc. These tests
 * are deliberately byte-sensitive on the emitted CSS because the JS
 * mirror in `gradient-borders/emitter.ts` must produce the same
 * output (assertion symmetry lives in the Vitest suite).
 *
 * @since 1.1.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\GradientBorder\GradientBorderEmitter;
use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditor\States\StateRegistry;

beforeEach( function (): void {
	// Explicit empty layer-arrays to skip the `config()` helper lookup
	// inside `fromLayers()` — these are pure unit tests with no
	// Laravel container booted.
	$this->emitter = new GradientBorderEmitter(
		StateRegistry::fromLayers( [], [] ),
		BreakpointRegistry::fromLayers( [], [] ),
	);
} );

describe( 'GradientBorderEmitter::emit', function (): void {
	it( 'returns empty when scope is blank or payload has no values', function (): void {
		expect( $this->emitter->emit( '', [ 'idle' => 'red' ] ) )->toBe( '' );

		expect( $this->emitter->emit( '.scope', [
			'idle'        => null,
			'states'      => [],
			'breakpoints' => [],
		] ) )->toBe( '' );
	} );

	it( 'emits the wrapper position + ::before mask layer for an idle gradient', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'  => 'linear-gradient(135deg, #ff0000, #0000ff)',
			'width' => '2px',
		] );

		expect( $css )->toContain( '.scope{position:relative;border-color:transparent !important}' );
		expect( $css )->toContain( '.scope::before{content:"";' );
		expect( $css )->toContain( 'padding:2px;' );
		expect( $css )->toContain( 'background:linear-gradient(135deg, #ff0000, #0000ff);' );
		expect( $css )->toContain( 'mask-composite:exclude' );
		expect( $css )->toContain( '-webkit-mask-composite:xor' );
		expect( $css )->toContain( 'pointer-events:none' );
	} );

	it( 'defaults the border width to 1px when none is supplied', function (): void {
		$css = $this->emitter->emit( '.scope', [ 'idle' => 'red' ] );

		expect( $css )->toContain( 'padding:1px;' );
	} );

	it( 'inherits border-radius from the wrapper when none is configured', function (): void {
		$css = $this->emitter->emit( '.scope', [ 'idle' => 'red' ] );

		expect( $css )->toContain( 'border-radius:inherit;' );
	} );

	it( 'emits an explicit border-radius when supplied as a string', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'red',
			'radius' => '8px',
		] );

		expect( $css )->toContain( 'border-radius:8px;' );
		expect( $css )->not->toContain( 'border-radius:inherit;' );
	} );

	it( 'expands per-corner radius objects to four declarations', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'red',
			'radius' => [
				'topLeft'     => '4px',
				'topRight'    => '8px',
				'bottomLeft'  => '12px',
				'bottomRight' => '16px',
			],
		] );

		expect( $css )->toContain( 'border-top-left-radius:4px' );
		expect( $css )->toContain( 'border-top-right-radius:8px' );
		expect( $css )->toContain( 'border-bottom-left-radius:12px' );
		expect( $css )->toContain( 'border-bottom-right-radius:16px' );
	} );

	it( 'emits a per-state override under the state selector', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'red',
			'states' => [ 'hover' => 'blue' ],
		] );

		// Hover wrapped in @media (hover: hover) so touch devices
		// don't sticky-state. Matches StateCssEmitter's behavior.
		expect( $css )->toContain( '@media (hover: hover){' );
		expect( $css )->toContain( '.scope:hover::before{background:blue}' );
	} );

	it( 'wraps the default transition on ::before when any non-idle override exists', function (): void {
		$cssWithNonIdle = $this->emitter->emit( '.scope', [
			'idle'   => 'red',
			'states' => [ 'hover' => 'blue' ],
		] );

		expect( $cssWithNonIdle )->toContain( '.scope::before{transition:' );

		$idleOnly = $this->emitter->emit( '.scope', [ 'idle' => 'red' ] );

		expect( $idleOnly )->not->toContain( 'transition:' );
	} );

	it( 'emits per-breakpoint overrides under @media (min-width:...)', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'        => 'red',
			'breakpoints' => [ 'md' => 'blue' ],
		] );

		expect( $css )->toContain( '@media (min-width:768px){.scope::before{background:blue}}' );
	} );

	it( 'silently drops breakpoint overrides for unknown keys', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'        => 'red',
			'breakpoints' => [ 'never-defined' => 'blue' ],
		] );

		expect( $css )->not->toContain( '@media' );
	} );

	it( 'silently drops state overrides for unknown keys', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'red',
			'states' => [ 'never-defined' => 'blue' ],
		] );

		expect( $css )->not->toContain( 'never-defined' );
		expect( $css )->not->toContain( 'background:blue' );
	} );

	it( 'sanitises gradient strings to strip context-breaking characters', function (): void {
		// `<`, `>`, `;`, and `}` aren't in the gradient whitelist —
		// stripping them prevents a hostile block tree from breaking
		// out of the `background:` declaration or the surrounding
		// `<style>` tag.
		$css = $this->emitter->emit( '.scope', [
			'idle' => 'linear-gradient(<script>red;</script>blue)',
		] );

		expect( $css )->not->toContain( '<' );
		expect( $css )->not->toContain( '>' );
		expect( $css )->not->toContain( ';script' );
	} );

	it( 'sanitises width values to strip context-breaking characters', function (): void {
		// The `content:""` declaration legitimately includes `"` —
		// assert the unsafe payload's `"`/`}`/`<` characters are
		// gone from the width slot. The `/` survives the whitelist
		// (legal in calc()), but on its own can't break out of a
		// `padding:` declaration.
		$css = $this->emitter->emit( '.scope', [
			'idle'  => 'red',
			'width' => '2px"; }</style>',
		] );

		expect( $css )->not->toContain( 'padding:2px";' );
		expect( $css )->not->toContain( '</style>' );
		expect( $css )->not->toContain( '}</' );
	} );
} );
