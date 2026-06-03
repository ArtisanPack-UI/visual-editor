<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;

/**
 * Phase I-Block-Fork — post navigation / metadata family (#520).
 *
 * The PostResolver must stamp the forked `artisanpack/post-navigation-link`,
 * `artisanpack/post-terms`, `artisanpack/read-more`, and
 * `artisanpack/term-description` blocks through the same branches as their
 * `core/*` counterparts so the Blade / React / Vue partials render
 * identically regardless of namespace.
 */

function navMetadataPost(): object
{
	$post            = new stdClass();
	$post->title     = 'Current post';
	$post->permalink = 'https://example.test/posts/current';

	$post->previous_post = (object) [
		'title'     => 'Previous post title',
		'permalink' => 'https://example.test/posts/previous',
	];
	$post->next_post = (object) [
		'title'     => 'Next post title',
		'permalink' => 'https://example.test/posts/next',
	];

	$post->terms = [
		(object) [
			'taxonomy'    => 'category',
			'name'        => 'News',
			'slug'        => 'news',
			'url'         => 'https://example.test/category/news',
			'description' => 'All news articles',
		],
		(object) [
			'taxonomy' => 'category',
			'name'     => 'Updates',
			'slug'     => 'updates',
			'url'      => 'https://example.test/category/updates',
		],
		(object) [
			'taxonomy' => 'post_tag',
			'name'     => 'Featured',
			'slug'     => 'featured',
			'url'      => 'https://example.test/tag/featured',
		],
	];

	return $post;
}

it( 'stamps both adjacent links on post-navigation-link for either namespace', function ( string $name ): void {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => $name, 'attributes' => [], 'innerBlocks' => [] ],
		navMetadataPost()
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( 'https://example.test/posts/previous' )
		->and( $resolved['attributes']['_resolvedPrevTitle'] )->toBe( 'Previous post title' )
		->and( $resolved['attributes']['_resolvedNextUrl'] )->toBe( 'https://example.test/posts/next' )
		->and( $resolved['attributes']['_resolvedNextTitle'] )->toBe( 'Next post title' );
} )->with( [
	'core'        => [ 'core/post-navigation-link' ],
	'artisanpack' => [ 'artisanpack/post-navigation-link' ],
] );

it( 'falls back to empty strings when no adjacent post is available', function (): void {
	$post = new stdClass();
	$post->title = 'Solo';

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedPrevTitle'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedNextUrl'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedNextTitle'] )->toBe( '' );
} );

it( 'stamps the post-terms _resolvedTermsByTaxonomy map keyed by taxonomy', function ( string $name ): void {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => $name, 'attributes' => [], 'innerBlocks' => [] ],
		navMetadataPost()
	);

	$map = $resolved['attributes']['_resolvedTermsByTaxonomy'];

	expect( $map )->toBeArray()
		->and( $map )->toHaveKey( 'category' )
		->and( $map )->toHaveKey( 'post_tag' )
		->and( $map['category'][0] )->toMatchArray( [
			'name' => 'News',
			'slug' => 'news',
			'url'  => 'https://example.test/category/news',
		] )
		->and( $map['post_tag'][0]['name'] )->toBe( 'Featured' );
} )->with( [
	'core'        => [ 'core/post-terms' ],
	'artisanpack' => [ 'artisanpack/post-terms' ],
] );

it( 'merges categories/tags shortcut relations into the post-terms map', function (): void {
	$post = new stdClass();
	$post->categories = [
		(object) [ 'name' => 'Notes', 'slug' => 'notes', 'url' => '/c/notes' ],
	];
	$post->tags = [
		(object) [ 'name' => 'pinned', 'slug' => 'pinned', 'url' => '/t/pinned' ],
	];

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-terms', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	$map = $resolved['attributes']['_resolvedTermsByTaxonomy'];

	expect( $map['category'][0]['name'] )->toBe( 'Notes' )
		->and( $map['post_tag'][0]['name'] )->toBe( 'pinned' );
} );

it( 'stamps read-more with the permalink for either namespace', function ( string $name ): void {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => $name, 'attributes' => [ 'content' => 'Keep reading' ], 'innerBlocks' => [] ],
		navMetadataPost()
	);

	expect( $resolved['attributes']['_resolvedPermalink'] )->toBe( 'https://example.test/posts/current' )
		// The block's own `content` attribute survives the resolver merge
		// — the renderer reads it directly.
		->and( $resolved['attributes']['content'] )->toBe( 'Keep reading' );
} )->with( [
	'core'        => [ 'core/read-more' ],
	'artisanpack' => [ 'artisanpack/read-more' ],
] );

it( 'stamps term-description from the primary term for either namespace', function ( string $name ): void {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => $name, 'attributes' => [], 'innerBlocks' => [] ],
		navMetadataPost()
	);

	// `terms` is iterated in order — first entry (News) is the primary.
	expect( $resolved['attributes']['_resolvedTermDescription'] )->toBe( 'All news articles' )
		->and( $resolved['attributes']['_resolvedTermName'] )->toBe( 'News' )
		->and( $resolved['attributes']['_resolvedTermUrl'] )->toBe( 'https://example.test/category/news' );
} )->with( [
	'core'        => [ 'core/term-description' ],
	'artisanpack' => [ 'artisanpack/term-description' ],
] );

it( 'prefers an explicit primary_term accessor when the post exposes one', function (): void {
	$post = navMetadataPost();
	$post->primary_term = (object) [
		'name'        => 'Special',
		'slug'        => 'special',
		'url'         => 'https://example.test/category/special',
		'description' => 'Hand-picked',
	];

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/term-description', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedTermDescription'] )->toBe( 'Hand-picked' )
		->and( $resolved['attributes']['_resolvedTermName'] )->toBe( 'Special' );
} );

it( 'falls back to the cms-framework permalink accessor for term URLs', function (): void {
	$post = new stdClass();
	$post->categories = [
		// PostCategory exposes its URL as `permalink` (no `url` field).
		(object) [
			'name'        => 'News',
			'slug'        => 'news',
			'permalink'   => 'https://example.test/blog/category/news',
			'description' => 'All news articles',
		],
	];

	$termsResolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-terms', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $termsResolved['attributes']['_resolvedTermsByTaxonomy']['category'][0]['url'] )
		->toBe( 'https://example.test/blog/category/news' );

	$descriptionResolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/term-description', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $descriptionResolved['attributes']['_resolvedTermUrl'] )
		->toBe( 'https://example.test/blog/category/news' );
} );

it( 'returns empty term-description when the post has no terms', function (): void {
	$post = new stdClass();
	$post->title = 'Termless';

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/term-description', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedTermDescription'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedTermName'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedTermUrl'] )->toBe( '' );
} );
