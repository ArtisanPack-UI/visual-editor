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

it( 'renders a flat list with post counts', function (): void {
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

it( 'omits post counts by default', function (): void {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Laravel', 'laravel', 7 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '<a href="/blog/category/laravel">Laravel</a></li>' )
		->and( $html )->not->toContain( '(7)' );
} );

it( 'filters empty categories unless showEmpty is true', function (): void {
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

it( 'restricts to top-level categories when showOnlyTopLevel is set', function (): void {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Frameworks', 'frameworks', 5 ),
		fakeCategory( 2, 'Laravel', 'laravel', 3, 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showOnlyTopLevel' => true ] ) );

	expect( $html )->toContain( 'cat-item-1' )
		->and( $html )->not->toContain( 'cat-item-2' );
} );

it( 'nests children when showHierarchy is set', function (): void {
	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Frameworks', 'frameworks', 5 ),
		fakeCategory( 2, 'Laravel', 'laravel', 3, 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showHierarchy' => true ] ) );

	expect( $html )->toContain( 'cat-item-1' )
		->and( $html )->toContain( 'cat-item-2' )
		->and( $html )->toContain( '<ul class="children">' );
} );

it( 'renders an empty shell when no categories survive filters', function (): void {
	test()->block->categories = new Collection( [
		fakeCategory( 2, 'Empty', 'empty', 0 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( 'cat-item-empty' );
} );

it( 'escapes attacker-controlled name and permalink', function (): void {
	test()->block->categories = new Collection( [
		fakeCategory( 9, '<img src=x onerror=alert(1)>', 'evil', 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->not->toContain( '<img src=x' )
		->and( $html )->toContain( '&lt;img' );
} );

/*
|--------------------------------------------------------------------------
| #960 — count style, current term, "All" item, host taxonomies
|--------------------------------------------------------------------------
*/

function visitCategoriesUrl( string $uri ): void
{
	app()->instance( 'request', Illuminate\Http\Request::create( $uri ) );
}

function categoriesBlockWithTotal( int $total ): CategoriesBlock
{
	$block = new class extends CategoriesBlock {
		/** @var Collection<int, object> */
		public Collection $categories;

		public int $total = 0;

		protected function fetchCategories( array $attrs ): Collection
		{
			return $this->withoutEmptyTerms( $this->categories, $attrs );
		}

		protected function countPublishedPosts(): int
		{
			return $this->total;
		}
	};

	$block->categories = new Collection( [
		fakeCategory( 1, 'Engineering', 'engineering', 2 ),
		fakeCategory( 2, 'Design', 'design', 3 ),
	] );
	$block->total      = $total;

	return $block;
}

afterEach( function (): void {
	removeAllFilters( CategoriesBlock::FILTER_TERMS );
	removeAllFilters( CategoriesBlock::FILTER_ALL_URL );
} );

it( 'keeps the inline markup byte-for-byte when no item is current', function (): void {
	visitCategoriesUrl( '/about' );

	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Laravel', 'laravel', 7 ),
		fakeCategory( 2, 'PHP', 'php', 3 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showPostCounts' => true ] ) );

	expect( $html )->toBe(
		'<ul class="wp-block-categories wp-block-categories-list">'
		. '<li class="cat-item cat-item-1"><a href="/blog/category/laravel">Laravel</a> (7)</li>'
		. '<li class="cat-item cat-item-2"><a href="/blog/category/php">PHP</a> (3)</li>'
		. '</ul>',
	);
} );

it( 'moves the count inside the link in badge style', function (): void {
	visitCategoriesUrl( '/about' );

	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Laravel', 'laravel', 7 ),
		fakeCategory( 2, 'PHP', 'php', 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [
		'showPostCounts' => true,
		'countStyle'     => 'badge',
	] ) );

	expect( $html )->toContain(
		'<li class="cat-item cat-item-1"><a href="/blog/category/laravel"><span class="wp-block-categories__name">Laravel</span><span class="wp-block-categories__count">7<span class="screen-reader-text"> posts</span></span></a></li>',
	)->and( $html )->toContain(
		'<span class="wp-block-categories__count">1<span class="screen-reader-text"> post</span></span>',
	);
} );

it( 'renders only the name span in badge style without post counts', function (): void {
	visitCategoriesUrl( '/about' );

	test()->block->categories = new Collection( [ fakeCategory( 1, 'Laravel', 'laravel', 7 ) ] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'countStyle' => 'badge' ] ) );

	expect( $html )->toContain( '<a href="/blog/category/laravel"><span class="wp-block-categories__name">Laravel</span></a></li>' )
		->and( $html )->not->toContain( 'wp-block-categories__count' );
} );

it( 'falls back to the inline count style for an unknown value', function (): void {
	expect( test()->block->validateAttrs( [ 'countStyle' => 'pill' ] )['countStyle'] )->toBe( 'inline' );
} );

it( 'marks the term whose path matches the request as current', function (): void {
	visitCategoriesUrl( '/blog/category/php/?page=2' );

	test()->block->categories = new Collection( [
		fakeCategory( 1, 'Laravel', 'laravel', 7 ),
		fakeCategory( 2, 'PHP', 'php', 3 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '<li class="cat-item cat-item-2 current-cat"><a href="/blog/category/php" aria-current="page">PHP</a></li>' )
		->and( $html )->toContain( '<li class="cat-item cat-item-1"><a href="/blog/category/laravel">Laravel</a></li>' )
		->and( substr_count( $html, 'aria-current' ) )->toBe( 1 );
} );

it( 'marks a query-parameter term as current only when the parameter matches', function (): void {
	addFilter( CategoriesBlock::FILTER_TERMS, static fn ( $terms, string $taxonomy ) => 'package-category' === $taxonomy ? [
		[ 'id' => 1, 'name' => 'Forms', 'url' => '/packages?package-category=forms', 'count' => 2 ],
		[ 'id' => 2, 'name' => 'Media', 'url' => '/packages?package-category=media', 'count' => 1 ],
	] : $terms );

	$attrs = test()->block->validateAttrs( [ 'taxonomy' => 'package-category' ] );

	visitCategoriesUrl( '/packages?package-category=media&query-3-page=2' );
	$onMedia = test()->block->render( $attrs );

	expect( $onMedia )->toContain( '<li class="cat-item cat-item-2 current-cat"><a href="/packages?package-category=media" aria-current="page">Media</a></li>' )
		->and( $onMedia )->toContain( '<li class="cat-item cat-item-1"><a href="/packages?package-category=forms">Forms</a></li>' );

	visitCategoriesUrl( '/packages' );

	expect( test()->block->render( $attrs ) )->not->toContain( 'current-cat' );
} );

it( 'prepends an All item linking to the posts index with the published total', function (): void {
	visitCategoriesUrl( '/blog/category/engineering' );

	$block = categoriesBlockWithTotal( 4 );
	$html  = $block->render( $block->validateAttrs( [ 'showAll' => true, 'showPostCounts' => true ] ) );

	expect( $html )->toStartWith(
		'<ul class="wp-block-categories wp-block-categories-list"><li class="cat-item cat-item-all"><a href="' . url( '/blog' ) . '">All</a> (4)</li><li class="cat-item cat-item-1 current-cat">',
	);
} );

it( 'marks the All item current on the unfiltered list', function (): void {
	visitCategoriesUrl( '/blog?page=3' );

	$block = categoriesBlockWithTotal( 4 );
	$html  = $block->render( $block->validateAttrs( [
		'showAll'        => true,
		'allLabel'       => 'Everything',
		'showPostCounts' => true,
		'countStyle'     => 'badge',
	] ) );

	expect( $html )->toContain(
		'<li class="cat-item cat-item-all current-cat"><a href="' . url( '/blog' ) . '" aria-current="page"><span class="wp-block-categories__name">Everything</span><span class="wp-block-categories__count">4<span class="screen-reader-text"> posts</span></span></a></li>',
	)->and( substr_count( $html, 'current-cat' ) )->toBe( 1 );
} );

it( 'links the All item to the configured posts index', function (): void {
	config()->set( 'visual-editor.categories.posts_index_url', '/news' );
	visitCategoriesUrl( '/about' );

	$block = categoriesBlockWithTotal( 4 );
	$html  = $block->render( $block->validateAttrs( [ 'showAll' => true ] ) );

	expect( $html )->toContain( '<li class="cat-item cat-item-all"><a href="' . url( '/news' ) . '">All</a></li>' );
} );

it( 'lists a host taxonomy through the terms filter', function (): void {
	visitCategoriesUrl( '/packages' );

	addFilter( CategoriesBlock::FILTER_TERMS, static function ( $terms, string $taxonomy, array $attrs ) {
		expect( $attrs['taxonomy'] )->toBe( 'package-category' );

		return 'package-category' === $taxonomy ? [
			[ 'id' => 10, 'name' => 'Forms', 'url' => '/packages/category/forms', 'count' => 2 ],
			[ 'id' => 11, 'name' => 'Inputs', 'url' => '/packages/category/inputs', 'count' => 1, 'parentId' => 10 ],
			[ 'id' => 12, 'name' => 'Empty', 'url' => '/packages/category/empty', 'count' => 0 ],
			[ 'id' => 13, 'name' => 'Evil', 'url' => 'javascript:alert(1)', 'count' => 5 ],
			[ 'name' => 'No id', 'url' => '/packages/category/no-id', 'count' => 5 ],
		] : $terms;
	} );
	addFilter( CategoriesBlock::FILTER_ALL_URL, static fn ( $url, string $taxonomy ) => 'package-category' === $taxonomy
		? [ 'url' => '/packages', 'count' => 3 ]
		: $url );

	$html = test()->block->render( test()->block->validateAttrs( [
		'taxonomy'       => 'package-category',
		'showHierarchy'  => true,
		'showPostCounts' => true,
		'showAll'        => true,
	] ) );

	expect( $html )->toBe(
		'<ul class="wp-block-categories wp-block-categories-list">'
		. '<li class="cat-item cat-item-all current-cat"><a href="/packages" aria-current="page">All</a> (3)</li>'
		. '<li class="cat-item cat-item-10"><a href="/packages/category/forms">Forms</a> (2)'
		. '<ul class="children"><li class="cat-item cat-item-11"><a href="/packages/category/inputs">Inputs</a> (1)</li></ul></li>'
		. '</ul>',
	);
} );

it( 'omits the All item for a host taxonomy without an All URL, and its count without a total', function (): void {
	visitCategoriesUrl( '/about' );

	addFilter( CategoriesBlock::FILTER_TERMS, static fn () => [
		[ 'id' => 1, 'name' => 'Forms', 'url' => '/packages?type=forms', 'count' => 2 ],
	] );

	$attrs = test()->block->validateAttrs( [ 'taxonomy' => 'package-category', 'showAll' => true, 'showPostCounts' => true ] );

	expect( test()->block->render( $attrs ) )->not->toContain( 'cat-item-all' );

	addFilter( CategoriesBlock::FILTER_ALL_URL, static fn () => '/packages' );

	expect( test()->block->render( $attrs ) )->toContain( '<li class="cat-item cat-item-all"><a href="/packages">All</a></li>' );

	visitCategoriesUrl( '/packages?type=media' );

	expect( test()->block->render( $attrs ) )->not->toContain( 'current-cat' );
} );

it( 'renders the empty shell for an unknown taxonomy', function ( mixed $answer ): void {
	addFilter( CategoriesBlock::FILTER_TERMS, static fn () => $answer );

	$html = test()->block->render( test()->block->validateAttrs( [ 'taxonomy' => 'genre', 'showAll' => true ] ) );

	expect( $html )->toBe( '<ul class="wp-block-categories wp-block-categories-list"><li class="cat-item-empty">No categories</li></ul>' );
} )->with( [
	'unanswered'   => [ null ],
	'not a list'   => [ 'genre' ],
	'empty answer' => [ [] ],
] );

it( 'defaults the taxonomy to category', function (): void {
	expect( test()->block->validateAttrs( [] )['taxonomy'] )->toBe( 'category' )
		->and( test()->block->validateAttrs( [ 'taxonomy' => '  ' ] )['taxonomy'] )->toBe( 'category' );
} );

it( 'keeps an empty parent whose child has published posts when nesting', function (): void {
	visitCategoriesUrl( '/packages' );

	addFilter( CategoriesBlock::FILTER_TERMS, static fn ( $terms, string $taxonomy ) => 'package-category' === $taxonomy ? [
		[ 'id' => 20, 'name' => 'Group', 'url' => '/packages?package-category=group', 'count' => 0 ],
		[ 'id' => 21, 'name' => 'Child', 'url' => '/packages?package-category=child', 'count' => 2, 'parentId' => 20 ],
		[ 'id' => 22, 'name' => 'Lonely', 'url' => '/packages?package-category=lonely', 'count' => 0 ],
	] : $terms );

	$nested = test()->block->render( test()->block->validateAttrs( [ 'taxonomy' => 'package-category', 'showHierarchy' => true ] ) );
	$flat   = test()->block->render( test()->block->validateAttrs( [ 'taxonomy' => 'package-category' ] ) );

	expect( $nested )->toContain( 'cat-item-20' )
		->and( $nested )->toContain( 'cat-item-21' )
		->and( $nested )->not->toContain( 'cat-item-22' )
		->and( $flat )->not->toContain( 'cat-item-20' )
		->and( $flat )->toContain( 'cat-item-21' );
} );

it( 'drops host term URLs that hide a script scheme', function ( string $url ): void {
	visitCategoriesUrl( '/packages' );

	addFilter( CategoriesBlock::FILTER_TERMS, static fn ( $terms, string $taxonomy ) => 'package-category' === $taxonomy ? [
		[ 'id' => 30, 'name' => 'Sneaky', 'url' => $url, 'count' => 1 ],
		[ 'id' => 31, 'name' => 'Fine', 'url' => '/packages?package-category=fine', 'count' => 1 ],
	] : $terms );

	$html = test()->block->render( test()->block->validateAttrs( [ 'taxonomy' => 'package-category' ] ) );

	expect( $html )->not->toContain( 'cat-item-30' )
		->and( $html )->toContain( 'cat-item-31' );
} )->with( [
	'unparseable'      => [ 'javascript://:65536/%0aalert(1)' ],
	'tab in scheme'    => [ "java\tscript:alert(1)" ],
	'newline in scheme' => [ "java\nscript:alert(1)" ],
	'data URL'         => [ 'data:text/html,<b>x</b>' ],
] );
