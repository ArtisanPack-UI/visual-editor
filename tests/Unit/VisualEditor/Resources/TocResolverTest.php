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

it( 'stamps auto-generated anchors on headings that have no anchor attribute (#760)', function (): void {
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

it( 'preserves author-set anchors and never overwrites them', function (): void {
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

it( 'suffixes duplicate slugs so anchors are unique across the tree', function (): void {
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

it( 'suffixes an auto-anchor that collides with a later author-set anchor', function (): void {
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

it( 'walks inner blocks so nested headings also get anchors', function (): void {
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

it( 'leaves non-heading blocks untouched', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/paragraph', [ 'content' => 'Hello world' ] ),
		tocBlock( 'core/image', [ 'url' => 'https://example.test/img.png' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes'] )->not->toHaveKey( 'anchor' )
		->and( $resolved[1]['attributes'] )->not->toHaveKey( 'anchor' );
} );

it( 'skips headings whose slug would be empty (e.g. punctuation-only content)', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => '???' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes'] )->not->toHaveKey( 'anchor' );
} );

it( 'stamps _resolvedItems on artisanpack/toc blocks with every heading in document order', function (): void {
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

it( 'filters _resolvedItems on artisanpack/toc by the block minLevel and maxLevel', function (): void {
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

it( 'swaps min/max on artisanpack/toc when the range is inverted so the output is still populated', function (): void {
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

it( 'clamps out-of-range min/max attributes on artisanpack/toc into 1-6', function (): void {
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

it( 'stamps an empty _resolvedItems array when the tree has no headings', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc' ),
		tocBlock( 'core/paragraph', [ 'content' => 'No headings here' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['_resolvedItems'] )->toBe( [] );
} );

it( 'strips HTML from heading content when building anchors and TOC labels', function (): void {
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

it( 'stamps _resolvedItems on multiple TOC blocks on the same page with their own filters', function (): void {
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

it( 'parses <h1>-<h6> tags out of core/post-content _resolvedContent and folds them into the TOC', function (): void {
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

it( 'injects id attributes back into the rewritten post-content HTML for TOC anchors to land on', function (): void {
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

it( 'preserves existing id attributes on post-content headings without rewriting them', function (): void {
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

it( 'suffixes a post-content heading whose slug already exists in the template tree', function (): void {
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

it( 'strips inline HTML from post-content heading text for TOC labels', function (): void {
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

it( 'skips post-content headings whose text is empty', function (): void {
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

it( 'leaves post-content _resolvedContent untouched when it has no headings', function (): void {
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

it( 'folds diacritics when slugging headings so accented uppercase text matches the editor', function (): void {
	// Editor parity: the JS `remove-accents` package + toLowerCase()
	// turns "Café" into "cafe"; TocResolver must match so a document
	// with the same heading anchors the same on both sides.
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Café Menü' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Über Uns' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'cafe-menu' )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( 'uber-uns' );
} );

it( 'preserves upstream-resolved _resolvedItems on TOC blocks without overwriting them', function (): void {
	// A host app may layer its own extractor in front of TocResolver
	// (e.g. stamping items from a search index) — the resolver must
	// respect a pre-populated list instead of clobbering it with its
	// own derivation.
	$resolver = new TocResolver();

	$upstream = [
		[ 'level' => 2, 'text' => 'Upstream heading', 'anchor' => 'upstream' ],
	];

	$tree = [
		tocBlock( 'artisanpack/toc', [ '_resolvedItems' => $upstream ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Different heading' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['_resolvedItems'] )->toBe( $upstream );
} );

it( 'ignores non-array top-level entries without dropping the good ones', function (): void {
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

it( 'reads heading text from the content tree but stamps the original tree (#851)', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static title' ] ),
		tocBlock( 'artisanpack/toc' ),
	];

	$contentTree                               = $tree;
	$contentTree[0]['attributes']['content']   = 'Core Package';

	$resolved = $resolver->resolveTree( $tree, $contentTree );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'core-package' )
		->and( $resolved[0]['attributes']['content'] )->toBe( 'Static title' )
		->and( $resolved[1]['attributes']['_resolvedItems'] )->toBe( [
			[ 'level' => 2, 'text' => 'Core Package', 'anchor' => 'core-package' ],
		] );
} );

it( 'reads nested heading text from the matching content-tree inner block', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/group', [], [
			tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Static' ] ),
		] ),
	];

	$contentTree                                               = $tree;
	$contentTree[0]['innerBlocks'][0]['attributes']['content'] = 'Bound';

	$resolved = $resolver->resolveTree( $tree, $contentTree );

	expect( $resolved[0]['innerBlocks'][0]['attributes']['anchor'] )->toBe( 'bound' );
} );

it( 'skips a heading whose content-tree content is null (hidden binding)', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static' ] ),
		tocBlock( 'artisanpack/toc' ),
	];

	$contentTree                               = $tree;
	$contentTree[0]['attributes']['content']   = null;

	$resolved = $resolver->resolveTree( $tree, $contentTree );

	expect( $resolved[0]['attributes'] )->not->toHaveKey( 'anchor' )
		->and( $resolved[1]['attributes']['_resolvedItems'] )->toBe( [] );
} );

it( 'does not stamp an anchor when the content tree carries one from a binding', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Static' ] ),
		tocBlock( 'artisanpack/toc' ),
	];

	$contentTree                              = $tree;
	$contentTree[0]['attributes']['anchor']   = 'bound-anchor';

	$resolved = $resolver->resolveTree( $tree, $contentTree );

	expect( $resolved[0]['attributes'] )->not->toHaveKey( 'anchor' )
		->and( $resolved[1]['attributes']['_resolvedItems'][0]['anchor'] )->toBe( 'bound-anchor' );
} );

it( 'falls back to the block\'s own content when the content-tree block type differs', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Own text' ] ),
	];

	$contentTree = [
		tocBlock( 'core/paragraph', [ 'content' => 'Other text' ] ),
	];

	$resolved = $resolver->resolveTree( $tree, $contentTree );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'own-text' );
} );

it( 'detects inline tokens in heading content (#858)', function (): void {
	$resolver = new TocResolver();

	expect( $resolver->hasInlineTokens( [
		tocBlock( 'core/heading', [ 'content' => 'About {{site.name}}' ] ),
	] ) )->toBeTrue()
		->and( $resolver->hasInlineTokens( [
			[ 'name' => 'artisanpack/heading', 'attrs' => [ 'content' => '{{site.name}}' ] ],
		] ) )->toBeTrue();
} );

it( 'detects inline tokens in nested post-content HTML', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/group', [], [
			tocBlock( 'core/post-content', [ '_resolvedContent' => '<h2>About {{site.name}}</h2>' ] ),
		] ),
	];

	expect( $resolver->hasInlineTokens( $tree ) )->toBeTrue();
} );

it( 'ignores inline tokens outside the attributes the TOC reads', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/paragraph', [ 'content' => 'Call {{business_info.phone}}' ] ),
		tocBlock( 'core/heading', [ 'content' => 'Plain heading', 'className' => '{{token}}' ] ),
		tocBlock( 'artisanpack/toc', [ 'title' => '{{site.name}}' ] ),
	];

	expect( $resolver->hasInlineTokens( $tree ) )->toBeFalse()
		->and( $resolver->hasInlineTokens( [] ) )->toBeFalse();
} );

it( 'stamps anchors from the content tree into the block\'s own post-content HTML', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/post-content', [ '_resolvedContent' => '<h2>About {{site.name}}</h2><h2 id="kept">Kept</h2><p>{{site.name}}</p>' ] ),
		tocBlock( 'artisanpack/toc' ),
	];

	$contentTree                                       = $tree;
	$contentTree[0]['attributes']['_resolvedContent']  = '<h2>About Acme</h2><h2 id="kept">Kept</h2><p>Acme</p>';

	$resolved = $resolver->resolveTree( $tree, $contentTree );

	expect( $resolved[0]['attributes']['_resolvedContent'] )
		->toBe( '<h2 id="about-acme">About {{site.name}}</h2><h2 id="kept">Kept</h2><p>{{site.name}}</p>' )
		->and( array_column( $resolved[1]['attributes']['_resolvedItems'], 'anchor' ) )->toBe( [ 'about-acme', 'kept' ] )
		->and( $resolved[1]['attributes']['_resolvedItems'][0]['text'] )->toBe( 'About Acme' );
} );

it( 'stamps post-content from its own HTML when the content tree holds a different number of headings', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'core/post-content', [ '_resolvedContent' => '<h2>Intro</h2>{{site.extra}}' ] ),
		tocBlock( 'artisanpack/toc' ),
	];

	$contentTree                                       = $tree;
	$contentTree[0]['attributes']['_resolvedContent']  = '<h2>Intro</h2><h2>Injected</h2>';

	$resolved = $resolver->resolveTree( $tree, $contentTree );

	expect( $resolved[0]['attributes']['_resolvedContent'] )->toBe( '<h2 id="intro">Intro</h2>{{site.extra}}' )
		->and( array_column( $resolved[1]['attributes']['_resolvedItems'], 'anchor' ) )->toBe( [ 'intro' ] );
} );

it( 'stamps anchors and TOC items into the attrs bag of a legacy attrs-shape tree (#863)', function (): void {
	$resolver = new TocResolver();

	$tree = [
		[ 'name' => 'artisanpack/toc', 'attrs' => [ 'minLevel' => 2 ], 'innerBlocks' => [] ],
		[ 'name' => 'core/heading', 'attrs' => [ 'level' => 2, 'content' => 'Getting Started' ], 'innerBlocks' => [] ],
		[ 'name' => 'core/paragraph', 'attrs' => [ 'content' => 'Body' ], 'innerBlocks' => [] ],
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[1]['attrs'] )->toBe( [ 'level' => 2, 'content' => 'Getting Started', 'anchor' => 'getting-started' ] )
		->and( $resolved[1] )->not->toHaveKey( 'attributes' )
		->and( $resolved[0]['attrs']['minLevel'] )->toBe( 2 )
		->and( array_column( $resolved[0]['attrs']['_resolvedItems'], 'anchor' ) )->toBe( [ 'getting-started' ] )
		->and( $resolved[2] )->toBe( $tree[2] );
} );

it( 'claims and links the rendered (sanitized) author anchor, not the raw value (#888)', function (): void {
	$resolver = new TocResolver();

	$resolved = $resolver->resolveTree( [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Author', 'anchor' => ' foo bar ' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Foobar' ] ),
		tocBlock( 'artisanpack/toc' ),
	] );

	$items = $resolved[2]['attributes']['_resolvedItems'];

	expect( $resolved[0]['attributes']['anchor'] )->toBe( ' foo bar ' )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( 'foobar-1' )
		->and( array_column( $items, 'anchor' ) )->toBe( [ 'foobar', 'foobar-1' ] );
} );

it( 'auto-anchors a heading whose author anchor sanitizes to nothing (#888)', function (): void {
	$resolver = new TocResolver();

	$resolved = $resolver->resolveTree( [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Quoted', 'anchor' => '"<>"' ] ),
	] );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 'quoted' );
} );

it( 'reduces auto anchors to their rendered id before the uniqueness check (M-13)', function (): void {
	$resolver = new TocResolver();

	$resolved = $resolver->resolveTree( [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Crème ½ x' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Crème ¼ x' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => '日本語' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Résumé 日本' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Résumé 中文' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Привет' ] ),
		tocBlock( 'artisanpack/toc' ),
	] );

	$anchors = array_map( fn ( array $block ): ?string => $block['attributes']['anchor'] ?? null, array_slice( $resolved, 0, 6 ) );

	expect( $anchors )->toBe( [ 'creme-x', 'creme-x-1', 'heading', 'resume', 'resume-1', 'heading-1' ] )
		->and( array_column( $resolved[6]['attributes']['_resolvedItems'], 'anchor' ) )->toBe( $anchors );
} );

it( 'still skips a heading whose text has no letters or numbers (M-13)', function (): void {
	$resolved = ( new TocResolver() )->resolveTree( [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => '— ? —' ] ),
	] );

	expect( $resolved[0]['attributes'] )->not->toHaveKey( 'anchor' );
} );

it( 'stamps id-safe anchors into post-content headings (M-13)', function (): void {
	$resolved = ( new TocResolver() )->resolveTree( [
		tocBlock( 'core/post-content', [ '_resolvedContent' => '<h2>日本語</h2><h2>中文</h2>' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Ελληνικά' ] ),
	] );

	expect( $resolved[0]['attributes']['_resolvedContent'] )->toBe( '<h2 id="heading">日本語</h2><h2 id="heading-1">中文</h2>' )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( 'heading-2' );
} );

it( 'claims a numeric author anchor as the id it renders (F-5)', function (): void {
	$resolved = ( new TocResolver() )->resolveTree( [
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Numbered', 'anchor' => 42 ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => '42' ] ),
		tocBlock( 'artisanpack/toc' ),
	] );

	expect( $resolved[0]['attributes']['anchor'] )->toBe( 42 )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( '42-1' )
		->and( array_column( $resolved[2]['attributes']['_resolvedItems'], 'anchor' ) )->toBe( [ '42', '42-1' ] );
} );

it( 'keeps only post-content headings on a TOC whose source is post-content', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'source' => 'post-content' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Template heading' ] ),
		tocBlock( 'artisanpack/post-content', [
			'_resolvedContent' => '<h2>Intro</h2><p>Text.</p><h3>Details</h3>',
		] ),
		tocBlock( 'core/group', [], [
			tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Related posts' ] ),
		] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( $resolved[0]['attributes']['_resolvedItems'] )->toBe( [
		[ 'level' => 2, 'text' => 'Intro', 'anchor' => 'intro' ],
		[ 'level' => 3, 'text' => 'Details', 'anchor' => 'details' ],
	] )
		->and( $resolved[1]['attributes']['anchor'] )->toBe( 'template-heading' )
		->and( $resolved[3]['innerBlocks'][0]['attributes']['anchor'] )->toBe( 'related-posts' );
} );

it( 'counts heading blocks nested inside post-content as post content', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'source' => 'post-content' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Outside' ] ),
		tocBlock( 'core/post-content', [], [
			tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Inside' ] ),
		] ),
	];

	$items = $resolver->resolveTree( $tree )[0]['attributes']['_resolvedItems'];

	expect( array_column( $items, 'text' ) )->toBe( [ 'Inside' ] );
} );

it( 'stamps an empty list on a post-content TOC when the post body has no headings', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'source' => 'post-content' ] ),
		tocBlock( 'core/post-content', [ '_resolvedContent' => '<p>No headings.</p>' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Related posts' ] ),
	];

	expect( $resolver->resolveTree( $tree )[0]['attributes']['_resolvedItems'] )->toBe( [] );
} );

it( 'keeps every heading on a post-content TOC when the tree has no post-content block', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'source' => 'post-content' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'One' ] ),
		tocBlock( 'core/heading', [ 'level' => 3, 'content' => 'Two' ] ),
	];

	$items = $resolver->resolveTree( $tree )[0]['attributes']['_resolvedItems'];

	expect( array_column( $items, 'text' ) )->toBe( [ 'One', 'Two' ] );
} );

it( 'keeps every heading on a TOC whose source is page alongside a post-content TOC', function (): void {
	$resolver = new TocResolver();

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'source' => 'page' ] ),
		tocBlock( 'artisanpack/toc', [ 'source' => 'post-content' ] ),
		tocBlock( 'core/post-content', [ '_resolvedContent' => '<h2>Body</h2>' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Footer' ] ),
	];

	$resolved = $resolver->resolveTree( $tree );

	expect( array_column( $resolved[0]['attributes']['_resolvedItems'], 'text' ) )->toBe( [ 'Body', 'Footer' ] )
		->and( array_column( $resolved[1]['attributes']['_resolvedItems'], 'text' ) )->toBe( [ 'Body' ] );
} );

it( 'loads a subclass that overrides the stamping methods with their 1.17 signatures', function (): void {
	$resolver = new class() extends TocResolver {
		protected function stampHeadings( array $tree, array &$used, array &$headings, ?array $contentTree = null ): array
		{
			return parent::stampHeadings( $tree, $used, $headings, $contentTree );
		}

		protected function stampHeadingBlock( array $block, array &$used, array &$headings, ?array $source = null ): array
		{
			return parent::stampHeadingBlock( $block, $used, $headings, $source );
		}

		protected function stampTocBlocks( array $tree, array $headings ): array
		{
			return parent::stampTocBlocks( $tree, $headings );
		}

		protected function stampTocBlock( array $block, array $headings ): array
		{
			return parent::stampTocBlock( $block, $headings );
		}
	};

	$tree = [
		tocBlock( 'artisanpack/toc', [ 'source' => 'post-content' ] ),
		tocBlock( 'core/post-content', [], [
			tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Inside' ] ),
		] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Outside' ] ),
	];

	$items = $resolver->resolveTree( $tree )[0]['attributes']['_resolvedItems'];

	expect( array_column( $items, 'text' ) )->toBe( [ 'Inside' ] );
} );

it( 'does not carry post-content state from one tree to the next on a reused resolver', function (): void {
	$resolver = new TocResolver();

	$resolver->resolveTree( [
		tocBlock( 'core/post-content', [ '_resolvedContent' => '<h2>Body</h2>' ] ),
	] );

	$items = $resolver->resolveTree( [
		tocBlock( 'artisanpack/toc', [ 'source' => 'post-content' ] ),
		tocBlock( 'core/heading', [ 'level' => 2, 'content' => 'Standalone' ] ),
	] )[0]['attributes']['_resolvedItems'];

	expect( array_column( $items, 'text' ) )->toBe( [ 'Standalone' ] );
} );
