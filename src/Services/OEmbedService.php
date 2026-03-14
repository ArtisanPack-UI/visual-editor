<?php

/**
 * oEmbed resolver service.
 *
 * Resolves oEmbed URLs from supported providers, caches responses,
 * and falls back to OpenGraph metadata when oEmbed fails.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for resolving oEmbed URLs and OpenGraph fallbacks.
 *
 * Provides server-side oEmbed resolution with caching, platform
 * detection, and OpenGraph meta tag fallback for unsupported providers.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      1.0.0
 */
class OEmbedService
{
	/**
	 * Known oEmbed provider endpoints.
	 *
	 * Maps URL patterns to their oEmbed API endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, array{endpoint: string, schemes: array<int, string>}>
	 */
	protected array $providers = [
		'youtube' => [
			'endpoint' => 'https://www.youtube.com/oembed',
			'schemes'  => [
				'https://www.youtube.com/watch*',
				'https://youtu.be/*',
				'https://www.youtube.com/shorts/*',
			],
		],
		'vimeo' => [
			'endpoint' => 'https://vimeo.com/api/oembed.json',
			'schemes'  => [
				'https://vimeo.com/*',
				'https://player.vimeo.com/video/*',
			],
		],
		'spotify' => [
			'endpoint' => 'https://open.spotify.com/oembed',
			'schemes'  => [
				'https://open.spotify.com/*',
			],
		],
		'soundcloud' => [
			'endpoint' => 'https://soundcloud.com/oembed',
			'schemes'  => [
				'https://soundcloud.com/*',
			],
		],
		'codepen' => [
			'endpoint' => 'https://codepen.io/api/oembed',
			'schemes'  => [
				'https://codepen.io/*/pen/*',
				'https://codepen.io/*/full/*',
			],
		],
		'twitter' => [
			'endpoint' => 'https://publish.twitter.com/oembed',
			'schemes'  => [
				'https://twitter.com/*/status/*',
				'https://x.com/*/status/*',
			],
		],
		'instagram' => [
			'endpoint' => 'https://graph.facebook.com/v18.0/instagram_oembed',
			'schemes'  => [
				'https://www.instagram.com/p/*',
				'https://www.instagram.com/reel/*',
				'https://instagram.com/p/*',
			],
		],
		'facebook' => [
			'endpoint' => 'https://graph.facebook.com/v18.0/oembed_post',
			'schemes'  => [
				'https://www.facebook.com/*/posts/*',
				'https://www.facebook.com/*/videos/*',
				'https://www.facebook.com/photo*',
			],
		],
		'tiktok' => [
			'endpoint' => 'https://www.tiktok.com/oembed',
			'schemes'  => [
				'https://www.tiktok.com/*/video/*',
				'https://www.tiktok.com/@*/video/*',
			],
		],
		'reddit' => [
			'endpoint' => 'https://www.reddit.com/oembed',
			'schemes'  => [
				'https://www.reddit.com/r/*/comments/*',
				'https://reddit.com/r/*/comments/*',
			],
		],
		'bluesky' => [
			'endpoint' => 'https://embed.bsky.app/oembed',
			'schemes'  => [
				'https://bsky.app/profile/*/post/*',
			],
		],
	];

	/**
	 * Cache TTL in seconds (24 hours).
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	protected int $cacheTtl = 86400;

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	protected int $timeout = 10;

	/**
	 * Resolve a URL via oEmbed or OpenGraph fallback.
	 *
	 * Returns the oEmbed response data on success, or an OpenGraph
	 * fallback array if oEmbed resolution fails.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $url    The URL to resolve.
	 * @param int|null    $maxWidth Optional maximum width for the embed.
	 * @param int|null    $maxHeight Optional maximum height for the embed.
	 *
	 * @return array<string, mixed>|null Resolved data or null on complete failure.
	 */
	public function resolve( string $url, ?int $maxWidth = null, ?int $maxHeight = null ): ?array
	{
		$cacheKey = $this->getCacheKey( $url, $maxWidth, $maxHeight );

		$cached = Cache::get( $cacheKey );

		if ( null !== $cached ) {
			return $cached;
		}

		$result = $this->resolveOEmbed( $url, $maxWidth, $maxHeight );

		if ( ! $result ) {
			$result = $this->resolveOpenGraph( $url );
		}

		if ( $result ) {
			Cache::put( $cacheKey, $result, $this->cacheTtl );
		}

		return $result;
	}

	/**
	 * Resolve a URL via oEmbed endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $url       The URL to resolve.
	 * @param int|null $maxWidth  Optional max width.
	 * @param int|null $maxHeight Optional max height.
	 *
	 * @return array<string, mixed>|null The oEmbed response data or null.
	 */
	public function resolveOEmbed( string $url, ?int $maxWidth = null, ?int $maxHeight = null ): ?array
	{
		$endpoint = $this->findEndpoint( $url );

		if ( ! $endpoint ) {
			return null;
		}

		$params = [
			'url'    => $url,
			'format' => 'json',
		];

		if ( $maxWidth ) {
			$params['maxwidth'] = $maxWidth;
		}

		if ( $maxHeight ) {
			$params['maxheight'] = $maxHeight;
		}

		try {
			$response = Http::timeout( $this->timeout )
				->acceptJson()
				->get( $endpoint, $params );

			if ( $response->successful() ) {
				$data             = $response->json();
				$data['_source']  = 'oembed';
				$data['_url']     = $url;

				return $data;
			}
		} catch ( Exception $e ) {
			Log::debug( "oEmbed resolution failed for {$url}: {$e->getMessage()}" );
		}

		return null;
	}

	/**
	 * Resolve a URL via OpenGraph meta tags as fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to resolve.
	 *
	 * @return array<string, mixed>|null OpenGraph data or null.
	 */
	public function resolveOpenGraph( string $url ): ?array
	{
		if ( ! $this->isUrlSafeForFetching( $url ) ) {
			return null;
		}

		try {
			$response = Http::timeout( $this->timeout )
				->withHeaders( [
					'User-Agent' => 'ArtisanPackUI VisualEditor/1.0',
				] )
				->get( $url );

			if ( ! $response->successful() ) {
				return null;
			}

			$html = $response->body();

			return $this->parseOpenGraphTags( $html, $url );
		} catch ( Exception $e ) {
			Log::debug( "OpenGraph resolution failed for {$url}: {$e->getMessage()}" );
		}

		return null;
	}

	/**
	 * Detect the social media platform from a URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to detect.
	 *
	 * @return string|null The platform identifier or null.
	 */
	public function detectPlatform( string $url ): ?string
	{
		$platformPatterns = [
			'twitter'    => [ 'twitter.com', 'x.com' ],
			'instagram'  => [ 'instagram.com' ],
			'facebook'   => [ 'facebook.com', 'fb.com' ],
			'tiktok'     => [ 'tiktok.com' ],
			'linkedin'   => [ 'linkedin.com' ],
			'reddit'     => [ 'reddit.com' ],
			'bluesky'    => [ 'bsky.app' ],
			'youtube'    => [ 'youtube.com', 'youtu.be' ],
			'vimeo'      => [ 'vimeo.com' ],
			'spotify'    => [ 'spotify.com' ],
			'soundcloud' => [ 'soundcloud.com' ],
			'codepen'    => [ 'codepen.io' ],
		];

		$parsedUrl = parse_url( $url );
		$host      = $parsedUrl['host'] ?? '';
		$host      = preg_replace( '/^www\./', '', $host );

		foreach ( $platformPatterns as $platform => $domains ) {
			foreach ( $domains as $domain ) {
				if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
					return $platform;
				}
			}
		}

		return null;
	}

	/**
	 * Check if a URL matches a known oEmbed provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check.
	 *
	 * @return bool Whether the URL matches a known provider.
	 */
	public function hasProvider( string $url ): bool
	{
		return null !== $this->findEndpoint( $url );
	}

	/**
	 * Get the list of supported social platforms.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function getSocialPlatforms(): array
	{
		return [ 'twitter', 'instagram', 'facebook', 'tiktok', 'linkedin', 'reddit', 'bluesky' ];
	}

	/**
	 * Flush cached oEmbed response for a URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $url       The URL whose cache to flush.
	 * @param int|null $maxWidth  Optional max width used in original request.
	 * @param int|null $maxHeight Optional max height used in original request.
	 *
	 * @return void
	 */
	public function flushCache( string $url, ?int $maxWidth = null, ?int $maxHeight = null ): void
	{
		Cache::forget( $this->getCacheKey( $url, $maxWidth, $maxHeight ) );
	}

	/**
	 * Find the oEmbed endpoint for a URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to find an endpoint for.
	 *
	 * @return string|null The endpoint URL or null.
	 */
	protected function findEndpoint( string $url ): ?string
	{
		foreach ( $this->providers as $provider ) {
			foreach ( $provider['schemes'] as $scheme ) {
				if ( $this->urlMatchesScheme( $url, $scheme ) ) {
					return $provider['endpoint'];
				}
			}
		}

		return null;
	}

	/**
	 * Check if a URL matches a provider scheme pattern.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url    The URL to check.
	 * @param string $scheme The scheme pattern (supports * wildcards).
	 *
	 * @return bool Whether the URL matches.
	 */
	protected function urlMatchesScheme( string $url, string $scheme ): bool
	{
		$regex = preg_quote( $scheme, '#' );
		$regex = str_replace( '\*', '.*', $regex );

		return (bool) preg_match( "#^{$regex}$#i", $url );
	}

	/**
	 * Parse OpenGraph meta tags from HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param string $html The HTML to parse.
	 * @param string $url  The original URL.
	 *
	 * @return array<string, mixed>|null Parsed data or null.
	 */
	protected function parseOpenGraphTags( string $html, string $url ): ?array
	{
		$data = [
			'_source' => 'opengraph',
			'_url'    => $url,
			'type'    => 'link',
		];

		// Parse og: and twitter: meta tags.
		if ( preg_match_all( '/<meta\s+(?:property|name)=["\'](?:og|twitter):([^"\']+)["\']\s+content=["\']([^"\']*)["\']/', $html, $matches ) ) {
			foreach ( $matches[1] as $i => $property ) {
				$data[ $property ] = $matches[2][ $i ];
			}
		}

		// Also check reversed attribute order.
		if ( preg_match_all( '/<meta\s+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\'](?:og|twitter):([^"\']+)["\']/', $html, $matches ) ) {
			foreach ( $matches[2] as $i => $property ) {
				if ( ! isset( $data[ $property ] ) ) {
					$data[ $property ] = $matches[1][ $i ];
				}
			}
		}

		// Fallback to <title> tag.
		if ( ! isset( $data['title'] ) && preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $match ) ) {
			$data['title'] = html_entity_decode( trim( $match[1] ), ENT_QUOTES, 'UTF-8' );
		}

		// Fallback to meta description.
		if ( ! isset( $data['description'] ) && preg_match( '/<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)["\']/', $html, $match ) ) {
			$data['description'] = $match[1];
		}

		// Need at least a title to consider it valid.
		if ( ! isset( $data['title'] ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * Check if a URL is safe to fetch (SSRF protection).
	 *
	 * Rejects localhost, loopback, and private/reserved IP ranges.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to validate.
	 *
	 * @return bool Whether the URL is safe to fetch.
	 */
	protected function isUrlSafeForFetching( string $url ): bool
	{
		$parsed = parse_url( $url );

		if ( ! $parsed || ! isset( $parsed['scheme'], $parsed['host'] ) ) {
			return false;
		}

		// Only allow http/https schemes.
		if ( ! in_array( strtolower( $parsed['scheme'] ), [ 'http', 'https' ], true ) ) {
			return false;
		}

		$host = strtolower( $parsed['host'] );

		// Reject common localhost hostnames.
		$blockedHosts = [ 'localhost', '127.0.0.1', '::1', '0.0.0.0', '[::1]' ];

		if ( in_array( $host, $blockedHosts, true ) ) {
			return false;
		}

		// Resolve hostname and reject private/reserved IP ranges.
		$ip = gethostbyname( $host );

		if ( $ip !== $host && ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate a cache key for a URL resolution.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $url       The URL.
	 * @param int|null $maxWidth  Optional max width.
	 * @param int|null $maxHeight Optional max height.
	 *
	 * @return string The cache key.
	 */
	protected function getCacheKey( string $url, ?int $maxWidth = null, ?int $maxHeight = null ): string
	{
		return 've_oembed_' . md5( $url . '_' . ( $maxWidth ?? '' ) . '_' . ( $maxHeight ?? '' ) );
	}
}
