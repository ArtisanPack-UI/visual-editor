<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use Illuminate\Support\Carbon;

beforeEach( function (): void {
	// Pin the locale to English so `translatedFormat( 'F j, Y' )`
	// produces "April 20, 2026" regardless of the host's default
	// locale; tests assert the exact English month name.
	Carbon::setLocale( 'en' );
} );

function fakePost( array $overrides = [] ): object
{
	$post                    = new stdClass();
	$post->title             = 'Hello world';
	$post->content           = '<p>Body.</p>';
	$post->excerpt           = 'A brief excerpt';
	// Pin the timezone to UTC so `toIso8601String()` always emits
	// `+00:00`; tests assert the literal offset and would otherwise
	// be flaky on machines whose `date.timezone` differs.
	$post->published_at      = Carbon::create( 2026, 4, 20, 12, 0, 0, 'UTC' );
	$post->updated_at        = Carbon::create( 2026, 4, 21, 9, 0, 0, 'UTC' );
	$post->permalink         = 'https://example.test/posts/hello';
	$post->author            = (object) [
		'name'        => 'Jane Doe',
		'bio'         => 'Writer',
		'url'         => 'https://example.test/jane',
		'avatar_url'  => 'https://example.test/avatar.jpg',
	];
	$post->featured_image_id = null;

	foreach ( $overrides as $key => $value ) {
		$post->{$key} = $value;
	}

	return $post;
}

it( 'stamps post-title attributes', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost()
	);

	expect( $resolved['attributes']['_resolvedTitle'] )->toBe( 'Hello world' )
		->and( $resolved['attributes']['_resolvedPermalink'] )->toBe( 'https://example.test/posts/hello' );
} );

it( 'stamps post-content attributes', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost()
	);

	expect( $resolved['attributes']['_resolvedContent'] )->toBe( '<p>Body.</p>' );
} );

it( 'prefers rendered_content over content when the host exposes the accessor', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [
			'content'          => [ [ 'name' => 'core/paragraph' ] ],
			'rendered_content' => '<p>Rendered HTML.</p>',
		] )
	);

	expect( $resolved['attributes']['_resolvedContent'] )->toBe( '<p>Rendered HTML.</p>' );
} );

it( 'falls back to content when rendered_content is missing or empty', function () {
	$emptyRendered = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'rendered_content' => '' ] )
	);

	$nonStringContent = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'content' => [ [ 'name' => 'core/paragraph' ] ] ] )
	);

	expect( $emptyRendered['attributes']['_resolvedContent'] )->toBe( '<p>Body.</p>' )
		->and( $nonStringContent['attributes']['_resolvedContent'] )->toBe( '' );
} );

it( 'stamps post-excerpt attributes', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-excerpt', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost()
	);

	expect( $resolved['attributes']['_resolvedExcerpt'] )->toBe( 'A brief excerpt' )
		->and( $resolved['attributes']['_resolvedPermalink'] )->toBe( 'https://example.test/posts/hello' );
} );

it( 'stamps post-date attributes including modified date', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-date', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost()
	);

	expect( $resolved['attributes']['_resolvedDate'] )->toBe( '2026-04-20T12:00:00+00:00' )
		->and( $resolved['attributes']['_resolvedDateFormatted'] )->toBe( 'April 20, 2026' )
		->and( $resolved['attributes']['_resolvedModifiedDate'] )->toBe( '2026-04-21T09:00:00+00:00' )
		->and( $resolved['attributes']['_resolvedModifiedDateFormatted'] )->toBe( 'April 21, 2026' );
} );

it( 'stamps post-author attributes from the loaded relation', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-author', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost()
	);

	expect( $resolved['attributes']['_resolvedAuthorName'] )->toBe( 'Jane Doe' )
		->and( $resolved['attributes']['_resolvedAuthorBio'] )->toBe( 'Writer' )
		->and( $resolved['attributes']['_resolvedAuthorUrl'] )->toBe( 'https://example.test/jane' )
		->and( $resolved['attributes']['_resolvedAuthorAvatar'] )->toBe( 'https://example.test/avatar.jpg' );
} );

it( 'leaves author fields empty when the relation is not loaded', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-author', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'author' => null ] )
	);

	expect( $resolved['attributes']['_resolvedAuthorName'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedAuthorBio'] )->toBe( '' );
} );

it( 'preserves pre-existing _resolved attributes on a block', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'core/post-title',
			'attributes'  => [ '_resolvedTitle' => 'Host override' ],
			'innerBlocks' => [],
		],
		fakePost()
	);

	expect( $resolved['attributes']['_resolvedTitle'] )->toBe( 'Host override' );
} );

it( 'recurses into innerBlocks', function () {
	$tree = [
		'name'        => 'core/group',
		'attributes'  => [],
		'innerBlocks' => [
			[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
		],
	];

	$resolved = ( new PostResolver() )->stampBlock( $tree, fakePost() );

	expect( $resolved['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Hello world' );
} );

it( 'leaves non-post-context blocks untouched', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'untouched' ], 'innerBlocks' => [] ],
		fakePost()
	);

	expect( $resolved['attributes'] )->toBe( [ 'content' => 'untouched' ] );
} );

// Comments-family Pass 2 (#519) — post-level comment metadata.

it( 'stamps post-comments-count from comments_count accessor', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-comments-count', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'comments_count' => 7 ] )
	);

	expect( $resolved['attributes']['_resolvedCommentCount'] )->toBe( 7 );
} );

it( 'stamps post-comments-count by counting a comments collection when no accessor is set', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-comments-count', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [
			'comments_count' => null,
			'comments'       => [ (object) [], (object) [], (object) [] ],
		] )
	);

	expect( $resolved['attributes']['_resolvedCommentCount'] )->toBe( 3 );
} );

it( 'stamps post-comments-count as zero when no count or collection is exposed', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-comments-count', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'comments_count' => null ] )
	);

	expect( $resolved['attributes']['_resolvedCommentCount'] )->toBe( 0 );
} );

it( 'stamps artisanpack/comments-number with the resolved count from comments_count accessor', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[
			'name'        => 'artisanpack/comments-number',
			'attributes'  => [
				'singularCommentText' => 'Reply',
				'pluralCommentText'   => 'Replies',
			],
			'innerBlocks' => [],
		],
		fakePost( [ 'comments_count' => 4 ] )
	);

	expect( $resolved['attributes']['_resolvedCommentCount'] )->toBe( 4 )
		->and( $resolved['attributes']['singularCommentText'] )->toBe( 'Reply' )
		->and( $resolved['attributes']['pluralCommentText'] )->toBe( 'Replies' );
} );

it( 'stamps artisanpack/comments-number as zero when the post exposes neither count nor collection', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/comments-number', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'comments_count' => null ] )
	);

	expect( $resolved['attributes']['_resolvedCommentCount'] )->toBe( 0 );
} );

it( 'stamps post-comments-link with permalink anchor when no explicit URL is set', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-comments-link', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'comments_count' => 2 ] )
	);

	expect( $resolved['attributes']['_resolvedCommentCount'] )->toBe( 2 )
		->and( $resolved['attributes']['_resolvedCommentsUrl'] )->toBe( 'https://example.test/posts/hello#comments' )
		->and( $resolved['attributes']['_resolvedCommentsLabel'] )->toBe( '2 Comments' );
} );

it( 'stamps post-comments-link with the explicit comments URL when present', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-comments-link', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [
			'comments_count' => 1,
			'comments_url'   => 'https://example.test/explicit-comments',
		] )
	);

	expect( $resolved['attributes']['_resolvedCommentsUrl'] )->toBe( 'https://example.test/explicit-comments' )
		->and( $resolved['attributes']['_resolvedCommentsLabel'] )->toBe( '1 Comment' );
} );

it( 'stamps post-comments-title with pluralization', function () {
	$resolver = new PostResolver();

	expect(
		$resolver->stampBlock(
			[ 'name' => 'core/post-comments-title', 'attributes' => [], 'innerBlocks' => [] ],
			fakePost( [ 'comments_count' => 0 ] )
		)['attributes']['_resolvedCommentsTitle']
	)->toBe( 'No Comments' )
		->and(
			$resolver->stampBlock(
				[ 'name' => 'core/post-comments-title', 'attributes' => [], 'innerBlocks' => [] ],
				fakePost( [ 'comments_count' => 1 ] )
			)['attributes']['_resolvedCommentsTitle']
		)->toBe( '1 Comment' )
		->and(
			$resolver->stampBlock(
				[ 'name' => 'core/post-comments-title', 'attributes' => [], 'innerBlocks' => [] ],
				fakePost( [ 'comments_count' => 5 ] )
			)['attributes']['_resolvedCommentsTitle']
		)->toBe( '5 Comments' );
} );

it( 'stamps post-comments-form with the post id so the rendered form posts to the right post', function () {
	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'core/post-comments-form', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'id' => 42 ] )
	);

	expect( $resolved['attributes']['_resolvedPostId'] )->toBe( 42 );

	// Same for the artisanpack/* fork.
	$forked = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-comments-form', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'id' => 42 ] )
	);

	expect( $forked['attributes']['_resolvedPostId'] )->toBe( 42 );
} );

it( 'resolves artisanpack/post-comments-* forks through the same branches as core/*', function () {
	$resolver = new PostResolver();

	$core      = $resolver->stampBlock(
		[ 'name' => 'core/post-comments-count', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'comments_count' => 4 ] )
	);
	$forked    = $resolver->stampBlock(
		[ 'name' => 'artisanpack/post-comments-count', 'attributes' => [], 'innerBlocks' => [] ],
		fakePost( [ 'comments_count' => 4 ] )
	);

	expect( $forked['attributes'] )->toEqual( $core['attributes'] );
} );
