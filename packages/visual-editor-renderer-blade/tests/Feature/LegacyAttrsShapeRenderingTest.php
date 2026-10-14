<?php

/**
 * Legacy `attrs`-shape block trees rendered through `<x-ve-blocks>` (#863).
 *
 * `parse_blocks()`-style trees carry their attribute bag under `attrs`
 * instead of `attributes`. The component's resolver pipeline used to
 * stamp an empty `attributes` key on every block, shadowing the real
 * bag, so every block rendered without its attributes.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.13.1
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BreadcrumbsResolver;
use ArtisanPackUI\VisualEditorRendererBlade\Resolvers\BusinessInfoResolver;
use Illuminate\Support\Facades\Blade;

/**
 * Build a legacy `attrs`-shape block.
 *
 * @param  array<string, mixed>              $attrs
 * @param  array<int, array<string, mixed>>  $innerBlocks
 *
 * @return array<string, mixed>
 */
function legacyAttrsBlock( string $name, array $attrs = [], array $innerBlocks = [] ): array
{
	return [
		'name'        => $name,
		'attrs'       => $attrs,
		'innerBlocks' => $innerBlocks,
	];
}

/**
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderLegacyAttrsTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

beforeEach( function (): void {
	if ( function_exists( 'removeAllFilters' ) ) {
		removeAllFilters( 'ap.visualEditor.businessInfo' );
		removeAllFilters( 'ap.visualEditor.breadcrumbs.trail' );
	}
} );

it( 'renders an attrs-shape heading through x-ve-blocks with its attributes intact', function (): void {
	$tree = [
		legacyAttrsBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static heading' ] ),
	];

	$html = renderLegacyAttrsTree( $tree );

	expect( $html )->toContain( 'Static heading</h2>' )
		->and( $html )->toContain( 'class="wp-block-heading"' );
} );

it( 'renders an attrs-shape tree the same as the canonical attributes shape', function (): void {
	$legacy = [
		legacyAttrsBlock( 'core/group', [], [
			legacyAttrsBlock( 'core/heading', [ 'level' => 3, 'content' => 'Nested heading' ] ),
			legacyAttrsBlock( 'core/paragraph', [ 'content' => 'Nested paragraph' ] ),
		] ),
	];

	$canonical = [
		[
			'name'        => 'core/group',
			'attributes'  => [],
			'innerBlocks' => [
				[ 'name' => 'core/heading', 'attributes' => [ 'level' => 3, 'content' => 'Nested heading' ], 'innerBlocks' => [] ],
				[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'Nested paragraph' ], 'innerBlocks' => [] ],
			],
		],
	];

	$legacyHtml = $this->normalizeHtml( $this->stripGlobalStyles( renderLegacyAttrsTree( $legacy ) ) );

	expect( $legacyHtml )
		->toBe( $this->normalizeHtml( $this->stripGlobalStyles( renderLegacyAttrsTree( $canonical ) ) ) )
		->toContain( 'Nested heading' )
		->toContain( 'Nested paragraph' );
} );

it( 'matches a direct BlockRenderer render of the same attrs-shape tree', function (): void {
	$tree = [
		legacyAttrsBlock( 'core/paragraph', [ 'content' => 'Direct render parity' ] ),
	];

	$direct = app( BlockRenderer::class )->render( $tree );

	expect( $this->normalizeHtml( $this->stripGlobalStyles( renderLegacyAttrsTree( $tree ) ) ) )
		->toBe( $this->normalizeHtml( $direct ) );
} );

it( 'stamps TOC anchors and items for attrs-shape headings', function (): void {
	$tree = [
		legacyAttrsBlock( 'artisanpack/toc' ),
		legacyAttrsBlock( 'core/heading', [ 'level' => 2, 'content' => 'Getting Started' ] ),
		legacyAttrsBlock( 'core/heading', [ 'level' => 3, 'content' => 'Install', 'anchor' => 'setup' ] ),
	];

	$html = renderLegacyAttrsTree( $tree );

	expect( $html )->toContain( 'id="getting-started"' )
		->and( $html )->toContain( 'href="#getting-started">Getting Started</a>' )
		->and( $html )->toContain( 'id="setup"' )
		->and( $html )->toContain( 'href="#setup">Install</a>' );
} );

it( 'renders an attrs-shape breadcrumbs block with a resolved trail', function (): void {
	$html = renderLegacyAttrsTree( [
		legacyAttrsBlock( 'artisanpack/breadcrumbs' ),
	] );

	expect( $html )->toContain( '<ol' )
		->and( $html )->toContain( '>Home<' )
		->and( $html )->toContain( 'aria-current="page"' );
} );

it( 'renders an attrs-shape business-info block from the filter envelope', function (): void {
	if ( ! function_exists( 'addFilter' ) ) {
		$this->markTestSkipped( 'artisanpack-ui/hooks is not installed.' );
	}

	addFilter( 'ap.visualEditor.businessInfo', fn ( array $env ): array => array_merge( $env, [ 'phone' => '+1 (555) 123-4567' ] ), 10 );

	$html = renderLegacyAttrsTree( [
		legacyAttrsBlock( 'artisanpack/business-phone' ),
	] );

	expect( $html )->toContain( 'href="tel:+15551234567"' );
} );

it( 'leaves non-target blocks untouched in both stamp resolvers', function ( string $resolverClass ): void {
	$block = legacyAttrsBlock( 'core/heading', [ 'level' => 2, 'content' => 'Untouched' ] );

	$stamped = app( $resolverClass )->stampTree( [ $block ] );

	expect( $stamped[0] )->toBe( $block )
		->and( $stamped[0] )->not->toHaveKey( 'attributes' );
} )->with( [
	'breadcrumbs'   => BreadcrumbsResolver::class,
	'business info' => BusinessInfoResolver::class,
] );

it( 'stamps target blocks into whichever attribute bag they carry', function ( string $resolverClass, string $name, string $stampKey ): void {
	$legacy    = app( $resolverClass )->stampTree( [ legacyAttrsBlock( $name, [ 'keep' => 'me' ] ) ] );
	$canonical = app( $resolverClass )->stampTree( [
		[ 'name' => $name, 'attributes' => [ 'keep' => 'me' ], 'innerBlocks' => [] ],
	] );

	expect( $legacy[0] )->not->toHaveKey( 'attributes' )
		->and( $legacy[0]['attrs']['keep'] )->toBe( 'me' )
		->and( $legacy[0]['attrs'] )->toHaveKey( $stampKey )
		->and( $canonical[0] )->not->toHaveKey( 'attrs' )
		->and( $canonical[0]['attributes']['keep'] )->toBe( 'me' )
		->and( $canonical[0]['attributes'] )->toHaveKey( $stampKey );
} )->with( [
	'breadcrumbs'   => [ BreadcrumbsResolver::class, 'artisanpack/breadcrumbs', '_resolvedTrail' ],
	'business info' => [ BusinessInfoResolver::class, 'artisanpack/business-phone', '_resolvedBusinessInfo' ],
] );
