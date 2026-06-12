<?php

/**
 * Coverage for {@see QueryInliner}'s handling of the single-post
 * content cluster (#501): `artisanpack/single-content` and
 * `artisanpack/related-posts`. Both blocks route through the same
 * `QueryResolverContract` plumbing as `core/query`, so the failure
 * modes (no runtime, resolver throws) mirror the existing query tests.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Tests\Fixtures\FakeQueryResolver;

function singlePostFixture( int $id, string $title ): object
{
	$post                    = new stdClass();
	$post->id                = $id;
	$post->title             = $title;
	$post->content           = "<p>Body for {$title}.</p>";
	$post->permalink         = "/posts/{$id}";
	$post->author            = null;
	$post->published_at      = null;
	$post->updated_at        = null;
	$post->featured_image_id = null;

	return $post;
}

function makeSingleContentBlock( int $postId, string $postType = 'post', array $inner = [] ): array
{
	return [
		'name'        => 'artisanpack/single-content',
		'attributes'  => [
			'postId'   => $postId,
			'postType' => $postType,
		],
		'innerBlocks' => $inner,
	];
}

function makeRelatedPostsBlock( int $numPosts = 3, array $inner = [] ): array
{
	return [
		'name'        => 'artisanpack/related-posts',
		'attributes'  => [ 'numPosts' => $numPosts ],
		'innerBlocks' => $inner,
	];
}

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );

	$this->inliner = new QueryInliner( $this->app, new PostResolver() );
} );

it( 'expands artisanpack/single-content by stamping the resolved post against the inner tree', function () {
	$this->fake->setItems( [ singlePostFixture( 42, 'Resolved' ) ] );

	$tree = [ makeSingleContentBlock( 42, 'post', [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree );

	$block = $inlined[0];

	expect( $block['attributes']['_resolvedHasPost'] )->toBeTrue()
		->and( count( $block['innerBlocks'] ) )->toBe( 1 )
		->and( $block['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Resolved' );

	expect( $this->fake->lastAttributes )->toBe( [
		'postType' => 'post',
		'include'  => [ 42 ],
		'perPage'  => 1,
	] );
} );

it( 'returns artisanpack/single-content with _resolvedHasPost true and untouched inner when postId is 0 + host post exists', function () {
	$tree = [ makeSingleContentBlock( 0, 'post', [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree, singlePostFixture( 7, 'Host' ) );

	$block = $inlined[0];

	expect( $block['attributes']['_resolvedHasPost'] )->toBeTrue()
		->and( count( $block['innerBlocks'] ) )->toBe( 1 )
		// The inner tree is left for top-level PostResolver to stamp
		// — QueryInliner only stamps when the block carries its own id.
		->and( $block['innerBlocks'][0]['attributes'] )->toBe( [] )
		->and( $this->fake->lastAttributes )->toBeNull();
} );

it( 'returns artisanpack/single-content with _resolvedHasPost false when the resolver returns no rows', function () {
	$this->fake->setItems( [] );

	$tree    = [ makeSingleContentBlock( 99 ) ];
	$inlined = $this->inliner->inline( $tree );

	expect( $inlined[0]['attributes']['_resolvedHasPost'] )->toBeFalse()
		->and( $inlined[0]['innerBlocks'] )->toBe( [] );
} );

it( 'marks artisanpack/single-content with _resolutionError when no resolver is bound', function () {
	$this->app->forgetInstance( QueryResolverContract::class );
	$this->app->offsetUnset( QueryResolverContract::class );

	$tree    = [ makeSingleContentBlock( 5 ) ];
	$inlined = $this->inliner->inline( $tree );

	expect( $inlined[0]['attributes']['_resolutionError'] )->toBe( QueryInliner::ERROR_NO_RUNTIME )
		->and( $inlined[0]['innerBlocks'] )->toBe( [] );
} );

it( 'expands artisanpack/related-posts into one core/post-template-item per resolved post', function () {
	$this->fake->setItems( [
		singlePostFixture( 11, 'Alpha' ),
		singlePostFixture( 12, 'Beta' ),
	] );

	$host          = singlePostFixture( 1, 'Host' );
	$host->post_type = 'post';

	$tree = [ makeRelatedPostsBlock( 2, [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree, $host );

	$block = $inlined[0];

	expect( $block['attributes']['_resolvedItems'] )->toBe( 2 )
		->and( count( $block['innerBlocks'] ) )->toBe( 2 )
		->and( $block['innerBlocks'][0]['name'] )->toBe( 'core/post-template-item' )
		->and( $block['innerBlocks'][1]['name'] )->toBe( 'core/post-template-item' )
		->and( $block['innerBlocks'][0]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Alpha' )
		->and( $block['innerBlocks'][1]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Beta' );

	expect( $this->fake->lastAttributes['postType'] )->toBe( 'post' )
		->and( $this->fake->lastAttributes['perPage'] )->toBe( 2 )
		->and( $this->fake->lastAttributes['exclude'] )->toBe( [ 1 ] );
} );

it( 'returns artisanpack/related-posts with zero items when no host post is in scope', function () {
	$this->fake->setItems( [ singlePostFixture( 99, 'Unused' ) ] );

	$tree    = [ makeRelatedPostsBlock( 3 ) ];
	$inlined = $this->inliner->inline( $tree );

	expect( $inlined[0]['attributes']['_resolvedItems'] )->toBe( 0 )
		->and( $inlined[0]['innerBlocks'] )->toBe( [] )
		->and( $this->fake->lastAttributes )->toBeNull();
} );

it( 'marks artisanpack/related-posts with _resolutionError when no resolver is bound', function () {
	$this->app->forgetInstance( QueryResolverContract::class );
	$this->app->offsetUnset( QueryResolverContract::class );

	$tree    = [ makeRelatedPostsBlock( 3 ) ];
	$inlined = $this->inliner->inline( $tree, singlePostFixture( 1, 'Host' ) );

	expect( $inlined[0]['attributes']['_resolutionError'] )->toBe( QueryInliner::ERROR_NO_RUNTIME );
} );
