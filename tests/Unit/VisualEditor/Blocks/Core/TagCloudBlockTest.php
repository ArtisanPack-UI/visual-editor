<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Core\TagCloudBlock;
use Illuminate\Support\Collection;

function fakeTag( int $id, string $name, string $slug, int $count ): object
{
	$tag              = new stdClass();
	$tag->id          = $id;
	$tag->name        = $name;
	$tag->slug        = $slug;
	$tag->posts_count = $count;
	$tag->permalink   = '/blog/tag/' . $slug;

	return $tag;
}

beforeEach( function (): void {
	test()->block = new class extends TagCloudBlock {
		/** @var Collection<int, object> */
		public Collection $tags;

		protected function fetchTags( array $attrs ): Collection
		{
			return $this->tags
				->take( $attrs['numberOfTags'] )
				->filter( static fn ( object $tag ): bool => (int) $tag->posts_count > 0 )
				->values();
		}
	};

	test()->block->tags = new Collection();
} );

it( 'renders a paragraph with tag links', function () {
	test()->block->tags = new Collection( [
		fakeTag( 1, 'Laravel', 'laravel', 10 ),
		fakeTag( 2, 'PHP', 'php', 5 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toStartWith( '<p class="wp-block-tag-cloud">' )
		->and( $html )->toContain( 'href="/blog/tag/laravel"' )
		->and( $html )->toContain( '>Laravel</a>' )
		->and( $html )->toContain( 'data-wp-tag-count="10"' );
} );

it( 'scales font sizes between smallest and largest', function () {
	test()->block->tags = new Collection( [
		fakeTag( 1, 'Big', 'big', 100 ),
		fakeTag( 2, 'Small', 'small', 1 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [
		'smallestFontSize' => '10pt',
		'largestFontSize'  => '20pt',
	] ) );

	// Tag with the highest count gets the largest size; lowest gets smallest.
	expect( $html )->toContain( 'style="font-size: 20pt"' )
		->and( $html )->toContain( 'style="font-size: 10pt"' );
} );

it( 'collapses to smallest size when all tags have the same count', function () {
	test()->block->tags = new Collection( [
		fakeTag( 1, 'A', 'a', 5 ),
		fakeTag( 2, 'B', 'b', 5 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [
		'smallestFontSize' => '8pt',
		'largestFontSize'  => '22pt',
	] ) );

	// Both tags should hit the smallest size; the largest size should not appear.
	expect( $html )->toContain( 'font-size: 8pt' )
		->and( $html )->not->toContain( 'font-size: 22pt' );
} );

it( 'omits empty tags', function () {
	test()->block->tags = new Collection( [
		fakeTag( 1, 'A', 'a', 5 ),
		fakeTag( 2, 'Empty', 'empty', 0 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( '>A</a>' )
		->and( $html )->not->toContain( '>Empty</a>' );
} );

it( 'shows tag counts when showTagCounts is set', function () {
	test()->block->tags = new Collection( [
		fakeTag( 1, 'Laravel', 'laravel', 7 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showTagCounts' => true ] ) );

	expect( $html )->toContain( '<span class="tag-link-count">(7)</span>' );
} );

it( 'caps numberOfTags between 1 and 100', function () {
	$normalised = test()->block->validateAttrs( [ 'numberOfTags' => 1000 ] );
	expect( $normalised['numberOfTags'] )->toBe( 100 );

	$normalised = test()->block->validateAttrs( [ 'numberOfTags' => -5 ] );
	expect( $normalised['numberOfTags'] )->toBe( 1 );
} );

it( 'renders an empty paragraph when no tags exist', function () {
	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toBe( '<p class="wp-block-tag-cloud"></p>' );
} );

it( 'escapes attacker-controlled tag names', function () {
	test()->block->tags = new Collection( [
		fakeTag( 1, '<script>alert(1)</script>', 'evil', 3 ),
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->not->toContain( '<script>alert(1)</script>' )
		->and( $html )->toContain( '&lt;script&gt;' );
} );
