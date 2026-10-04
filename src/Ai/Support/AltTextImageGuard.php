<?php

/**
 * Restricts which images the alt-text feature may read.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.12.1
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Ai\Support;

use ArtisanPackUI\Ai\Exceptions\FeatureError;

/**
 * The alt-text agent can read a server path, fetch a URL, or decode
 * base64. Only the last two are safe to accept from a browser: a `path`
 * source would read any file the PHP process can see and send it to the
 * AI provider, and an arbitrary URL can be fetched server-side by some
 * providers. This guard allows base64 / `data:image/` input and
 * `http(s)` URLs whose host is the `app.url` host or listed in
 * `artisanpack.visual-editor.ai.alt_text.allowed_hosts`, and always
 * hands the agent an explicit `{source, value}` pair so it never guesses
 * a bare string is a path. URLs must use the default port (or the
 * `app.url` port). Some AI gateways fetch URLs server-side and follow
 * redirects, so never list a host with an open redirect in
 * `allowed_hosts`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.12.1
 */
class AltTextImageGuard
{
	/**
	 * Longest base64 / data URI accepted, in characters (~5 MB of image
	 * data, the common provider limit).
	 *
	 * @since 1.12.1
	 *
	 * @var int
	 */
	public const MAX_BASE64_LENGTH = 7000000;

	/**
	 * Return the reason the image is not allowed, or `null` when it is.
	 *
	 * @since 1.12.1
	 *
	 * @param  mixed  $image  A string or `{source, value}` array.
	 */
	public static function violation( mixed $image ): ?string
	{
		return self::normalize( $image )[1];
	}

	/**
	 * Return the allowed image as an explicit `{source, value}` pair.
	 *
	 * @since 1.12.1
	 *
	 * @param  mixed  $image       A string or `{source, value}` array.
	 * @param  string $featureKey  Feature key for the error.
	 *
	 * @throws FeatureError When the image is not allowed.
	 *
	 * @return array{ source: string, value: string }
	 */
	public static function assertAllowed( mixed $image, string $featureKey ): array
	{
		[ $normalized, $violation ] = self::normalize( $image );

		if ( null !== $violation || null === $normalized ) {
			throw FeatureError::forFeature( $featureKey, $violation ?? __( 'The image is not allowed.' ) );
		}

		return $normalized;
	}

	/**
	 * Laravel validation closure for the request's `image` field.
	 *
	 * @since 1.12.1
	 *
	 * @return callable(string, mixed, callable): void
	 */
	public static function rule(): callable
	{
		return static function ( string $attribute, mixed $value, callable $fail ): void {
			$violation = self::violation( $value );

			if ( null !== $violation ) {
				$fail( $violation );
			}
		};
	}

	/**
	 * @since 1.12.1
	 *
	 * @param  mixed  $image  A string or `{source, value}` array.
	 *
	 * @return array{ 0: array{ source: string, value: string }|null, 1: string|null }
	 */
	protected static function normalize( mixed $image ): array
	{
		if ( is_array( $image ) ) {
			$source = is_string( $image['source'] ?? null ) ? $image['source'] : '';
			$value  = is_string( $image['value'] ?? null ) ? trim( $image['value'] ) : '';
		} elseif ( is_string( $image ) ) {
			$value  = trim( $image );
			$source = str_starts_with( $value, 'data:image/' ) ? 'base64' : ( self::isHttpUrl( $value ) ? 'url' : '' );
		} else {
			return [ null, __( 'The image must be a data URI, a base64 string, or an image URL.' ) ];
		}

		if ( '' === $value ) {
			return [ null, __( 'The image must not be empty.' ) ];
		}

		if ( 'base64' === $source ) {
			if ( strlen( $value ) > self::MAX_BASE64_LENGTH ) {
				return [ null, __( 'The image is too large.' ) ];
			}

			return [ [ 'source' => 'base64', 'value' => $value ], null ];
		}

		if ( 'url' === $source && self::isHttpUrl( $value ) && self::isAllowedHost( (string) parse_url( $value, PHP_URL_HOST ) ) && self::isAllowedPort( $value ) ) {
			return [ [ 'source' => 'url', 'value' => $value ], null ];
		}

		if ( 'url' === $source || self::isHttpUrl( $value ) ) {
			return [ null, __( 'Image URLs must point at this site.' ) ];
		}

		return [ null, __( 'The image must be a data URI, a base64 string, or an image URL.' ) ];
	}

	/**
	 * @since 1.12.1
	 */
	protected static function isHttpUrl( string $value ): bool
	{
		$scheme = strtolower( (string) parse_url( $value, PHP_URL_SCHEME ) );

		return in_array( $scheme, [ 'http', 'https' ], true ) && '' !== (string) parse_url( $value, PHP_URL_HOST );
	}

	/**
	 * Only the scheme's default port or the `app.url` port, so an allowed
	 * host can't be used to reach other services on it (e.g. `:6379`).
	 *
	 * @since 1.12.1
	 */
	protected static function isAllowedPort( string $url ): bool
	{
		$port = parse_url( $url, PHP_URL_PORT );

		if ( null === $port || false === $port ) {
			return true;
		}

		$appPort = parse_url( (string) config( 'app.url', '' ), PHP_URL_PORT );

		return in_array( (int) $port, array_filter( [ 80, 443, is_int( $appPort ) ? $appPort : null ] ), true );
	}

	/**
	 * @since 1.12.1
	 */
	protected static function isAllowedHost( string $host ): bool
	{
		$host = strtolower( $host );

		if ( '' === $host ) {
			return false;
		}

		// Only configured hosts count. The request's own Host header is
		// client-controlled, so trusting it would let a caller allow any
		// host, including internal or metadata addresses.
		$allowed   = (array) config( 'artisanpack.visual-editor.ai.alt_text.allowed_hosts', [] );
		$allowed[] = (string) parse_url( (string) config( 'app.url', '' ), PHP_URL_HOST );

		$allowed = array_filter( array_map( static fn ( $entry ): string => strtolower( trim( (string) $entry ) ), $allowed ) );

		return in_array( $host, $allowed, true );
	}
}
