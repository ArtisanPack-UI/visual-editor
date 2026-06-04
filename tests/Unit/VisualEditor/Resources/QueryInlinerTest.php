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

it( 'expands core/query into one post-template wrapping N post-template-item blocks', function () {
	$this->fake->setItems( [ postFixture( 1, 'First' ), postFixture( 2, 'Second' ) ] );

	$tree = [ makeQueryBlock( [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree );

	$query = $inlined[0];

	// The query keeps one post-template child; its innerBlocks hold
	// one core/post-template-item per result, each wrapping the
	// stamped template. The synthetic item blocks render as `<li>` so
	// the parent post-template emits a single `<ul>` with N items.
	$postTemplate = $query['innerBlocks'][0];

	expect( $query['name'] )->toBe( 'core/query' )
		->and( count( $query['innerBlocks'] ) )->toBe( 1 )
		->and( $postTemplate['name'] )->toBe( 'core/post-template' )
		->and( count( $postTemplate['innerBlocks'] ) )->toBe( 2 )
		->and( $postTemplate['innerBlocks'][0]['name'] )->toBe( 'core/post-template-item' )
		->and( $postTemplate['innerBlocks'][1]['name'] )->toBe( 'core/post-template-item' )
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

it( 'clears the post-template iterations when the result set is empty but keeps the wrapper', function () {
	// Empty-result behavior changed in #521 so artisanpack/query-no-results
	// siblings can render alongside an empty post-template. The post-template
	// wrapper survives but its inner-block tree is cleared (zero iterations)
	// so the renderer emits an empty `<ul>` rather than rendering N copies
	// of the un-stamped template.
	$tree = [ makeQueryBlock( [
		[ 'name' => 'core/post-title', 'attributes' => [], 'innerBlocks' => [] ],
	] ) ];

	$inlined = $this->inliner->inline( $tree );

	expect( count( $inlined[0]['innerBlocks'] ) )->toBe( 1 )
		->and( $inlined[0]['innerBlocks'][0]['name'] )->toBe( 'core/post-template' )
		->and( $inlined[0]['innerBlocks'][0]['innerBlocks'] )->toBe( [] )
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

	// Post-template → core/post-template-item[0] → post-title, item[1] → post-title.
	$postTemplate = $inlined[0]['innerBlocks'][0];
	$first  = $postTemplate['innerBlocks'][0]['innerBlocks'][0];
	$second = $postTemplate['innerBlocks'][1]['innerBlocks'][0];

	expect( $first['attributes']['_resolvedTitle'] )->toBe( 'A' )
		->and( $second['attributes']['_resolvedTitle'] )->toBe( 'B' );
} );

// --- Query family wiring (#521) -----------------------------------------

/**
 * Build a query block with an `artisanpack/post-template` child plus
 * additional siblings (typically the new query-* control blocks the
 * inliner's filter pass should resolve).
 *
 * @param array<int, array<string, mixed>> $siblings
 */
function makeQueryBlockWithSiblings( array $siblings = [], array $queryAttrs = [ 'postType' => 'post', 'perPage' => 1 ] ): array
{
	return [
		'name'        => 'artisanpack/query',
		'attributes'  => [ 'query' => $queryAttrs ],
		'innerBlocks' => array_merge( [
			[
				'name'        => 'artisanpack/post-template',
				'attributes'  => [],
				'innerBlocks' => [
					[ 'name' => 'artisanpack/post-title', 'attributes' => [], 'innerBlocks' => [] ],
				],
			],
		], $siblings ),
	];
}

it( 'drops artisanpack/query-no-results when the query has results', function () {
	$this->fake->setItems( [ postFixture( 1, 'A' ) ] );

	$noResultsMarkup = [
		'name'        => 'artisanpack/query-no-results',
		'attributes'  => [],
		'innerBlocks' => [
			[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'No matches.' ], 'innerBlocks' => [] ],
		],
	];

	$tree = [ makeQueryBlockWithSiblings( [ $noResultsMarkup ] ) ];

	$inlined = $this->inliner->inline( $tree );

	$names = array_column( $inlined[0]['innerBlocks'], 'name' );

	expect( $names )->toBe( [ 'artisanpack/post-template' ] );
} );

it( 'keeps artisanpack/query-no-results when the query has zero rows', function () {
	$noResultsMarkup = [
		'name'        => 'artisanpack/query-no-results',
		'attributes'  => [],
		'innerBlocks' => [
			[ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => 'No matches.' ], 'innerBlocks' => [] ],
		],
	];

	$tree = [ makeQueryBlockWithSiblings( [ $noResultsMarkup ] ) ];

	$inlined = $this->inliner->inline( $tree );

	$names = array_column( $inlined[0]['innerBlocks'], 'name' );

	expect( $names )->toContain( 'artisanpack/query-no-results' );

	// The empty-state markup survives intact — the inliner only gates
	// the wrapper, not its inner-block tree.
	$noResults = array_values( array_filter(
		$inlined[0]['innerBlocks'],
		static fn ( array $block ): bool => 'artisanpack/query-no-results' === ( $block['name'] ?? '' )
	) )[0];

	expect( $noResults['innerBlocks'][0]['attributes']['content'] )->toBe( 'No matches.' );
} );

it( 'stamps pagination URLs on the next / previous / numbers leaves', function () {
	// Three posts spread over multiple pages so the paginator reports a
	// meaningful next + previous + page range.
	$this->fake->setItems( [ postFixture( 1, 'A' ), postFixture( 2, 'B' ) ] );
	$this->fake->totalOverride = 6;
	$this->fake->perPage       = 2;
	$this->fake->currentPage   = 2;

	$paginationMarkup = [
		'name'        => 'artisanpack/query-pagination',
		'attributes'  => [],
		'innerBlocks' => [
			[ 'name' => 'artisanpack/query-pagination-previous', 'attributes' => [], 'innerBlocks' => [] ],
			[ 'name' => 'artisanpack/query-pagination-numbers', 'attributes' => [], 'innerBlocks' => [] ],
			[ 'name' => 'artisanpack/query-pagination-next', 'attributes' => [], 'innerBlocks' => [] ],
		],
	];

	$tree = [ makeQueryBlockWithSiblings( [ $paginationMarkup ] ) ];

	$inlined = $this->inliner->inline( $tree );

	$pagination = array_values( array_filter(
		$inlined[0]['innerBlocks'],
		static fn ( array $block ): bool => 'artisanpack/query-pagination' === ( $block['name'] ?? '' )
	) )[0];

	$leaves = [];
	foreach ( $pagination['innerBlocks'] as $child ) {
		$leaves[ $child['name'] ] = $child['attributes'];
	}

	expect( $leaves['artisanpack/query-pagination-previous']['_resolvedCurrentPage'] )->toBe( 2 )
		->and( $leaves['artisanpack/query-pagination-previous']['_resolvedTotalPages'] )->toBe( 3 )
		->and( is_string( $leaves['artisanpack/query-pagination-previous']['_resolvedPreviousPageUrl'] ) )->toBeTrue()
		->and( $leaves['artisanpack/query-pagination-next']['_resolvedCurrentPage'] )->toBe( 2 )
		->and( is_string( $leaves['artisanpack/query-pagination-next']['_resolvedNextPageUrl'] ) )->toBeTrue()
		->and( count( $leaves['artisanpack/query-pagination-numbers']['_resolvedPageNumbers'] ) )->toBe( 3 )
		->and( $leaves['artisanpack/query-pagination-numbers']['_resolvedPageNumbers'][0]['number'] )->toBe( 1 )
		->and( $leaves['artisanpack/query-pagination-numbers']['_resolvedPageNumbers'][2]['number'] )->toBe( 3 )
		->and( $leaves['artisanpack/query-pagination-numbers']['_resolvedCurrentPage'] )->toBe( 2 );
} );

it( 'emits an empty previous-page url on page 1 and stamps the next link', function () {
	$this->fake->setItems( [ postFixture( 1, 'A' ) ] );
	$this->fake->totalOverride = 4;
	$this->fake->perPage       = 2;
	$this->fake->currentPage   = 1;

	$paginationMarkup = [
		'name'        => 'artisanpack/query-pagination',
		'attributes'  => [],
		'innerBlocks' => [
			[ 'name' => 'artisanpack/query-pagination-previous', 'attributes' => [], 'innerBlocks' => [] ],
			[ 'name' => 'artisanpack/query-pagination-next', 'attributes' => [], 'innerBlocks' => [] ],
		],
	];

	$tree = [ makeQueryBlockWithSiblings( [ $paginationMarkup ] ) ];

	$inlined = $this->inliner->inline( $tree );

	$pagination = array_values( array_filter(
		$inlined[0]['innerBlocks'],
		static fn ( array $block ): bool => 'artisanpack/query-pagination' === ( $block['name'] ?? '' )
	) )[0];

	$leaves = [];
	foreach ( $pagination['innerBlocks'] as $child ) {
		$leaves[ $child['name'] ] = $child['attributes'];
	}

	expect( $leaves['artisanpack/query-pagination-previous']['_resolvedPreviousPageUrl'] )->toBe( '' )
		->and( $leaves['artisanpack/query-pagination-previous']['_resolvedCurrentPage'] )->toBe( 1 )
		->and( is_string( $leaves['artisanpack/query-pagination-next']['_resolvedNextPageUrl'] ) )->toBeTrue()
		->and( '' )->not->toBe( $leaves['artisanpack/query-pagination-next']['_resolvedNextPageUrl'] );
} );

it( 'stamps query-title with the configured type label', function () {
	$this->fake->setItems( [ postFixture( 1, 'A' ) ] );

	$titleMarkup = [
		'name'        => 'artisanpack/query-title',
		'attributes'  => [ 'type' => 'search' ],
		'innerBlocks' => [],
	];

	$tree = [ makeQueryBlockWithSiblings( [ $titleMarkup ], [
		'postType' => 'post',
		'search'   => 'laravel',
	] ) ];

	$inlined = $this->inliner->inline( $tree );

	$title = array_values( array_filter(
		$inlined[0]['innerBlocks'],
		static fn ( array $block ): bool => 'artisanpack/query-title' === ( $block['name'] ?? '' )
	) )[0];

	expect( $title['attributes']['_resolvedQueryTitle'] )->toContain( 'laravel' );
} );

it( 'stamps post-type query-title even when the result set is empty', function () {
	// No items configured — the resolver returns zero rows but the title
	// should still resolve from the query attributes.
	$titleMarkup = [
		'name'        => 'artisanpack/query-title',
		'attributes'  => [ 'type' => 'post-type' ],
		'innerBlocks' => [],
	];

	$tree = [ makeQueryBlockWithSiblings( [ $titleMarkup ], [ 'postType' => 'page' ] ) ];

	$inlined = $this->inliner->inline( $tree );

	$title = array_values( array_filter(
		$inlined[0]['innerBlocks'],
		static fn ( array $block ): bool => 'artisanpack/query-title' === ( $block['name'] ?? '' )
	) )[0];

	expect( $title['attributes']['_resolvedQueryTitle'] )->toBe( 'Pages' );
} );

it( 'preserves host-stamped _resolvedQueryTitle overrides', function () {
	$this->fake->setItems( [ postFixture( 1, 'A' ) ] );

	$titleMarkup = [
		'name'        => 'artisanpack/query-title',
		// Host has already resolved the title (e.g. via a custom adapter
		// upstream of the inliner) and stamped the attribute — the
		// inliner must not clobber it.
		'attributes'  => [ 'type' => 'archive', '_resolvedQueryTitle' => 'Custom: 2026 Posts' ],
		'innerBlocks' => [],
	];

	$tree = [ makeQueryBlockWithSiblings( [ $titleMarkup ] ) ];

	$inlined = $this->inliner->inline( $tree );

	$title = array_values( array_filter(
		$inlined[0]['innerBlocks'],
		static fn ( array $block ): bool => 'artisanpack/query-title' === ( $block['name'] ?? '' )
	) )[0];

	expect( $title['attributes']['_resolvedQueryTitle'] )->toBe( 'Custom: 2026 Posts' );
} );
