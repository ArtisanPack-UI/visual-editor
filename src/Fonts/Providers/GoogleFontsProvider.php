<?php

/**
 * Google Fonts font source provider.
 *
 * Browses and installs from Google Fonts without an API key, mirroring how
 * WordPress's Font Library avoids the keyed Developer API:
 *
 * - The catalog comes from the keyless metadata endpoint that fonts.google.com
 *   itself consumes ({@see $metadataUrl}), which lists every family with its
 *   weights, styles, and variable-axis ranges. It is fetched once and cached
 *   for {@see $cacheTtl} seconds; search and family lookup read the cache.
 * - Face files come from the keyless CSS2 API ({@see $cssUrl}). Requested with
 *   a browser User-Agent it returns `@font-face` rules whose `src` points at
 *   the public `fonts.gstatic.com` `.woff2`; {@see fetchFace()} parses out the
 *   chosen subset's URL and downloads the bytes server-side, so installed
 *   fonts are self-hosted and GDPR-safe.
 *
 * Neither endpoint exposes per-family license metadata, so {@see getFamily()}
 * reports `license => null`; every Google Fonts family is nonetheless
 * libre-licensed and safe to self-host.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Providers;

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use ArtisanPackUI\VisualEditor\Fonts\Exceptions\FontProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GoogleFontsProvider implements FontProvider
{
	/**
	 * Cache key prefix for the normalized catalog. The active
	 * {@see $metadataUrl} is folded in by {@see catalogCacheKey()} so a
	 * repointed endpoint — or two differently-configured apps sharing a cache
	 * store — never serve each other's catalog.
	 */
	protected const CACHE_KEY = 'artisanpack.visual-editor.fonts.google.catalog';

	/**
	 * Host suffixes a resolved face file may be downloaded from. The CSS2 body
	 * is parsed by regex, so the extracted URL is treated as untrusted and
	 * checked against this allowlist before any request is made — a hostile or
	 * MITM'd CSS response cannot point the downloader at an internal host.
	 */
	protected const ALLOWED_FILE_HOST_SUFFIXES = [ 'gstatic.com' ];

	/**
	 * @param  string  $metadataUrl  The keyless family-metadata endpoint.
	 * @param  string  $cssUrl       The keyless CSS2 endpoint face files resolve through.
	 * @param  string  $userAgent    Sent with CSS2 requests so Google serves WOFF2.
	 * @param  int     $perPage      Families returned per {@see searchCatalog()} page.
	 * @param  int     $cacheTtl     Seconds to cache the fetched catalog.
	 * @param  int     $timeout      HTTP request timeout in seconds.
	 * @param  string  $subset       The `@font-face` subset to self-host (e.g. `latin`).
	 */
	public function __construct(
		protected string $metadataUrl = 'https://fonts.google.com/metadata/fonts',
		protected string $cssUrl = 'https://fonts.googleapis.com/css2',
		protected string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		protected int $perPage = 24,
		protected int $cacheTtl = 86400,
		protected int $timeout = 10,
		protected string $subset = 'latin',
	) {}

	/**
	 * @since 1.7.0
	 */
	public function key(): string
	{
		return 'google';
	}

	/**
	 * @since 1.7.0
	 */
	public function label(): string
	{
		return __( 'Google Fonts' );
	}

	/**
	 * @since 1.7.0
	 */
	public function isSelfHostable(): bool
	{
		return true;
	}

	/**
	 * @since 1.7.0
	 *
	 * @param  string  $query  The search term, or an empty string to browse.
	 * @param  int     $page   The one-based page number.
	 *
	 * @return array{families: array<int, array<string, mixed>>, page: int, has_more: bool}
	 */
	public function searchCatalog( string $query, int $page = 1 ): array
	{
		$page  = max( 1, $page );
		$query = trim( strtolower( $query ) );

		$families = array_values( $this->catalog() );

		if ( '' !== $query ) {
			$families = array_values( array_filter(
				$families,
				static fn ( array $family ): bool => str_contains( strtolower( $family['family'] ), $query )
			) );
		}

		$total  = count( $families );
		$offset = ( $page - 1 ) * $this->perPage;
		$window = array_slice( $families, $offset, $this->perPage );

		$summaries = array_map(
			static fn ( array $family ): array => [
				'slug'        => $family['slug'],
				'family'      => $family['family'],
				'category'    => $family['category'],
				'variants'    => $family['variants'],
				'is_variable' => $family['is_variable'],
			],
			$window
		);

		return [
			'families' => array_values( $summaries ),
			'page'     => $page,
			'has_more' => ( $offset + $this->perPage ) < $total,
		];
	}

	/**
	 * @since 1.7.0
	 *
	 * @param  string  $slug  The provider-scoped family slug.
	 *
	 * @return array<string, mixed>|null
	 */
	public function getFamily( string $slug ): ?array
	{
		$family = $this->catalog()[ trim( $slug ) ] ?? null;

		if ( null === $family ) {
			return null;
		}

		return [
			'slug'        => $family['slug'],
			'family'      => $family['family'],
			'category'    => $family['category'],
			'is_variable' => $family['is_variable'],
			'license'     => null,
			'faces'       => $family['faces'],
			'axes'        => $family['axes'],
		];
	}

	/**
	 * @since 1.7.0
	 *
	 * @param  string  $slug    The provider-scoped family slug.
	 * @param  string  $weight  The face weight (e.g. `400`, `700`).
	 * @param  string  $style   The face style (`normal` or `italic`).
	 *
	 * @return string The raw binary contents of the face file (WOFF2).
	 */
	public function fetchFace( string $slug, string $weight, string $style ): string
	{
		$family = $this->catalog()[ trim( $slug ) ] ?? null;

		if ( null === $family ) {
			throw new FontProviderException( sprintf(
				'Google Fonts has no family for slug "%s".',
				$slug
			) );
		}

		$isItalic = 'italic' === strtolower( $style );
		$variant  = ( (int) $weight ) . ( $isItalic ? 'i' : '' );

		if ( ! in_array( $variant, $family['variants'], true ) ) {
			throw new FontProviderException( sprintf(
				'Google Fonts family "%s" has no %s %s face.',
				$family['family'],
				$weight,
				$style
			) );
		}

		$css     = $this->fetchFaceCss( $family['family'], (int) $weight, $isItalic );
		$fileUrl = $this->resolveFaceUrl( $css );

		if ( null === $fileUrl ) {
			throw new FontProviderException( sprintf(
				'Google Fonts returned no WOFF2 URL for "%s" %s %s.',
				$family['family'],
				$weight,
				$style
			) );
		}

		$this->assertDownloadable( $fileUrl );

		return $this->download( $fileUrl );
	}

	/**
	 * Fetch and normalize the Google Fonts catalog, keyed by family slug.
	 *
	 * Cached for {@see $cacheTtl} seconds. A failed or empty response throws
	 * rather than caching, so a transient outage does not poison the cache
	 * with an empty catalog.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function catalog(): array
	{
		return Cache::remember( $this->catalogCacheKey(), $this->cacheTtl, function (): array {
			return $this->fetchCatalog();
		} );
	}

	/**
	 * The catalog cache key, scoped to the active metadata endpoint.
	 *
	 * @since 1.7.0
	 */
	protected function catalogCacheKey(): string
	{
		return self::CACHE_KEY . ':' . md5( $this->metadataUrl );
	}

	/**
	 * Hit the keyless metadata endpoint and normalize its `familyMetadataList`
	 * into slug-keyed families.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function fetchCatalog(): array
	{
		try {
			$response = Http::timeout( $this->timeout )->get( $this->metadataUrl );
		} catch ( Throwable $e ) {
			throw new FontProviderException(
				'Failed to reach the Google Fonts metadata endpoint.',
				0,
				$e
			);
		}

		if ( ! $response->successful() ) {
			throw new FontProviderException( sprintf(
				'The Google Fonts metadata endpoint returned HTTP %d.',
				$response->status()
			) );
		}

		$payload = $this->decodeMetadata( $response->body() );
		$list    = $payload['familyMetadataList'] ?? null;

		if ( ! is_array( $list ) ) {
			throw new FontProviderException(
				'The Google Fonts metadata response did not contain a font list.'
			);
		}

		$catalog = [];

		foreach ( $list as $item ) {
			if ( ! is_array( $item ) || empty( $item['family'] ) ) {
				continue;
			}

			$slug             = Str::slug( $item['family'] );
			$catalog[ $slug ] = $this->normalizeFamily( $slug, $item );
		}

		return $catalog;
	}

	/**
	 * Decode the metadata body, tolerating Google's anti-JSON-hijacking
	 * `)]}'` prefix if the endpoint ever emits it.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $body  The raw response body.
	 *
	 * @return array<string, mixed>
	 */
	protected function decodeMetadata( string $body ): array
	{
		$body = ltrim( $body );

		if ( str_starts_with( $body, ")]}'" ) ) {
			$body = substr( $body, 4 );
		}

		$decoded = json_decode( trim( $body ), true );

		if ( ! is_array( $decoded ) ) {
			throw new FontProviderException(
				'The Google Fonts metadata response was not valid JSON.'
			);
		}

		return $decoded;
	}

	/**
	 * Normalize one `familyMetadataList` entry into the provider's family shape.
	 *
	 * @since 1.7.0
	 *
	 * @param  string                $slug  The slugified family name.
	 * @param  array<string, mixed>  $item  A raw metadata font entry.
	 *
	 * @return array<string, mixed>
	 */
	protected function normalizeFamily( string $slug, array $item ): array
	{
		// PHP casts numeric string array keys ('400') to ints, so normalize
		// every `fonts` key back to a string token ('400', '400i').
		$variants = array_map(
			'strval',
			array_keys( is_array( $item['fonts'] ?? null ) ? $item['fonts'] : [] )
		);

		$faces = [];
		foreach ( $variants as $variant ) {
			[ $weight, $style ] = $this->parseVariant( $variant );
			$faces[]            = [ 'weight' => $weight, 'style' => $style ];
		}

		$axes = $this->normalizeAxes( $item['axes'] ?? [] );

		return [
			'slug'        => $slug,
			'family'      => (string) $item['family'],
			'category'    => isset( $item['category'] ) ? Str::slug( (string) $item['category'] ) : null,
			'variants'    => $variants,
			'is_variable' => [] !== $axes,
			'axes'        => $axes,
			'faces'       => $faces,
		];
	}

	/**
	 * Reduce the metadata `axes` list to a `tag => {min, max, default}` map.
	 *
	 * @since 1.7.0
	 *
	 * @param  mixed  $axes  The raw `axes` value from the metadata entry.
	 *
	 * @return array<string, array{min: float, max: float, default: float}>
	 */
	protected function normalizeAxes( mixed $axes ): array
	{
		if ( ! is_array( $axes ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $axes as $axis ) {
			if ( ! is_array( $axis ) || empty( $axis['tag'] ) ) {
				continue;
			}

			$normalized[ (string) $axis['tag'] ] = [
				'min'     => (float) ( $axis['min'] ?? 0 ),
				'max'     => (float) ( $axis['max'] ?? 0 ),
				'default' => (float) ( $axis['defaultValue'] ?? 0 ),
			];
		}

		return $normalized;
	}

	/**
	 * Map a metadata variant token to a `[ weight, style ]` pair.
	 *
	 * The metadata keys faces as `400` and `400i`, where the numeric prefix is
	 * the weight and a trailing `i` marks italic.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $variant  A metadata variant token.
	 *
	 * @return array{0: int, 1: string}
	 */
	protected function parseVariant( string $variant ): array
	{
		if ( str_ends_with( $variant, 'i' ) ) {
			return [ (int) substr( $variant, 0, -1 ), 'italic' ];
		}

		return [ (int) $variant, 'normal' ];
	}

	/**
	 * Request the CSS2 `@font-face` payload for a single face.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $family    The display family name.
	 * @param  int     $weight    The face weight.
	 * @param  bool    $isItalic  Whether the italic style is wanted.
	 */
	protected function fetchFaceCss( string $family, int $weight, bool $isItalic ): string
	{
		$url = $this->cssUrl . '?' . http_build_query( [
			'family'  => $family . ':ital,wght@' . ( $isItalic ? 1 : 0 ) . ',' . $weight,
			'display' => 'swap',
		] );

		try {
			$response = Http::timeout( $this->timeout )
				->withHeaders( [ 'User-Agent' => $this->userAgent ] )
				->get( $url );
		} catch ( Throwable $e ) {
			throw new FontProviderException(
				sprintf( 'Failed to resolve the Google Fonts face CSS for "%s".', $family ),
				0,
				$e
			);
		}

		if ( ! $response->successful() ) {
			throw new FontProviderException( sprintf(
				'Google Fonts returned HTTP %d resolving the face CSS for "%s".',
				$response->status(),
				$family
			) );
		}

		return $response->body();
	}

	/**
	 * Pick the WOFF2 URL for the configured subset out of a CSS2 payload,
	 * falling back to the first WOFF2 `src` when the subset is unlabeled.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $css  The CSS2 response body.
	 */
	protected function resolveFaceUrl( string $css ): ?string
	{
		// Subset labels are short slugs (`latin`, `cyrillic-ext`); bounding the
		// capture keeps the pattern linear on a pathological comment that never
		// closes rather than backtracking over the whole body.
		preg_match_all(
			'#/\*\s*(?<subset>[^*]{1,64}?)\s*\*/\s*@font-face\s*\{(?<body>[^}]*)\}#s',
			$css,
			$blocks,
			PREG_SET_ORDER
		);

		$urls = [];

		foreach ( $blocks as $block ) {
			if ( preg_match( '#url\((?<url>[^)]+?\.woff2)\)#', $block['body'], $match ) ) {
				$urls[ trim( $block['subset'] ) ] = $match['url'];
			}
		}

		if ( isset( $urls[ $this->subset ] ) ) {
			return $urls[ $this->subset ];
		}

		if ( [] !== $urls ) {
			return reset( $urls );
		}

		// Last resort when no `@font-face` carried a `/* subset */` comment:
		// take the first WOFF2 in document order. Google always comments its
		// subsets, so this only fires on a malformed response and may not be
		// the latin subset.
		return preg_match( '#url\((?<url>[^)]+?\.woff2)\)#', $css, $match )
			? $match['url']
			: null;
	}

	/**
	 * Guard a parsed face URL before it is fetched.
	 *
	 * The URL comes out of a regex over the CSS2 response, so it is treated as
	 * untrusted: it must be HTTPS and its host must sit under an allowlisted
	 * suffix ({@see ALLOWED_FILE_HOST_SUFFIXES}). This closes the SSRF seam
	 * where a hostile or tampered CSS body could aim the downloader at an
	 * internal or cloud-metadata host.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $fileUrl  The resolved WOFF2 URL.
	 */
	protected function assertDownloadable( string $fileUrl ): void
	{
		$scheme = strtolower( (string) parse_url( $fileUrl, PHP_URL_SCHEME ) );
		$host   = parse_url( $fileUrl, PHP_URL_HOST );

		if ( 'https' !== $scheme || ! is_string( $host ) || ! $this->isAllowedFileHost( $host ) ) {
			throw new FontProviderException( sprintf(
				'Refusing to download a Google Fonts face from the untrusted URL "%s".',
				$fileUrl
			) );
		}
	}

	/**
	 * Whether a host is exactly, or a subdomain of, an allowlisted suffix.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $host  The URL host to check.
	 */
	protected function isAllowedFileHost( string $host ): bool
	{
		$host = strtolower( $host );

		foreach ( self::ALLOWED_FILE_HOST_SUFFIXES as $suffix ) {
			if ( $host === $suffix || str_ends_with( $host, '.' . $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Download an allowlisted face file and return its raw bytes.
	 *
	 * Redirects are not followed — a 3xx from the CDN falls through to the
	 * unsuccessful-response guard rather than chasing an off-allowlist
	 * location — and the body is verified to carry the WOFF2 signature so a
	 * 200 error page never masquerades as a font on disk.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $fileUrl  The gstatic WOFF2 URL.
	 */
	protected function download( string $fileUrl ): string
	{
		try {
			$response = Http::timeout( $this->timeout )
				->withOptions( [ 'allow_redirects' => false ] )
				->get( $fileUrl );
		} catch ( Throwable $e ) {
			throw new FontProviderException(
				sprintf( 'Failed to download the Google Fonts face at "%s".', $fileUrl ),
				0,
				$e
			);
		}

		if ( ! $response->successful() ) {
			throw new FontProviderException( sprintf(
				'Google Fonts returned HTTP %d downloading the face at "%s".',
				$response->status(),
				$fileUrl
			) );
		}

		$body = $response->body();

		if ( ! str_starts_with( $body, 'wOF2' ) ) {
			throw new FontProviderException( sprintf(
				'The file downloaded from "%s" is not a WOFF2 font.',
				$fileUrl
			) );
		}

		return $body;
	}
}
