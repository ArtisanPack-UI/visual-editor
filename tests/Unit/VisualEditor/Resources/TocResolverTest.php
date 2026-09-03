<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\TocResolver;

function tocBlock( string $name, array $attributes = [], array $innerBlocks = [] ): array
{
	return [
		'clientId'    => 'cid-' . uniqid( '', true ),
		'name'        => $name,
		'attributes'  => $attributes,
		'innerBlocks' => $innerBlocks,
	];
}

it( 'stamps auto-generated anchors on headings that have no anchor attribute (#760)', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Getting Started' ] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Installation Steps' ] ),
		tocBlock( 'artisanpack/heading', [ 'level' => 2, 'content' => 'FAQ &amp; Support' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'getting-started' )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( 'installation-steps' )
		->and( $resolved[2]['attributes']['anchor'] )->toBe( 'faq-support' );
} );

it( 'preserves author-set anchors and never overwrites them', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [
			'level'   => 2,
			'content' => 'Custom anchor',
			'anchor'  => 'my-custom-anchor',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'my-custom-anchor' );
} );

it( 'suffixes duplicate slugs so anchors are unique across the tree', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Overview' ] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Overview' ] ),
		tocBlock( 'artisanpack/heading', [ 'level' => 3, 'content' => 'Overview' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'overview' )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( 'overview-1' )
		->and( $resolved[2]['attributes']['anchor'] )->toBe( 'overview-2' );
} );

it( 'suffixes an auto-anchor that collides with a later author-set anchor', function () {
	$resolver = new TocResolver();

	// The author-set anchor gets claimed BEFORE anchors are auto-
	// generated for the sibling headings, so a heading whose slug
	// would collide with it takes the `-1` suffix.
	$tree = [
		tocBlock( 'core/heading', [
			'level'   => 2,
			'content' => 'Setup',
			'anchor'  => 'setup',
		] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Setup' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'setup' )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( 'setup-1' );
} );

it( 'walks inner blocks so nested headings also get anchors', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/group', [], [
			tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Nested heading' ] ),
			tocBlock( 'core/columns', [], [
				tocBlock( 'core/column', [], [
					tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Deeply nested' ] ),
				] ),
			] ),
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$outerHeading = $resolved[0]['innerBlocks'][0];
	$innerHeading = $resolved[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0];

	expect( $outerHeading['attributes']['anchor'] )->toBe( 'nested-heading' )
		->and( $innerHeading['attributes']['anchor'] )->toBe( 'deeply-nested' );
} );

it( 'leaves non-heading blocks untouched', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/paragraph', [ 'content' => 'Hello world' ] ),
		tocBlock( 'core/image', [ 'url' => 'https://example.test/img.png' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes'] )->not->toHaveKey( 'anchor' )
		->and( $resolved[1]['attributes'] )->not->toHaveKey( 'anchor' );
} );

it( 'skips headings whose slug would be empty (e.g. punctuation-only content)', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => '???' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes'] )->not->toHaveKey( 'anchor' );
} );

it( 'stamps _resolvedItems on artisanpack/toc blocks with every heading in document order', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'minLevel' => 2, 'maxLevel' => 6 ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'One' ] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Two' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Three' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items )->toHaveCount( 3 )
		->and( $items[0] )->toBe( [ 'level' => 2, 'text' => 'One', 'anchor' => 'one' ] )
		->and( $items[1] )->toBe( [ 'level' => 3, 'text' => 'Two', 'anchor' => 'two' ] )
		->and( $items[2] )->toBe( [ 'level' => 2, 'text' => 'Three', 'anchor' => 'three' ] );
} );

it( 'filters _resolvedItems on artisanpack/toc by the block minLevel and maxLevel', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'minLevel' => 2, 'maxLevel' => 3 ] ),
		tocBlock( 'core/heading', [ 'level' => 1, 'content' => 'Skipped (H1)' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Kept (H2)' ] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Kept (H3)' ] ),
		tocBlock( 'core/heading', [ 'level' => 4, 'content' => 'Skipped (H4)' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items )->toHaveCount( 2 )
		->and( $items[0]['text'] )->toBe( 'Kept (H2)' )
		->and( $items[1]['text'] )->toBe( 'Kept (H3)' );
} );

it( 'swaps min/max on artisanpack/toc when the range is inverted so the output is still populated', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'minLevel' => 4, 'maxLevel' => 2 ] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Middle' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items )->toHaveCount( 1 )
		->and( $items[0]['text'] )->toBe( 'Middle' );
} );

it( 'clamps out-of-range min/max attributes on artisanpack/toc into 1-6', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'minLevel' => 0, 'maxLevel' => 99 ] ),
		tocBlock( 'core/heading', [ 'level' => 1, 'content' => 'H1' ] ),
		tocBlock( 'core/heading', [ 'level' => 6, 'content' => 'H6' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items )->toHaveCount( 2 );
} );

it( 'stamps an empty _resolvedItems array when the tree has no headings', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/paragraph', [ 'content' => 'No headings here' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['_resolvedItems'] )->toBe( [] );
} );

it( 'strips HTML from heading content when building anchors and TOC labels', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/heading', [
			'level'   => 2,
			'content' => '<strong>Bold</strong> heading with <em>emphasis</em>',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items[0]['text'] )->toBe( 'Bold heading with emphasis' )
		->and( $items[0]['anchor'] )->toBe( 'bold-heading-with-emphasis' );
} );

it( 'stamps _resolvedItems on multiple TOC blocks on the same page with their own filters', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'minLevel' => 2, 'maxLevel' => 2 ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Alpha' ] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Beta' ] ),
		tocBlock( 'artisanpack/toc', [ 'minLevel' => 3, 'maxLevel' => 3 ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['_resolvedItems'] )->toHaveCount( 1 )
		->and( $resolved[0]['attributes']['_resolvedItems'][0]['text'] )->toBe( 'Alpha' )
		->and( $resolved[3]['attributes']['_resolvedItems'] )->toHaveCount( 1 )
		->and( $resolved[3]['attributes']['_resolvedItems'][0]['text'] )->toBe( 'Beta' );
} );

it( 'parses <h1>-<h6> tags out of core/post-content _resolvedContent and folds them into the TOC', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/post-content', [
			'_resolvedContent' => '<h2>Intro</h2><p>Text.</p><h3>Details</h3><p>More text.</p>',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items )->toHaveCount( 2 )
		->and( $items[0] )->toBe( [ 'level' => 2, 'text' => 'Intro', 'anchor' => 'intro' ] )
		->and( $items[1] )->toBe( [ 'level' => 3, 'text' => 'Details', 'anchor' => 'details' ] );
} );

it( 'injects id attributes back into the rewritten post-content HTML for TOC anchors to land on', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'artisanpack/post-content', [
			'_resolvedContent' => '<h2>Intro</h2><p>Body.</p>',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[1]['attributes']['_resolvedContent'] )
		->toContain( '<h2 id="intro">Intro</h2>' );
} );

it( 'preserves existing id attributes on post-content headings without rewriting them', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/post-content', [
			'_resolvedContent' => '<h2 id="custom-slug" class="wp-block-heading">Intro</h2>',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[1]['attributes']['_resolvedContent'] )
		->toContain( '<h2 id="custom-slug" class="wp-block-heading">Intro</h2>' )
		->and( $resolved[0]['attributes']['_resolvedItems'][0] )
		->toBe( [ 'level' => 2, 'text' => 'Intro', 'anchor' => 'custom-slug' ] );
} );

it( 'suffixes a post-content heading whose slug already exists in the template tree', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Intro' ] ),
		tocBlock( 'core/post-content', [
			'_resolvedContent' => '<h2>Intro</h2>',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items )->toHaveCount( 2 )
		->and( $items[0]['anchor'] )->toBe( 'intro' )
		->and( $items[1]['anchor'] )->toBe( 'intro-1' )
		->and( $resolved[2]['attributes']['_resolvedContent'] )
			->toContain( '<h2 id="intro-1">Intro</h2>' );
} );

it( 'strips inline HTML from post-content heading text for TOC labels', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/post-content', [
			'_resolvedContent' => '<h2><strong>Bold</strong> intro</h2>',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['_resolvedItems'][0]['text'] )->toBe( 'Bold intro' )
		->and( $resolved[0]['attributes']['_resolvedItems'][0]['anchor'] )->toBe( 'bold-intro' );
} );

it( 'skips post-content headings whose text is empty', function () {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/post-content', [
			'_resolvedContent' => '<h2></h2><h2>Kept</h2>',
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	$items = $resolved[0]['attributes']['_resolvedItems'];

	expect( $items )->toHaveCount( 1 )
		->and( $items[0]['text'] )->toBe( 'Kept' );
} );

it( 'leaves post-content _resolvedContent untouched when it has no headings', function () {
	$resolver = new TocResolver();

	$original = '<p>Just a paragraph.</p>';

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/post-content', [ '_resolvedContent' => $original ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[1]['attributes']['_resolvedContent'] )->toBe( $original )
		->and( $resolved[0]['attributes']['_resolvedItems'] )->toBe( [] );
} );

it( 'ignores non-array top-level entries without dropping the good ones', function () {
	$resolver = new TocResolver();

	$tree = [
		'not-a-block',
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Kept' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	// The invalid entry is skipped by the walker; the good one still
	// gets an anchor.
	expect( $resolved )->toHaveCount( 1 )
		->and( $resolved[0]['attributes']['anchor'] )->toBe( 'kept' );
} );
