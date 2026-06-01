<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use ArtisanPackUI\VisualEditorRendererBlade\Support\BlockSupports;

beforeEach( function (): void {
	app( ResponsiveCssAccumulator::class )->reset();
} );

it( 'returns empty rules and class when no responsive overrides exist', function (): void {
	$result = BlockSupports::compileResponsive( [] );

	expect( $result )->toBe( [ 'class' => '', 'rules' => '' ] );
} );

it( 'returns empty rules when the responsive payload is empty', function (): void {
	$result = BlockSupports::compileResponsive( [ 'responsive' => [] ] );

	expect( $result )->toBe( [ 'class' => '', 'rules' => '' ] );
} );

it( 'emits a scoped @media rule for a scalar spacing override', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.padding' => [ 'md' => '2rem' ],
		],
	] );

	expect( $result['class'] )->toStartWith( 've-r-' );
	expect( $result['rules'] )->toContain( '@media (min-width:768px)' );
	expect( $result['rules'] )->toContain( '{padding:2rem!important}' );
	expect( $result['rules'] )->not->toContain( '<style' );
} );

it( 'emits a base rule (no @media) for the base breakpoint', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.padding' => [ 'base' => '1rem' ],
		],
	] );

	$tripled = sprintf( '.%1$s.%1$s.%1$s', $result['class'] );
	expect( $result['rules'] )->toContain( $tripled . '{padding:1rem!important}' );
	expect( $result['rules'] )->not->toContain( '@media' );
} );

it( 'emits per-side declarations for a spacing object', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.padding' => [
				'md' => [
					'top'    => '1rem',
					'bottom' => '2rem',
				],
			],
		],
	] );

	expect( $result['rules'] )->toContain( 'padding-top:1rem!important' );
	expect( $result['rules'] )->toContain( 'padding-bottom:2rem!important' );
	expect( $result['rules'] )->not->toContain( 'padding-left' );
	expect( $result['rules'] )->not->toContain( 'padding-right' );
} );

it( 'maps blockGap to gap and border.radius to border-radius', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.blockGap' => [ 'md' => '8px' ],
			'style.border.radius'    => [ 'lg' => '4px' ],
		],
	] );

	expect( $result['rules'] )->toContain( 'gap:8px!important' );
	expect( $result['rules'] )->toContain( 'border-radius:4px!important' );
	expect( $result['rules'] )->toContain( '@media (min-width:768px)' );
	expect( $result['rules'] )->toContain( '@media (min-width:1024px)' );
} );

it( 'expands a Gutenberg preset reference to a CSS var', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.padding' => [ 'md' => 'var:preset|spacing|40' ],
		],
	] );

	expect( $result['rules'] )->toContain( 'padding:var(--wp--preset--spacing--40)!important' );
} );

it( 'skips overrides for unknown breakpoints (orphans)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.padding' => [ 'legacy' => '4rem' ],
		],
	] );

	expect( $result )->toBe( [ 'class' => '', 'rules' => '' ] );
} );

it( 'skips paths outside the property map', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'columnCount' => [ 'md' => 5 ],
		],
	] );

	// columnCount is handled by the columns partial, not the generic emitter.
	expect( $result )->toBe( [ 'class' => '', 'rules' => '' ] );
} );

it( 'compile() pushes responsive rules into the per-request accumulator', function (): void {
	$accumulator = app( ResponsiveCssAccumulator::class );

	$compiled = BlockSupports::compile( [
		'responsive' => [
			'style.spacing.padding' => [ 'md' => '2rem' ],
		],
	] );

	expect( $compiled['responsiveClass'] )->toStartWith( 've-r-' );
	expect( $compiled['classes'] )->toContain( $compiled['responsiveClass'] );

	$accumulated = $accumulator->all();
	expect( $accumulated )->toHaveKey( $compiled['responsiveClass'] );
	expect( $accumulated[ $compiled['responsiveClass'] ] )->toContain( '{padding:2rem!important}' );
} );

it( 'duplicate pushes for the same scope collapse to one accumulator entry', function (): void {
	$accumulator = app( ResponsiveCssAccumulator::class );

	$attributes = [
		'responsive' => [
			'style.spacing.padding' => [ 'md' => '2rem' ],
		],
	];

	BlockSupports::compile( $attributes );
	BlockSupports::compile( $attributes );
	BlockSupports::compile( $attributes );

	expect( $accumulator->all() )->toHaveCount( 1 );
} );

it( 'wrapperCss() returns an empty string (rules now go through the accumulator)', function (): void {
	expect( BlockSupports::wrapperCss( [
		'responsive' => [
			'style.spacing.padding' => [ 'md' => '2rem' ],
		],
	] ) )->toBe( '' );
} );

it( 'wrapperCss() still pushes into the accumulator for the side effect', function (): void {
	$accumulator = app( ResponsiveCssAccumulator::class );

	BlockSupports::wrapperCss( [
		'responsive' => [
			'style.spacing.margin' => [ 'md' => '0.5rem' ],
		],
	] );

	expect( $accumulator->all() )->not->toBeEmpty();
} );

it( 'wrapperAttrs() includes the responsive class in the class attribute', function (): void {
	$html = BlockSupports::wrapperAttrs(
		[
			'responsive' => [
				'style.spacing.padding' => [ 'md' => '2rem' ],
			],
		],
		[ 'wp-block-columns' ]
	);

	expect( $html )->toMatch( '/class="wp-block-columns ve-r-[a-f0-9]+"/' );
} );

it( 'produces a stable scope class for the same responsive payload', function (): void {
	$payload = [
		'responsive' => [
			'style.spacing.padding' => [ 'md' => '2rem' ],
		],
	];

	$first  = BlockSupports::compileResponsive( $payload );
	$second = BlockSupports::compileResponsive( $payload );

	expect( $first['class'] )->toBe( $second['class'] );
} );
