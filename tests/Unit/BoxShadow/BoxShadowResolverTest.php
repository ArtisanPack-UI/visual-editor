<?php

/**
 * Unit tests for {@see BoxShadowResolver} (#607).
 *
 * Covers the three cascade sources the resolver reads from
 * (`style.shadow`, `attributes.states['style.shadow']`,
 * `attributes.responsive['style.shadow']`), slug-vs-raw value
 * expansion, the shorthand path key, preset detection, and the
 * slug-collection helper the editor's token-warning surface uses.
 *
 * @since 1.2.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\BoxShadow\BoxShadowResolver;

describe( 'BoxShadowResolver::resolve', function (): void {
	it( 'returns null when no shadow configuration exists anywhere', function (): void {
		expect( BoxShadowResolver::resolve( [] ) )->toBeNull();

		expect( BoxShadowResolver::resolve( [
			'style' => [ 'shadow' => [] ],
		] ) )->toBeNull();
	} );

	it( 'returns a structured idle layer when at least one field is set', function (): void {
		$resolved = BoxShadowResolver::resolve( [
			'style' => [
				'shadow' => [
					'offsetX' => '2px',
					'offsetY' => '4px',
					'blur'    => '8px',
					'color'   => '#000',
				],
			],
		] );

		expect( $resolved )->not->toBeNull();
		expect( $resolved['idle']['offsetX'] )->toBe( '2px' );
		expect( $resolved['idle']['blur'] )->toBe( '8px' );
		expect( $resolved['idle']['color'] )->toBe( '#000' );
		expect( $resolved['idle']['inset'] )->toBeFalse();
		expect( $resolved['idle']['preset'] )->toBeNull();
	} );

	it( 'expands a gradient slug into var(--wp--preset--gradient--{slug})', function (): void {
		$resolved = BoxShadowResolver::resolve( [
			'style' => [ 'shadow' => [ 'gradient' => 'primary-glow' ] ],
		] );

		expect( $resolved['idle']['gradient'] )->toBe( 'var(--wp--preset--gradient--primary-glow)' );
	} );

	it( 'passes raw CSS gradient values through unchanged', function (): void {
		$raw      = 'linear-gradient(135deg, #ff0000, #0000ff)';
		$resolved = BoxShadowResolver::resolve( [
			'style' => [ 'shadow' => [ 'gradient' => $raw ] ],
		] );

		expect( $resolved['idle']['gradient'] )->toBe( $raw );
	} );

	it( 'captures a preset slug onto the layer', function (): void {
		$resolved = BoxShadowResolver::resolve( [
			'style' => [ 'shadow' => [ 'preset' => 'shadow-md' ] ],
		] );

		expect( $resolved['idle']['preset'] )->toBe( 'shadow-md' );
	} );

	it( 'honours the inset flag', function (): void {
		$resolved = BoxShadowResolver::resolve( [
			'style' => [ 'shadow' => [ 'offsetX' => '0', 'inset' => true ] ],
		] );

		expect( $resolved['idle']['inset'] )->toBeTrue();
	} );

	it( 'collects per-state overrides from attributes.states canonical path', function (): void {
		$resolved = BoxShadowResolver::resolve( [
			'states' => [
				'style.shadow' => [
					'hover' => [ 'offsetX' => '4px', 'blur' => '12px', 'color' => '#111' ],
					'focus' => null,
				],
			],
		] );

		expect( $resolved )->not->toBeNull();
		expect( $resolved['states']['hover']['blur'] )->toBe( '12px' );
		expect( $resolved['states'] )->not->toHaveKey( 'focus' );
	} );

	it( 'collects per-breakpoint overrides from attributes.responsive', function (): void {
		$resolved = BoxShadowResolver::resolve( [
			'responsive' => [
				'style.shadow' => [
					'md' => [ 'blur' => '16px', 'color' => '#000' ],
				],
			],
		] );

		expect( $resolved['breakpoints']['md']['blur'] )->toBe( '16px' );
	} );

	it( 'accepts the shorthand `shadow` path key with canonical winning on conflict', function (): void {
		$resolved = BoxShadowResolver::resolve( [
			'states' => [
				'shadow'       => [ 'hover' => [ 'blur' => '99px' ] ],
				'style.shadow' => [ 'hover' => [ 'blur' => '8px' ] ],
			],
		] );

		expect( $resolved['states']['hover']['blur'] )->toBe( '8px' );
	} );
} );

describe( 'BoxShadowResolver::referencedSlugs', function (): void {
	it( 'pulls shadow + gradient slugs from every cascade slot', function (): void {
		$slugs = BoxShadowResolver::referencedSlugs( [
			'style'      => [ 'shadow' => [ 'preset' => 'shadow-md', 'gradient' => 'brand-glow' ] ],
			'states'     => [
				'style.shadow' => [
					'hover' => [ 'preset' => 'shadow-elevated' ],
				],
			],
			'responsive' => [
				'style.shadow' => [
					'md' => [ 'gradient' => 'vertical' ],
				],
			],
		] );

		expect( $slugs['shadows'] )->toContain( 'shadow-md' );
		expect( $slugs['shadows'] )->toContain( 'shadow-elevated' );
		expect( $slugs['gradients'] )->toContain( 'brand-glow' );
		expect( $slugs['gradients'] )->toContain( 'vertical' );
	} );

	it( 'deduplicates slugs that appear in multiple cascade slots', function (): void {
		$slugs = BoxShadowResolver::referencedSlugs( [
			'style'  => [ 'shadow' => [ 'preset' => 'shadow-md' ] ],
			'states' => [ 'style.shadow' => [ 'hover' => [ 'preset' => 'shadow-md' ] ] ],
		] );

		expect( $slugs['shadows'] )->toBe( [ 'shadow-md' ] );
	} );

	it( 'ignores raw CSS gradient values — they cannot become stale', function (): void {
		$slugs = BoxShadowResolver::referencedSlugs( [
			'style' => [ 'shadow' => [ 'gradient' => 'linear-gradient(#f00, #00f)' ] ],
		] );

		expect( $slugs['gradients'] )->toBe( [] );
	} );
} );
