<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;
use ArtisanPackUI\VisualEditor\Fonts\Providers\BunnyFontsProvider;
use Illuminate\Support\Facades\Http;

/**
 * A trimmed `fonts.bunny.net/list` payload: one static family (ABeeZee, with
 * both styles) and one variable family (Inter, normal only) so the `weights`×
 * `styles` product and the `isVariable` flag are both exercised. Bunny keys the
 * object by slug and exposes no axis ranges.
 *
 * @return array<string, mixed>
 */
function bunnyListFixture(): array
{
	return [
		'abeezee' => [
			'variants'   => [ 'latin' => 2, 'latin-ext' => 2 ],
			'isVariable' => false,
			'styles'     => [ 'italic', 'normal' ],
			'weights'    => [ 400 ],
			'familyName' => 'ABeeZee',
			'defSubset'  => 'latin',
			'category'   => 'sans-serif',
		],
		'inter' => [
			'variants'   => [ 'latin' => 18 ],
			'isVariable' => true,
			'styles'     => [ 'normal' ],
			'weights'    => [ 400, 700 ],
			'familyName' => 'Inter',
			'defSubset'  => 'latin',
			'category'   => 'sans-serif',
		],
	];
}

/**
 * A trimmed Bunny CSS response for a single face, with the latin subset placed
 * last so the picker is proven to select by subset name rather than by document
 * order. Each `src` carries both WOFF2 and WOFF, as Bunny's keyless endpoint does.
 */
function bunnyCssFixture(): string
{
	return <<<CSS
	/* latin-ext */
	@font-face {
	  font-family: 'ABeeZee';
	  font-style: normal;
	  font-weight: 400;
	  src: url(https://fonts.bunny.net/abeezee/files/abeezee-latin-ext-400-normal.woff2) format('woff2'), url(https://fonts.bunny.net/abeezee/files/abeezee-latin-ext-400-normal.woff) format('woff');
	  unicode-range: U+0100-02BA;
	}
	/* latin */
	@font-face {
	  font-family: 'ABeeZee';
	  font-style: normal;
	  font-weight: 400;
	  src: url(https://fonts.bunny.net/abeezee/files/abeezee-latin-400-normal.woff2) format('woff2'), url(https://fonts.bunny.net/abeezee/files/abeezee-latin-400-normal.woff) format('woff');
	  unicode-range: U+0000-00FF;
	}
	CSS;
}

/**
 * A minimal body carrying the WOFF2 signature the downloader validates.
 */
function fakeBunnyWoff2Bytes(): string
{
	return "wOF2\x00\x01\x00\x00fake-bunny-font-bytes";
}

function fakeBunnyFonts(): void
{
	Http::fake( [
		'fonts.bunny.net/list*'       => Http::response( bunnyListFixture(), 200 ),
		'fonts.bunny.net/css*'        => Http::response( bunnyCssFixture(), 200 ),
		'fonts.bunny.net/*/files/*'   => Http::response( fakeBunnyWoff2Bytes(), 200 ),
	] );
}

beforeEach( function () {
	$this->provider = new BunnyFontsProvider();
} );

it( 'reports its key, label, and self-hostability', function () {
	expect( $this->provider->key() )->toBe( 'bunny' )
		->and( $this->provider->label() )->toBeString()->not->toBeEmpty()
		->and( $this->provider->isSelfHostable() )->toBeTrue();
} );

it( 'browses the catalog and reports the second page as the last', function () {
	fakeBunnyFonts();
	$provider = new BunnyFontsProvider( perPage: 1 );

	$first = $provider->searchCatalog( '' );

	expect( $first['families'] )->toHaveCount( 1 )
		->and( $first['families'][0]['family'] )->toBe( 'ABeeZee' )
		->and( $first['families'][0]['category'] )->toBe( 'sans-serif' )
		->and( $first['page'] )->toBe( 1 )
		->and( $first['has_more'] )->toBeTrue();

	$second = $provider->searchCatalog( '', 2 );

	expect( $second['families'] )->toHaveCount( 1 )
		->and( $second['families'][0]['family'] )->toBe( 'Inter' )
		->and( $second['has_more'] )->toBeFalse();
} );

it( 'clamps a non-positive page size so it never serves empty pages', function () {
	fakeBunnyFonts();
	$provider = new BunnyFontsProvider( perPage: 0 );

	$result = $provider->searchCatalog( '' );

	expect( $result['families'] )->not->toBeEmpty()
		->and( $result['families'] )->toHaveCount( 1 );
} );

it( 'filters the catalog by a case-insensitive query', function () {
	fakeBunnyFonts();

	$result = $this->provider->searchCatalog( 'INTER' );

	expect( $result['families'] )->toHaveCount( 1 )
		->and( $result['families'][0]['slug'] )->toBe( 'inter' )
		->and( $result['families'][0]['is_variable'] )->toBeTrue();
} );

it( 'resolves a static family with the product of its weights and styles', function () {
	fakeBunnyFonts();

	$family = $this->provider->getFamily( 'abeezee' );

	expect( $family['family'] )->toBe( 'ABeeZee' )
		->and( $family['is_variable'] )->toBeFalse()
		->and( $family['license'] )->toBeNull()
		->and( $family['axes'] )->toBe( [] )
		->and( $family['faces'] )->toEqual( [
			[ 'weight' => 400, 'style' => 'normal' ],
			[ 'weight' => 400, 'style' => 'italic' ],
		] );
} );

it( 'marks a variable family without exposing axis ranges', function () {
	fakeBunnyFonts();

	$family = $this->provider->getFamily( 'inter' );

	expect( $family['is_variable'] )->toBeTrue()
		->and( $family['axes'] )->toBe( [] )
		->and( $family['faces'] )->toEqual( [
			[ 'weight' => 400, 'style' => 'normal' ],
			[ 'weight' => 700, 'style' => 'normal' ],
		] );
} );

it( 'returns null for an unknown family slug', function () {
	fakeBunnyFonts();

	expect( $this->provider->getFamily( 'does-not-exist' ) )->toBeNull();
} );

it( 'fetches the latin woff2 bytes and requests the right face', function () {
	fakeBunnyFonts();

	$bytes = $this->provider->fetchFace( 'abeezee', '400', 'normal' );

	expect( $bytes )->toBe( fakeBunnyWoff2Bytes() );

	Http::assertSent( fn ( $request ) => str_contains( $request->url(), 'family=abeezee%3A400&display=swap' ) );
	Http::assertSent( fn ( $request ) => $request->url() === 'https://fonts.bunny.net/abeezee/files/abeezee-latin-400-normal.woff2' );
} );

it( 'denotes italic faces with a trailing i on the weight', function () {
	fakeBunnyFonts();

	$this->provider->fetchFace( 'abeezee', '400', 'italic' );

	Http::assertSent( fn ( $request ) => str_contains( $request->url(), 'family=abeezee%3A400i' ) );
} );

it( 'aborts a download whose body exceeds the configured size cap', function () {
	Http::fake( [
		'fonts.bunny.net/list*'     => Http::response( bunnyListFixture(), 200 ),
		'fonts.bunny.net/css*'      => Http::response( bunnyCssFixture(), 200 ),
		// A signature-valid but oversized body — a compromised/MITM'd CDN body.
		'fonts.bunny.net/*/files/*' => Http::response( 'wOF2' . str_repeat( 'x', 200_000 ), 200 ),
	] );

	$provider = new BunnyFontsProvider( maxBytes: 65_536 );

	expect( fn () => $provider->fetchFace( 'abeezee', '400', 'normal' ) )
		->toThrow( FontProviderException::class, 'exceeded the maximum allowed size' );
} );

it( 'sends a browser user-agent when resolving face css', function () {
	fakeBunnyFonts();

	$this->provider->fetchFace( 'abeezee', '400', 'normal' );

	Http::assertSent( fn ( $request ) => str_contains( $request->url(), 'css' )
		&& str_contains( $request->header( 'User-Agent' )[0] ?? '', 'Chrome' ) );
} );

it( 'caches the catalog so browsing hits the list endpoint once', function () {
	fakeBunnyFonts();

	$this->provider->searchCatalog( '' );
	$this->provider->searchCatalog( 'abeezee' );
	$this->provider->getFamily( 'inter' );

	Http::assertSentCount( 1 );
} );

it( 'throws when the list endpoint responds with an error', function () {
	Http::fake( [
		'fonts.bunny.net/list*' => Http::response( 'nope', 500 ),
	] );

	expect( fn () => $this->provider->searchCatalog( '' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws rather than caching an empty font list', function () {
	Http::fake( [
		'fonts.bunny.net/list*' => Http::response( [], 200 ),
	] );

	expect( fn () => $this->provider->searchCatalog( '' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws when no list entry yields a usable family', function () {
	Http::fake( [
		'fonts.bunny.net/list*' => Http::response(
			[ 'ghost' => [ 'category' => 'sans-serif' ], 'blank' => [ 'familyName' => '' ] ],
			200
		),
	] );

	expect( fn () => $this->provider->searchCatalog( '' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws when a requested face is not in the family', function () {
	fakeBunnyFonts();

	expect( fn () => $this->provider->fetchFace( 'abeezee', '900', 'normal' ) )
		->toThrow( FontProviderException::class );
} );

it( 'rejects an unsupported style instead of falling back to normal', function () {
	fakeBunnyFonts();

	expect( fn () => $this->provider->fetchFace( 'abeezee', '400', 'oblique' ) )
		->toThrow( FontProviderException::class );

	// The face must be refused before any CSS/file request is made, so an
	// unsupported style can never resolve to the normal face.
	Http::assertNotSent( fn ( $request ) => str_contains( $request->url(), '/css' ) );
} );

it( 'rejects a non-numeric weight token instead of coercing it', function () {
	fakeBunnyFonts();

	expect( fn () => $this->provider->fetchFace( 'abeezee', '400junk', 'normal' ) )
		->toThrow( FontProviderException::class );

	Http::assertNotSent( fn ( $request ) => str_contains( $request->url(), '/css' ) );
} );

it( 'throws when the css endpoint yields no woff2 url', function () {
	Http::fake( [
		'fonts.bunny.net/list*' => Http::response( bunnyListFixture(), 200 ),
		'fonts.bunny.net/css*'  => Http::response( '/* nothing here */', 200 ),
	] );

	expect( fn () => $this->provider->fetchFace( 'abeezee', '400', 'normal' ) )
		->toThrow( FontProviderException::class );
} );

it( 'throws when the face file download fails', function () {
	Http::fake( [
		'fonts.bunny.net/list*'     => Http::response( bunnyListFixture(), 200 ),
		'fonts.bunny.net/css*'      => Http::response( bunnyCssFixture(), 200 ),
		'fonts.bunny.net/*/files/*' => Http::response( 'missing', 404 ),
	] );

	expect( fn () => $this->provider->fetchFace( 'abeezee', '400', 'normal' ) )
		->toThrow( FontProviderException::class );
} );

it( 'refuses to download a face URL pointing off the bunny allowlist', function () {
	Http::fake( [
		'fonts.bunny.net/list*' => Http::response( bunnyListFixture(), 200 ),
		'fonts.bunny.net/css*'  => Http::response(
			"/* latin */\n@font-face { src: url(http://169.254.169.254/latest/meta-data/x.woff2) format('woff2'); }",
			200
		),
	] );

	expect( fn () => $this->provider->fetchFace( 'abeezee', '400', 'normal' ) )
		->toThrow( FontProviderException::class );

	Http::assertNotSent( fn ( $request ) => str_contains( $request->url(), '169.254.169.254' ) );
} );

it( 'rejects a downloaded body that is not a WOFF2 font', function () {
	Http::fake( [
		'fonts.bunny.net/list*'     => Http::response( bunnyListFixture(), 200 ),
		'fonts.bunny.net/css*'      => Http::response( bunnyCssFixture(), 200 ),
		'fonts.bunny.net/*/files/*' => Http::response( '<html>error</html>', 200 ),
	] );

	expect( fn () => $this->provider->fetchFace( 'abeezee', '400', 'normal' ) )
		->toThrow( FontProviderException::class );
} );
