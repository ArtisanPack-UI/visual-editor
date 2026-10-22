<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Resources\ReadingTime;

/*
 * Tests for {@see ReadingTime} (#943) — the estimate behind the
 * `artisanpack/post-reading-time` block and its editor preview.
 */

beforeEach( function (): void {
	removeAllFilters( 'ap.visualEditor.readingTime.wordsPerMinute' );
	removeAllFilters( 'ap.visualEditor.readingTime.minutes' );
} );

afterEach( function (): void {
	removeAllFilters( 'ap.visualEditor.readingTime.wordsPerMinute' );
	removeAllFilters( 'ap.visualEditor.readingTime.minutes' );
} );

function readingPost( mixed $content = null, mixed $blockContent = null ): object
{
	return (object) [ 'content' => $content, 'block_content' => $blockContent ];
}

function paragraphBlock( string $text, array $inner = [] ): array
{
	return [ 'name' => 'artisanpack/paragraph', 'attributes' => [ 'content' => $text, 'className' => 'is-style-lead' ], 'innerBlocks' => $inner ];
}

it( 'counts the words of an HTML content string', function (): void {
	$post = readingPost( '<p>Don’t stop &mdash; it\'s <strong>well-known</strong> café.</p><p>Second</p>' );

	expect( ( new ReadingTime() )->words( $post ) )->toBe( 6 );
} );

it( 'counts the text attributes of a saved block tree, nested blocks included', function (): void {
	$post = readingPost( null, [
		paragraphBlock( 'One two three' ),
		[ 'name' => 'artisanpack/group', 'attributes' => [ 'tagName' => 'section' ], 'innerBlocks' => [
			paragraphBlock( 'four <em>five</em>' ),
			[ 'name' => 'artisanpack/quote', 'attributes' => [ 'citation' => 'six' ], 'innerBlocks' => [] ],
		] ],
	] );

	expect( ( new ReadingTime() )->words( $post ) )->toBe( 6 );
} );

it( 'reads a JSON-encoded block tree', function (): void {
	$post = readingPost( null, json_encode( [ paragraphBlock( 'alpha beta' ) ] ) );

	expect( ( new ReadingTime() )->words( $post ) )->toBe( 2 );
} );

it( 'rounds minutes up at 200 words a minute, with zero for no text', function (): void {
	$words = fn ( int $count ): object => readingPost( implode( ' ', array_fill( 0, $count, 'word' ) ) );

	expect( ( new ReadingTime() )->minutes( $words( 1 ) ) )->toBe( 1 )
		->and( ( new ReadingTime() )->minutes( $words( 200 ) ) )->toBe( 1 )
		->and( ( new ReadingTime() )->minutes( $words( 201 ) ) )->toBe( 2 )
		->and( ( new ReadingTime() )->minutes( readingPost() ) )->toBe( 0 );
} );

it( 'lets hosts change the rate and override the minutes', function (): void {
	$post = readingPost( implode( ' ', array_fill( 0, 300, 'word' ) ) );

	addFilter( 'ap.visualEditor.readingTime.wordsPerMinute', fn (): int => 100 );
	expect( ( new ReadingTime() )->minutes( $post ) )->toBe( 3 );

	addFilter( 'ap.visualEditor.readingTime.minutes', fn ( int $minutes, object $filtered, int $words ): int => 300 === $words ? 12 : $minutes );
	expect( ( new ReadingTime() )->minutes( $post ) )->toBe( 12 );
} );
