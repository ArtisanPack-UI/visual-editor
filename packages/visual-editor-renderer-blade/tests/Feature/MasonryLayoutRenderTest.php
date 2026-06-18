<?php

/**
 * Issue #593 — masonry layout output from the Blade renderer.
 *
 * Asserts the post-template and grid partials emit the right wrapper
 * classes + `data-ap-cols` attribute when the masonry layout option is
 * active, and that the `<x-ve-blocks-styles />` component wires up the
 * shared masonry CSS + fallback script.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 */

declare( strict_types=1 );

use Illuminate\Support\Facades\Blade;

it( 'renders the post-template wrapper with is-layout-masonry when layout is masonry', function () {
	$attrs = [ 'layout' => 'masonry', 'columns' => 4 ];

	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.post-template', [
		'attributes'      => $attrs,
		'innerBlocksHtml' => '<li>x</li>',
	] )->render();

	expect( $html )
		->toContain( 'is-layout-masonry' )
		// Masonry layers is-layout-grid underneath so Gutenberg's
		// bundled layout baseline provides `display: grid` inside the
		// editor canvas iframe and the post-template grid CSS sets the
		// per-breakpoint columns-N tracks.
		->toContain( 'is-layout-grid' )
		->toContain( 'columns-4' )
		->toContain( 'data-ap-cols="4"' )
		->not->toContain( 'is-layout-flow' );
} );

it( 'keeps the existing is-layout-grid path for layout: grid', function () {
	$attrs = [ 'layout' => 'grid', 'columns' => 3 ];

	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.post-template', [
		'attributes'      => $attrs,
		'innerBlocksHtml' => '<li>x</li>',
	] )->render();

	expect( $html )
		->toContain( 'is-layout-grid' )
		->toContain( 'columns-3' )
		->not->toContain( 'is-layout-masonry' )
		->not->toContain( 'data-ap-cols' );
} );

it( 'defaults the post-template wrapper to is-layout-flow without columns-N', function () {
	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.post-template', [
		'attributes'      => [],
		'innerBlocksHtml' => '<li>x</li>',
	] )->render();

	expect( $html )
		->toContain( 'is-layout-flow' )
		->not->toContain( 'is-layout-masonry' )
		->not->toContain( 'is-layout-grid' )
		->not->toContain( 'data-ap-cols' );
} );

it( 'falls back to list when the layout value is not in the enum', function () {
	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.post-template', [
		'attributes'      => [ 'layout' => 'evil"><script>' ],
		'innerBlocksHtml' => '<li>x</li>',
	] )->render();

	expect( $html )
		->toContain( 'is-layout-flow' )
		->not->toContain( 'is-layout-masonry' )
		->not->toContain( '<script>' );
} );

it( 'emits the masonry layout-mode class + data-ap-cols on the grid wrapper when layoutMode is masonry', function () {
	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.grid', [
		'attributes' => [
			'numColumns' => 3,
			'layoutMode' => 'masonry',
		],
		'innerBlocksHtml' => '',
	] )->render();

	expect( $html )
		->toContain( 'ap-grid-layout-masonry' )
		->toContain( 'data-ap-cols="3"' )
		->not->toContain( 'ap-grid-layout-fixed' );
} );

it( 'emits per-breakpoint data-ap-cols-{bp} attributes when the grid carries responsive numColumns overrides in masonry mode', function () {
	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.grid', [
		'attributes' => [
			'numColumns' => 2,
			'layoutMode' => 'masonry',
			'responsive' => [ 'numColumns' => [ 'md' => 4, 'lg' => 6 ] ],
		],
		'innerBlocksHtml' => '',
	] )->render();

	expect( $html )
		->toContain( 'data-ap-cols="2"' )
		->toContain( 'data-ap-cols-md="4"' )
		->toContain( 'data-ap-cols-lg="6"' );
} );

it( 'skips responsive data attributes when the grid is in fixed mode', function () {
	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.grid', [
		'attributes' => [
			'numColumns' => 2,
			'responsive' => [ 'numColumns' => [ 'md' => 4 ] ],
		],
		'innerBlocksHtml' => '',
	] )->render();

	expect( $html )
		->not->toContain( 'data-ap-cols' );
} );

it( 'emits the fixed layout-mode class on the grid wrapper by default', function () {
	$html = view( 'visual-editor-renderer-blade::blocks.artisanpack.grid', [
		'attributes' => [ 'numColumns' => 3 ],
		'innerBlocksHtml' => '',
	] )->render();

	expect( $html )
		->toContain( 'ap-grid-layout-fixed' )
		->not->toContain( 'ap-grid-layout-masonry' )
		->not->toContain( 'data-ap-cols' );
} );

it( 'wires the shared masonry stylesheet and fallback script through <x-ve-blocks-styles />', function () {
	$rendered = Blade::render( '<x-ve-blocks-styles />' );

	expect( $rendered )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/masonry.css' )
		->toContain( 'data-ve-masonry' )
		->toContain( '/vendor/visual-editor-renderer-blade/frontend/masonry-fallback.js' )
		->toContain( 'data-ve-masonry-fallback' );
} );
