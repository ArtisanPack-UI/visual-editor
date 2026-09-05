<?php

/**
 * Rendering tests for the artisanpack/toc block (#760).
 *
 * These tests wire `TocResolver` in front of `BlockRenderer` manually
 * so the resolver's `_resolvedItems` stamp reaches the Blade partial
 * (in production the resolver runs from `<x-ve-blocks>`). Resolver
 * behavior in isolation is covered by
 * `tests/Unit/VisualEditor/Resources/TocResolverTest.php`; these tests
 * verify that the resolved tree renders into the expected DOM.
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\TocResolver;
use ArtisanPackUI\VisualEditorRendererBlade\BlockRenderer;

function tocRenderer(): BlockRenderer
{
	return app( BlockRenderer::class );
}

function tocResolver(): TocResolver
{
	return app( TocResolver::class );
}

function tocRenderBlock( string $name, array $attributes = [], array $innerBlocks = [] ): array
{
	return [
		'clientId'    => 'cid-' . uniqid( '', true ),
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

function renderTocTree( array $tree ): string
{
	$resolved = tocResolver()->resolveTree( $tree );

	return tocRenderer()->render( $resolved );
}

it( 'renders a table of contents with in-page anchor links (#760)', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc', [ 'heading' => 'On this page' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'Getting started' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 3, 'content' => 'Installation' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'Configuration' ] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( '<nav' )
		->and( $html )->toContain( 'class="ap-toc"' )
		->and( $html )->toContain( '<h2 class="ap-toc__heading">On this page</h2>' )
		->and( $html )->toContain( '<a class="ap-toc__link" href="#getting-started">Getting started</a>' )
		->and( $html )->toContain( '<a class="ap-toc__link" href="#installation">Installation</a>' )
		->and( $html )->toContain( '<a class="ap-toc__link" href="#configuration">Configuration</a>' );
} );

it( 'nests deeper headings under their preceding parent heading', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'Parent A' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 3, 'content' => 'Child A1' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 3, 'content' => 'Child A2' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'Parent B' ] ),
	];

	$html = renderTocTree( $tree );

	$parentA = strpos( $html, 'href="#parent-a"' );
	$childA1 = strpos( $html, 'href="#child-a1"' );
	$childA2 = strpos( $html, 'href="#child-a2"' );
	$parentB = strpos( $html, 'href="#parent-b"' );

	expect( $parentA )->toBeInt()
		->and( $childA1 )->toBeGreaterThan( $parentA )
		->and( $childA2 )->toBeGreaterThan( $childA1 )
		->and( $parentB )->toBeGreaterThan( $childA2 );

	// A nested list is emitted for the children of Parent A, plus the
	// outer list itself.
	expect( substr_count( $html, 'class="ap-toc__list"' ) )->toBeGreaterThanOrEqual( 2 );
} );

it( 'renders an ordered list when the ordered attribute is on', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc', [ 'ordered' => true ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'One' ] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( '<ol class="ap-toc__list">' )
		->and( $html )->not->toContain( '<ul class="ap-toc__list">' );
} );

it( 'defaults to an unordered list', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'One' ] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( '<ul class="ap-toc__list">' );
} );

it( 'stamps an id on every rendered heading so the TOC links land somewhere', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'Heading one' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 3, 'content' => 'Heading two' ] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( 'id="heading-one"' )
		->and( $html )->toContain( 'id="heading-two"' );
} );

it( 'omits an out-of-range heading from the list but still stamps its id', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc', [ 'minLevel' => 2, 'maxLevel' => 3 ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'In range' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 4, 'content' => 'Out of range' ] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( 'href="#in-range"' )
		->and( $html )->not->toContain( 'href="#out-of-range"' )
		->and( $html )->toContain( 'id="in-range"' )
		->and( $html )->toContain( 'id="out-of-range"' );
} );

it( 'renders a placeholder when no headings are on the page', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/paragraph', [ 'content' => 'No headings here' ] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( 'ap-toc__placeholder' );
} );

it( 'sets an aria-label on the landmark from the heading text, or the default when blank', function () {
	$labeled = [
		tocRenderBlock( 'artisanpack/toc', [ 'heading' => 'Contents' ] ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'One' ] ),
	];

	$labeledHtml = renderTocTree( $labeled );

	expect( $labeledHtml )->toContain( 'aria-label="Contents"' );

	$blank = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'One' ] ),
	];

	$blankHtml = renderTocTree( $blank );

	expect( $blankHtml )->toContain( 'aria-label="Table of contents"' );
} );

it( 'derives entries from headings nested inside container blocks', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/group', [], [
			tocRenderBlock( 'core/heading', [ 'level' => 2, 'content' => 'Nested heading' ] ),
		] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( 'href="#nested-heading"' )
		->and( $html )->toContain( 'id="nested-heading"' );
} );

it( 'reuses an author-set anchor for the TOC link and the heading id', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/heading', [
			'level'   => 2,
			'content' => 'Author anchor',
			'anchor'  => 'custom-slug',
		] ),
	];

	$html = renderTocTree( $tree );

	expect( $html )->toContain( 'id="custom-slug"' )
		->and( $html )->toContain( 'href="#custom-slug"' )
		->and( $html )->not->toContain( 'id="author-anchor"' );
} );

it( 'renders the same anchor label as the heading, with HTML tags stripped', function () {
	$tree = [
		tocRenderBlock( 'artisanpack/toc' ),
		tocRenderBlock( 'core/heading', [
			'level'   => 2,
			'content' => '<strong>Bold</strong> heading',
		] ),
	];

	$html = renderTocTree( $tree );

	// TOC link text is the plain-text form of the heading.
	expect( $html )->toContain( '<a class="ap-toc__link" href="#bold-heading">Bold heading</a>' );
} );
