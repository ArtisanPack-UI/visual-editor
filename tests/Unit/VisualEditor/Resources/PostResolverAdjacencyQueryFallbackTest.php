<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\PostResolver;

/**
 * #527 — Generic query-fallback for `post-navigation-link` when the host's
 * Post model exposes neither `previous_post` / `next_post` accessors nor any
 * of the other named adjacency conventions. Gated behind
 * `artisanpack.visual-editor.resolver.adjacency.auto_query` so hosts opt in
 * to the extra query per render.
 */

/**
 * Lightweight fake that mimics the surface the fallback inspects on an
 * Eloquent Post model: a `newQuery()` method and a public `published_at`
 * property. The returned builder records the where/orderBy/first calls so
 * the test can both stub the result and assert the query shape.
 */
#[AllowDynamicProperties]
class FakeAdjacencyPost
{
	public ?string $published_at = null;

	public ?object $stubResult = null;

	public ?object $lastBuilder = null;

	public function newQuery(): object
	{
		$this->lastBuilder = new class( $this->stubResult ) {
			public string $whereColumn   = '';
			public string $whereOperator = '';
			public mixed $whereValue     = null;
			public string $orderColumn   = '';
			public string $orderDir      = '';

			public function __construct( private readonly ?object $result )
			{
			}

			public function where( string $column, string $operator, mixed $value ): static
			{
				$this->whereColumn   = $column;
				$this->whereOperator = $operator;
				$this->whereValue    = $value;

				return $this;
			}

			public function orderBy( string $column, string $direction ): static
			{
				$this->orderColumn = $column;
				$this->orderDir    = $direction;

				return $this;
			}

			public function first(): ?object
			{
				return $this->result;
			}
		};

		return $this->lastBuilder;
	}
}

it( 'does not run the query fallback when the config flag is off', function (): void {
	config()->set( 'artisanpack.visual-editor.resolver.adjacency.auto_query', false );

	$post                = new FakeAdjacencyPost();
	$post->published_at  = '2026-01-15 10:00:00';
	$post->stubResult    = (object) [
		'title'     => 'Should not be used',
		'permalink' => 'https://example.test/should-not-be-used',
	];

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedNextUrl'] )->toBe( '' )
		->and( $post->lastBuilder )->toBeNull();
} );

it( 'runs the query fallback for both directions when the config flag is on', function (): void {
	config()->set( 'artisanpack.visual-editor.resolver.adjacency.auto_query', true );

	$previous = (object) [
		'title'     => 'Older post',
		'permalink' => 'https://example.test/posts/older',
	];
	$next = (object) [
		'title'     => 'Newer post',
		'permalink' => 'https://example.test/posts/newer',
	];

	// Two separate fakes so each direction's query result is independent.
	$resolver = new class extends PostResolver {
		public function exposeAdjacent( object $post, string $direction ): ?object
		{
			return $this->adjacentPost( $post, $direction );
		}
	};

	$prevHost                 = new FakeAdjacencyPost();
	$prevHost->published_at   = '2026-01-15 10:00:00';
	$prevHost->stubResult     = $previous;

	$nextHost                 = new FakeAdjacencyPost();
	$nextHost->published_at   = '2026-01-15 10:00:00';
	$nextHost->stubResult     = $next;

	expect( $resolver->exposeAdjacent( $prevHost, 'previous' ) )->toBe( $previous )
		->and( $prevHost->lastBuilder->whereColumn )->toBe( 'published_at' )
		->and( $prevHost->lastBuilder->whereOperator )->toBe( '<' )
		->and( $prevHost->lastBuilder->whereValue )->toBe( '2026-01-15 10:00:00' )
		->and( $prevHost->lastBuilder->orderColumn )->toBe( 'published_at' )
		->and( $prevHost->lastBuilder->orderDir )->toBe( 'desc' );

	expect( $resolver->exposeAdjacent( $nextHost, 'next' ) )->toBe( $next )
		->and( $nextHost->lastBuilder->whereOperator )->toBe( '>' )
		->and( $nextHost->lastBuilder->orderDir )->toBe( 'asc' );
} );

it( 'stamps both adjacent links through the query fallback when the flag is on', function (): void {
	config()->set( 'artisanpack.visual-editor.resolver.adjacency.auto_query', true );

	$post                = new FakeAdjacencyPost();
	$post->published_at  = '2026-01-15 10:00:00';
	$post->stubResult    = (object) [
		'title'     => 'Adjacent post',
		'permalink' => 'https://example.test/posts/adjacent',
	];

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( 'https://example.test/posts/adjacent' )
		->and( $resolved['attributes']['_resolvedPrevTitle'] )->toBe( 'Adjacent post' )
		->and( $resolved['attributes']['_resolvedNextUrl'] )->toBe( 'https://example.test/posts/adjacent' )
		->and( $resolved['attributes']['_resolvedNextTitle'] )->toBe( 'Adjacent post' );
} );

it( 'skips the query fallback when the model lacks newQuery()', function (): void {
	config()->set( 'artisanpack.visual-editor.resolver.adjacency.auto_query', true );

	$post                = new stdClass();
	$post->published_at  = '2026-01-15 10:00:00';

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedNextUrl'] )->toBe( '' );
} );

it( 'skips the query fallback when published_at is missing', function (): void {
	config()->set( 'artisanpack.visual-editor.resolver.adjacency.auto_query', true );

	$post = new FakeAdjacencyPost();
	// published_at intentionally left null.

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedNextUrl'] )->toBe( '' )
		->and( $post->lastBuilder )->toBeNull();
} );

it( 'prefers the named accessor over the query fallback when both are available', function (): void {
	config()->set( 'artisanpack.visual-editor.resolver.adjacency.auto_query', true );

	$post                = new FakeAdjacencyPost();
	$post->published_at  = '2026-01-15 10:00:00';
	$post->stubResult    = (object) [
		'title'     => 'Should not win',
		'permalink' => 'https://example.test/should-not-win',
	];
	$post->previous_post = (object) [
		'title'     => 'Explicit previous',
		'permalink' => 'https://example.test/posts/explicit-previous',
	];

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( 'https://example.test/posts/explicit-previous' )
		->and( $resolved['attributes']['_resolvedPrevTitle'] )->toBe( 'Explicit previous' );
} );

it( 'returns empty stamps when the query fallback finds no adjacent row', function (): void {
	config()->set( 'artisanpack.visual-editor.resolver.adjacency.auto_query', true );

	$post                = new FakeAdjacencyPost();
	$post->published_at  = '2026-01-15 10:00:00';
	$post->stubResult    = null;

	$resolved = ( new PostResolver() )->stampBlock(
		[ 'name' => 'artisanpack/post-navigation-link', 'attributes' => [], 'innerBlocks' => [] ],
		$post
	);

	expect( $resolved['attributes']['_resolvedPrevUrl'] )->toBe( '' )
		->and( $resolved['attributes']['_resolvedNextUrl'] )->toBe( '' );
} );
