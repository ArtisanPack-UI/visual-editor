<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Blocks\Core\ReviewsBlock;

/*
 * Tests for {@see ReviewsBlock}. The block owns no data of its own — every
 * review payload comes from a contributor hooked to the
 * `ap.visualEditor.reviews.collectReviews` filter. The suite covers the
 * validator, the render happy path (single + multi-source), the empty
 * state that prompts connecting a source, the SSR-safety commitment that
 * no `Review` / `AggregateRating` JSON-LD leaks into the markup, and the
 * "host apps + packages + cms-framework plugins/themes can all
 * contribute" wiring — several `addFilter` callers stacking on the same
 * filter, with priority preserved.
 */

beforeEach( function (): void {
	removeAllFilters( ReviewsBlock::FILTER_COLLECT_REVIEWS );
	test()->block = new ReviewsBlock();
} );

afterEach( function (): void {
	removeAllFilters( ReviewsBlock::FILTER_COLLECT_REVIEWS );
} );

/* ---- name + validateAttrs ---- */

it( 'exposes the artisanpack/reviews name for the block registry', function (): void {
	expect( test()->block->name() )->toBe( 'artisanpack/reviews' );
} );

it( 'clamps limit to 1..24 and columns to 1..4', function (): void {
	$attrs = test()->block->validateAttrs( [ 'limit' => 999, 'columns' => 99 ] );

	expect( $attrs['limit'] )->toBe( 24 )
		->and( $attrs['columns'] )->toBe( 4 );

	$attrs = test()->block->validateAttrs( [ 'limit' => 0, 'columns' => 0 ] );

	expect( $attrs['limit'] )->toBe( 1 )
		->and( $attrs['columns'] )->toBe( 1 );
} );

it( 'defaults layout to grid and only accepts list as the alternative', function (): void {
	expect( test()->block->validateAttrs( [] )['layout'] )->toBe( 'grid' )
		->and( test()->block->validateAttrs( [ 'layout' => 'list' ] )['layout'] )->toBe( 'list' )
		->and( test()->block->validateAttrs( [ 'layout' => 'carousel' ] )['layout'] )->toBe( 'grid' );
} );

it( 'trims the source filter and coerces non-strings to empty', function (): void {
	expect( test()->block->validateAttrs( [ 'source' => '  Google  ' ] )['source'] )->toBe( 'Google' )
		->and( test()->block->validateAttrs( [ 'source' => 42 ] )['source'] )->toBe( '' )
		->and( test()->block->validateAttrs( [] )['source'] )->toBe( '' );
} );

it( 'normalizes string-valued boolean toggles instead of naive-casting (CodeRabbit PR #781)', function (): void {
	// `(bool) "false"` is `true` in PHP because `"false"` is a non-empty
	// string. A saved attribute that round-tripped through JSON / a URL
	// can arrive as the literal string, and would silently flip a
	// hide-stars toggle into show-stars.
	$normalized = test()->block->validateAttrs( [
		'showStars'  => 'false',
		'showSource' => 'FALSE',
		'showDate'   => 'no',
	] );

	expect( $normalized['showStars'] )->toBeFalse()
		->and( $normalized['showSource'] )->toBeFalse()
		->and( $normalized['showDate'] )->toBeFalse();

	$normalized = test()->block->validateAttrs( [
		'showStars'  => 'true',
		'showSource' => 'YES',
		'showDate'   => '1',
	] );

	expect( $normalized['showStars'] )->toBeTrue()
		->and( $normalized['showSource'] )->toBeTrue()
		->and( $normalized['showDate'] )->toBeTrue();
} );

it( 'falls back to the documented default (true) for unrecognized toggle values', function (): void {
	$normalized = test()->block->validateAttrs( [
		'showStars'  => 'maybe',
		'showSource' => new stdClass(),
		'showDate'   => [ 1, 2 ],
	] );

	expect( $normalized['showStars'] )->toBeTrue()
		->and( $normalized['showSource'] )->toBeTrue()
		->and( $normalized['showDate'] )->toBeTrue();
} );

it( 'suppresses stars end-to-end when a string-valued "false" is saved on showStars', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[ 'reviewer' => 'A', 'quote' => 'Q', 'rating' => 5, 'source' => 'Google' ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showStars' => 'false' ] ) );

	expect( $html )->not->toContain( 'wp-block-artisanpack-reviews__rating' )
		->and( $html )->not->toContain( 'Rated 5 out of 5' );
} );

/* ---- Empty state ---- */

it( 'renders the empty-state prompt when no contributor supplies reviews', function (): void {
	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( 'wp-block-artisanpack-reviews--empty' )
		->and( $html )->toContain( 'wp-block-artisanpack-reviews__empty' )
		->and( $html )->toContain( 'Connect a review source' )
		->and( $html )->not->toContain( 'wp-block-artisanpack-reviews__card' );
} );

it( 'renders the empty state when the filter returns a non-array value', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => 'not an array' );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( 'wp-block-artisanpack-reviews--empty' );
} );

it( 'renders the empty state when every returned entry is malformed', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		'not-an-array-entry',
		[ 'rating' => 5 ], // no reviewer, no quote
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( 'wp-block-artisanpack-reviews--empty' );
} );

/* ---- Happy path render ---- */

it( 'renders host-supplied review payloads as review cards', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn ( array $reviews ) => array_merge( $reviews, [
		[
			'reviewer' => 'Jane Doe',
			'quote'    => 'Fantastic service, would use again!',
			'rating'   => 5,
			'source'   => 'Google',
			'date'     => '2026-03-15',
			'url'      => 'https://example.com/review/1',
		],
	] ) );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->toContain( 'wp-block-artisanpack-reviews__card' )
		->and( $html )->toContain( 'Fantastic service, would use again!' )
		->and( $html )->toContain( 'Jane Doe' )
		->and( $html )->toContain( 'wp-block-artisanpack-reviews__rating' )
		->and( $html )->toContain( 'is-filled' )
		->and( $html )->toContain( 'Rated 5 out of 5' )
		->and( $html )->toContain( 'href="https://example.com/review/1"' )
		->and( $html )->toContain( '>Google<' )
		->and( $html )->not->toContain( 'wp-block-artisanpack-reviews--empty' );
} );

it( 'stacks contributions from multiple filter callers (host + package + plugin)', function (): void {
	// Simulate the host app contributing a review.
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn ( array $reviews ) => array_merge( $reviews, [
		[ 'reviewer' => 'Host Reviewer', 'quote' => 'From the host app.', 'rating' => 5, 'source' => 'Host' ],
	] ) );

	// Simulate another package (e.g. an SEO package) contributing a review.
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn ( array $reviews ) => array_merge( $reviews, [
		[ 'reviewer' => 'Package Reviewer', 'quote' => 'From another package.', 'rating' => 4, 'source' => 'Package' ],
	] ) );

	// Simulate a cms-framework plugin/theme registering late at runtime.
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn ( array $reviews ) => array_merge( $reviews, [
		[ 'reviewer' => 'Plugin Reviewer', 'quote' => 'From a cms-framework plugin.', 'rating' => 3, 'source' => 'Plugin' ],
	] ) );

	$html = test()->block->render( test()->block->validateAttrs( [ 'limit' => 10 ] ) );

	expect( $html )->toContain( 'Host Reviewer' )
		->and( $html )->toContain( 'Package Reviewer' )
		->and( $html )->toContain( 'Plugin Reviewer' )
		->and( substr_count( $html, 'wp-block-artisanpack-reviews__card' ) )->toBe( 3 );
} );

it( 'respects the limit attribute against a large contributor pool', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static function ( array $reviews ): array {
		for ( $i = 1; $i <= 10; $i++ ) {
			$reviews[] = [
				'reviewer' => "Reviewer {$i}",
				'quote'    => "Quote {$i}",
				'rating'   => 5,
				'source'   => 'Google',
			];
		}
		return $reviews;
	} );

	$html = test()->block->render( test()->block->validateAttrs( [ 'limit' => 3 ] ) );

	expect( substr_count( $html, 'wp-block-artisanpack-reviews__card' ) )->toBe( 3 );
} );

it( 'filters reviews by the source attribute (case-insensitive)', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn ( array $r ) => [
		[ 'reviewer' => 'A', 'quote' => 'google review', 'rating' => 5, 'source' => 'Google' ],
		[ 'reviewer' => 'B', 'quote' => 'yelp review',   'rating' => 5, 'source' => 'Yelp' ],
		[ 'reviewer' => 'C', 'quote' => 'other google',  'rating' => 5, 'source' => 'GOOGLE' ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'source' => 'google', 'limit' => 10 ] ) );

	expect( $html )->toContain( 'google review' )
		->and( $html )->toContain( 'other google' )
		->and( $html )->not->toContain( 'yelp review' );
} );

it( 'clamps ratings outside 0..5 and hides the stars when rating is 0', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn ( array $r ) => [
		[ 'reviewer' => 'A', 'quote' => 'Q', 'rating' => 99 ],
		[ 'reviewer' => 'B', 'quote' => 'Q', 'rating' => 0 ],
		[ 'reviewer' => 'C', 'quote' => 'Q', 'rating' => -5 ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'limit' => 10 ] ) );

	// A's rating is clamped down to 5, B and C are 0 -> no stars block.
	expect( $html )->toContain( 'Rated 5 out of 5' )
		->and( substr_count( $html, 'wp-block-artisanpack-reviews__rating' ) )->toBe( 1 );
} );

it( 'omits the source badge and date when their toggles are off', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[ 'reviewer' => 'A', 'quote' => 'Q', 'rating' => 5, 'source' => 'Google', 'date' => '2026-01-01' ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'showSource' => false, 'showDate' => false ] ) );

	expect( $html )->not->toContain( '>Google<' )
		->and( $html )->not->toContain( 'wp-block-artisanpack-reviews__date' )
		->and( $html )->not->toContain( '2026-01-01' );
} );

it( 'escapes reviewer, quote, and source content to prevent XSS injection', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[
			'reviewer' => '<script>alert(1)</script>',
			'quote'    => '"><img src=x onerror=alert(1)>',
			'source'   => '<b>evil</b>',
			'rating'   => 5,
		],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->not->toContain( '<script>alert(1)</script>' )
		->and( $html )->not->toContain( '<img src=x' )
		->and( $html )->toContain( '&lt;script&gt;' );
} );

it( 'strips javascript: / data: / vbscript: schemes from url and avatar_url', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[
			'reviewer'   => 'A',
			'quote'      => 'Q',
			'rating'     => 5,
			'source'     => 'Google',
			'url'        => 'javascript:alert(1)',
			'avatar_url' => 'data:text/html,<script>alert(1)</script>',
		],
		[
			'reviewer'   => 'B',
			'quote'      => 'Q2',
			'rating'     => 5,
			'source'     => 'Yelp',
			'url'        => 'VBSCRIPT:msgbox("x")',
			'avatar_url' => 'javascript:alert(2)',
		],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'limit' => 10 ] ) );

	// No dangerous scheme survives — even escaped, `href="javascript:..."`
	// is executable in some browsers, so the URL is dropped entirely and
	// the source badge collapses to a plain span.
	expect( $html )->not->toContain( 'javascript:' )
		->and( $html )->not->toContain( 'JAVASCRIPT:' )
		->and( $html )->not->toContain( 'vbscript:' )
		->and( $html )->not->toContain( 'VBSCRIPT:' )
		->and( $html )->not->toContain( 'data:text/html' )
		->and( $html )->toContain( '<span class="wp-block-artisanpack-reviews__source">Google</span>' )
		->and( $html )->toContain( '<span class="wp-block-artisanpack-reviews__source">Yelp</span>' );
} );

it( 'preserves http, https, and mailto URLs verbatim', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[ 'reviewer' => 'A', 'quote' => 'Q', 'rating' => 5, 'source' => 'S1', 'url' => 'https://example.com/r/1' ],
		[ 'reviewer' => 'B', 'quote' => 'Q', 'rating' => 5, 'source' => 'S2', 'url' => 'http://example.com/r/2' ],
		[ 'reviewer' => 'C', 'quote' => 'Q', 'rating' => 5, 'source' => 'S3', 'url' => 'mailto:reviews@example.com' ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'limit' => 10 ] ) );

	expect( $html )->toContain( 'href="https://example.com/r/1"' )
		->and( $html )->toContain( 'href="http://example.com/r/2"' )
		->and( $html )->toContain( 'href="mailto:reviews@example.com"' );
} );

/* ---- SSR safety: no Review / AggregateRating JSON-LD leaks ---- */

it( 'emits no Review or AggregateRating JSON-LD (issue #763 constraint)', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[ 'reviewer' => 'A', 'quote' => 'A quote', 'rating' => 5, 'source' => 'Google' ],
		[ 'reviewer' => 'B', 'quote' => 'B quote', 'rating' => 4, 'source' => 'Google' ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->not->toContain( 'application/ld+json' )
		->and( $html )->not->toContain( '"Review"' )
		->and( $html )->not->toContain( '"AggregateRating"' )
		->and( $html )->not->toContain( 'schema.org' )
		->and( $html )->not->toContain( 'itemtype' )
		->and( $html )->not->toContain( 'itemscope' )
		->and( $html )->not->toContain( 'itemprop' );
} );

it( 'emits no structured data on the empty state either', function (): void {
	$html = test()->block->render( test()->block->validateAttrs( [] ) );

	expect( $html )->not->toContain( 'application/ld+json' )
		->and( $html )->not->toContain( 'schema.org' );
} );

/* ---- Layout wrapper classes ---- */

it( 'applies grid layout classes with the requested column count', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[ 'reviewer' => 'A', 'quote' => 'Q' ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'layout' => 'grid', 'columns' => 2 ] ) );

	expect( $html )->toContain( 'is-layout-grid' )
		->and( $html )->toContain( 'columns-2' )
		->and( $html )->not->toContain( 'is-layout-list' );
} );

it( 'applies list layout when explicitly requested', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[ 'reviewer' => 'A', 'quote' => 'Q' ],
	] );

	$html = test()->block->render( test()->block->validateAttrs( [ 'layout' => 'list' ] ) );

	expect( $html )->toContain( 'is-layout-list' )
		->and( $html )->not->toContain( 'is-layout-grid' );
} );

/* ---- searchableText ---- */

it( 'exposes reviewer + quote text for the block-tree search index', function (): void {
	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static fn () => [
		[ 'reviewer' => 'Jane Doe', 'quote' => 'A wonderful experience.', 'rating' => 5 ],
		[ 'reviewer' => 'John Doe', 'quote' => 'Would recommend.',        'rating' => 4 ],
	] );

	$text = test()->block->searchableText( [] );

	expect( $text )->toContain( 'Jane Doe' )
		->and( $text )->toContain( 'A wonderful experience.' )
		->and( $text )->toContain( 'John Doe' )
		->and( $text )->toContain( 'Would recommend.' );
} );

it( 'returns an empty searchable-text string when no contributor supplies reviews', function (): void {
	expect( test()->block->searchableText( [] ) )->toBe( '' );
} );

it( 'memoises the collectReviews filter per instance and only fires it once for repeated renders with the same attrs (M-01)', function (): void {
	$calls = 0;

	addFilter( ReviewsBlock::FILTER_COLLECT_REVIEWS, static function ( array $reviews ) use ( &$calls ): array {
		++$calls;

		return array_merge( $reviews, [
			[ 'reviewer' => 'A', 'quote' => 'Q', 'rating' => 5 ],
		] );
	} );

	$attrs = test()->block->validateAttrs( [] );

	test()->block->render( $attrs );
	test()->block->render( $attrs );

	expect( $calls )->toBe( 1 );
} );
