<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use Illuminate\Support\Carbon;

function fakePost( array $overrides = [] ): object
{
	$post                    = new stdClass();
	$post->title             = 'Hello world';
	$post->content           = '<p>Body.</p>';
	$post->excerpt           = 'A brief excerpt';
	$post->published_at      = Carbon::create( 2026, 4, 20, 12 );
	$post->updated_at        = Carbon::create( 2026, 4, 21, 9 );
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
