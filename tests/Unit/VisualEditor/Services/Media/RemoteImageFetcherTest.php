<?php

declare( strict_types=1 );

use ArtisanPackUI\VisualEditor\Services\Media\RemoteImageException;
use ArtisanPackUI\VisualEditor\Services\Media\RemoteImageFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

/**
 * A fetcher with a fixed DNS table, so no test touches the network.
 *
 * @param  array<string, list<string>>  $hosts  Addresses by host.
 */
function fetcherWithDns( array $hosts = [ 'cdn.example.com' => [ '93.184.216.34' ] ] ): RemoteImageFetcher
{
	return new class( $hosts ) extends RemoteImageFetcher {
		public function __construct( protected array $hosts )
		{
		}

		protected function resolveHost( string $host ): array
		{
			return filter_var( $host, FILTER_VALIDATE_IP ) ? [ $host ] : ( $this->hosts[ $host ] ?? [] );
		}
	};
}

function pngResponse()
{
	return Http::response( base64_decode( TINY_PNG ), 200, [ 'Content-Type' => 'image/png' ] );
}

it( 'treats only public addresses as public', function ( string $address, bool $public ): void {
	expect( ( new RemoteImageFetcher() )->isPublicAddress( $address ) )->toBe( $public );
} )->with( [
	'public v4'          => [ '93.184.216.34', true ],
	'public v6'          => [ '2606:4700:4700::1111', true ],
	'loopback'           => [ '127.0.0.1', false ],
	'loopback range'     => [ '127.8.8.8', false ],
	'zero'               => [ '0.0.0.0', false ],
	'private 10'         => [ '10.0.0.1', false ],
	'private 172'        => [ '172.16.0.1', false ],
	'private 192'        => [ '192.168.1.1', false ],
	'metadata'           => [ '169.254.169.254', false ],
	'carrier nat'        => [ '100.64.0.1', false ],
	'carrier nat end'    => [ '100.127.255.255', false ],
	'just past cgnat'    => [ '100.128.0.1', true ],
	'v6 loopback'        => [ '::1', false ],
	'v6 unique local'    => [ 'fd00::1', false ],
	'v6 link local'      => [ 'fe80::1', false ],
	'mapped v4'          => [ '::ffff:127.0.0.1', false ],
	'mapped hex'         => [ '::ffff:7f00:1', false ],
	'compatible v4'      => [ '::127.0.0.1', false ],
	'nat64'              => [ '64:ff9b::7f00:1', false ],
	'6to4'               => [ '2002:7f00:1::', false ],
	'long mapped'        => [ '0:0:0:0:0:ffff:7f00:1', false ],
	'long compatible'    => [ '0:0:0:0:0:0:7f00:1', false ],
	'long nat64'         => [ '0064:ff9b:0:0:0:0:7f00:1', false ],
	'upper 6to4'         => [ '2002:7F00:0001::', false ],
	'not an address'     => [ 'localhost', false ],
] );

it( 'refuses URLs it should not request', function ( string $url ): void {
	Http::fake();

	expect( fn () => fetcherWithDns( [ 'internal.test' => [ '10.0.0.5' ], 'mixed.test' => [ '93.184.216.34', '10.0.0.5' ] ] )->fetch( $url ) )
		->toThrow( RemoteImageException::class );

	Http::assertNothingSent();
} )->with( [
	'file scheme'      => [ 'file:///etc/passwd' ],
	'gopher'           => [ 'gopher://cdn.example.com/x' ],
	'ftp'              => [ 'ftp://cdn.example.com/a.png' ],
	'no scheme'        => [ 'cdn.example.com/a.png' ],
	'credentials'      => [ 'https://user:pass@cdn.example.com/a.png' ],
	'odd port'         => [ 'https://cdn.example.com:8080/a.png' ],
	'loopback'         => [ 'http://127.0.0.1/a.png' ],
	'v6 loopback'      => [ 'http://[::1]/a.png' ],
	'metadata'         => [ 'http://169.254.169.254/latest/meta-data' ],
	'internal name'    => [ 'https://internal.test/a.png' ],
	'one bad address'  => [ 'https://mixed.test/a.png' ],
	'unresolvable'     => [ 'https://nowhere.test/a.png' ],
	'template in host' => [ 'http://cdn{y}.example.com/a.png' ],
	'template in path' => [ 'http://cdn.example.com/{y}.png' ],
	'encoded host'     => [ 'http://cdn%2eexample.com/a.png' ],
	'trailing dot'     => [ 'http://cdn.example.com./a.png' ],
	'long mapped v6'   => [ 'http://[0:0:0:0:0:ffff:7f00:1]/a.png' ],
] );

it( 'pins IPv6 addresses in brackets, and leaves an IP address host unpinned', function (): void {
	$sent = [];

	Http::fake( function ( Request $request, array $options ) use ( &$sent ) {
		$sent[] = $options['curl'][ CURLOPT_RESOLVE ] ?? null;

		return pngResponse();
	} );

	$fetcher = fetcherWithDns( [ 'cdn.example.com' => [ '93.184.216.34', '2606:4700:4700::1111' ] ] );

	unlink( $fetcher->fetch( 'https://cdn.example.com/a.png' )->getPathname() );
	unlink( $fetcher->fetch( 'https://93.184.216.34/a.png' )->getPathname() );

	expect( $sent )->toBe( [ [ 'cdn.example.com:443:93.184.216.34,[2606:4700:4700::1111]' ], null ] );
} );

it( 'sends exactly the URL it checked', function (): void {
	Http::fake( [ '*' => pngResponse() ] );

	unlink( fetcherWithDns()->fetch( 'https://cdn.example.com/a%20b.png?w=1' )->getPathname() );

	Http::assertSent( fn ( Request $request ): bool => 'https://cdn.example.com/a%20b.png?w=1' === $request->url() );
} );

it( 'downloads an image and names it from the URL and its bytes', function (): void {
	Http::fake( [ '*' => pngResponse() ] );

	$file = fetcherWithDns()->fetch( 'https://cdn.example.com/photos/My%20Photo.jpeg?w=100' );

	expect( $file->getClientOriginalName() )->toBe( 'My-Photo.png' )
		->and( $file->getMimeType() )->toBe( 'image/png' );

	unlink( $file->getPathname() );
} );

it( 'checks every redirect target', function (): void {
	Http::fake( [ '*' => Http::response( '', 302, [ 'Location' => 'http://169.254.169.254/secret' ] ) ] );

	expect( fn () => fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' ) )->toThrow( RemoteImageException::class );

	Http::assertSentCount( 1 );
} );

it( 'follows a redirect to a public host', function (): void {
	Http::fake( function ( Request $request ) {
		return str_ends_with( $request->url(), '/moved.png' ) ? pngResponse() : Http::response( '', 301, [ 'Location' => '/moved.png' ] );
	} );

	$file = fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' );

	expect( $file->getClientOriginalName() )->toBe( 'moved.png' );

	unlink( $file->getPathname() );
} );

it( 'gives up on a redirect loop', function (): void {
	Http::fake( [ '*' => Http::response( '', 302, [ 'Location' => '/again.png' ] ) ] );

	expect( fn () => fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' ) )->toThrow( RemoteImageException::class );

	Http::assertSentCount( RemoteImageFetcher::MAX_REDIRECTS + 1 );
} );

it( 'refuses what the bytes say is not an allowed image', function ( string $body ): void {
	Http::fake( [ '*' => Http::response( $body, 200, [ 'Content-Type' => 'image/png' ] ) ] );

	expect( fn () => fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' ) )->toThrow( RemoteImageException::class );
} )->with( [
	'html'  => [ '<html><script>alert(1)</script></html>' ],
	'svg'   => [ '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' ],
	'text'  => [ 'plain text' ],
	'empty' => [ '' ],
] );

it( 'honours the configured types', function (): void {
	config()->set( 'artisanpack.visual-editor.media_import.allowed_mime_types', [ 'image/jpeg' ] );

	Http::fake( [ '*' => pngResponse() ] );

	expect( fn () => fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' ) )->toThrow( RemoteImageException::class );
} );

it( 'refuses an image over the size limit', function (): void {
	config()->set( 'artisanpack.visual-editor.media_import.max_size_kb', 1 );

	Http::fake( [ '*' => Http::response( str_repeat( 'a', 2048 ) ) ] );

	expect( fn () => fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' ) )->toThrow( RemoteImageException::class, 'too large' );
} );

it( 'refuses a failed response', function (): void {
	Http::fake( [ '*' => Http::response( base64_decode( TINY_PNG ), 404 ) ] );

	expect( fn () => fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' ) )->toThrow( RemoteImageException::class );
} );

it( 'reports a connection failure', function (): void {
	Http::fake( fn () => throw new ConnectionException( 'timed out' ) );

	expect( fn () => fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' ) )->toThrow( RemoteImageException::class );
} );

it( 'pins the connection to the checked addresses', function (): void {
	$options = [];

	Http::fake( function ( Request $request, array $sent ) use ( &$options ) {
		$options = $sent;

		return pngResponse();
	} );

	$file = fetcherWithDns()->fetch( 'https://cdn.example.com/a.png' );

	unlink( $file->getPathname() );

	expect( $options['curl'][ CURLOPT_RESOLVE ] )->toBe( [ 'cdn.example.com:443:93.184.216.34' ] )
		->and( $options['curl'][ CURLOPT_PROTOCOLS ] )->toBe( CURLPROTO_HTTP | CURLPROTO_HTTPS )
		->and( $options['allow_redirects'] )->toBeFalse();
} );
