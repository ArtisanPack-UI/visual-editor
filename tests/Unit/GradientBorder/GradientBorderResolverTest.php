<?php

/**
 * Unit tests for {@see GradientBorderResolver} (#490).
 *
 * Covers the three cascade sources the resolver reads from
 * (`style.border.gradient`, `attributes.states['style.border.gradient']`,
 * `attributes.responsive['style.border.gradient']`), slug-vs-raw value
 * expansion, the shorthand path key, and the slug-collection helper
 * the editor's token-warning surface uses.
 *
 * @since 1.1.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\GradientBorder\GradientBorderResolver;

describe( 'GradientBorderResolver::resolve', function (): void {
	it( 'returns null when no gradient configuration exists anywhere', function (): void {
		expect( GradientBorderResolver::resolve( [] ) )->toBeNull();

		expect( GradientBorderResolver::resolve( [
			'style' => [ 'border' => [ 'width' => '2px', 'color' => '#000' ] ],
		] ) )->toBeNull();
	} );

	it( 'expands a slug into var(--wp--preset--gradient--{slug})', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'style' => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
		] );

		expect( $resolved )->not->toBeNull();
		expect( $resolved['idle'] )->toBe( 'var(--wp--preset--gradient--primary-glow)' );
	} );

	it( 'passes raw CSS gradient values through unchanged', function (): void {
		$raw      = 'linear-gradient(135deg, #ff0000, #0000ff)';
		$resolved = GradientBorderResolver::resolve( [
			'style' => [ 'border' => [ 'gradient' => $raw ] ],
		] );

		expect( $resolved['idle'] )->toBe( $raw );
	} );

	it( 'collects per-state overrides from attributes.states canonical path', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'states' => [
				'style.border.gradient' => [
					'hover' => 'primary-glow-bright',
					'focus' => 'linear-gradient(180deg, red, blue)',
				],
			],
		] );

		expect( $resolved )->not->toBeNull();
		expect( $resolved['states'] )->toBe( [
			'hover' => 'var(--wp--preset--gradient--primary-glow-bright)',
			'focus' => 'linear-gradient(180deg, red, blue)',
		] );
	} );

	it( 'accepts the shorthand `border.gradient` path key for state overrides', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'states' => [
				'border.gradient' => [
					'hover' => 'primary-glow-bright',
				],
			],
		] );

		expect( $resolved['states'] )->toBe( [
			'hover' => 'var(--wp--preset--gradient--primary-glow-bright)',
		] );
	} );

	it( 'lets the canonical state path overwrite the shorthand when both exist', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'states' => [
				'border.gradient'       => [ 'hover' => 'shorthand-loses' ],
				'style.border.gradient' => [ 'hover' => 'canonical-wins' ],
			],
		] );

		expect( $resolved['states']['hover'] )->toBe( 'var(--wp--preset--gradient--canonical-wins)' );
	} );

	it( 'collects per-breakpoint overrides', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'responsive' => [
				'style.border.gradient' => [
					'md' => 'primary-vertical',
					'lg' => 'linear-gradient(45deg, #000, #fff)',
				],
			],
		] );

		expect( $resolved['breakpoints'] )->toBe( [
			'md' => 'var(--wp--preset--gradient--primary-vertical)',
			'lg' => 'linear-gradient(45deg, #000, #fff)',
		] );
	} );

	it( 'drops null and empty-string overrides', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'states' => [
				'style.border.gradient' => [
					'hover'  => null,
					'focus'  => '',
					'active' => 'primary-glow',
				],
			],
		] );

		expect( $resolved['states'] )->toBe( [
			'active' => 'var(--wp--preset--gradient--primary-glow)',
		] );
	} );

	it( 'preserves border width and radius alongside the gradient', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'style' => [
				'border' => [
					'gradient' => 'primary-glow',
					'width'    => '4px',
					'radius'   => '12px',
				],
			],
		] );

		expect( $resolved['width'] )->toBe( '4px' );
		expect( $resolved['radius'] )->toBe( '12px' );
	} );

	it( 'preserves a per-corner radius object', function (): void {
		$resolved = GradientBorderResolver::resolve( [
			'style' => [
				'border' => [
					'gradient' => 'primary-glow',
					'radius'   => [ 'topLeft' => '4px', 'bottomRight' => '24px' ],
				],
			],
		] );

		expect( $resolved['radius'] )->toBe( [ 'topLeft' => '4px', 'bottomRight' => '24px' ] );
	} );
} );

describe( 'GradientBorderResolver::referencedSlugs', function (): void {
	it( 'returns an empty list when no slugs are referenced', function (): void {
		expect( GradientBorderResolver::referencedSlugs( [] ) )->toBe( [] );

		expect( GradientBorderResolver::referencedSlugs( [
			'style' => [ 'border' => [ 'gradient' => 'linear-gradient(red, blue)' ] ],
		] ) )->toBe( [] );
	} );

	it( 'pulls slugs from idle + state + responsive bags', function (): void {
		$slugs = GradientBorderResolver::referencedSlugs( [
			'style'      => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
			'states'     => [
				'style.border.gradient' => [ 'hover' => 'primary-glow-bright' ],
			],
			'responsive' => [
				'style.border.gradient' => [ 'md' => 'vertical' ],
			],
		] );

		sort( $slugs );

		expect( $slugs )->toBe( [ 'primary-glow', 'primary-glow-bright', 'vertical' ] );
	} );

	it( 'deduplicates slugs that appear in multiple cascade slots', function (): void {
		$slugs = GradientBorderResolver::referencedSlugs( [
			'style'      => [ 'border' => [ 'gradient' => 'primary-glow' ] ],
			'states'     => [
				'style.border.gradient' => [ 'hover' => 'primary-glow' ],
			],
			'responsive' => [
				'style.border.gradient' => [ 'md' => 'primary-glow' ],
			],
		] );

		expect( $slugs )->toBe( [ 'primary-glow' ] );
	} );

	it( 'ignores raw CSS values — they cannot become stale', function (): void {
		$slugs = GradientBorderResolver::referencedSlugs( [
			'style' => [ 'border' => [ 'gradient' => 'radial-gradient(circle, #f00, #00f)' ] ],
		] );

		expect( $slugs )->toBe( [] );
	} );
} );
