<?php

/**
 * `core/navigation` collapse breakpoint (#977).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @since      1.15.2
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Responsive\BreakpointRegistry;
use ArtisanPackUI\VisualEditorRendererBlade\Services\NavigationOverlayTracker;
use Illuminate\Support\Facades\Blade;

/**
 * Build a `core/navigation` block with one link.
 *
 * @since 1.15.2
 *
 * @param  array<string, mixed>  $attributes
 *
 * @return array<string, mixed>
 */
function collapseNavBlock( string $clientId, array $attributes = [] ): array
{
	return [
		'clientId'    => $clientId,
		'name'        => 'core/navigation',
		'attributes'  => $attributes,
		'innerBlocks' => [
			[
				'clientId'    => $clientId . '-l1',
				'name'        => 'core/navigation-link',
				'attributes'  => [ 'label' => 'Home', 'url' => '/' ],
				'innerBlocks' => [],
			],
		],
	];
}

/**
 * Render a block tree through `<x-ve-blocks>`.
 *
 * @since 1.15.2
 *
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderCollapseTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

beforeEach( function (): void {
	app( NavigationOverlayTracker::class )->reset();
} );

it( 'leaves the default nav output unchanged without a collapse breakpoint', function ( array $attributes ): void {
	$rendered = renderCollapseTree( [ collapseNavBlock( 'nav-1', $attributes ) ] );

	preg_match( '/<nav[^>]*>/', $rendered, $navTag );

	expect( $navTag[0] )->toBe( '<nav class="wp-block-navigation is-layout-flex wp-block-navigation-is-layout-flex is-horizontal is-responsive">' )
		->and( $rendered )->not->toContain( 'collapses-at-' )
		->and( $rendered )->not->toContain( 'data-ve-navigation-collapse' );
} )->with( [
	'absent'  => [ [] ],
	'empty'   => [ [ 'collapseBreakpoint' => '' ] ],
	'unknown' => [ [ 'collapseBreakpoint' => 'desktop' ] ],
	'array'   => [ [ 'collapseBreakpoint' => [ 'tablet' ] ] ],
] );

it( 'collapses a tablet nav up to the registry tablet max-width and shows it inline above', function (): void {
	$rendered = renderCollapseTree( [ collapseNavBlock( 'nav-1', [ 'collapseBreakpoint' => 'tablet' ] ) ] );

	$container = '.wp-block-navigation.collapses-at-tablet .wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open)';
	$open      = '.wp-block-navigation.collapses-at-tablet .wp-block-navigation__responsive-container-open:not(.always-shown)';

	expect( $rendered )
		->toContain( 'is-responsive collapses-at-tablet">' )
		->toContain( '<style data-ve-navigation-collapse="tablet">' )
		->toContain( "@media (max-width: 1023px) {{$container} { display: none; }{$open} { display: flex; }}" )
		->toContain( "@media (min-width: 1024px) {{$container} { display: block; width: 100%; position: relative; z-index: auto; background-color: inherit; }" )
		->toContain( "{$container} .wp-block-navigation__responsive-container-close { display: none; }{$open} { display: none; }}" );
} );

it( 'collapses a mobile nav at the registry mobile max-width', function (): void {
	$rendered = renderCollapseTree( [ collapseNavBlock( 'nav-1', [ 'collapseBreakpoint' => 'mobile' ] ) ] );

	expect( $rendered )
		->toContain( 'collapses-at-mobile' )
		->toContain( '@media (max-width: 767px) {' )
		->toContain( '@media (min-width: 768px) {' );
} );

it( 'emits the rules once per breakpoint per response', function (): void {
	$rendered = renderCollapseTree( [
		collapseNavBlock( 'nav-1', [ 'collapseBreakpoint' => 'tablet' ] ),
		collapseNavBlock( 'nav-2', [ 'collapseBreakpoint' => 'tablet' ] ),
		collapseNavBlock( 'nav-3', [ 'collapseBreakpoint' => 'mobile' ] ),
	] );

	expect( substr_count( $rendered, 'collapses-at-tablet"' ) )->toBe( 2 )
		->and( substr_count( $rendered, '<style data-ve-navigation-collapse="tablet">' ) )->toBe( 1 )
		->and( substr_count( $rendered, '<style data-ve-navigation-collapse="mobile">' ) )->toBe( 1 )
		->and( substr_count( $rendered, '@media (max-width: 1023px)' ) )->toBe( 1 );
} );

it( 'reads the max-width from the breakpoint registry', function (): void {
	app()->instance(
		BreakpointRegistry::class,
		BreakpointRegistry::fromLayers( [ 'tablet' => [ 'maxWidthPx' => 899, 'previewWidthPx' => 800 ] ] ),
	);

	$rendered = renderCollapseTree( [ collapseNavBlock( 'nav-1', [ 'collapseBreakpoint' => 'tablet' ] ) ] );

	expect( $rendered )
		->toContain( '@media (max-width: 899px) {' )
		->toContain( '@media (min-width: 900px) {' )
		->not->toContain( '1023px' );
} );

it( 'falls back to the default when the registry has no entry for the key', function (): void {
	app()->instance( BreakpointRegistry::class, BreakpointRegistry::fromLayers( [ 'tablet' => null ] ) );

	$rendered = renderCollapseTree( [ collapseNavBlock( 'nav-1', [ 'collapseBreakpoint' => 'tablet' ] ) ] );

	expect( $rendered )
		->not->toContain( 'collapses-at-' )
		->not->toContain( 'data-ve-navigation-collapse' );
} );

it( 'ignores the collapse breakpoint unless the overlay is mobile', function ( string $overlayMenu ): void {
	$rendered = renderCollapseTree( [
		collapseNavBlock( 'nav-1', [ 'collapseBreakpoint' => 'tablet', 'overlayMenu' => $overlayMenu ] ),
	] );

	expect( $rendered )
		->not->toContain( 'collapses-at-' )
		->not->toContain( 'data-ve-navigation-collapse' );
} )->with( [ 'always', 'never' ] );

it( 'keeps the overlay toggle script and its open/close wiring', function (): void {
	$rendered = renderCollapseTree( [ collapseNavBlock( 'nav-1', [ 'collapseBreakpoint' => 'tablet' ] ) ] );

	expect( $rendered )
		->toContain( 'data-ap-nav-overlay-open="ap-modal-nav-1"' )
		->toContain( 'data-ap-nav-overlay-close' )
		->toContain( "function open(c){c.classList.add('is-menu-open');" )
		->toContain( "function close(c,keepFocus){c.classList.remove('is-menu-open');" )
		->and( substr_count( $rendered, 'window.__apNavOverlayInit' ) )->toBe( 2 );
} );

it( 'clears the emitted breakpoints on tracker reset', function (): void {
	$tracker = new NavigationOverlayTracker();

	$tracker->markCollapseCssEmitted( 'tablet' );

	expect( $tracker->hasEmittedCollapseCss( 'tablet' ) )->toBeTrue()
		->and( $tracker->hasEmittedCollapseCss( 'mobile' ) )->toBeFalse();

	$tracker->reset();

	expect( $tracker->hasEmittedCollapseCss( 'tablet' ) )->toBeFalse();
} );
