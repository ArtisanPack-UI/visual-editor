<?php

/**
 * Unit tests for {@see UrlSanitizer}.
 *
 * The cases live in `packages/renderer-markup-parity/url-sanitizer-cases.json`,
 * which the React / Vue `safeUrl()` suite reads too, so the twins agree.
 *
 * @since 1.15.0
 */

declare( strict_types=1 );

use ArtisanPackUI\VisualEditorRendererBlade\Support\UrlSanitizer;

/**
 * @return array<string, array{0: string, 1: string}>
 */
function urlSanitizerCases(): array
{
	$json = json_decode(
		(string) file_get_contents( dirname( __DIR__, 3 ) . '/renderer-markup-parity/url-sanitizer-cases.json' ),
		true,
		512,
		JSON_THROW_ON_ERROR,
	);

	$cases = [];

	foreach ( $json['cases'] as $index => [ $input, $expected ] ) {
		$cases[ sprintf( '#%d %s', $index, json_encode( $input ) ) ] = [ $input, $expected ];
	}

	return $cases;
}

it( 'keeps relative and safe-scheme URLs and drops everything else', function ( string $input, string $expected ): void {
	expect( UrlSanitizer::safe( $input ) )->toBe( $expected );
} )->with( urlSanitizerCases() );

it( 'drops URLs a browser runs as javascript: but parse_url() misreads', function ( string $input ): void {
	expect( UrlSanitizer::safe( $input ) )->toBe( '' );
} )->with( [
	'empty authority'    => 'javascript:///%0aalert(1)',
	'host and port'      => 'javascript:1/%0aalert(1)',
	'tab in the scheme'  => "java\tscript:alert(1)",
	'leading C0 control' => "\x01javascript:alert(1)",
] );

it( 'reads the scheme the way a browser does', function (): void {
	expect( UrlSanitizer::scheme( "\x0c JaVa\nScript:x" ) )->toBe( 'javascript' )
		->and( UrlSanitizer::scheme( '/relative:path' ) )->toBeNull()
		->and( UrlSanitizer::scheme( '1abc:x' ) )->toBeNull();
} );
