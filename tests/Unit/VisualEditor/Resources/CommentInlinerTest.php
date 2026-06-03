<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\CommentInliner;
use ArtisanPackUI\VisualEditor\Resources\CommentResolver;
use Illuminate\Support\Carbon;

beforeEach( function (): void {
	Carbon::setLocale( 'en' );
} );

function fakeComments( int $count ): array
{
	$comments = [];
	for ( $i = 1; $i <= $count; $i++ ) {
		$comment             = new stdClass();
		$comment->id         = $i;
		$comment->content    = "<p>Comment {$i}</p>";
		$comment->created_at = Carbon::create( 2026, 4, 20, 12, 0, 0, 'UTC' );
		$comment->permalink  = "https://example.test/posts/hello#comment-{$i}";
		$comment->author     = ( object ) [
			'name'       => "Commenter {$i}",
			'url'        => "https://example.test/u/{$i}",
			'avatar_url' => "https://example.test/a/{$i}.jpg",
		];
		$comment->edit_link  = "https://example.test/wp-admin/edit-comment.php?id={$i}";
		$comment->reply_link = "https://example.test/posts/hello?replytocom={$i}";

		$comments[] = $comment;
	}
	return $comments;
}

function fakeCommentablePost( array $overrides = [] ): object
{
	$post                 = new stdClass();
	$post->id             = 1;
	$post->permalink      = 'https://example.test/posts/hello';
	$post->comments_count = null;
	$post->comments_url   = null;
	$post->comments       = [];

	foreach ( $overrides as $key => $value ) {
		$post->{$key} = $value;
	}

	return $post;
}

function commentsTreeWithTemplate(): array
{
	return [
		[
			'name'        => 'artisanpack/comments',
			'attributes'  => [],
			'innerBlocks' => [
				[
					'name'        => 'artisanpack/post-comments-title',
					'attributes'  => [],
					'innerBlocks' => [],
				],
				[
					'name'        => 'artisanpack/comment-template',
					'attributes'  => [],
					'innerBlocks' => [
						[ 'name' => 'artisanpack/comment-author-name', 'attributes' => [], 'innerBlocks' => [] ],
						[ 'name' => 'artisanpack/comment-content',     'attributes' => [], 'innerBlocks' => [] ],
					],
				],
				[
					'name'        => 'artisanpack/post-comments-form',
					'attributes'  => [],
					'innerBlocks' => [],
				],
			],
		],
	];
}

it( 'marks the comments block as unresolved when no post is supplied', function () {
	$inliner = new CommentInliner( new CommentResolver() );
	$out     = $inliner->inline( commentsTreeWithTemplate() );

	expect( $out[0]['attributes']['_resolutionError'] )
		->toBe( CommentInliner::ERROR_NO_POST_CONTEXT );
} );

it( 'stamps post-level _resolved* attributes onto the wrapper', function () {
	$post = fakeCommentablePost( [
		'comments_count' => 3,
		'comments'       => fakeComments( 3 ),
	] );

	$out = ( new CommentInliner( new CommentResolver() ) )->inline( commentsTreeWithTemplate(), $post );

	$attrs = $out[0]['attributes'];
	expect( $attrs['_resolvedPostId'] )->toBe( 1 )
		->and( $attrs['_resolvedCommentCount'] )->toBe( 3 )
		->and( $attrs['_resolvedCommentsUrl'] )->toBe( 'https://example.test/posts/hello#comments' )
		->and( $attrs['_resolvedCommentsLabel'] )->toBe( '3 Comments' );
} );

it( 'clones the comment-template once per comment and stamps each iteration', function () {
	$post = fakeCommentablePost( [
		'comments' => fakeComments( 2 ),
	] );

	$out = ( new CommentInliner( new CommentResolver() ) )->inline( commentsTreeWithTemplate(), $post );

	// Find the comment-template inside the expanded wrapper.
	$template = null;
	foreach ( $out[0]['innerBlocks'] as $child ) {
		if ( 'artisanpack/comment-template' === $child['name'] ) {
			$template = $child;
			break;
		}
	}

	expect( $template )->not->toBeNull()
		->and( $template['innerBlocks'] )->toHaveCount( 2 )
		->and( $template['innerBlocks'][0]['name'] )->toBe( 'artisanpack/comment-template-item' )
		->and( $template['innerBlocks'][0]['attributes']['commentId'] )->toBe( 1 );

	// Each iteration's author-name leaf must be stamped against its comment.
	$firstIterationAuthor = $template['innerBlocks'][0]['innerBlocks'][0];
	expect( $firstIterationAuthor['name'] )->toBe( 'artisanpack/comment-author-name' )
		->and( $firstIterationAuthor['attributes']['_resolvedAuthorName'] )->toBe( 'Commenter 1' );

	$secondIterationAuthor = $template['innerBlocks'][1]['innerBlocks'][0];
	expect( $secondIterationAuthor['attributes']['_resolvedAuthorName'] )->toBe( 'Commenter 2' );
} );

it( 'forwards post-level _resolved* attributes to non-template children', function () {
	$post = fakeCommentablePost( [
		'comments_count' => 4,
		'comments'       => fakeComments( 4 ),
	] );

	$out = ( new CommentInliner( new CommentResolver() ) )->inline( commentsTreeWithTemplate(), $post );

	// post-comments-form should inherit _resolvedPostId; post-comments-title
	// should inherit _resolvedCommentCount + _resolvedCommentsLabel.
	$form  = null;
	$title = null;
	foreach ( $out[0]['innerBlocks'] as $child ) {
		if ( 'artisanpack/post-comments-form' === $child['name'] ) {
			$form = $child;
		}
		if ( 'artisanpack/post-comments-title' === $child['name'] ) {
			$title = $child;
		}
	}

	expect( $form['attributes']['_resolvedPostId'] )->toBe( 1 )
		->and( $title['attributes']['_resolvedCommentCount'] )->toBe( 4 );
} );

it( 'collapses the template to empty when the post has no comments', function () {
	$post = fakeCommentablePost( [
		'comments' => [],
	] );

	$out = ( new CommentInliner( new CommentResolver() ) )->inline( commentsTreeWithTemplate(), $post );

	$template = null;
	foreach ( $out[0]['innerBlocks'] as $child ) {
		if ( 'artisanpack/comment-template' === $child['name'] ) {
			$template = $child;
			break;
		}
	}

	expect( $template['innerBlocks'] )->toBeEmpty()
		->and( $out[0]['attributes']['_resolvedCommentCount'] )->toBe( 0 )
		->and( $out[0]['attributes']['_resolvedCommentsLabel'] )->toBe( '0 Comments' );
} );

it( 'does not mutate the original tree on subsequent passes', function () {
	$tree = commentsTreeWithTemplate();
	$post = fakeCommentablePost( [
		'comments' => fakeComments( 2 ),
	] );

	$inliner = new CommentInliner( new CommentResolver() );

	$first  = $inliner->inline( $tree, $post );
	$second = $inliner->inline( $tree, $post );

	// The original tree must still have the un-expanded template — the
	// inliner copies on write rather than mutating shared state.
	$template = $tree[0]['innerBlocks'][1];
	expect( $template['name'] )->toBe( 'artisanpack/comment-template' )
		->and( $template['innerBlocks'] )->toHaveCount( 2 )
		->and( $template['innerBlocks'][0]['name'] )->toBe( 'artisanpack/comment-author-name' );

	// Both runs share the same input tree and must produce the same
	// stamped iterations — proves the inliner copies on write
	// rather than relying on mutated shared state. The author-name
	// leaf lives under: comments → comment-template →
	// comment-template-item (synthetic wrapper) → comment-author-name.
	$firstAuthor  = $first[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attributes']['_resolvedAuthorName'] ?? null;
	$secondAuthor = $second[0]['innerBlocks'][1]['innerBlocks'][0]['innerBlocks'][0]['attributes']['_resolvedAuthorName'] ?? null;
	expect( $firstAuthor )->toBe( 'Commenter 1' )
		->and( $secondAuthor )->toBe( 'Commenter 1' );
	expect( $second[0]['innerBlocks'][1]['innerBlocks'] )->toHaveCount( 2 );
} );

it( 'iterates iterable comment sources (e.g. Eloquent collections)', function () {
	$post           = fakeCommentablePost();
	$post->comments = new ArrayIterator( fakeComments( 2 ) );

	$out = ( new CommentInliner( new CommentResolver() ) )->inline( commentsTreeWithTemplate(), $post );

	$template = $out[0]['innerBlocks'][1];
	expect( $template['innerBlocks'] )->toHaveCount( 2 );
} );
