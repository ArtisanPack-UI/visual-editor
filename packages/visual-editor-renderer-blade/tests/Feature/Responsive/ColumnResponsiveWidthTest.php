<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Services\ResponsiveCssAccumulator;
use Illuminate\Support\Facades\Blade;

beforeEach( function (): void {
	app( ResponsiveCssAccumulator::class )->reset();
} );

it( 'emits a flex-basis @media rule (not width) with gap-aware calc for responsive column width', function (): void {
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
	expect( $rendered )->toContain( 'flex-basis:calc(25% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * 0.75)!important' );
	expect( $rendered )->toContain( 'flex-grow:0!important' );
	expect( $rendered )->not->toContain( 'width:25%' );
} );

it( 'promotes legacy width attribute into a gap-aware base !important rule', function (): void {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [ 'width' => '50%' ],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

	// Legacy `width` attribute keeps producing the bare inline style
	// (for backward compat) AND a base rule with `!important` that
	// applies from WP core's stacking breakpoint up (#910). The base rule
	// subtracts the parent's block-gap so two 50% columns fit on one
	// row when the theme sets `gap` on `.wp-block-columns`.
	expect( $rendered )->toContain( 'style="flex-basis: 50%;"' );
	expect( $rendered )->toContain( '<style data-ve-responsive>' );
	// 50% width → factor (100-50)/100 = 0.5
	expect( $rendered )->toMatch( '/\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\{flex-basis:calc\(50% - var\(--ap-columns-gap, var\(--wp--style--block-gap, 0\.5em\)\) \* 0\.5\)!important;flex-grow:0!important\}/' );
} );

it( 'lets Stack on mobile win below 782px, as WordPress does (#910)', function (): void {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [ 'width' => '52.5%' ],
			'innerBlocks' => [],
		],
	];

	$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );
	$scope    = '\\.ve-w-[a-f0-9]+\\.ve-w-[a-f0-9]+\\.ve-w-[a-f0-9]+';

	// The "All sizes" width only applies from the stacking breakpoint up,
	// and everywhere inside a Columns block that doesn't stack on mobile.
	expect( $rendered )->toMatch( '/@media \\(min-width: 782px\\)\\{' . $scope . '\\{flex-basis:calc\\(52\\.5%/' )
		->toMatch( '/\\.wp-block-columns\\.is-not-stacked-on-mobile ' . $scope . '\\{flex-basis:calc\\(52\\.5%/' )
		->not->toMatch( '/\\}' . $scope . '\\{flex-basis/' );
} );

it( 'merges legacy base width with responsive overrides into one rule set', function (): void {
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
	expect( $rendered )->toContain( 'flex-basis:calc(50% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * 0.5)!important' );
	expect( $rendered )->toContain( 'flex-basis:calc(25% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * 0.75)!important' );
	expect( $rendered )->toContain( '@media (min-width:768px)' );
} );

it( 'prefers an explicit responsive.base over the legacy width attribute', function (): void {
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

	expect( $rendered )->toContain( 'flex-basis:calc(60% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * 0.4)!important' );
	expect( $rendered )->not->toContain( 'flex-basis:calc(50%' );
} );

it( 'tripled selector specificity beats WP core stacking rule', function (): void {
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

	expect( $rendered )->toMatch( '/\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\{flex-basis:calc\(50% - var\(--ap-columns-gap, var\(--wp--style--block-gap, 0\.5em\)\) \* 0\.5\)!important;flex-grow:0!important\}/' );
	expect( $rendered )->toMatch( '/\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\{flex-basis:calc\(25% - var\(--ap-columns-gap, var\(--wp--style--block-gap, 0\.5em\)\) \* 0\.75\)!important;flex-grow:0!important\}/' );
} );

it( 'passes string percentage values through with gap-aware calc', function (): void {
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

	expect( $rendered )->toContain( 'flex-basis:calc(33.33% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * 0.6667)!important' );
} );

it( 'emits absolute units (e.g. 200px) without the calc wrapper', function (): void {
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

it( 'emits 100% width without the calc wrapper (factor would be 0)', function (): void {
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

it( 'skips the consolidated style block when no width overrides are present', function (): void {
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

it( 'multiple columns with the same width payload only emit one rule set', function (): void {
	$tree = [
		[
			'clientId'    => 'cols-1',
			'name'        => 'artisanpack/columns',
			'attributes'  => [],
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

/**
 * #720 — a hostile stored width must be dropped before it is spliced into
 * the raw `<style data-ve-responsive>` block. With no surviving rule the
 * `ve-w-<hash>` scope class is not attached to the wrapper either.
 */
it( 'drops a hostile column width so it never reaches the stylesheet', function (): void {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [
				'width' => '10px}body{display:none',
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// The only width is hostile, so no rule survives: no responsive style
	// block, no `ve-w-<hash>` scope class, and the raw payload appears
	// nowhere — not even in the (escaped) inline style attribute.
	expect( $rendered )->not->toContain( '10px}body' );
	expect( $rendered )->not->toContain( 've-w-' );
	expect( $rendered )->not->toContain( '<style data-ve-responsive>' );
} );

it( 'keeps a safe base width while dropping a hostile per-breakpoint override', function (): void {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [
				'width'      => 50,
				'responsive' => [
					'width' => [ 'md' => '25%}html{opacity:0' ],
				],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	// Isolate the responsive style block so the assertions target the
	// column-width emission, not the global stylesheet.
	preg_match( '#<style data-ve-responsive>(.*?)</style>#s', $rendered, $matches );
	$responsive = $matches[1] ?? '';

	// The safe base rule survives; the hostile md override is skipped, so
	// no breakpoint `@media` block (only the base rule's stacking-aware
	// min-width query, #910) and no injected declaration are emitted.
	expect( $responsive )->toContain( 'flex-basis:calc(50% - var(--ap-columns-gap, var(--wp--style--block-gap, 0.5em)) * 0.5)!important' );
	expect( $responsive )->not->toMatch( '/@media (?!\\(min-width: 782px\\))/' );
	expect( $responsive )->not->toContain( 'html{opacity:0' );
} );

describe( 'desktop-first Mobile override (#820)', function (): void {
	it( 'stacks 50/50 columns set to 100% under Mobile only at the mobile width', function (): void {
		$column = static fn ( string $id ): array => [
			'clientId'    => $id,
			'name'        => 'artisanpack/column',
			'attributes'  => [ 'width' => '50%', 'responsive' => [ 'width' => [ 'mobile' => '100%' ] ] ],
			'innerBlocks' => [],
		];

		$tree = [
			[
				'clientId'    => 'cols',
				'name'        => 'artisanpack/columns',
				'attributes'  => [],
				'innerBlocks' => [ $column( 'a' ), $column( 'b' ) ],
			],
		];

		$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

		// Desktop keeps 50%; the 100% rule only applies at 767px and below.
		expect( $rendered )
			->toMatch( '/\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\.ve-w-[a-f0-9]+\{flex-basis:calc\(50%/' )
			->toMatch( '/@media \(max-width:767px\)\{[^}]*\{flex-basis:(calc\()?100%/' )
			->not->toContain( '@media (min-width:640px)' );
	} );

	it( 'emits tablet before mobile regardless of stored order', function (): void {
		$tree = [
			[
				'clientId'    => 'col',
				'name'        => 'artisanpack/column',
				'attributes'  => [ 'responsive' => [ 'width' => [ 'mobile' => '100%', 'tablet' => '75%' ] ] ],
				'innerBlocks' => [],
			],
		];

		$rendered = $this->stripGlobalStyles( Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] ) );

		expect( strpos( $rendered, '@media (max-width:1023px)' ) )
			->toBeLessThan( strpos( $rendered, '@media (max-width:767px)' ) );
	} );
} );

it( 'treats a width that is not a length, like "undefined%", as no width (#925)', function ( string $width ): void {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [ 'width' => $width ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->not->toContain( 'flex-basis' );
	expect( $rendered )->not->toContain( 've-w-' );
	expect( $rendered )->not->toContain( '<style data-ve-responsive>' );
} )->with( [ 'undefined%', 'NaN%', 'auto%' ] );

it( 'skips a per-breakpoint width that is not a length but keeps the base width (#925)', function (): void {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [
				'width'      => '40%',
				'responsive' => [ 'width' => [ 'md' => 'undefined%' ] ],
			],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	preg_match( '#<style data-ve-responsive>(.*?)</style>#s', $rendered, $matches );
	$responsive = $matches[1] ?? '';

	expect( $rendered )->toContain( 'flex-basis: 40%' );
	expect( $responsive )->toContain( 'flex-basis:calc(40%' );
	expect( $responsive )->not->toContain( 'undefined' );
} );

it( 'keeps lengths and CSS functions as widths (#925)', function ( string $width ): void {
	$tree = [
		[
			'clientId'    => 'col-1',
			'name'        => 'artisanpack/column',
			'attributes'  => [ 'width' => $width ],
			'innerBlocks' => [],
		],
	];

	$rendered = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $rendered )->toContain( 'flex-basis: ' . $width );
} )->with( [ '33.33%', '250px', '12.5rem', '.5em', 'calc(50% - 1rem)', 'var(--col-width)' ] );
