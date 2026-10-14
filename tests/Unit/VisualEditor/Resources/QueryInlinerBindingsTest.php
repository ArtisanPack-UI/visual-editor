<?php

/**
 * Per-iteration block binding resolution inside query loops (#850).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.14.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;
use ArtisanPackUI\VisualEditor\Resources\QueryInliner;
use ArtisanPackUI\VisualEditor\Services\Bindings\BindingResolver;
use ArtisanPackUI\VisualEditor\Services\QueryResolverContract;
use ArtisanPackUI\VisualEditor\Support\BlockShape;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\FakeQueryResolver;
use Tests\Fixtures\TestBindingsModel;

/**
 * @param  array<string, array<string, mixed>>  $bindings
 *
 * @return array<string, mixed>
 */
function loopBoundParagraph( array $bindings, string $content = 'Static' ): array
{
	return [
		'name'        => 'core/paragraph',
		'attributes'  => [ 'content' => $content, 'bindings' => $bindings ],
		'innerBlocks' => [],
	];
}

/**
 * @param  array<int, array<string, mixed>>  $template
 *
 * @return array<string, mixed>
 */
function loopQueryBlock( array $template, bool $withPostTemplate = true ): array
{
	return [
		'name'        => 'core/query',
		'attributes'  => [ 'query' => [ 'postType' => 'post', 'perPage' => 10 ] ],
		'innerBlocks' => $withPostTemplate
			? [ [ 'name' => 'core/post-template', 'attributes' => [], 'innerBlocks' => $template ] ]
			: $template,
	];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function loopModel( array $attributes ): TestBindingsModel
{
	$model = new TestBindingsModel();
	$model->setRawAttributes( $attributes, true );

	return $model;
}

/**
 * The bound paragraph's attributes for each iteration of an expanded
 * `core/query` → `core/post-template` block.
 *
 * @param  array<int, array<string, mixed>>  $inlined
 *
 * @return array<int, array<string, mixed>>
 */
function loopIterationAttributes( array $inlined ): array
{
	return array_map(
		static fn ( array $item ): array => $item['innerBlocks'][0]['attributes'],
		$inlined[0]['innerBlocks'][0]['innerBlocks'],
	);
}

beforeEach( function (): void {
	$this->fake = new FakeQueryResolver();
	$this->app->instance( QueryResolverContract::class, $this->fake );

	$this->bindingResolver = $this->app->make( BindingResolver::class );
	$this->inliner         = new QueryInliner(
		$this->app,
		new PostResolver( $this->bindingResolver ),
		null,
		$this->bindingResolver,
	);
} );

it( 'resolves bindings in each post-template iteration against that iteration\'s post', function (): void {
	$this->fake->setItems( [
		loopModel( [ 'id' => 1, 'title' => 'First' ] ),
		loopModel( [ 'id' => 2, 'title' => 'Second' ] ),
	] );

	$inlined = $this->inliner->inline( [ loopQueryBlock( [
		loopBoundParagraph( [ 'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ] ] ),
	] ) ] );

	$attributes = loopIterationAttributes( $inlined );

	expect( $attributes[0]['content'] )->toBe( 'First' )
		->and( $attributes[1]['content'] )->toBe( 'Second' );
} );

it( 'strips the resolved bindings so a later host pass cannot overwrite them', function (): void {
	$this->fake->setItems( [ loopModel( [ 'id' => 1, 'title' => 'First' ] ) ] );

	$inlined = $this->inliner->inline( [ loopQueryBlock( [
		loopBoundParagraph( [ 'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ], 'onEmpty' => 'hide' ] ] ),
	] ) ] );

	$paragraph = $inlined[0]['innerBlocks'][0]['innerBlocks'][0]['innerBlocks'][0];

	expect( BlockShape::readBindings( $paragraph ) )->toBeNull()
		->and( $paragraph['attributes'] )->not->toHaveKey( 'bindings' )
		->and( $paragraph['attributes']['content'] )->toBe( 'First' );
} );

it( 'applies the onEmpty policy when iteration posts are not Eloquent models', function ( string $policy, ?string $expected ): void {
	$post        = new stdClass();
	$post->id    = 1;
	$post->title = 'Plain object title';

	$this->fake->setItems( [ $post ] );

	$inlined = $this->inliner->inline( [ loopQueryBlock( [
		loopBoundParagraph( [ 'content' => [
			'source'      => 'post_core',
			'args'        => [ 'key' => 'title' ],
			'onEmpty'     => $policy,
			'placeholder' => 'n/a',
		] ] ),
	] ) ] );

	$attributes = loopIterationAttributes( $inlined );

	expect( $attributes[0]['content'] )->toBe( $expected )
		->and( $attributes[0] )->not->toHaveKey( 'bindings' );
} )->with( [
	'fallback'    => [ 'fallback', 'Static' ],
	'hide'        => [ 'hide', null ],
	'placeholder' => [ 'placeholder', 'n/a' ],
] );

it( 'resolves bindings per iteration for queries without a post-template', function (): void {
	$this->fake->setItems( [
		loopModel( [ 'id' => 1, 'title' => 'First' ] ),
		loopModel( [ 'id' => 2, 'title' => 'Second' ] ),
	] );

	$inlined = $this->inliner->inline( [ loopQueryBlock( [
		loopBoundParagraph( [ 'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ] ] ),
	], false ) ] );

	expect( $inlined[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'First' )
		->and( $inlined[0]['innerBlocks'][1]['attributes']['content'] )->toBe( 'Second' );
} );

it( 'resolves single-content bindings against the selected post', function (): void {
	$this->fake->setItems( [ loopModel( [ 'id' => 7, 'title' => 'Picked entry' ] ) ] );

	$inlined = $this->inliner->inline( [ [
		'name'        => 'artisanpack/single-content',
		'attributes'  => [ 'postId' => 7, 'postType' => 'post' ],
		'innerBlocks' => [
			loopBoundParagraph( [ 'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ] ] ),
		],
	] ], loopModel( [ 'id' => 1, 'title' => 'Host page' ] ) );

	expect( $inlined[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Picked entry' );
} );

it( 'resolves related-posts bindings per result, with and without a post-template', function ( bool $withPostTemplate ): void {
	$this->fake->setItems( [
		loopModel( [ 'id' => 11, 'title' => 'Alpha' ] ),
		loopModel( [ 'id' => 12, 'title' => 'Beta' ] ),
	] );

	$host            = new stdClass();
	$host->id        = 1;
	$host->post_type = 'post';

	$paragraph = loopBoundParagraph( [ 'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ] ] );

	$inlined = $this->inliner->inline( [ [
		'name'        => 'artisanpack/related-posts',
		'attributes'  => [ 'numPosts' => 2 ],
		'innerBlocks' => $withPostTemplate
			? [ [ 'name' => 'artisanpack/post-template', 'attributes' => [], 'innerBlocks' => [ $paragraph ] ] ]
			: [ $paragraph ],
	] ], $host );

	$items = $withPostTemplate ? $inlined[0]['innerBlocks'][0]['innerBlocks'] : $inlined[0]['innerBlocks'];

	expect( $items[0]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Alpha' )
		->and( $items[1]['innerBlocks'][0]['attributes']['content'] )->toBe( 'Beta' );
} )->with( [ 'post-template' => true, 'flat' => false ] );

it( 'resolves bindings inside an adjacent-post container against the neighbour', function (): void {
	$next = loopModel( [ 'id' => 3, 'title' => 'Next up' ] );
	$post = loopModel( [ 'id' => 2, 'title' => 'Current' ] );
	$post->setRelation( 'next_post', $next );

	$stamped = ( new PostResolver( $this->bindingResolver ) )->stampTree( [ [
		'name'        => 'artisanpack/next-post',
		'attributes'  => [],
		'innerBlocks' => [
			loopBoundParagraph( [ 'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ] ] ),
		],
	] ], $post );

	$paragraph = $stamped[0]['innerBlocks'][0];

	expect( $paragraph['attributes']['content'] )->toBe( 'Next up' )
		->and( $paragraph['attributes'] )->not->toHaveKey( 'bindings' );
} );

it( 'loads relation bindings across a loop\'s results in one query', function (): void {
	$posts = [];

	for ( $i = 1; $i <= 5; $i++ ) {
		$author = Tests\TestUser::query()->create( [
			'name'     => "Author {$i}",
			'email'    => "author{$i}+" . uniqid() . '@example.com',
			'password' => 'secret',
		] );

		$posts[] = TestBindingsModel::query()->create( [
			'title'     => "Post {$i}",
			'status'    => 'published',
			'author_id' => $author->getKey(),
			'content'   => [],
		] );
	}

	$posts = TestBindingsModel::query()->whereKey( array_map( fn ( $post ) => $post->getKey(), $posts ) )->orderBy( 'id' )->get()->all();

	$this->fake->setItems( $posts );

	DB::connection()->enableQueryLog();
	DB::connection()->flushQueryLog();

	$inlined = $this->inliner->inline( [ loopQueryBlock( [
		loopBoundParagraph( [ 'content' => [ 'source' => 'relation', 'args' => [ 'path' => 'author.name' ] ] ] ),
	] ) ] );

	$queries = DB::connection()->getQueryLog();
	DB::connection()->disableQueryLog();

	$attributes = loopIterationAttributes( $inlined );

	expect( $queries )->toHaveCount( 1 )
		->and( array_column( $attributes, 'content' ) )->toBe( [ 'Author 1', 'Author 2', 'Author 3', 'Author 4', 'Author 5' ] );
} );

it( 'leaves bindings for the renderer when no binding resolver is wired', function (): void {
	$this->fake->setItems( [ loopModel( [ 'id' => 1, 'title' => 'First' ] ) ] );

	$inliner = new QueryInliner( $this->app, new PostResolver() );

	$inlined = $inliner->inline( [ loopQueryBlock( [
		loopBoundParagraph( [ 'content' => [ 'source' => 'post_core', 'args' => [ 'key' => 'title' ] ] ] ),
	] ) ] );

	$attributes = loopIterationAttributes( $inlined );

	expect( $attributes[0]['content'] )->toBe( 'Static' )
		->and( $attributes[0] )->toHaveKey( 'bindings' );
} );
