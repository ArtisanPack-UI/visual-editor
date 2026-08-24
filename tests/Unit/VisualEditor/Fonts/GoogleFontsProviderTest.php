<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;
use ArtisanPackUI\VisualEditor\Fonts\Providers\GoogleFontsProvider;
use Illuminate\Support\Facades\Http;

/**
 * A trimmed `fonts.google.com/metadata/fonts` payload: one static family
 * (Roboto) and one variable family (Inter) carrying axis metadata.
 *
 * @return array<string, mixed>
 */
function googleMetadataFixture(): array
{
	return [
		'familyMetadataList' => [
			[
				'family'   => 'Roboto',
				'category' => 'Sans Serif',
				'fonts'    => [ '400' => [], '400i' => [], '700' => [] ],
				'axes'     => [],
			],
			[
				'family'   => 'Inter',
				'category' => 'Sans Serif',
				'fonts'    => [ '400' => [] ],
				'axes'     => [
					[ 'tag' => 'wght', 'min' => 100, 'max' => 900, 'defaultValue' => 400 ],
					[ 'tag' => 'slnt', 'min' => -10, 'max' => 0, 'defaultValue' => 0 ],
				],
			],
		],
	];
}

/**
 * A trimmed CSS2 response for a single face, with the latin subset last (as
 * Google emits it) so the subset picker is genuinely exercised.
 */
function googleCss2Fixture(): string
{
	return <<<CSS
	/* latin-ext */
	@font-face {
	  font-family: 'Roboto';
	  font-style: normal;
	  font-weight: 700;
	  src: url(https://fonts.gstatic.com/s/roboto/v51/roboto-700-latin-ext.woff2) format('woff2');
	  unicode-range: U+0100-02BA;
	}
	/* latin */
	@font-face {
	  font-family: 'Roboto';
	  font-style: normal;
	  font-weight: 700;
	  src: url(https://fonts.gstatic.com/s/roboto/v51/roboto-700-latin.woff2) format('woff2');
	  unicode-range: U+0000-00FF;
	}
	CSS;
}

/**
 * A minimal body carrying the WOFF2 signature the downloader validates.
 */
function fakeWoff2Bytes(): string
{
	return "wOF2\x00\x01\x00\x00fake-font-bytes";
}

function fakeGoogleFonts(): void
{
	Http::fake( [
		'fonts.google.com/metadata/*'   => Http::response( googleMetadataFixture(), 200 ),
		'fonts.googleapis.com/css2*'    => Http::response( googleCss2Fixture(), 200 ),
		'fonts.gstatic.com/*'           => Http::response( fakeWoff2Bytes(), 200 ),
	] );
}

beforeEach( function () {
	$this->provider = new GoogleFontsProvider();
} );

it( 'reports its key, label, and self-hostability', function () {
	expect( $this->provider->key() )->toBe( 'google' )
		->and( $this->provider->label() )->toBeString()->not->toBeEmpty()
		->and( $this->provider->isSelfHostable() )->toBeTrue();
} );

it( 'browses the catalog and reports the second page as the last', function () {
	fakeGoogleFonts();
	$provider = new GoogleFontsProvider( perPage: 1 );

	$first = $provider->searchCatalog( '' );

	expect( $first['families'] )->toHaveCount( 1 )
		->and( $first['families'][0]['family'] )->toBe( 'Roboto' )
		->and( $first['families'][0]['category'] )->toBe( 'sans-serif' )
		->and( $first['page'] )->toBe( 1 )
		->and( $first['has_more'] )->toBeTrue();

	$second = $provider->searchCatalog( '', 2 );

	expect( $second['families'] )->toHaveCount( 1 )
		->and( $second['families'][0]['family'] )->toBe( 'Inter' )
		->and( $second['has_more'] )->toBeFalse();
} );

it( 'clamps a non-positive page size so it never serves empty pages', function () {
	fakeGoogleFonts();
	$provider = new GoogleFontsProvider( perPage: 0 );

	$result = $provider->searchCatalog( '' );

	expect( $result['families'] )->not->toBeEmpty()
		->and( $result['families'] )->toHaveCount( 1 );
} );

it( 'filters the catalog by a case-insensitive query', function () {
	fakeGoogleFonts();

	$result = $this->provider->searchCatalog( 'INTER' );

	expect( $result['families'] )->toHaveCount( 1 )
		->and( $result['families'][0]['slug'] )->toBe( 'inter' )
		->and( $result['families'][0]['is_variable'] )->toBeTrue();
} );

it( 'resolves a static family with its weight/style faces', function () {
	fakeGoogleFonts();

	$family = $this->provider->getFamily( 'roboto' );

	expect( $family['family'] )->toBe( 'Roboto' )
		->and( $family['is_variable'] )->toBeFalse()
		->and( $family['license'] )->toBeNull()
		->and( $family['axes'] )->toBe( [] )
		->and( $family['faces'] )->toEqual( [
			[ 'weight' => 400, 'style' => 'normal' ],
			[ 'weight' => 400, 'style' => 'italic' ],
			[ 'weight' => 700, 'style' => 'normal' ],
		] );
} );

it( 'exposes variable axis ranges for a variable family', function () {
	fakeGoogleFonts();

	$family = $this->provider->getFamily( 'inter' );

	expect( $family['is_variable'] )->toBeTrue()
		->and( $family['axes'] )->toEqual( [
			'wght' => [ 'min' => 100.0, 'max' => 900.0, 'default' => 400.0 ],
			'slnt' => [ 'min' => -10.0, 'max' => 0.0, 'default' => 0.0 ],
		] );
} );

it( 'returns null for an unknown family slug', function () {
	fakeGoogleFonts();

	expect( $this->provider->getFamily( 'does-not-exist' ) )->toBeNull();
} );

it( 'fetches the latin woff2 bytes and requests the right face', function () {
	fakeGoogleFonts();

	$bytes = $this->provider->fetchFace( 'roboto', '700', 'normal' );

	expect( $bytes )->toBe( fakeWoff2Bytes() );

	Http::assertSent( fn ( $request ) => str_contains( $request->url(), 'family=Roboto%3Aital%2Cwght%400%2C700' ) );
	Http::assertSent( fn ( $request ) => $request->url() === 'https://fonts.gstatic.com/s/roboto/v51/roboto-700-latin.woff2' );
} );

it( 'sends a browser user-agent so Google serves woff2', function () {
	fakeGoogleFonts();

	$this->provider->fetchFace( 'roboto', '400', 'italic' );

	Http::assertSent( fn ( $request ) => str_contains( $request->url(), 'css2' )
		&& str_contains( $request->header( 'User-Agent' )[0] ?? '', 'Chrome' ) );
} );

it( 'caches the catalog so browsing hits the metadata endpoint once', function () {
	fakeGoogleFonts();

	$this->provider->searchCatalog( '' );
	$this->provider->searchCatalog( 'roboto' );
	$this->provider->getFamily( 'inter' );

	Http::assertSentCount( 1 );
} );

it( 'throws when the metadata endpoint responds with an error', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response( 'nope', 500 ),
	] );

	expect( fn () => $this->provider->searchCatalog( '' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws rather than caching an empty font list', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response( [ 'familyMetadataList' => [] ], 200 ),
	] );

	expect( fn () => $this->provider->searchCatalog( '' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws when no metadata entry yields a usable family', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response(
			[ 'familyMetadataList' => [ [ 'category' => 'Sans Serif' ], [ 'family' => '' ] ] ],
			200
		),
	] );

	expect( fn () => $this->provider->searchCatalog( '' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws when a requested face is not in the family', function () {
	fakeGoogleFonts();

	expect( fn () => $this->provider->fetchFace( 'roboto', '900', 'normal' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws when the CSS2 endpoint yields no woff2 url', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response( googleMetadataFixture(), 200 ),
		'fonts.googleapis.com/css2*'  => Http::response( '/* nothing here */', 200 ),
	] );

	expect( fn () => $this->provider->fetchFace( 'roboto', '400', 'normal' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws when the face file download fails', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response( googleMetadataFixture(), 200 ),
		'fonts.googleapis.com/css2*'  => Http::response( googleCss2Fixture(), 200 ),
		'fonts.gstatic.com/*'         => Http::response( 'missing', 404 ),
	] );

	expect( fn () => $this->provider->fetchFace( 'roboto', '400', 'normal' ) )
		->toThrow( FontProviderException::class );
} );

it( 'refuses to download a face URL pointing off the gstatic allowlist', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response( googleMetadataFixture(), 200 ),
		'fonts.googleapis.com/css2*'  => Http::response(
			"/* latin */\n@font-face { src: url(http://169.254.169.254/latest/meta-data/x.woff2) format('woff2'); }",
			200
		),
	] );

	expect( fn () => $this->provider->fetchFace( 'roboto', '400', 'normal' ) )
		->toThrow( FontProviderException::class );

	Http::assertNotSent( fn ( $request ) => str_contains( $request->url(), '169.254.169.254' ) );
} );

it( 'rejects a downloaded body that is not a WOFF2 font', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response( googleMetadataFixture(), 200 ),
		'fonts.googleapis.com/css2*'  => Http::response( googleCss2Fixture(), 200 ),
		'fonts.gstatic.com/*'         => Http::response( '<html>error</html>', 200 ),
	] );

	expect( fn () => $this->provider->fetchFace( 'roboto', '400', 'normal' ) )
		->toThrow( FontProviderException::class );
} );

it( 'tolerates the anti-json-hijacking prefix on the metadata body', function () {
	Http::fake( [
		'fonts.google.com/metadata/*' => Http::response(
			")]}'\n" . json_encode( googleMetadataFixture() ),
			200
		),
	] );

	$result = $this->provider->searchCatalog( 'roboto' );

	expect( $result['families'] )->toHaveCount( 1 )
		->and( $result['families'][0]['slug'] )->toBe( 'roboto' );
} );
