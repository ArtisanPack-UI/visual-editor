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

it( 'pairs the group layout class with its per-block compound', function ( string $type, string $class ) {
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

it( 'keys the row and stack compounds on group, matching their rendered wrapper', function ( string $partial, string $orientation ) {
	$html = renderLayoutPartial( $partial );

	expect( $html )
		->toContain( 'wp-block-group is-layout-flex wp-block-group-is-layout-flex ' . $orientation )
		->not->toContain( 'wp-block-' . $partial . '-is-layout' );
} )->with( [
	'row'   => [ 'row', 'is-horizontal' ],
	'stack' => [ 'stack', 'is-vertical' ],
] );

it( 'emits the flex layout pair on columns so the flex rules match', function () {
	$html = renderLayoutPartial( 'columns' );

	expect( $html )->toContain( 'wp-block-columns is-layout-flex wp-block-columns-is-layout-flex' );
} );

it( 'pairs the buttons flex layout class', function () {
	$html = renderLayoutPartial( 'buttons' );

	expect( $html )->toContain( 'wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex' );
} );

it( 'pairs the post-content layout class resolved from its layout attribute', function () {
	$html = renderLayoutPartial( 'post-content', [ 'layout' => [ 'type' => 'constrained' ] ] );

	expect( $html )
		->toContain( 'is-layout-constrained' )
		->toContain( 'wp-block-post-content-is-layout-constrained' );
} );

it( 'defaults post-content to the flow pair when no layout is stored', function () {
	$html = renderLayoutPartial( 'post-content' );

	expect( $html )
		->toContain( 'is-layout-flow' )
		->toContain( 'wp-block-post-content-is-layout-flow' );
} );

it( 'pairs the post-template layout class without pairing the masonry extension', function ( string $layout, string $class ) {
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
