<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use Tests\Fixtures\FakeQueryResolver;

function makeQueryBlock( array $template = [], array $queryAttrs = [ 'postType' => 'post', 'perPage' => 3 ] ): array
{
	return [
		'name'        => 'core/query',
		'attributes'  => [ 'query' => $queryAttrs ],
		'innerBlocks' => [
			[
				'name'        => 'core/post-template',
				'attributes'  => [],
				'innerBlocks' => $template,
			],
		],
	];
}

function postFixture( int $id, string $title ): object
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

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );

	$this->inliner = new QueryInliner( $this->app, new PostResolver() );
} );

it( 'expands core/query into one post-template instance per result', function () {
	$this->fake->setItems( [ postFixture( 1, 'First' ), postFixture( 2, 'Second' ) ] );

	$tree = [ makeQueryBlock( [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree );

	$query = $inlined[0];

	// The query keeps one post-template child; its innerBlocks hold
	// one _query-iteration per result, each wrapping the stamped template.
	$postTemplate = $query['innerBlocks'][0];

	expect( $query['name'] )->toBe( 'core/query' )
		->and( count( $query['innerBlocks'] ) )->toBe( 1 )
		->and( $postTemplate['name'] )->toBe( 'core/post-template' )
		->and( count( $postTemplate['innerBlocks'] ) )->toBe( 2 )
		->and( $postTemplate['innerBlocks'][0]['name'] )->toBe( '_query-iteration' )
		->and( $postTemplate['innerBlocks'][0]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'First' )
		->and( $postTemplate['innerBlocks'][1]['innerBlocks'][0]['attributes']['_resolvedTitle'] )->toBe( 'Second' );
} );

it( 'forwards the nested query attribute payload to the resolver', function () {
	$tree = [ makeQueryBlock( [], [
		'postType' => 'page',
		'perPage'  => 5,
		'orderBy'  => 'title',
	] ) ];

	$this->inliner->inline( $tree );

	expect( $this->fake->lastAttributes )->toBe( [
		'postType' => 'page',
		'perPage'  => 5,
		'orderBy'  => 'title',
	] );
} );

it( 'falls back to top-level attributes when query is not nested', function () {
	$tree = [
		[
			'name'        => 'core/query',
			'attributes'  => [ 'postType' => 'post', 'perPage' => 2 ],
			'innerBlocks' => [],
		],
	];

	$this->inliner->inline( $tree );

	expect( $this->fake->lastAttributes )->toBe( [ 'postType' => 'post', 'perPage' => 2 ] );
} );

it( 'marks core/query with _resolutionError when no resolver is bound', function () {
	$this->app->forgetInstance( QueryResolverContract::class );
	$this->app->offsetUnset( QueryResolverContract::class );

	$tree = [ makeQueryBlock() ];
	$inlined = $this->inliner->inline( $tree );

	expect( $inlined[0]['attributes']['_resolutionError'] )->toBe( QueryInliner::ERROR_NO_RUNTIME )
		->and( $inlined[0]['innerBlocks'] )->toBe( [] );
} );

it( 'marks core/query with _resolutionError when the resolver throws', function () {
	$throwingFake = new class extends FakeQueryResolver {
		public function resolve( array $attributes ): \Illuminate\Contracts\Pagination\LengthAwarePaginator
		{
			throw new \RuntimeException( 'boom' );
		}
	};
	$this->app->instance( QueryResolverContract::class, $throwingFake );
	$this->inliner = new QueryInliner( $this->app, new PostResolver() );

	$tree = [ makeQueryBlock() ];
	$inlined = $this->inliner->inline( $tree );

	expect( $inlined[0]['attributes']['_resolutionError'] )->toBe( QueryInliner::ERROR_RESOLVER_ERROR );
} );

it( 'leaves the inner blocks empty when the result set is empty', function () {
	$tree = [ makeQueryBlock( [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree );

	expect( $inlined[0]['innerBlocks'] )->toBe( [] )
		->and( $inlined[0]['attributes']['_resolvedTotal'] )->toBe( 0 );
} );

it( 'recurses into nested queries', function () {
	$this->fake->setItems( [ postFixture( 1, 'Outer' ) ] );

	$inner = [
		'name'        => 'core/group',
		'attributes'  => [],
		'innerBlocks' => [
			makeQueryBlock( [
				[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
			] ),
		],
	];

	$inlined = $this->inliner->inline( [ $inner ] );

	// The outer wrapper passes through; the nested core/query gets
	// its own expansion pass.
	$nestedQuery = $inlined[0]['innerBlocks'][0];

	expect( $nestedQuery['name'] )->toBe( 'core/query' )
		->and( count( $nestedQuery['innerBlocks'] ) )->toBe( 1 );
} );

it( 'deep-clones the template subtree per result so mutations do not leak', function () {
	$this->fake->setItems( [ postFixture( 1, 'A' ), postFixture( 2, 'B' ) ] );

	$tree = [ makeQueryBlock( [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree );

	// Post-template → _query-iteration[0] → post-title, iteration[1] → post-title.
	$postTemplate = $inlined[0]['innerBlocks'][0];
	$first  = $postTemplate['innerBlocks'][0]['innerBlocks'][0];
	$second = $postTemplate['innerBlocks'][1]['innerBlocks'][0];

	expect( $first['attributes']['_resolvedTitle'] )->toBe( 'A' )
		->and( $second['attributes']['_resolvedTitle'] )->toBe( 'B' );
} );
