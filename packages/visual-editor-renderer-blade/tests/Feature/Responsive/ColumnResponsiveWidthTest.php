<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
	app( ResponsiveCssAccumulator::class )->reset();
} );

it( 'emits a flex-basis @media rule (not width) with gap-aware calc for responsive column width', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [
				'responsive' => [
					'width' => [ 'md' => 25 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( '<style data-ve-responsive>' );
	expect( $rendered )->toContain( '@media (min-width:768px)' );
	// 25% width → factor (100-25)/100 = 0.75
	expect( $rendered )->toContain( 'flex-basis:calc(25% - var(--wp--style--block-gap, 0.5em) * 0.75)!important' );
	expect( $rendered )->toContain( 'flex-grow:0!important' );
	expect( $rendered )->not->toContain( 'width:25%' );
} );

it( 'promotes legacy width attribute into a gap-aware base !important rule', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [ 'width' => '50%' ],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	// Legacy `width` attribute keeps producing the bare inline style
	// (for backward compat) AND a base rule with `!important` so it
	// beats WP core's stacking rule at <782px. The base rule
	// subtracts the parent's block-gap so two 50% columns fit on one
	// row when the theme sets `gap` on `.wp-block-columns`.
	expect( $rendered )->toContain( 'style="flex-basis: 50%;"' );
	expect( $rendered )->toContain( '<style data-ve-responsive>' );
	// 50% width → factor (100-50)/100 = 0.5
	expect( $rendered )->toMatch( '/\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\{flex-basis:calc\(50% - var\(--wp--style--block-gap, 0\.5em\) \* 0\.5\)!important;flex-grow:0!important\}/' );
} );

it( 'merges legacy base width with responsive overrides into one rule set', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [
				'width'      => '50%',
				'responsive' => [
					'width' => [ 'md' => 25 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	// Base rule (no @media) for the legacy 50% AND a @media rule for
	// the md 25% override — both with the gap-aware calc.
	expect( $rendered )->toContain( 'flex-basis:calc(50% - var(--wp--style--block-gap, 0.5em) * 0.5)!important' );
	expect( $rendered )->toContain( 'flex-basis:calc(25% - var(--wp--style--block-gap, 0.5em) * 0.75)!important' );
	expect( $rendered )->toContain( '@media (min-width:768px)' );
} );

it( 'prefers an explicit responsive.base over the legacy width attribute', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [
				'width'      => '50%',
				'responsive' => [
					'width' => [ 'base' => '60%' ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( 'flex-basis:calc(60% - var(--wp--style--block-gap, 0.5em) * 0.4)!important' );
	expect( $rendered )->not->toContain( 'flex-basis:calc(50%' );
} );

it( 'tripled selector specificity beats WP core stacking rule', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [
				'responsive' => [
					'width' => [ 'sm' => 50, 'md' => 25 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toMatch( '/\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\{flex-basis:calc\(50% - var\(--wp--style--block-gap, 0\.5em\) \* 0\.5\)!important;flex-grow:0!important\}/' );
	expect( $rendered )->toMatch( '/\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\{flex-basis:calc\(25% - var\(--wp--style--block-gap, 0\.5em\) \* 0\.75\)!important;flex-grow:0!important\}/' );
} );

it( 'passes string percentage values through with gap-aware calc', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [
				'responsive' => [
					'width' => [ 'md' => '33.33%' ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( 'flex-basis:calc(33.33% - var(--wp--style--block-gap, 0.5em) * 0.6667)!important' );
} );

it( 'emits absolute units (e.g. 200px) without the calc wrapper', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [
				'responsive' => [
					'width' => [ 'md' => '200px' ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( 'flex-basis:200px!important' );
	expect( $rendered )->not->toContain( 'calc(200px' );
} );

it( 'emits 100% width without the calc wrapper (factor would be 0)', function () {
	$tree = [
		[
			'clientId'   => 'col-1',
			'name'       => 'artisanpack/column',
			'attributes' => [
				'responsive' => [
					'width' => [ 'md' => 100 ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->toContain( 'flex-basis:100%!important' );
	expect( $rendered )->not->toContain( 'calc(100%' );
} );

it( 'skips the consolidated style block when no width overrides are present', function () {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	expect( $rendered )->not->toContain( '<style data-ve-responsive>' );
} );

it( 'multiple columns with the same width payload only emit one rule set', function () {
	$tree = [
		[
			'clientId'   => 'cols-1',
			'name'       => 'artisanpack/columns',
			'attributes' => [],
			'innerBlocks' => [
				[
					'clientId'   => 'col-1',
					'name'       => 'artisanpack/column',
					'attributes' => [
						'responsive' => [ 'width' => [ 'md' => 50 ] ],
					],
					'innerBlocks' => [],
				],
				[
					'clientId'   => 'col-2',
					'name'       => 'artisanpack/column',
					'attributes' => [
						'responsive' => [ 'width' => [ 'md' => 50 ] ],
					],
					'innerBlocks' => [],
				],
			],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	// Both columns share the same payload → one scope class → one
	// rule set in the consolidated style block.
	expect( substr_count( $rendered, 'flex-basis:calc(50%' ) )->toBe( 1 );
} );
