<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Support\VariableFontMetadataParser;
use Tests\Fixtures\Fonts\FontBinaryFactory;

/**
 * Absolute path to a committed binary font fixture.
 */
function fontFixture( string $name ): string
{
	return file_get_contents( __DIR__ . '/../../../Fixtures/Fonts/files/' . $name );
}

beforeEach( function (): void {
	$this->parser = new VariableFontMetadataParser();
} );

it( 'reads fvar axes and axis names from a variable TTF fixture', function (): void {
	$result = $this->parser->parse( fontFixture( 'variable.ttf' ) );

	expect( $result['is_variable'] )->toBeTrue()
		->and( $result['axes'] )->toHaveKeys( [ 'wght', 'opsz' ] )
		->and( $result['axes']['wght'] )->toBe( [
			'min'     => 100.0,
			'max'     => 900.0,
			'default' => 400.0,
			'name'    => 'Weight',
		] )
		->and( $result['axes']['opsz'] )->toBe( [
			'min'     => 8.0,
			'max'     => 144.0,
			'default' => 14.0,
			'name'    => 'Optical Size',
		] );
} );

it( 'parses a CFF-flavored (OTF) variable font', function (): void {
	$result = $this->parser->parse( fontFixture( 'variable.otf' ) );

	expect( $result['is_variable'] )->toBeTrue()
		->and( $result['axes'] )->toHaveKey( 'wght' );
} );

it( 'inflates and parses a WOFF-wrapped variable font', function (): void {
	$result = $this->parser->parse( fontFixture( 'variable.woff' ) );

	expect( $result['is_variable'] )->toBeTrue()
		->and( $result['axes']['wght']['max'] )->toBe( 900.0 );
} );

it( 'falls back to non-variable for a static font with no fvar table', function (): void {
	$result = $this->parser->parse( fontFixture( 'static.ttf' ) );

	expect( $result )->toBe( [ 'is_variable' => false, 'axes' => [] ] );
} );

it( 'returns the non-variable fallback for empty or junk content without throwing', function ( string $content ): void {
	$result = $this->parser->parse( $content );

	expect( $result )->toBe( [ 'is_variable' => false, 'axes' => [] ] );
} )->with( [
	'empty'          => '',
	'short'          => 'ab',
	'not a font'     => 'this is plainly not a font file at all',
	'truncated sfnt' => "\x00\x01\x00\x00\x00\x02",
] );

it( 'reads a custom axis with a negative minimum (slant)', function (): void {
	$bytes = FontBinaryFactory::variableTtf( [
		'slnt' => [ 'min' => -15.0, 'default' => 0.0, 'max' => 0.0, 'name' => 'Slant' ],
	] );

	$result = $this->parser->parse( $bytes );

	expect( $result['axes']['slnt'] )->toBe( [
		'min'     => -15.0,
		'max'     => 0.0,
		'default' => 0.0,
		'name'    => 'Slant',
	] );
} );

it( 'falls back to the axis tag as the name when the name table is absent', function (): void {
	$result = $this->parser->parse( FontBinaryFactory::fvarOnlyTtf( [
		'wght' => [ 'min' => 100.0, 'default' => 400.0, 'max' => 900.0, 'name' => 'Weight' ],
	] ) );

	expect( $result['is_variable'] )->toBeTrue()
		->and( $result['axes']['wght']['name'] )->toBe( 'wght' );
} );

it( 'rejects a truncated fvar axis record and falls back to non-variable', function (): void {
	$result = $this->parser->parse( FontBinaryFactory::truncatedFvarTtf() );

	expect( $result )->toBe( [ 'is_variable' => false, 'axes' => [] ] );
} );

it( 'reports WOFF2 container support according to a bounded brotli decoder', function (): void {
	$woff2Signature = 'wOF2' . str_repeat( "\x00", 40 );
	$boundedDecoder = function_exists( 'brotli_uncompress_init' ) && function_exists( 'brotli_uncompress_add' );

	expect( $this->parser->isSupportedContainer( $woff2Signature ) )->toBe( $boundedDecoder );
} );

it( 'always supports SFNT and WOFF containers', function (): void {
	expect( $this->parser->isSupportedContainer( fontFixture( 'variable.ttf' ) ) )->toBeTrue()
		->and( $this->parser->isSupportedContainer( fontFixture( 'variable.woff' ) ) )->toBeTrue();
} );
