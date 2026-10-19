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

it( 'maps a per-axis blockGap override to row-gap / column-gap, not gap-top (#914)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.blockGap' => [ 'md' => [ 'top' => '1rem', 'left' => '2rem' ] ],
		],
	] );

	expect( $result['rules'] )->toContain( '{row-gap:1rem!important;column-gap:2rem!important}' )
		->not->toContain( 'gap-top' )
		->not->toContain( 'gap-left' )
		->not->toContain( '--ap-columns-gap' );
} );

it( 'keeps --ap-columns-gap in sync with a Columns block\'s responsive gap (#914)', function ( mixed $gap, string $declarations ): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [ 'style.spacing.blockGap' => [ 'tablet' => $gap ] ],
	], null, true );

	expect( $result['rules'] )->toBe( sprintf(
		'@media (max-width:1023px){.%1$s.%1$s.%1$s{%2$s}}',
		$result['class'],
		$declarations,
	) );
} )->with( [
	'scalar'          => [ '2rem', 'gap:2rem!important;--ap-columns-gap:2rem!important' ],
	'per-axis'        => [ [ 'top' => '1rem', 'left' => '3rem' ], 'row-gap:1rem!important;column-gap:3rem!important;--ap-columns-gap:3rem!important' ],
	'preset'          => [ 'var:preset|spacing|40', 'gap:var(--wp--preset--spacing--40)!important;--ap-columns-gap:var(--wp--preset--spacing--40)!important' ],
	'unitless zero'   => [ [ 'left' => '0' ], 'column-gap:0!important;--ap-columns-gap:0px!important' ],
	'normal'          => [ 'Normal', 'gap:Normal!important;--ap-columns-gap:0px!important' ],
	'keyword'         => [ 'inherit', 'gap:inherit!important' ],
	'top-only object' => [ [ 'top' => '1rem' ], 'row-gap:1rem!important' ],
] );

it( 'keeps --ap-columns-gap off a non-Columns block\'s responsive gap (#914)', function (): void {
	$responsive = [ 'responsive' => [ 'style.spacing.blockGap' => [ 'md' => '2rem' ] ] ];
	$group      = BlockSupports::compileResponsive( $responsive );
	$columns    = BlockSupports::compileResponsive( $responsive, null, true );

	expect( $group['rules'] )->toContain( 'gap:2rem!important' )
		->not->toContain( '--ap-columns-gap' );

	// Different rules need different scopes, or the accumulator keeps one.
	expect( $columns['class'] )->not->toBe( $group['class'] );
} );

it( 'leaves a Columns scope unchanged when no gap is overridden (#914)', function (): void {
	$responsive = [ 'responsive' => [ 'style.spacing.padding' => [ 'md' => '2rem' ] ] ];

	expect( BlockSupports::compileResponsive( $responsive, null, true ) )
		->toBe( BlockSupports::compileResponsive( $responsive ) );
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
		[ 'wp-block-columns' ],
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

it( 'never writes a responsive number in exponent notation (R-13)', function ( mixed $value, string $expected ): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [ 'height' => [ 'md' => $value ] ],
	] );

	expect( $result['rules'] )->toContain( '{height:' . $expected . '!important}' )
		->not->toContain( 'E-' )
		->not->toContain( 'E+' );
} )->with( [
	'tiny float'     => [ 1e-8, '0px' ],
	'tiny string'    => [ '0.00001', '0px' ],
	'rounds up'      => [ 0.00009999, '0.0001px' ],
	'decimal'        => [ 12.5, '12.5px' ],
	'precise'        => [ 0.03125, '0.03125px' ],
	'huge'           => [ 1e14, '100000000000000px' ],
	'integer string' => [ '40', '40px' ],
] );

it( 'formats CSS numbers without exponents (R-13)', function (): void {
	expect( BlockSupports::cssNumber( 1e-8 ) )->toBe( '0' )
		->and( BlockSupports::cssNumber( -1e-8 ) )->toBe( '0' )
		->and( BlockSupports::cssNumber( 12.5 ) )->toBe( '12.5' )
		->and( BlockSupports::cssNumber( 1.23456789 ) )->toBe( '1.23456789' )
		->and( BlockSupports::cssNumber( 2e15 ) )->toBe( '2000000000000000' );
} );

it( 'renders a gap axis saved under a legacy deeper key (#918)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.blockGap.left' => [ 'tablet' => '3em' ],
			'style.spacing.blockGap.top'  => [ 'tablet' => '1em', 'mobile' => '0' ],
		],
	], null, true );

	$selector = sprintf( '.%1$s.%1$s.%1$s', $result['class'] );

	expect( $result['rules'] )
		->toContain( '@media (max-width:1023px){' . $selector . '{row-gap:1em!important;column-gap:3em!important;--ap-columns-gap:3em!important}}' )
		->toContain( '@media (max-width:767px){' . $selector . '{row-gap:0!important}}' );
} );

it( 'renders padding sides saved under legacy deeper keys (#918)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.padding.top'   => [ 'tablet' => '2rem' ],
			'style.spacing.margin.bottom' => [ 'mobile' => '1rem' ],
		],
	] );

	expect( $result['rules'] )
		->toContain( '{padding-top:2rem!important}' )
		->toContain( '{margin-bottom:1rem!important}' );
} );

it( 'splits a legacy shallow spacing object into its properties (#918)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing' => [ 'tablet' => [ 'padding' => '2rem', 'blockGap' => [ 'left' => '3em' ], 'other' => 'x' ] ],
		],
	] );

	expect( $result['rules'] )
		->toContain( '{padding:2rem!important}' )
		->toContain( '{column-gap:3em!important}' )
		->not->toContain( 'other' );
} );

it( 'prefers the property path over a legacy key at the same breakpoint (#918)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing.blockGap'      => [ 'tablet' => [ 'top' => '1em' ] ],
			'style.spacing.blockGap.left' => [ 'tablet' => '3em', 'mobile' => '4em' ],
		],
	] );

	expect( $result['rules'] )
		->toContain( '@media (max-width:1023px){' . sprintf( '.%1$s.%1$s.%1$s', $result['class'] ) . '{row-gap:1em!important}}' )
		->toContain( '{column-gap:4em!important}' )
		->not->toContain( 'column-gap:3em' );
} );

it( 'spreads a scalar shallow value under a later legacy side (#918)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.spacing'             => [ 'tablet' => [ 'padding' => '2rem' ] ],
			'style.spacing.padding.top' => [ 'tablet' => '5rem' ],
		],
	] );

	expect( $result['rules'] )->toContain( '{padding-top:5rem!important;padding-right:2rem!important;padding-bottom:2rem!important;padding-left:2rem!important}' );
} );

it( 'emits per-corner radius longhands (#918)', function (): void {
	$result = BlockSupports::compileResponsive( [
		'responsive' => [
			'style.border.radius.topLeft' => [ 'tablet' => '8px' ],
			'style.border.radius'         => [ 'mobile' => [ 'bottomRight' => '2px' ] ],
		],
	] );

	expect( $result['rules'] )
		->toContain( '{border-top-left-radius:8px!important}' )
		->toContain( '{border-bottom-right-radius:2px!important}' );
} );

it( 'keeps the scope class of a payload that only uses property paths (#918)', function (): void {
	$payload = [ 'style.spacing.padding' => [ 'md' => '2rem' ] ];

	expect( BlockSupports::compileResponsive( [ 'responsive' => $payload ] )['class'] )->toBe( 've-r-e22d713cfb' );
} );
