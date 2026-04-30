<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Core\CategoriesBlock;
use Illuminate\Database\Eloquent\Collection;

function fakeCategory( int $id, string $name, string $slug, int $count, ?int $parent = null ): object
{
	$category               = new stdClass();
	$category->id           = $id;
	$category->name         = $name;
	$category->slug         = $slug;
	$category->parent_id    = $parent;
	$category->posts_count  = $count;
	$category->permalink    = '/blog/category/' . $slug;

	return $category;
}

beforeEach( function (): void {
	test()->block = new class extends CategoriesBlock {
		/** @var Collection<int, object> */
		public Collection $categories;

		protected function fetchCategories( array $attrs ): Collection
		{
			$collection = $this->categories;

			if ( $attrs['showOnlyTopLevel'] ) {
				$collection = $collection->filter( static fn ( object $c ): bool => null === $c->parent_id )->values();
			}

			if ( ! $attrs['showEmpty'] ) {
				$collection = $collection->filter( static fn ( object $c ): bool => (int) $c->posts_count > 0 )->values();
			}

			return $collection;
		}
	};

	test()->block->categories = new Collection();
} );

it( 'renders a flat list with post counts', function () {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Laravel', 'laravel', 7 ),
		fakeCategory( 2, 'PHP', 'php', 3 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [
		'showPostCounts' => true,
		'showEmpty'      => true,
	] ) );

	expect( $html )->toContain( '<ul class="wp-block-categories wp-block-categories-list">' )
		->and( $html )->toContain( '<li class="cat-item cat-item-1"><a href="/blog/category/laravel">Laravel</a> (7)</li>' )
		->and( $html )->toContain( '<li class="cat-item cat-item-2"><a href="/blog/category/php">PHP</a> (3)</li>' );
} );

it( 'omits post counts by default', function () {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Laravel', 'laravel', 7 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '<a href="/blog/category/laravel">Laravel</a></li>' )
		->and( $html )->not->toContain( '(7)' );
} );

it( 'filters empty categories unless showEmpty is true', function () {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Laravel', 'laravel', 7 ),
		fakeCategory( 2, 'Empty', 'empty', 0 ),
	] );

	$default = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $default )->toContain( 'cat-item-1' )
		->and( $default )->not->toContain( 'cat-item-2' );

	$withEmpty = test()->block->render( test()->block->validateAttrs( [ 'showEmpty' => true ] ) );

	expect( $withEmpty )->toContain( 'cat-item-1' )
		->and( $withEmpty )->toContain( 'cat-item-2' );
} );

it( 'restricts to top-level categories when showOnlyTopLevel is set', function () {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Frameworks', 'frameworks', 5 ),
		fakeCategory( 2, 'Laravel', 'laravel', 3, 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showOnlyTopLevel' => true ] ) );

	expect( $html )->toContain( 'cat-item-1' )
		->and( $html )->not->toContain( 'cat-item-2' );
} );

it( 'nests children when showHierarchy is set', function () {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Frameworks', 'frameworks', 5 ),
		fakeCategory( 2, 'Laravel', 'laravel', 3, 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showHierarchy' => true ] ) );

	expect( $html )->toContain( 'cat-item-1' )
		->and( $html )->toContain( 'cat-item-2' )
		->and( $html )->toContain( '<ul class="children">' );
} );

it( 'renders an empty shell when no categories survive filters', function () {
	test()->block->categories = new Collection( [
		fakeCategory( 2, 'Empty', 'empty', 0 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( 'cat-item-empty' );
} );

it( 'escapes attacker-controlled name and permalink', function () {
	test()->block->categories = new Collection( [
		fakeCategory( 9, '<img src=x onerror=alert(1)>', 'evil', 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->not->toContain( '<img src=x' )
		->and( $html )->toContain( '&lt;img' );
} );
