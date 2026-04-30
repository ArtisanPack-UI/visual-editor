<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Core\ArchivesBlock;
use Illuminate\Support\Collection;

beforeEach( function (): void {
	test()->block = new class extends ArchivesBlock {
		/** @var Collection<int, array{year: int, month: int|null, count: int}> */
		public Collection $buckets;

		protected function fetchBuckets( string $type ): Collection
		{
			if ( 'yearly' === $type ) {
				return $this->buckets
					->groupBy( 'year' )
					->map( static fn ( Collection $rows ): array => [
						'year'  => (int) $rows->first()['year'],
						'month' => null,
						'count' => $rows->sum( 'count' ),
					] )
					->sortByDesc( 'year' )
					->values();
			}

			return $this->buckets;
		}
	};

	test()->block->buckets = new Collection();
} );

it( 'renders a list of monthly archive links', function () {
	test()->block->buckets = new Collection( [
		[ 'year' => 2026, 'month' => 4, 'count' => 5 ],
		[ 'year' => 2026, 'month' => 3, 'count' => 7 ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '<ul class="wp-block-archives wp-block-archives-list">' )
		->and( $html )->toContain( 'href="' )
		->and( $html )->toContain( '/blog/2026/04' )
		->and( $html )->toContain( '/blog/2026/03' )
		->and( $html )->toContain( 'April 2026' )
		->and( $html )->toContain( 'March 2026' );
} );

it( 'shows post counts when showPostCounts is set', function () {
	test()->block->buckets = new Collection( [
		[ 'year' => 2026, 'month' => 4, 'count' => 5 ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showPostCounts' => true ] ) );

	expect( $html )->toContain( '&nbsp;(5)' );
} );

it( 'collapses months into years when type is yearly', function () {
	test()->block->buckets = new Collection( [
		[ 'year' => 2026, 'month' => 4, 'count' => 5 ],
		[ 'year' => 2026, 'month' => 3, 'count' => 7 ],
		[ 'year' => 2025, 'month' => 12, 'count' => 2 ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [
		'type'           => 'yearly',
		'showPostCounts' => true,
	] ) );

	expect( $html )->toContain( '/blog/2026' )
		->and( $html )->not->toContain( '/blog/2026/' )
		->and( $html )->toContain( '&nbsp;(12)' )
		->and( $html )->toContain( '&nbsp;(2)' );
} );

it( 'renders a dropdown when displayAsDropdown is set', function () {
	test()->block->buckets = new Collection( [
		[ 'year' => 2026, 'month' => 4, 'count' => 5 ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'displayAsDropdown' => true ] ) );

	expect( $html )->toMatch( '/<select id="wp-block-archives-dropdown-[a-f0-9.]+">/' )
		->and( $html )->toContain( '<option value="' )
		->and( $html )->toContain( '/blog/2026/04' )
		->and( $html )->toContain( 'April 2026' );
} );

it( 'gives multiple dropdowns unique ids on the same render pass', function () {
	test()->block->buckets = new Collection( [
		[ 'year' => 2026, 'month' => 4, 'count' => 5 ],
	] );

	$first = test()->block->render( test()->block->validateAttrs( [ 'displayAsDropdown' => true ] ) );
	$second = test()->block->render( test()->block->validateAttrs( [ 'displayAsDropdown' => true ] ) );

	preg_match( '/id="(wp-block-archives-dropdown-[a-f0-9.]+)"/', $first, $firstMatch );
	preg_match( '/id="(wp-block-archives-dropdown-[a-f0-9.]+)"/', $second, $secondMatch );

	expect( $firstMatch[1] ?? null )->not->toBeNull()
		->and( $secondMatch[1] ?? null )->not->toBeNull()
		->and( $firstMatch[1] )->not->toBe( $secondMatch[1] );
} );

it( 'renders an empty-state notice when no buckets exist', function () {
	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '<div class="wp-block-archives">' )
		->and( $html )->toContain( 'No archives' );
} );
