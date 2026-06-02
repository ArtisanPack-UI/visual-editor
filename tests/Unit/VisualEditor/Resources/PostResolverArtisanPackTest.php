<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use Illuminate\Support\Carbon;

/**
 * Phase I5 entity-cluster (#413) — the PostResolver must stamp the forked
 * `artisanpack/post-*` blocks through the exact same branches as their
 * `core/post-*` counterparts, so the Blade / React / Vue partials render
 * identically regardless of namespace.
 */

beforeEach( function (): void {
	// Pin the locale to English so `translatedFormat()` is deterministic,
	// and remember the previous locale so afterEach can restore the global
	// Carbon state for the rest of the suite.
	$this->originalCarbonLocale = Carbon::getLocale();
	Carbon::setLocale( 'en' );
} );

afterEach( function (): void {
	Carbon::setLocale( $this->originalCarbonLocale );
} );

function fakeArtisanPackPost(): object
{
	$post               = new stdClass();
	$post->title        = 'Hello world';
	$post->content      = '<p>Body.</p>';
	$post->excerpt      = 'A brief excerpt';
	$post->published_at = Carbon::create( 2026, 4, 20, 12, 0, 0, 'UTC' );
	$post->updated_at   = Carbon::create( 2026, 4, 21, 9, 0, 0, 'UTC' );
	$post->permalink    = 'https://example.test/posts/hello';
	$post->author       = (object) [
		'name'       => 'Jane Doe',
		'bio'        => 'Writer',
		'url'        => 'https://example.test/jane',
		'avatar_url' => 'https://example.test/avatar.jpg',
	];

	return $post;
}

it( 'stamps artisanpack/post-title the same as core/post-title', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-title', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedTitle'] )->toBe( 'Hello world' )
		->and( $resolved['attributes']['_resolvedPermalink'] )->toBe( 'https://example.test/posts/hello' );
} );

it( 'stamps artisanpack/post-content', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedContent'] )->toBe( '<p>Body.</p>' );
} );

it( 'stamps artisanpack/post-excerpt', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedExcerpt'] )->toBe( 'A brief excerpt' );
} );

it( 'stamps artisanpack/post-date including the modified date', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-date', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedDateFormatted'] )->toBe( 'April 20, 2026' )
		->and( $resolved['attributes']['_resolvedModifiedDateFormatted'] )->toBe( 'April 21, 2026' );
} );

it( 'stamps artisanpack/post-author from the loaded relation', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-author', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedAuthorName'] )->toBe( 'Jane Doe' )
		->and( $resolved['attributes']['_resolvedAuthorUrl'] )->toBe( 'https://example.test/jane' );
} );

it( 'stamps artisanpack/post-author-name from the loaded relation (#518)', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-author-name', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedAuthorName'] )->toBe( 'Jane Doe' )
		->and( $resolved['attributes']['_resolvedAuthorUrl'] )->toBe( 'https://example.test/jane' );
} );

it( 'stamps artisanpack/post-author-biography from the loaded relation (#518)', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-author-biography', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedAuthorBio'] )->toBe( 'Writer' );
} );

it( 'stamps artisanpack/avatar with the author avatar URL and name (#518)', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/avatar', 'attributes' => [], 'innerBlocks' => [] ],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedAuthorAvatar'] )->toBe( 'https://example.test/avatar.jpg' )
		->and( $resolved['attributes']['_resolvedAuthorName'] )->toBe( 'Jane Doe' );
} );

it( 'stamps the core/* counterparts for the author family identically to the artisanpack/* forks (#518)', function () {
	$resolver = new PostResolver();
	$post     = fakeArtisanPackPost();

	$artisanName = $resolver->stampBlock(
		[ 'name' => 'artisanpack/post-author-name', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);
	$coreName = $resolver->stampBlock(
		[ 'name' => 'core/post-author-name', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	$artisanBio = $resolver->stampBlock(
		[ 'name' => 'artisanpack/post-author-biography', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);
	$coreBio = $resolver->stampBlock(
		[ 'name' => 'core/post-author-biography', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	$artisanAvatar = $resolver->stampBlock(
		[ 'name' => 'artisanpack/avatar', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);
	$coreAvatar = $resolver->stampBlock(
		[ 'name' => 'core/avatar', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	// Compare the resolved attribute bags directly so a regression on
	// either side (e.g. artisanpack/post-author-name silently stops
	// stamping `_resolvedAuthorUrl`) trips the assertion instead of
	// passing because the literal-value baseline didn't move.
	expect( $coreName['attributes'] )->toEqual( $artisanName['attributes'] )
		->and( $coreBio['attributes'] )->toEqual( $artisanBio['attributes'] )
		->and( $coreAvatar['attributes'] )->toEqual( $artisanAvatar['attributes'] );
} );

it( 'recurses into inner blocks so an artisanpack/query template stamps too', function () {
	$tree = [
		[
			'name'        => 'artisanpack/post-title',
			'attributes'  => [],
			'innerBlocks' => [
				[ 'name' => 'artisanpack/post-date', 'attributes' => [], 'innerBlocks' => [] ],
			],
		],
	];

	$stamped = ( new PostResolver() )->stampTree( $tree, fakeArtisanPackPost() );

	expect( $stamped[0]['attributes']['_resolvedTitle'] )->toBe( 'Hello world' )
		->and( $stamped[0]['innerBlocks'][0]['attributes']['_resolvedDateFormatted'] )->toBe( 'April 20, 2026' );
} );

it( 'leaves a pre-existing _resolved* value untouched (host wins)', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/post-title',
			'attributes'  => [ '_resolvedTitle' => 'Host override' ],
			'innerBlocks' => [],
		],
		fakeArtisanPackPost()
	);

	expect( $resolved['attributes']['_resolvedTitle'] )->toBe( 'Host override' );
} );
