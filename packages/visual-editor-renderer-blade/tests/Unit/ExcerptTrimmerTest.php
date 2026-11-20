<?php

/**
 * Unit tests for {@see ExcerptTrimmer} (#1139).
 *
 * The cases live in `packages/renderer-markup-parity/excerpt-trim-cases.json`,
 * which the React / Vue `trimExcerpt()` suite reads too, so the twins agree.
 *
 * @since 1.22.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\ExcerptTrimmer;

/**
 * @return array<string, array{0: string, 1: mixed, 2: string}>
 */
function excerptTrimCases(): array
{
	$json = json_decode(
		(string) file_get_contents( dirname( __DIR__, 3 ) . '/renderer-markup-parity/excerpt-trim-cases.json' ),
		true,
		512,
		JSON_THROW_ON_ERROR,
	);

	$cases = [];

	foreach ( $json['cases'] as $index => [ $html, $length, $expected ] ) {
		$cases[ sprintf( '#%d %s', $index, json_encode( $length ) ) ] = [ $html, $length, $expected ];
	}

	return $cases;
}

it( 'word-trims the excerpt like wp_trim_words()', function ( string $html, mixed $length, string $expected ): void {
	expect( ExcerptTrimmer::trim( $html, $length ) )->toBe( $expected );
} )->with( excerptTrimCases() );

it( 'is a no-op on an excerpt it already trimmed', function (): void {
	$once = ExcerptTrimmer::trim( str_repeat( 'word ', 80 ), 20 );

	expect( ExcerptTrimmer::trim( $once, 20 ) )->toBe( $once );
} );
