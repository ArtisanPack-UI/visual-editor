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
		expect( $this->emitter->emit( '', [ 'idle' => 'var(--r)' ] ) )->toBe( '' );

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

		expect( $css )->toContain( '.scope{position:relative;border-color:transparent !important;overflow-clip-margin:border-box}' );
		expect( $css )->toContain( '.scope::before{content:"";' );
		expect( $css )->toContain( 'padding:2px;' );
		expect( $css )->toContain( 'background:linear-gradient(135deg, #ff0000, #0000ff);' );
		expect( $css )->toContain( 'mask-composite:exclude' );
		expect( $css )->toContain( '-webkit-mask-composite:xor' );
		expect( $css )->toContain( 'pointer-events:none' );
	} );

	it( 'defaults the border width to 1px when none is supplied', function (): void {
		$css = $this->emitter->emit( '.scope', [ 'idle' => 'var(--r)' ] );

		expect( $css )->toContain( 'padding:1px;' );
	} );

	it( 'inherits border-radius from the wrapper when none is configured', function (): void {
		$css = $this->emitter->emit( '.scope', [ 'idle' => 'var(--r)' ] );

		expect( $css )->toContain( 'border-radius:inherit;' );
	} );

	it( 'emits an explicit border-radius when supplied as a string', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'var(--r)',
			'radius' => '8px',
		] );

		expect( $css )->toContain( 'border-radius:8px;' );
		expect( $css )->not->toContain( 'border-radius:inherit;' );
	} );

	it( 'expands per-corner radius objects to four declarations', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'var(--r)',
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
			'idle'   => 'var(--r)',
			'states' => [ 'hover' => 'var(--b)' ],
		] );

		// Hover wrapped in @media (hover: hover) so touch devices
		// don't sticky-state. Matches StateCssEmitter's behavior.
		expect( $css )->toContain( '@media (hover: hover){' );
		expect( $css )->toContain( '.scope:hover::before{background:var(--b)}' );
	} );

	it( 'wraps the default transition on ::before when any non-idle override exists', function (): void {
		$cssWithNonIdle = $this->emitter->emit( '.scope', [
			'idle'   => 'var(--r)',
			'states' => [ 'hover' => 'var(--b)' ],
		] );

		expect( $cssWithNonIdle )->toContain( '.scope::before{transition:' );

		$idleOnly = $this->emitter->emit( '.scope', [ 'idle' => 'var(--r)' ] );

		expect( $idleOnly )->not->toContain( 'transition:' );
	} );

	it( 'emits per-breakpoint overrides under @media (min-width:...)', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'        => 'var(--r)',
			'breakpoints' => [ 'md' => 'var(--b)' ],
		] );

		expect( $css )->toContain( '@media (min-width:768px){.scope::before{background:var(--b)}}' );
	} );

	it( 'silently drops breakpoint overrides for unknown keys', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'        => 'var(--r)',
			'breakpoints' => [ 'never-defined' => 'var(--b)' ],
		] );

		expect( $css )->not->toContain( '@media' );
	} );

	it( 'silently drops state overrides for unknown keys', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'var(--r)',
			'states' => [ 'never-defined' => 'var(--b)' ],
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
			'idle'  => 'var(--r)',
			'width' => '2px"; }</style>',
		] );

		expect( $css )->not->toContain( 'padding:2px";' );
		expect( $css )->not->toContain( '</style>' );
		expect( $css )->not->toContain( '}</' );
	} );

	it( 'lets a Cover stop clipping where overflow-clip-margin is unsupported (#912)', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'var(--r)',
			'width'  => '3px',
			'radius' => '12px',
		] );

		expect( $css )->toContain( '@supports not (overflow-clip-margin:border-box){.scope.wp-block-cover{overflow:visible}' );
		expect( $css )->toContain(
			'.scope.wp-block-cover>:is(.wp-block-cover__background,.wp-block-cover__gradient-background,'
			. '.wp-block-cover__image-background,.wp-block-cover__video-background)'
			. '{border-radius:max(0px, calc(12px - 3px));}}',
		);
	} );

	it( 'insets each per-corner radius by the ring width in the Cover fallback', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'var(--r)',
			'width'  => '3px',
			'radius' => [ 'topLeft' => '12px', 'topRight' => '1rem' ],
		] );

		expect( $css )->toContain( 'border-top-left-radius:max(0px, calc(12px - 3px));border-top-right-radius:max(0px, calc(1rem - 3px));}}' );
	} );

	it( 'leaves Cover background layers square in the fallback when no radius is set', function (): void {
		$css = $this->emitter->emit( '.scope', [ 'idle' => 'var(--r)', 'width' => '3px' ] );

		expect( $css )->toContain( '@supports not (overflow-clip-margin:border-box){.scope.wp-block-cover{overflow:visible}}' );
		expect( $css )->not->toContain( 'wp-block-cover__background' );
	} );
} );

describe( 'GradientBorderEmitter value guard (#1083)', function (): void {
	it( 'treats a width or radius that opens a comment as unset', function ( string $field, mixed $value ): void {
		$css = $this->emitter->emit( '.scope', [ 'idle' => 'linear-gradient(red, blue)', $field => $value ] );

		expect( $css )->not->toContain( '/*' )
			->not->toContain( '*/' )
			->not->toContain( '//' )
			->toContain( 'background:linear-gradient(red, blue)' );
	} )->with( [
		'width comment open'   => [ 'width', '/*' ],
		'width unbalanced'     => [ 'width', 'calc(2px' ],
		'radius comment close' => [ 'radius', '4px */' ],
		'corner double slash'  => [ 'radius', [ 'topLeft' => '4px//', 'topRight' => '2px' ] ],
		'joined once stripped' => [ 'width', '/"*' ],
	] );

	it( 'falls back to the default width for a rejected width', function (): void {
		$css = $this->emitter->emit( '.scope', [ 'idle' => 'linear-gradient(red, blue)', 'width' => '/*' ] );

		expect( $css )->toContain( 'inset:calc(-1 * 1px);padding:1px;' );
	} );

	it( 'keeps the safe corners of a per-corner radius', function (): void {
		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'linear-gradient(red, blue)',
			'radius' => [ 'topLeft' => '/*', 'topRight' => '2px' ],
		] );

		expect( $css )->toContain( 'border-top-right-radius:2px' )->not->toContain( 'border-top-left-radius' );
	} );

	it( 'drops gradients that open a comment', function (): void {
		expect( $this->emitter->emit( '.scope', [ 'idle' => 'linear-gradient(red, blue) /*' ] ) )->toBe( '' );
		expect( $this->emitter->emit( '.scope', [ 'breakpoints' => [ 'md' => 'linear-gradient(red, blue) */' ] ] ) )->toBe( '' );

		$css = $this->emitter->emit( '.scope', [
			'idle'        => 'linear-gradient(red, blue)',
			'states'      => [ 'hover' => 'linear-gradient(red, blue /*' ],
			'breakpoints' => [ 'md' => 'linear-gradient(red, blue) */' ],
		] );

		expect( $css )->not->toContain( '/*' )->not->toContain( '*/' )->not->toContain( ':hover' );
	} );

	it( 'drops gradients that could fetch a resource', function ( string $gradient ): void {
		expect( $this->emitter->emit( '.scope', [ 'idle' => $gradient ] ) )->toBe( '' );

		$css = $this->emitter->emit( '.scope', [
			'idle'   => 'linear-gradient(red, blue)',
			'states' => [ 'hover' => $gradient ],
		] );

		expect( $css )->not->toContain( 'url(' )->not->toContain( 'image-set(' )->not->toContain( ':hover' );
	} )->with( [
		'url'               => [ 'url(http:evil.example/t.png)' ],
		'image-set'         => [ 'image-set(t.png 1x)' ],
		'gradient then url' => [ 'linear-gradient(red,red) padding-box,url(t.png)' ],
	] );
} );
