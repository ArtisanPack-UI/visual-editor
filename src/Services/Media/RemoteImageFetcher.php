<?php

/**
 * Fetches a remote image for the media library.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.20.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services\Media;

use finfo;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Downloads an author-supplied image URL into a temporary file (#1066).
 *
 * The URL comes from pasted content, so a request to it is one a stranger
 * chose. Every hop is checked before it's made: only HTTP(S) on the default
 * ports, no credentials in the URL, and a host whose every address is
 * public. The connection is pinned to the addresses checked, so a DNS
 * answer that changes afterwards can't reach an internal one. Redirects
 * are followed by hand so each target gets the same check.
 *
 * Whatever comes back is capped in size, and typed from its bytes rather
 * than from the response's headers.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.20.0
 */
class RemoteImageFetcher
{
	/**
	 * How many redirects a fetch may follow.
	 *
	 * @since 1.20.0
	 */
	public const MAX_REDIRECTS = 3;

	/**
	 * Image types accepted when the config doesn't name any. SVG isn't one,
	 * because it can hold script.
	 *
	 * @since 1.20.0
	 *
	 * @var array<string, string>
	 */
	public const MIME_EXTENSIONS = [
		'image/avif' => 'avif',
		'image/gif'  => 'gif',
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/webp' => 'webp',
	];

	/**
	 * IPv6 prefixes that carry an IPv4 address, which PHP's reserved-range
	 * filter doesn't look inside: IPv4-mapped, IPv4-compatible, NAT64 and
	 * 6to4. Hex strings, compared with the address's packed bytes so every
	 * way of writing it counts.
	 *
	 * @since 1.20.0
	 *
	 * @var list<string>
	 */
	protected const TUNNELLED_IPV6 = [
		'00000000000000000000ffff',
		'000000000000000000000000',
		'0064ff9b0000000000000000',
		'2002',
	];

	/**
	 * A host name the HTTP client and curl will read exactly as written.
	 * Anything else could reach a different host from the one checked: the
	 * client expands `{…}` as a URI template, and curl decodes `%2e`.
	 *
	 * @since 1.20.0
	 */
	protected const PLAIN_HOST = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*$/i';

	/**
	 * Fetch a remote image into a temporary file.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $url  The image's URL.
	 *
	 * @throws RemoteImageException When the URL is refused or the fetch fails.
	 *
	 * @return UploadedFile The image. The caller deletes it.
	 */
	public function fetch( string $url ): UploadedFile
	{
		for ( $hop = 0; $hop <= self::MAX_REDIRECTS; $hop++ ) {
			$response = $this->request( $url, $this->assertPublicUrl( $url ) );

			if ( ! $response->redirect() ) {
				return $this->toFile( $url, $response );
			}

			$location = $response->header( 'Location' );

			if ( '' === $location ) {
				break;
			}

			$url = (string) UriResolver::resolve( new Uri( $url ), new Uri( $location ) );
		}

		throw new RemoteImageException( __( 'The image could not be downloaded.' ) );
	}

	/**
	 * Check a URL, and return the addresses it may connect to.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $url  The URL.
	 *
	 * @throws RemoteImageException When the URL isn't allowed.
	 *
	 * @return list<string> The host's addresses.
	 */
	public function assertPublicUrl( string $url ): array
	{
		$parts  = parse_url( $url );
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$port   = $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 );
		$host   = trim( (string) ( $parts['host'] ?? '' ), '[]' );

		$isAddress = false !== filter_var( $host, FILTER_VALIDATE_IP );

		if (
			! in_array( $scheme, [ 'http', 'https' ], true )
			|| false !== strpbrk( $url, '{}' )
			|| ( ! $isAddress && 1 !== preg_match( self::PLAIN_HOST, $host ) )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| ! in_array( $port, [ 80, 443 ], true )
		) {
			throw new RemoteImageException( __( 'This address cannot be imported.' ) );
		}

		$addresses = $this->resolveHost( $host );

		if ( [] === $addresses || [] !== array_filter( $addresses, fn ( string $address ): bool => ! $this->isPublicAddress( $address ) ) ) {
			throw new RemoteImageException( __( 'This address cannot be imported.' ) );
		}

		return $addresses;
	}

	/**
	 * Whether an IP address is reachable from the public internet.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $address  An IPv4 or IPv6 address.
	 */
	public function isPublicAddress( string $address ): bool
	{
		if ( false === filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		$hex = bin2hex( (string) inet_pton( $address ) );

		foreach ( self::TUNNELLED_IPV6 as $prefix ) {
			if ( 32 === strlen( $hex ) && str_starts_with( $hex, $prefix ) ) {
				return false;
			}
		}

		// Carrier-grade NAT (100.64.0.0/10), which PHP treats as public.
		return 1 !== preg_match( '/^100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])\./', $address );
	}

	/**
	 * A host's IPv4 and IPv6 addresses.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $host  A host name or IP address.
	 *
	 * @return list<string>
	 */
	protected function resolveHost( string $host ): array
	{
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [ $host ];
		}

		$addresses = gethostbynamel( $host ) ?: [];

		foreach ( dns_get_record( $host, DNS_AAAA ) ?: [] as $record ) {
			if ( isset( $record['ipv6'] ) ) {
				$addresses[] = $record['ipv6'];
			}
		}

		return array_values( array_unique( $addresses ) );
	}

	/**
	 * Make the request, connecting only to the checked addresses.
	 *
	 * @since 1.20.0
	 *
	 * @param  string        $url        The URL.
	 * @param  list<string>  $addresses  Its checked addresses.
	 *
	 * @throws RemoteImageException When the request fails.
	 */
	protected function request( string $url, array $addresses ): Response
	{
		$parts   = parse_url( $url );
		$host    = trim( (string) ( $parts['host'] ?? '' ), '[]' );
		$port    = $parts['port'] ?? ( 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) ? 443 : 80 );
		$maxSize = $this->maxBytes();

		try {
			return Http::withoutRedirecting()
				->connectTimeout( 5 )
				->timeout( 20 )
				->accept( 'image/*' )
				->withOptions( [
					// `+`, because a spread would renumber curl's integer keys.
					'curl' => $this->pinnedAddresses( $host, $port, $addresses ) + [
						CURLOPT_PROTOCOLS        => CURLPROTO_HTTP | CURLPROTO_HTTPS,
						CURLOPT_MAXFILESIZE      => $maxSize,
						CURLOPT_NOPROGRESS       => false,
						CURLOPT_XFERINFOFUNCTION => static fn ( mixed $handle, int $total, int $received ): int => $received > $maxSize ? 1 : 0,
					],
				] )
				->get( $url );
		} catch ( ConnectionException ) {
			throw new RemoteImageException( __( 'The image could not be downloaded.' ) );
		}
	}

	/**
	 * The curl option that pins a host name to its checked addresses. An IP
	 * address has nothing to look up, so it needs no pin.
	 *
	 * @since 1.20.0
	 *
	 * @param  string        $host       The host.
	 * @param  int           $port       The port.
	 * @param  list<string>  $addresses  Its checked addresses.
	 *
	 * @return array<int, list<string>>
	 */
	protected function pinnedAddresses( string $host, int $port, array $addresses ): array
	{
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return [];
		}

		$formatted = array_map(
			static fn ( string $address ): string => str_contains( $address, ':' ) ? '[' . $address . ']' : $address,
			$addresses,
		);

		return [ CURLOPT_RESOLVE => [ sprintf( '%s:%d:%s', $host, $port, implode( ',', $formatted ) ) ] ];
	}

	/**
	 * Turn a response into a temporary file.
	 *
	 * @since 1.20.0
	 *
	 * @param  string    $url       The URL it came from.
	 * @param  Response  $response  The response.
	 *
	 * @throws RemoteImageException When it isn't an acceptable image.
	 */
	protected function toFile( string $url, Response $response ): UploadedFile
	{
		$body = $response->body();

		if ( ! $response->successful() || '' === $body ) {
			throw new RemoteImageException( __( 'The image could not be downloaded.' ) );
		}

		if ( strlen( $body ) > $this->maxBytes() ) {
			throw new RemoteImageException( __( 'The image is too large to import.' ) );
		}

		$mime       = ( new finfo( FILEINFO_MIME_TYPE ) )->buffer( $body );
		$extensions = $this->allowedTypes();

		if ( ! is_string( $mime ) || ! isset( $extensions[ $mime ] ) ) {
			throw new RemoteImageException( __( 'This file type cannot be imported.' ) );
		}

		$path = tempnam( sys_get_temp_dir(), 've-import-' );

		if ( false === $path || false === file_put_contents( $path, $body ) ) {
			throw new RemoteImageException( __( 'The image could not be downloaded.' ) );
		}

		return new UploadedFile( $path, $this->fileName( $url, $extensions[ $mime ] ), $mime, null, true );
	}

	/**
	 * A safe file name from the URL's last path segment.
	 *
	 * @since 1.20.0
	 *
	 * @param  string  $url        The URL.
	 * @param  string  $extension  The extension its bytes call for.
	 */
	protected function fileName( string $url, string $extension ): string
	{
		$name = pathinfo( rawurldecode( (string) parse_url( $url, PHP_URL_PATH ) ), PATHINFO_FILENAME );
		$name = trim( (string) preg_replace( '/[^A-Za-z0-9_-]+/', '-', $name ), '-' );

		return ( '' === $name ? 'image' : $name ) . '.' . $extension;
	}

	/**
	 * The configured size limit in bytes.
	 *
	 * @since 1.20.0
	 */
	protected function maxBytes(): int
	{
		return max( 1, (int) config( 'artisanpack.visual-editor.media_import.max_size_kb', 10240 ) ) * 1024;
	}

	/**
	 * The accepted image types, limited to what the config allows.
	 *
	 * @since 1.20.0
	 *
	 * @return array<string, string> Extensions keyed by MIME type.
	 */
	protected function allowedTypes(): array
	{
		$configured = config( 'artisanpack.visual-editor.media_import.allowed_mime_types', array_keys( self::MIME_EXTENSIONS ) );

		return array_intersect_key( self::MIME_EXTENSIONS, array_flip( is_array( $configured ) ? $configured : [] ) );
	}
}
