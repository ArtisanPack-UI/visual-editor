<?php

/**
 * Tab ids stay unique across Tabs blocks on a page (#1057).
 *
 * @since 1.20.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;
use Illuminate\Support\Facades\Blade;

/**
 * @param  array<int, array{0: string, 1: string}>  $sections  Label and tabId pairs.
 *
 * @return array<string, mixed>
 */
function tabsBlockWith( array $sections ): array
{
	return [
		'clientId'    => 'tabs',
		'name'        => 'artisanpack/tabs',
		'attributes'  => [],
		'innerBlocks' => array_map( static fn ( array $section ): array => [
			'clientId'    => 'section',
			'name'        => 'artisanpack/tab-section',
			'attributes'  => [ 'label' => $section[0], 'tabId' => $section[1] ],
			'innerBlocks' => [],
		], $sections ),
	];
}

/**
 * @return array<int, string>
 */
function tabElementIds( string $html ): array
{
	preg_match_all( '/\bid="(tabs-(?:tab|panel)-[^"]*)"/', $html, $matches );

	return $matches[1];
}

it( 'keeps ids unique across Tabs blocks that share tabId values', function (): void {
	$tabs = tabsBlockWith( [ [ 'Overview', 'overview' ], [ 'Specs', 'specs' ] ] );

	$html = app( BlockRenderer::class )->render( [ $tabs, $tabs ] );
	$ids  = tabElementIds( $html );

	expect( $ids )->toHaveCount( 8 )
		->and( array_unique( $ids ) )->toHaveCount( 8 )
		->and( $html )->toContain( 'id="tabs-panel-overview"' )
		->and( $html )->toContain( 'id="tabs-panel-overview-2"' )
		->and( $html )->toContain( 'href="#tabs-panel-overview-2"' )
		->and( $html )->toContain( 'aria-controls="tabs-panel-specs-2"' )
		->and( $html )->toContain( 'aria-labelledby="tabs-tab-specs-2"' );
} );

it( 'keeps ids unique across Tabs blocks whose sections have no tabId', function (): void {
	$tabs = tabsBlockWith( [ [ 'One', '' ], [ 'Two', '' ] ] );

	$ids = tabElementIds( app( BlockRenderer::class )->render( [ $tabs, $tabs ] ) );

	expect( $ids )->toHaveCount( 8 )
		->and( array_unique( $ids ) )->toHaveCount( 8 )
		->and( $ids )->toContain( 'tabs-panel-tab-1-2' );
} );

it( 'keeps ids unique across separate trees in one request', function (): void {
	$tree = [ tabsBlockWith( [ [ 'Overview', 'overview' ] ] ) ];

	$first  = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
	$second = Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );

	expect( $first )->toContain( 'id="tabs-panel-overview"' )
		->and( $second )->toContain( 'id="tabs-panel-overview-2"' );
} );

it( 'does not skip a generated id that a later block stores', function (): void {
	$ids = tabElementIds( app( BlockRenderer::class )->render( [
		tabsBlockWith( [ [ 'One', 'overview' ] ] ),
		tabsBlockWith( [ [ 'Two', 'overview' ] ] ),
		tabsBlockWith( [ [ 'Three', 'overview-2' ] ] ),
	] ) );

	expect( array_unique( $ids ) )->toHaveCount( 6 )
		->and( $ids )->toContain( 'tabs-panel-overview-2-2' );
} );

it( 'ignores an id stored on a tab section', function (): void {
	$tabs                                                   = tabsBlockWith( [ [ 'One', 'overview' ] ] );
	$tabs['innerBlocks'][0]['attributes']['_resolvedTabId'] = 'forged';

	$html = app( BlockRenderer::class )->render( [ $tabs ] );

	expect( $html )->toContain( 'id="tabs-panel-overview"' )
		->and( $html )->not->toContain( 'forged' );
} );

it( 'keeps ids unique within a Tabs block rendered on its own', function (): void {
	$html = app( BlockRenderer::class )->renderBlock(
		tabsBlockWith( [ [ 'One', 'overview' ], [ 'Two', 'overview' ] ] ),
	);

	expect( $html )->toContain( 'id="tabs-panel-overview"' )
		->and( $html )->toContain( 'id="tabs-panel-overview-2"' );
} );

it( 'keeps ids unique within a Tabs block whose sections already carry ids', function (): void {
	$tabs                                                   = tabsBlockWith( [ [ 'One', '' ], [ 'Two', 'same' ], [ 'Three', '' ] ] );
	$tabs['innerBlocks'][0]['attributes']['_resolvedTabId'] = 'same';
	$tabs['innerBlocks'][2]['attributes']['_resolvedTabId'] = 'same';

	$ids = tabElementIds( app( BlockRenderer::class )->renderBlock( $tabs ) );

	expect( $ids )->toContain( 'tabs-panel-same' )
		->and( $ids )->toContain( 'tabs-panel-same-2' )
		->and( $ids )->toContain( 'tabs-panel-same-3' );
} );

it( 'gives Tabs inside an Accordion their ids despite the second render', function (): void {
	$html = app( BlockRenderer::class )->render( [ [
		'clientId'    => 'accordion',
		'name'        => 'artisanpack/accordion',
		'attributes'  => [ 'panelId' => 'panel' ],
		'innerBlocks' => [ [
			'clientId'    => 'body',
			'name'        => 'artisanpack/accordion-body',
			'attributes'  => [],
			'innerBlocks' => [ tabsBlockWith( [ [ 'Kept', 'kept' ] ] ) ],
		] ],
	] ] );

	expect( tabElementIds( $html ) )->toBe( [ 'tabs-tab-kept', 'tabs-panel-kept' ] );
} );
