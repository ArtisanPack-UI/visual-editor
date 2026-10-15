<?php

/**
 * Issue #700 — per-block layout classes from the Blade renderer.
 *
 * The shipped block-library stylesheet targets the per-block compound
 * (`wp-block-group-is-layout-constrained`,
 * `wp-block-post-template-is-layout-flow`, …) rather than the shared
 * `is-layout-*` modifier alone. These assertions lock in that every
 * layout-supporting partial emits both.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

/**
 * @param  array<string, mixed>  $attributes
 */
function renderLayoutPartial( string $partial, array $attributes = [] ): string
{
	return view( 'visual-editor-renderer-blade::blocks.artisanpack.' . $partial, [
		'attributes'      => $attributes,
		'innerBlocksHtml' => '',
	] )->render();
}

it( 'pairs the group layout class with its per-block compound', function ( string $type, string $class ): void {
	$html = renderLayoutPartial( 'group', [ 'layout' => [ 'type' => $type ] ] );

	expect( $html )
		->toContain( 'is-layout-' . $class )
		->toContain( 'wp-block-group-is-layout-' . $class );
} )->with( [
	'constrained' => [ 'constrained', 'constrained' ],
	'flex'        => [ 'flex', 'flex' ],
	'grid'        => [ 'grid', 'grid' ],
	'unset'       => [ '', 'flow' ],
] );

it( 'keys the row and stack compounds on group, matching their rendered wrapper', function ( string $partial, string $orientation ): void {
	$html = renderLayoutPartial( $partial );

	expect( $html )
		->toContain( 'wp-block-group is-layout-flex wp-block-group-is-layout-flex ' . $orientation )
		->not->toContain( 'wp-block-' . $partial . '-is-layout' );
} )->with( [
	'row'   => [ 'row', 'is-horizontal' ],
	'stack' => [ 'stack', 'is-vertical' ],
] );

it( 'emits the flex layout pair on columns so the flex rules match', function (): void {
	$html = renderLayoutPartial( 'columns' );

	expect( $html )->toContain( 'wp-block-columns is-layout-flex wp-block-columns-is-layout-flex' );
} );

it( 'pairs the buttons flex layout class', function (): void {
	$html = renderLayoutPartial( 'buttons' );

	expect( $html )->toContain( 'wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex' );
} );

it( 'pairs the post-content layout class resolved from its layout attribute', function (): void {
	$html = renderLayoutPartial( 'post-content', [ 'layout' => [ 'type' => 'constrained' ] ] );

	expect( $html )
		->toContain( 'is-layout-constrained' )
		->toContain( 'wp-block-post-content-is-layout-constrained' );
} );

it( 'defaults post-content to the flow pair when no layout is stored', function (): void {
	$html = renderLayoutPartial( 'post-content' );

	expect( $html )
		->toContain( 'is-layout-flow' )
		->toContain( 'wp-block-post-content-is-layout-flow' );
} );

it( 'pairs the post-template layout class without pairing the masonry extension', function ( string $layout, string $class ): void {
	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.post-template', [
		'attributes'      => [ 'layout' => $layout, 'columns' => 3 ],
		'innerBlocksHtml' => '',
	] )->render();

	expect( $html )
		->toContain( 'wp-block-post-template-is-layout-' . $class )
		->not->toContain( 'wp-block-post-template-is-layout-masonry' );
} )->with( [
	'list'    => [ 'list', 'flow' ],
	'grid'    => [ 'grid', 'grid' ],
	'masonry' => [ 'masonry', 'grid' ],
] );

/**
 * Render a block tree through `<x-ve-blocks>` so the per-instance
 * `wp-container-*` rules land in its `<style data-ve-responsive>` block.
 *
 * @param  array<int, array<string, mixed>>  $tree
 */
function renderLayoutTree( array $tree ): string
{
	return Blade::render( '<x-ve-blocks :tree="$tree" />', [ 'tree' => $tree ] );
}

/**
 * @param  array<string, mixed>               $attributes
 * @param  array<int, array<string, mixed>>  $innerBlocks
 *
 * @return array<string, mixed>
 */
function layoutNode( string $name, array $attributes = [], array $innerBlocks = [] ): array
{
	return [
		'clientId'    => 'cid-' . md5( $name . json_encode( $attributes ) . count( $innerBlocks ) ),
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

it( 'gives every layout block its pair and a scope for its own Block spacing (F-3)', function ( string $partial, string $wrapper, string $layoutClass ): void {
	$html = renderLayoutPartial( $partial, [ 'style' => [ 'spacing' => [ 'blockGap' => '1.25rem' ] ] ] );

	expect( $html )
		->toMatch( '/class="[^"]*' . preg_quote( $wrapper, '/' ) . ' ' . $layoutClass . ' wp-block-' . $partial . '-' . $layoutClass . ' wp-container-[0-9a-f]{12}/' );
} )->with( [
	'buttons'             => [ 'buttons', 'wp-block-buttons', 'is-layout-flex' ],
	'column'              => [ 'column', 'wp-block-column', 'is-layout-flow' ],
	'cover inner'         => [ 'cover', 'wp-block-cover__inner-container', 'is-layout-flow' ],
	'navigation'          => [ 'navigation', 'wp-block-navigation', 'is-layout-flex' ],
	'query'               => [ 'query', 'wp-block-query', 'is-layout-flow' ],
	'quote'               => [ 'quote', 'wp-block-quote', 'is-layout-flow' ],
	'details'             => [ 'details', 'wp-block-details', 'is-layout-flow' ],
	'comments-pagination' => [ 'comments-pagination', 'wp-block-comments-pagination', 'is-layout-flex' ],
	'post-content'        => [ 'post-content', 'wp-block-post-content', 'is-layout-flow' ],
	'group'               => [ 'group', 'wp-block-group', 'is-layout-flow' ],
] );

it( 'adds the layout modifier classes WordPress adds (F-3)', function (): void {
	$html = renderLayoutPartial( 'group', [ 'layout' => [ 'type' => 'flex', 'orientation' => 'vertical', 'justifyContent' => 'center', 'flexWrap' => 'nowrap' ] ] );

	expect( $html )->toMatch( '/wp-container-[0-9a-f]{12} is-vertical is-content-justification-center is-nowrap/' );
} );

it( 'keeps the buttons justification class and adds the orientation and wrap modifiers (F-3)', function (): void {
	$html = renderLayoutPartial( 'buttons', [ 'layout' => [ 'orientation' => 'vertical', 'flexWrap' => 'nowrap' ] ] );

	expect( $html )
		->toMatch( '/wp-container-[0-9a-f]{12} is-content-justification-left is-vertical is-nowrap/' )
		->not->toContain( 'is-content-justification-left is-content-justification' );
} );

it( 'flips a column with an active Flex panel to a flex layout, like the group (F-3)', function (): void {
	$html = renderLayoutPartial( 'column', [ 'artisanpackFlex' => [ 'container' => [ 'enabled' => [ 'base' => true ] ] ] ] );

	expect( $html )->toContain( 'wp-block-column is-layout-flex wp-block-column-is-layout-flex' );
} );

it( 'scopes the cover layout to its inner container (F-3)', function (): void {
	$html = renderLayoutPartial( 'cover', [ 'layout' => [ 'type' => 'constrained', 'contentSize' => '600px' ] ] );

	expect( $html )
		->toMatch( '/<div class="wp-block-cover__inner-container is-layout-constrained wp-block-cover-is-layout-constrained wp-container-[0-9a-f]{12}">/' )
		->toContain( 'wp-block-cover is-dark is-layout-flex wp-block-cover-is-layout-flex' );
} );

it( 'scopes the legacy navigation orientation and justification (F-3)', function (): void {
	$legacy = renderLayoutTree( [ layoutNode( 'core/navigation', [ 'overlayMenu' => 'never', 'orientation' => 'vertical', 'itemsJustification' => 'right' ] ) ] );
	$modern = renderLayoutTree( [ layoutNode( 'core/navigation', [ 'overlayMenu' => 'never', 'itemsJustification' => 'center', 'layout' => [ 'justifyContent' => 'left' ] ] ) ] );

	expect( $legacy )->toMatch( '/\.wp-block-navigation\.wp-block-navigation-is-layout-flex\.wp-container-[0-9a-f]{12}\{flex-direction:column;align-items:flex-end;\}/' )
		->and( $modern )->toMatch( '/\.wp-container-[0-9a-f]{12}\{justify-content:flex-start;\}/' )
		->and( $modern )->not->toContain( 'justify-content:center' );
} );

it( 'leaves vertical alignment out of the navigation scope (F-3)', function (): void {
	foreach ( [ [ 'type' => 'flex', 'verticalAlignment' => 'bottom' ], [ 'verticalAlignment' => 'bottom' ] ] as $layout ) {
		$html = renderLayoutPartial( 'navigation', [ 'overlayMenu' => 'never', 'layout' => $layout ] );

		expect( $html )
			->toContain( 'wp-block-navigation is-layout-flex wp-block-navigation-is-layout-flex is-horizontal' )
			->not->toContain( 'wp-container-' );
	}
} );

it( 'leaves columns, gallery, post-template and query pagination to their own layout handling (F-3)', function ( string $partial, array $attributes ): void {
	$html = renderLayoutPartial( $partial, $attributes + [ 'style' => [ 'spacing' => [ 'blockGap' => '1.25rem' ] ] ] );

	expect( $html )->not->toContain( 'wp-container-' );
} )->with( [
	'columns'          => [ 'columns', [] ],
	'gallery'          => [ 'gallery', [] ],
	'post-template'    => [ 'post-template', [ 'layout' => 'grid' ] ],
	'query-pagination' => [ 'query-pagination', [ 'layout' => [ 'type' => 'flex', 'justifyContent' => 'center' ] ] ],
] );

it( 'keeps an outer group Block spacing out of a nested flex container (M-15)', function (): void {
	$html = renderLayoutTree( [
		layoutNode( 'core/group', [ 'style' => [ 'spacing' => [ 'blockGap' => '5rem' ] ] ], [
			layoutNode( 'core/paragraph', [ 'content' => 'Intro' ] ),
			layoutNode( 'core/group', [ 'layout' => [ 'type' => 'flex' ] ], [
				layoutNode( 'core/paragraph', [ 'content' => 'One' ] ),
			] ),
		] ),
	] );

	expect( $html )
		->toMatch( '/\.wp-block-group\.wp-block-group-is-layout-flow\.wp-container-[0-9a-f]{12} > \* \+ \*\{margin-block-start:5rem;margin-block-end:0;\}/' )
		->toContain( '<div class="wp-block-group is-layout-flex wp-block-group-is-layout-flex">' )
		->not->toMatch( '/is-layout-flex\.wp-container-[0-9a-f]{12}\{[^}]*gap/' );
} );
