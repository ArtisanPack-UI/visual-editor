<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
	app( ResponsiveCssAccumulator::class )->reset();
} );

/**
 * @param  array<string, mixed>  $attributes
 */
function renderResponsiveBlock( string $name, array $attributes ): string
{
	$tree = [
		[
			'clientId'    => 'resp-1',
			'name'        => $name,
			'attributes'  => $attributes,
			'innerBlocks' => [],
		],
	];

	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

it( 'emits a mobile spacer height override (#877)', function (): void {
	$rendered = $this->stripGlobalStyles( renderResponsiveBlock( 'artisanpack/spacer', [
		'height'     => '120px',
		'responsive' => [ 'height' => [ 'mobile' => '40px' ] ],
	] ) );

	expect( $rendered )->toContain( 'style="height: 120px;"' )
		->toContain( '<style data-ve-responsive>' )
		->toContain( '@media (max-width:767px)' )
		->toContain( '{height:40px!important}' )
		->toMatch( '/class="wp-block-spacer ve-r-[a-f0-9]{10}"/' );
} );

it( 'treats a numeric spacer height override as pixels (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/spacer', [
		'responsive' => [ 'height' => [ 'tablet' => 64 ] ],
	] );

	expect( $rendered )->toContain( '{height:64px!important}' );
} );

it( 'expands a spacing preset in a spacer height override (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/spacer', [
		'responsive' => [ 'height' => [ 'mobile' => 'var:preset|spacing|40' ] ],
	] );

	expect( $rendered )->toContain( '{height:var(--wp--preset--spacing--40)!important}' );
} );

it( 'emits a mobile spacer width override through its own scope (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/spacer', [
		'responsive' => [ 'width' => [ 'mobile' => '24px' ] ],
	] );

	expect( $rendered )->toContain( '{width:24px!important}' )
		->toMatch( '/class="wp-block-spacer ve-r-[a-f0-9]{10}"/' );
} );

it( 'emits a mobile cover min-height override in the base unit (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/cover', [
		'minHeight'     => 600,
		'minHeightUnit' => 'px',
		'responsive'    => [ 'minHeight' => [ 'mobile' => 320 ] ],
	] );

	expect( $rendered )->toContain( 'min-height: 600px' )
		->toContain( '@media (max-width:767px)' )
		->toContain( '{min-height:320px!important}' );
} );

it( 'applies a per-breakpoint min-height unit, cascading tablet to mobile (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/cover', [
		'minHeight'     => 600,
		'minHeightUnit' => 'px',
		'responsive'    => [
			'minHeight'     => [ 'tablet' => 80, 'mobile' => 60 ],
			'minHeightUnit' => [ 'tablet' => 'vh' ],
		],
	] );

	expect( $rendered )->toContain( '{min-height:80vh!important}' )
		->toContain( '{min-height:60vh!important}' );
} );

it( 'emits a rule when only the min-height unit changes at a breakpoint (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/cover', [
		'minHeight'     => 50,
		'minHeightUnit' => 'px',
		'responsive'    => [ 'minHeightUnit' => [ 'mobile' => 'vh' ] ],
	] );

	expect( $rendered )->toContain( '{min-height:50vh!important}' );
} );

it( 'falls back to px for an unrecognised min-height unit (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/cover', [
		'responsive' => [
			'minHeight'     => [ 'mobile' => 40 ],
			'minHeightUnit' => [ 'mobile' => 'vh;color:red' ],
		],
	] );

	expect( $rendered )->toContain( '{min-height:40px!important}' )
		->not->toContain( 'color:red' );
} );

it( 'emits font size overrides from a preset slug and a custom size (#877)', function (): void {
	$preset = renderResponsiveBlock( 'artisanpack/heading', [
		'content'    => 'Title',
		'level'      => 2,
		'fontSize'   => 'xx-large',
		'responsive' => [ 'fontSize' => [ 'mobile' => 'large' ] ],
	] );

	expect( $preset )->toContain( '{font-size:var(--wp--preset--font-size--large)!important}' );

	app( ResponsiveCssAccumulator::class )->reset();

	$custom = renderResponsiveBlock( 'artisanpack/heading', [
		'content'    => 'Title',
		'level'      => 2,
		'responsive' => [ 'style.typography.fontSize' => [ 'mobile' => '2.25rem' ] ],
	] );

	expect( $custom )->toContain( '{font-size:2.25rem!important}' );
} );

it( 'emits text and background colour overrides (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/group', [
		'responsive' => [
			'textColor'              => [ 'mobile' => 'contrast' ],
			'style.color.background' => [ 'tablet' => '#0f172a' ],
			'style.color.text'       => [ 'tablet' => '#f8fafc' ],
			'backgroundColor'        => [ 'mobile' => 'accentPink' ],
		],
	] );

	expect( $rendered )->toContain( '{color:var(--wp--preset--color--contrast)!important}' )
		->toContain( '{background-color:#0f172a!important}' )
		->toContain( '{color:#f8fafc!important}' )
		->toContain( '{background-color:var(--wp--preset--color--accent-pink)!important}' );
} );

it( 'emits a dimensions.minHeight override (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/group', [
		'responsive' => [ 'style.dimensions.minHeight' => [ 'mobile' => '50vh' ] ],
	] );

	expect( $rendered )->toContain( '{min-height:50vh!important}' );
} );

it( 'drops hostile values from the new responsive paths (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/group', [
		'responsive' => [
			'textColor'        => [ 'mobile' => 'x)!important}body{display:none' ],
			'style.color.text' => [ 'mobile' => '</style><script>alert(1)</script>' ],
		],
	] );

	expect( $rendered )->not->toContain( '<script>alert' )
		->not->toContain( 'body{display:none' );
} );

it( 'drops responsive values with a comment or unbalanced parentheses (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/group', [
		'responsive' => [
			'style.typography.fontSize'  => [ 'mobile' => '2rem /*' ],
			'style.dimensions.minHeight' => [ 'mobile' => 'calc(50vh' ],
			'style.color.text'           => [ 'mobile' => '#0f172a' ],
		],
	] );

	expect( $rendered )->toContain( '{color:#0f172a!important}' )
		->not->toContain( '/*' )
		->not->toContain( 'calc(50vh' );
} );

it( 'gives covers with the same overrides but different base heights their own scope (#877)', function (): void {
	$tree = [];

	foreach ( [ 50, 80 ] as $index => $height ) {
		$tree[] = [
			'clientId'    => 'cover-' . $index,
			'name'        => 'artisanpack/cover',
			'attributes'  => [
				'minHeight'     => $height,
				'minHeightUnit' => 'px',
				'responsive'    => [ 'minHeightUnit' => [ 'mobile' => 'vh' ] ],
			],
			'innerBlocks' => [],
		];
	}

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( '{min-height:50vh!important}' )
		->toContain( '{min-height:80vh!important}' );
} );

it( 'lets the narrower breakpoint win across a preset and its custom twin (#877)', function ( array $responsive ): void {
	$rendered = renderResponsiveBlock( 'artisanpack/paragraph', [
		'content'    => 'Copy',
		'responsive' => $responsive,
	] );

	$tablet = strpos( $rendered, '{font-size:1.25rem!important}' );
	$mobile = strpos( $rendered, '{font-size:var(--wp--preset--font-size--small)!important}' );

	expect( $tablet )->not->toBeFalse()
		->and( $mobile )->not->toBeFalse()
		->and( $mobile )->toBeGreaterThan( $tablet );
} )->with( [
	'preset stored first' => [ [ 'fontSize' => [ 'mobile' => 'small' ], 'style.typography.fontSize' => [ 'tablet' => '1.25rem' ] ] ],
	'custom stored first' => [ [ 'style.typography.fontSize' => [ 'tablet' => '1.25rem' ], 'fontSize' => [ 'mobile' => 'small' ] ] ],
] );

it( 'emits one font-size per breakpoint when both twins set it, the later-stored winning (#877)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/paragraph', [
		'content'    => 'Copy',
		'responsive' => [
			'style.typography.fontSize' => [ 'mobile' => '1rem' ],
			'fontSize'                  => [ 'mobile' => 'small' ],
		],
	] );

	expect( $rendered )->toContain( '{font-size:var(--wp--preset--font-size--small)!important}' )
		->not->toContain( '{font-size:1rem!important}' );
} );

it( 'never lets an editor tombstone displace its twin at the same breakpoint (#887)', function ( string $path, string $twin, string $value, string $expected ): void {
	$rendered = renderResponsiveBlock( 'artisanpack/paragraph', [
		'content'    => 'Copy',
		'responsive' => [
			$twin => [ 'mobile' => $value ],
			$path => [ 'tablet' => '#123456', 'mobile' => false ],
		],
	] );

	expect( $rendered )->toContain( $expected )
		->and( substr_count( $rendered, '@media (max-width:767px)' ) )->toBe( 1 );
} )->with( [
	'font size'  => [ 'style.typography.fontSize', 'fontSize', 'small', '{font-size:var(--wp--preset--font-size--small)!important}' ],
	'text'       => [ 'style.color.text', 'textColor', 'contrast', '{color:var(--wp--preset--color--contrast)!important}' ],
	'background' => [ 'backgroundColor', 'style.color.background', '#0f172a', '{background-color:#0f172a!important}' ],
] );

it( 'emits nothing for a tombstone with no twin value (#887)', function (): void {
	$rendered = renderResponsiveBlock( 'artisanpack/paragraph', [
		'content'    => 'Copy',
		'responsive' => [
			'style.typography.fontSize' => [ 'mobile' => false ],
			'fontSize'                  => [ 'tablet' => false ],
		],
	] );

	expect( $rendered )->not->toContain( 've-r-' );
} );
