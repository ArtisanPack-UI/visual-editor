<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\CommentResolver;
use Illuminate\Support\Carbon;

beforeEach( function (): void {
	// Pin the locale to English so `translatedFormat( 'F j, Y' )`
	// produces "April 20, 2026" regardless of the host's default
	// locale; tests assert the exact English month name.
	Carbon::setLocale( 'en' );
} );

function fakeComment( array $overrides = [] ): object
{
	$comment             = new stdClass();
	$comment->content    = '<p>Great post!</p>';
	// Pin the timezone to UTC so `toIso8601String()` always emits
	// `+00:00`; tests assert the literal offset.
	$comment->created_at = Carbon::create( 2026, 4, 20, 12, 0, 0, 'UTC' );
	$comment->permalink  = 'https://example.test/posts/hello#comment-7';
	$comment->author     = (object) [
		'name'       => 'Jane Doe',
		'url'        => 'https://example.test/jane',
		'avatar_url' => 'https://example.test/avatar.jpg',
	];
	$comment->edit_link  = 'https://example.test/wp-admin/edit-comment.php?id=7';
	$comment->reply_link = 'https://example.test/posts/hello?replytocom=7';

	foreach ( $overrides as $key => $value ) {
		$comment->{$key} = $value;
	}

	return $comment;
}

it( 'stamps comment-author-name attributes', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'core/comment-author-name', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $resolved['attributes']['_resolvedAuthorName'] )->toBe( 'Jane Doe' )
		->and( $resolved['attributes']['_resolvedAuthorUrl'] )->toBe( 'https://example.test/jane' );
} );

it( 'stamps comment-author-avatar attributes', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'artisanpack/comment-author-avatar', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $resolved['attributes']['_resolvedAvatarUrl'] )->toBe( 'https://example.test/avatar.jpg' )
		->and( $resolved['attributes']['_resolvedAvatarAlt'] )->toBe( 'Jane Doe' );
} );

it( 'stamps comment-content attributes', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'core/comment-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $resolved['attributes']['_resolvedContent'] )->toBe( '<p>Great post!</p>' );
} );

it( 'sanitizes comment-content against stored XSS payloads', function () {
	$resolver = new CommentResolver();

	// Script tags and disallowed structural tags are stripped wholesale.
	$scripted = $resolver->stampBlock(
		[ 'name' => 'artisanpack/comment-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment( [ 'content' => '<p>Hi</p><script>alert(1)</script><iframe src="https://evil"></iframe>' ] )
	);
	expect( $scripted['attributes']['_resolvedContent'] )->toBe( '<p>Hi</p>alert(1)' );

	// Inline event handlers on otherwise-safe tags are stripped.
	$handler = $resolver->stampBlock(
		[ 'name' => 'artisanpack/comment-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment( [ 'content' => '<a href="https://example.test" onclick="alert(1)">link</a>' ] )
	);
	expect( $handler['attributes']['_resolvedContent'] )->toBe( '<a href="https://example.test">link</a>' );

	// `javascript:` URLs on safe tags are neutralized to a harmless anchor.
	$jsUrl = $resolver->stampBlock(
		[ 'name' => 'artisanpack/comment-content', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment( [ 'content' => '<a href="javascript:alert(1)">click</a>' ] )
	);
	expect( $jsUrl['attributes']['_resolvedContent'] )->toBe( '<a href="#">click</a>' );
} );

it( 'stamps comment-date attributes', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'core/comment-date', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $resolved['attributes']['_resolvedDate'] )->toBe( '2026-04-20T12:00:00+00:00' )
		->and( $resolved['attributes']['_resolvedDateFormatted'] )->toBe( 'April 20, 2026' )
		->and( $resolved['attributes']['_resolvedPermalink'] )->toBe( 'https://example.test/posts/hello#comment-7' );
} );

it( 'stamps comment-edit-link attributes', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'core/comment-edit-link', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $resolved['attributes']['_resolvedEditLinkUrl'] )->toBe( 'https://example.test/wp-admin/edit-comment.php?id=7' )
		->and( $resolved['attributes']['_resolvedEditLinkLabel'] )->toBe( 'Edit' );
} );

it( 'stamps comment-reply-link attributes', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'core/comment-reply-link', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $resolved['attributes']['_resolvedReplyLinkUrl'] )->toBe( 'https://example.test/posts/hello?replytocom=7' )
		->and( $resolved['attributes']['_resolvedReplyLinkLabel'] )->toBe( 'Reply' );
} );

it( 'resolves artisanpack/* forks through the same branches', function () {
	$resolver = new CommentResolver();

	$core      = $resolver->stampBlock(
		[ 'name' => 'core/comment-author-name', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);
	$forked    = $resolver->stampBlock(
		[ 'name' => 'artisanpack/comment-author-name', 'attributes' => [], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $forked['attributes'] )->toEqual( $core['attributes'] );
} );

it( 'recursively stamps inner blocks', function () {
	$tree = [
		[
			'name'        => 'core/comment-template',
			'attributes'  => [],
			'innerBlocks' => [
				[ 'name' => 'core/comment-author-name', 'attributes' => [], 'innerBlocks' => [] ],
				[ 'name' => 'core/comment-content', 'attributes' => [], 'innerBlocks' => [] ],
			],
		],
	];

	$stamped = ( new CommentResolver() )->stampTree( $tree, fakeComment() );

	expect( $stamped[0]['innerBlocks'][0]['attributes']['_resolvedAuthorName'] )->toBe( 'Jane Doe' )
		->and( $stamped[0]['innerBlocks'][1]['attributes']['_resolvedContent'] )->toBe( '<p>Great post!</p>' );
} );

it( 'leaves pre-existing _resolved* keys intact', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[
			'name'        => 'core/comment-author-name',
			'attributes'  => [ '_resolvedAuthorName' => 'Existing' ],
			'innerBlocks' => [],
		],
		fakeComment()
	);

	expect( $resolved['attributes']['_resolvedAuthorName'] )->toBe( 'Existing' );
} );

it( 'returns unchanged blocks for unsupported names', function () {
	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'core/paragraph', 'attributes' => [ 'content' => 'x' ], 'innerBlocks' => [] ],
		fakeComment()
	);

	expect( $resolved['attributes'] )->toBe( [ 'content' => 'x' ] );
} );

it( 'is tolerant of missing fields', function () {
	$bareComment             = new stdClass();
	$bareComment->author     = null;
	$bareComment->content    = null;
	$bareComment->created_at = null;

	$resolved = ( new CommentResolver() )->stampBlock(
		[ 'name' => 'core/comment-author-name', 'attributes' => [], 'innerBlocks' => [] ],
		$bareComment
	);

	expect( $resolved['attributes']['_resolvedAuthorName'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedAuthorUrl'] )->toBe( '' );
} );
