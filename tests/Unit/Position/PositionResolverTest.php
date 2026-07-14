<?php

/**
 * Unit tests for {@see PositionResolver} (#640).
 *
 * @since 1.4.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Position\PositionResolver;

describe( 'PositionResolver::resolve', function (): void {
	it( 'returns null when no position configuration exists', function (): void {
		expect( PositionResolver::resolve( [] ) )->toBeNull();
		expect( PositionResolver::resolve( [ 'style' => [ 'position' => null ] ] ) )->toBeNull();
	} );

	it( 'widens a legacy sticky string into a structured base layer', function (): void {
		$resolved = PositionResolver::resolve( [ 'style' => [ 'position' => 'sticky' ] ] );

		expect( $resolved )->not->toBeNull();
		expect( $resolved[ 'base' ][ 'value' ] )->toBe( 'sticky' );
	} );

	it( 'resolves offsets + zIndex on the base layer', function (): void {
		$resolved = PositionResolver::resolve( [
			'style' => [
				'position' => [
					'value'   => 'absolute',
					'offsets' => [ 'top' => [ 'value' => 10, 'unit' => 'px' ] ],
					'zIndex'  => 5,
				],
			],
		] );

		expect( $resolved[ 'base' ][ 'value' ] )->toBe( 'absolute' );
		expect( $resolved[ 'base' ][ 'offsets' ][ 'top' ] )->toBe( [ 'value' => 10, 'unit' => 'px' ] );
		expect( $resolved[ 'base' ][ 'zIndex' ] )->toBe( 5 );
	} );

	it( 'reads per-breakpoint overrides from the responsive bag', function (): void {
		$resolved = PositionResolver::resolve( [
			'style' => [ 'position' => [ 'value' => 'relative' ] ],
			'responsive' => [
				'style.position' => [
					'md' => [ 'value' => 'absolute', 'zIndex' => 2 ],
				],
			],
		] );

		expect( $resolved[ 'breakpoints' ][ 'md' ][ 'value' ] )->toBe( 'absolute' );
		expect( $resolved[ 'breakpoints' ][ 'md' ][ 'zIndex' ] )->toBe( 2 );
	} );

	it( 'drops offsets with an invalid unit', function (): void {
		$resolved = PositionResolver::resolve( [
			'style' => [
				'position' => [
					'value'   => 'absolute',
					'offsets' => [
						'top'   => [ 'value' => 10, 'unit' => 'garbage' ],
						'right' => [ 'value' => 5, 'unit' => 'rem' ],
					],
				],
			],
		] );

		expect( $resolved[ 'base' ][ 'offsets' ][ 'top' ] )->toBeNull();
		expect( $resolved[ 'base' ][ 'offsets' ][ 'right' ] )->toBe( [ 'value' => 5, 'unit' => 'rem' ] );
	} );

	it( 'accepts the `auto` unit without a numeric value', function (): void {
		$resolved = PositionResolver::resolve( [
			'style' => [
				'position' => [
					'value'   => 'absolute',
					'offsets' => [ 'top' => [ 'unit' => 'auto' ] ],
				],
			],
		] );

		expect( $resolved[ 'base' ][ 'offsets' ][ 'top' ] )->toBe( [ 'value' => 0, 'unit' => 'auto' ] );
	} );

	it( 'drops responsive entries under the `base` key', function (): void {
		$resolved = PositionResolver::resolve( [
			'responsive' => [
				'style.position' => [
					'base' => [ 'value' => 'fixed' ],
					'lg'   => [ 'value' => 'sticky' ],
				],
			],
		] );

		expect( $resolved[ 'breakpoints' ] )->toHaveKey( 'lg' );
		expect( $resolved[ 'breakpoints' ] )->not->toHaveKey( 'base' );
	} );
} );

describe( 'PositionResolver::normalizeZIndex (via resolve)', function (): void {
	it( 'rejects scientific notation strings to stay JS/PHP consistent', function (): void {
		// PHP `is_numeric` accepts these; `0 + trim($v)` overflows to
		// PHP_INT_MAX. JS `Number.isFinite` rejects `Infinity`. The
		// resolver rejects them explicitly to keep preview and server
		// render byte-identical for the same input.
		$resolved = PositionResolver::resolve( [
			'style' => [ 'position' => [ 'value' => 'absolute', 'zIndex' => '1e100' ] ],
		] );

		expect( $resolved[ 'base' ][ 'zIndex' ] )->toBeNull();
	} );

	it( 'accepts plain integer strings', function (): void {
		$resolved = PositionResolver::resolve( [
			'style' => [ 'position' => [ 'value' => 'absolute', 'zIndex' => '42' ] ],
		] );

		expect( $resolved[ 'base' ][ 'zIndex' ] )->toBe( 42 );
	} );
} );

describe( 'PositionResolver::mergedBreakpointLayers', function (): void {
	it( 'folds each defined breakpoint on top of every smaller layer', function (): void {
		$payload = PositionResolver::resolve( [
			'style' => [
				'position' => [
					'value'   => 'absolute',
					'offsets' => [ 'top' => [ 'value' => 5, 'unit' => 'px' ] ],
					'zIndex'  => 1,
				],
			],
			'responsive' => [
				'style.position' => [
					'md' => [ 'zIndex' => 9 ],
					'lg' => [ 'value' => 'sticky' ],
				],
			],
		] );

		$merged = PositionResolver::mergedBreakpointLayers( $payload, [ 'sm', 'md', 'lg', 'xl', '2xl' ] );

		expect( $merged[ 'md' ][ 'value' ] )->toBe( 'absolute' );
		expect( $merged[ 'md' ][ 'zIndex' ] )->toBe( 9 );
		expect( $merged[ 'md' ][ 'offsets' ][ 'top' ] )->toBe( [ 'value' => 5, 'unit' => 'px' ] );

		expect( $merged[ 'lg' ][ 'value' ] )->toBe( 'sticky' );
		expect( $merged[ 'lg' ][ 'zIndex' ] )->toBe( 9 );
	} );
} );
