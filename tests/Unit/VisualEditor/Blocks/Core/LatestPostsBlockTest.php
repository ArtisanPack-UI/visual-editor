<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Core\LatestPostsBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

function fakeLatestPost(
	int $id,
	string $title,
	string $slug,
	?string $excerpt = null,
	?Carbon $publishedAt = null,
	?string $authorName = null,
	?string $featuredImageUrl = null
): object {
	$post                     = new stdClass();
	$post->id                 = $id;
	$post->title              = $title;
	$post->permalink          = '/blog/' . $slug;
	$post->excerpt            = $excerpt;
	$post->published_at       = $publishedAt;
	$post->author             = null === $authorName ? null : (object) [ 'name' => $authorName ];
	$post->featured_image_url = $featuredImageUrl;

	return $post;
}

beforeEach( function (): void {
	test()->block = new class extends LatestPostsBlock {
		/** @var Collection<int, object> */
		public Collection $posts;

		protected function fetchPosts( array $attrs ): Collection
		{
			return $this->posts->take( $attrs['postsToShow'] )->values();
		}
	};

	test()->block->posts = new Collection();
} );

it( 'renders a list of post titles linked to their permalinks', function () {
	test()->block->posts = new Collection( [
		fakeLatestPost( 1, 'Hello Laravel', 'hello-laravel' ),
		fakeLatestPost( 2, 'On Livewire', 'on-livewire' ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '<ul class="wp-block-latest-posts__list wp-block-latest-posts">' )
		->and( $html )->toContain( '<a class="wp-block-latest-posts__post-title" href="/blog/hello-laravel">Hello Laravel</a>' )
		->and( $html )->toContain( '<a class="wp-block-latest-posts__post-title" href="/blog/on-livewire">On Livewire</a>' );
} );

it( 'limits the list to postsToShow', function () {
	test()->block->posts = new Collection( [
		fakeLatestPost( 1, 'One', 'one' ),
		fakeLatestPost( 2, 'Two', 'two' ),
		fakeLatestPost( 3, 'Three', 'three' ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'postsToShow' => 2 ] ) );

	expect( substr_count( $html, 'wp-block-latest-posts__post-title' ) )->toBe( 2 )
		->and( $html )->not->toContain( '>Three<' );
} );

it( 'shows the post date when displayPostDate is set', function () {
	test()->block->posts = new Collection( [
		fakeLatestPost( 1, 'Dated', 'dated', null, Carbon::create( 2026, 4, 19 ) ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'displayPostDate' => true ] ) );

	expect( $html )->toContain( '<time datetime="' )
		->and( $html )->toContain( 'class="wp-block-latest-posts__post-date"' )
		->and( $html )->toContain( 'April 19, 2026' )
		->and( $html )->toContain( 'has-dates' );
} );

it( 'shows the author name when displayAuthor is set', function () {
	test()->block->posts = new Collection( [
		fakeLatestPost( 1, 'Byline', 'byline', null, null, 'Jacob Martella' ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'displayAuthor' => true ] ) );

	expect( $html )->toContain( 'wp-block-latest-posts__post-author' )
		->and( $html )->toContain( 'Jacob Martella' )
		->and( $html )->toContain( 'has-author' );
} );

it( 'renders a trimmed excerpt when displayPostContent shows the excerpt', function () {
	$longExcerpt = implode( ' ', array_fill( 0, 60, 'token' ) );
	test()->block->posts = new Collection( [
		fakeLatestPost( 1, 'Lengthy', 'lengthy-post', $longExcerpt ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [
		'displayPostContent' => true,
		'excerptLength'      => 12,
	] ) );

	expect( $html )->toContain( 'wp-block-latest-posts__post-excerpt' )
		->and( $html )->toContain( '…' )
		->and( substr_count( $html, 'token' ) )->toBe( 12 );
} );

it( 'renders the featured image with an optional link', function () {
	test()->block->posts = new Collection( [
		fakeLatestPost( 1, 'Pictured', 'pictured', null, null, null, 'https://example.test/img.jpg' ),
	] );

	$withoutLink = test()->block->render( test()->block->validateAttrs( [ 'displayFeaturedImage' => true ] ) );
	expect( $withoutLink )->toContain( '<img src="https://example.test/img.jpg"' )
		->and( $withoutLink )->toContain( 'wp-block-latest-posts__featured-image' );

	$withLink = test()->block->render( test()->block->validateAttrs( [
		'displayFeaturedImage'   => true,
		'addLinkToFeaturedImage' => true,
	] ) );
	expect( $withLink )->toContain( '<a href="/blog/pictured"' )
		->and( $withLink )->toContain( '<img src="https://example.test/img.jpg"' );
} );

it( 'renders the featured image from the media relation when no direct url is set', function () {
	$post                     = fakeLatestPost( 1, 'Relational', 'relational' );
	$post->featuredImageMedia = (object) [ 'url' => 'https://example.test/from-relation.jpg' ];
	test()->block->posts      = new Collection( [ $post ] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'displayFeaturedImage' => true ] ) );

	expect( $html )->toContain( '<img src="https://example.test/from-relation.jpg"' );
} );

it( 'treats an empty featured image media url as no image (no src="")', function () {
	$post                     = fakeLatestPost( 1, 'Blank media', 'blank-media' );
	$post->featuredImageMedia = (object) [ 'url' => '   ' ];
	test()->block->posts      = new Collection( [ $post ] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'displayFeaturedImage' => true ] ) );

	expect( $html )->not->toContain( '<img' )
		->and( $html )->not->toContain( 'src=""' );
} );

it( 'adds grid layout classes with a clamped column count', function () {
	test()->block->posts = new Collection( [ fakeLatestPost( 1, 'Grid', 'grid' ) ] );

	$html = test()->block->render( test()->block->validateAttrs( [
		'postLayout' => 'grid',
		'columns'    => 99,
	] ) );

	expect( $html )->toContain( 'is-grid' )
		->and( $html )->toContain( 'columns-6' );
} );

it( 'renders an empty shell when there are no posts', function () {
	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '<ul class="wp-block-latest-posts__list wp-block-latest-posts">' )
		->and( $html )->toContain( 'No posts to show.' );
} );

it( 'escapes attacker-controlled title and permalink', function () {
	test()->block->posts = new Collection( [
		fakeLatestPost( 9, '<img src=x onerror=alert(1)>', '"><script>alert(1)</script>' ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->not->toContain( '<img src=x' )
		->and( $html )->not->toContain( '<script>alert(1)</script>' )
		->and( $html )->toContain( '&lt;img' );
} );

it( 'clamps postsToShow and excerptLength in validateAttrs', function () {
	$attrs = test()->block->validateAttrs( [
		'postsToShow'   => 5000,
		'excerptLength' => 5000,
		'columns'       => 0,
	] );

	expect( $attrs['postsToShow'] )->toBe( 100 )
		->and( $attrs['excerptLength'] )->toBe( 100 )
		->and( $attrs['columns'] )->toBe( 1 );
} );

it( 'normalizes categories to a list of integer ids', function () {
	$attrs = test()->block->validateAttrs( [
		'categories' => [ [ 'id' => '3' ], [ 'id' => 5 ], 7 ],
	] );

	expect( $attrs['categories'] )->toBe( [ 3, 5, 7 ] );
} );

it( 'collects post titles for searchableText', function () {
	test()->block->posts = new Collection( [
		fakeLatestPost( 1, 'First', 'first' ),
		fakeLatestPost( 2, 'Second', 'second' ),
	] );

	expect( test()->block->searchableText( [] ) )->toBe( 'First Second' );
} );
