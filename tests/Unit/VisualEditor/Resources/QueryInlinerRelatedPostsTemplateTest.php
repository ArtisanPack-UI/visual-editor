<?php

/**
 * Coverage for the #601 related-posts WYSIWYG parity path: when the
 * saved tree nests an `artisanpack/post-template`, the inliner expands
 * iterations under that template (matching the Query Loop pattern) so
 * per-post variants, grid spans, and masonry packing all flow through
 * the same code path.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.2.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Tests\Fixtures\FakeQueryResolver;

function relatedPostFixture( int $id, string $title ): object
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

function relatedHostFixture( int $id = 1, string $title = 'Host' ): object
{
	$host             = relatedPostFixture( $id, $title );
	$host->post_type  = 'post';

	return $host;
}

function makeRelatedPostsWithTemplateBlock( int $numPosts, array $templateInner, array $postTemplateAttrs = [] ): array
{
	return [
		'name'        => 'artisanpack/related-posts',
		'attributes'  => [ 'numPosts' => $numPosts ],
		'innerBlocks' => [
			[
				'name'        => 'artisanpack/post-template',
				'attributes'  => $postTemplateAttrs,
				'innerBlocks' => $templateInner,
			],
		],
	];
}

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );

	$this->inliner = new QueryInliner( $this->app, new PostResolver() );
} );

it( 'expands related-posts under a nested post-template with one post-template-item per result', function () {
	$this->fake->setItems( [
		relatedPostFixture( 11, 'Alpha' ),
		relatedPostFixture( 12, 'Beta' ),
	] );

	$tree = [ makeRelatedPostsWithTemplateBlock( 2, [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree, relatedHostFixture() );

	$block        = $inlined[0];
	$postTemplate = $block['innerBlocks'][0];
	$items        = $postTemplate['innerBlocks'];

	expect( $block['attributes']['_resolvedItems'] )->toBe( 2 )
		->and( $postTemplate['name'] )->toBe( 'artisanpack/post-template' )
		->and( count( $items ) )->toBe( 2 )
		->and( $items[0]['name'] )->toBe( 'core/post-template-item' )
		->and( $items[0]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Alpha' )
		->and( $items[1]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Beta' );
} );

it( 'keeps the post-template wrapper on zero-result related-posts with empty inner blocks', function () {
	$this->fake->setItems( [] );

	$tree = [ makeRelatedPostsWithTemplateBlock( 3, [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree, relatedHostFixture() );

	$block        = $inlined[0];
	$postTemplate = $block['innerBlocks'][0];

	expect( $block['attributes']['_resolvedItems'] )->toBe( 0 )
		->and( $postTemplate['name'] )->toBe( 'artisanpack/post-template' )
		->and( $postTemplate['innerBlocks'] )->toBe( [] );
} );

it( 'matches the first post-variant under a related-posts post-template', function () {
	$this->fake->setItems( [
		relatedPostFixture( 21, 'Lead' ),
		relatedPostFixture( 22, 'Trailing' ),
	] );

	$variant = [
		'name'       => 'artisanpack/post-variant',
		'attributes' => [
			'matcher'  => [ 'kind' => 'position', 'value' => 'first' ],
			'priority' => 10,
		],
		'innerBlocks' => [
			[ 'name' => 'core/post-title', 'attributes' => [ 'level' => 1 ], 'innerBlocks' => [] ],
		],
	];

	$base = [ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ];

	$tree = [ makeRelatedPostsWithTemplateBlock( 2, [ $base, $variant ] ) ];

	$inlined = $this->inliner->inline( $tree, relatedHostFixture() );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['attributes']['className'] )->toContain( 'is-variant' )
		->and( $items[1]['attributes']['className'] )->not->toContain( 'is-variant' );
} );

it( 'stamps _resolvedGridSpan onto variant iterations when the related-posts post-template is grid layout', function () {
	$this->fake->setItems( [
		relatedPostFixture( 31, 'Hero' ),
	] );

	$variant = [
		'name'       => 'artisanpack/post-variant',
		'attributes' => [
			'matcher'        => [ 'kind' => 'position', 'value' => 'first' ],
			'priority'       => 10,
			'gridColumnSpan' => 2,
			'gridRowSpan'    => 1,
		],
		'innerBlocks' => [
			[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
		],
	];

	$tree = [ makeRelatedPostsWithTemplateBlock( 1, [ $variant ], [ 'layout' => 'grid' ] ) ];

	$inlined = $this->inliner->inline( $tree, relatedHostFixture() );

	$items = $inlined[0]['innerBlocks'][0]['innerBlocks'];

	expect( $items[0]['attributes'] )->toHaveKey( '_resolvedGridSpan' )
		->and( $items[0]['attributes']['_resolvedGridSpan']['columns']['base'] )->toBe( 2 );
} );

it( 'preserves the legacy flat related-posts path (no post-template wrapper) for backward compat', function () {
	$this->fake->setItems( [
		relatedPostFixture( 41, 'Legacy' ),
	] );

	$tree = [
		[
			'name'        => 'artisanpack/related-posts',
			'attributes'  => [ 'numPosts' => 1 ],
			'innerBlocks' => [
				[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
			],
		],
	];

	$inlined = $this->inliner->inline( $tree, relatedHostFixture() );

	$items = $inlined[0]['innerBlocks'];

	expect( $items[0]['name'] )->toBe( 'core/post-template-item' )
		->and( $items[0]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Legacy' );
} );
