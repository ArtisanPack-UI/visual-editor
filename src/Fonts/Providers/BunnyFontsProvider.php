<?php

/**
 * Bunny Fonts font source provider.
 *
 * Browses and installs from Bunny Fonts, the GDPR-first drop-in replacement
 * for Google Fonts, without an API key:
 *
 * - The catalog comes from the keyless list endpoint ({@see $listUrl}), a JSON
 *   object keyed by family slug that reports each family's weights, styles,
 *   subsets, and whether it is variable. It is fetched once and cached for
 *   {@see $cacheTtl} seconds; search and family lookup read the cache.
 * - Face files come from the keyless CSS endpoint ({@see $cssUrl}), which — like
 *   Google's — returns `@font-face` rules whose `src` points at Bunny's public
 *   `fonts.bunny.net` `.woff2`; {@see fetchFace()} parses out the chosen
 *   subset's URL and downloads the bytes server-side, so installed fonts are
 *   self-hosted and GDPR-safe.
 *
 * Bunny's list endpoint exposes no per-axis ranges even for variable families,
 * so {@see getFamily()} reports `axes => []` and an empty `license`; the
 * `is_variable` flag is carried through for the picker, and axis metadata is
 * recovered from the downloaded files by the variable-font parser at install
 * time. Because the list reports `weights` and `styles` as independent lists
 * rather than an explicit variant matrix, faces are the product of the two;
 * any combination the family does not actually ship simply yields no WOFF2 URL
 * from the CSS endpoint and {@see fetchFace()} rejects it.
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
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class BunnyFontsProvider implements FontProvider
{
	/**
	 * Cache key prefix for the normalized catalog. The active {@see $listUrl}
	 * is folded in by {@see catalogCacheKey()} so a repointed endpoint — or two
	 * differently-configured apps sharing a cache store — never serve each
	 * other's catalog.
	 */
	protected const CACHE_KEY = 'artisanpack.visual-editor.fonts.bunny.catalog';

	/**
	 * Host suffixes a resolved face file may be downloaded from. The CSS body
	 * is parsed by regex, so the extracted URL is treated as untrusted and
	 * checked against this allowlist before any request is made — a hostile or
	 * MITM'd CSS response cannot point the downloader at an internal host.
	 */
	protected const ALLOWED_FILE_HOST_SUFFIXES = [ 'bunny.net' ];

	/**
	 * Per-instance memo of the normalized catalog. The registry keeps providers
	 * as effective singletons, so caching the multi-MB unserialized catalog on
	 * the instance avoids re-reading and re-unserializing it from the cache store
	 * on every catalog/family/face call within a request.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	protected ?array $catalog = null;

	/**
	 * @param  string  $listUrl    The keyless family-list endpoint.
	 * @param  string  $cssUrl     The keyless CSS endpoint face files resolve through.
	 * @param  string  $userAgent  Sent with CSS requests so Bunny serves WOFF2.
	 * @param  int     $perPage    Families returned per {@see searchCatalog()} page.
	 * @param  int     $cacheTtl   Seconds to cache the fetched catalog.
	 * @param  int     $timeout    HTTP request timeout in seconds.
	 * @param  string  $subset     The `@font-face` subset to self-host (e.g. `latin`).
	 * @param  int     $maxBytes   Ceiling on any single fetched response body, in bytes.
	 */
	public function __construct(
		protected string $listUrl = 'https://fonts.bunny.net/list',
		protected string $cssUrl = 'https://fonts.bunny.net/css',
		protected string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
		protected int $perPage = 24,
		protected int $cacheTtl = 86400,
		protected int $timeout = 10,
		protected string $subset = 'latin',
		protected int $maxBytes = 15_728_640,
	) {
		// A non-positive page size would let searchCatalog() slice an empty
		// window while reporting has_more, paging forever over no results.
		$this->perPage = max( 1, $this->perPage );

		// A non-positive ceiling would reject every response; keep a sane floor.
		$this->maxBytes = max( 1, $this->maxBytes );
	}

	/**
	 * @since 1.7.0
	 */
	public function key(): string
	{
		return 'bunny';
	}

	/**
	 * @since 1.7.0
	 */
	public function label(): string
	{
		return __( 'Bunny Fonts' );
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
		$slug   = trim( $slug );
		$family = $this->catalog()[ $slug ] ?? null;

		if ( null === $family ) {
			throw new FontProviderException( __(
				'Bunny Fonts has no family for slug ":slug".',
				[ 'slug' => $slug ]
			) );
		}

		$style  = strtolower( trim( $style ) );
		$weight = trim( $weight );

		// Reject an unsupported style up front: without this an `oblique` (or
		// any non-`italic` token) would fall through to the normal face rather
		// than being refused.
		if ( ! in_array( $style, [ 'normal', 'italic' ], true ) ) {
			throw new FontProviderException( __(
				'Bunny Fonts does not support the ":style" style.',
				[ 'style' => $style ]
			) );
		}

		// Validate the weight token rather than casting it: `(int) '400junk'`
		// would silently resolve to the 400 face.
		if ( ! ctype_digit( $weight ) ) {
			throw new FontProviderException( __(
				'Bunny Fonts weight ":weight" is not a numeric weight.',
				[ 'weight' => $weight ]
			) );
		}

		$isItalic = 'italic' === $style;
		$variant  = $weight . ( $isItalic ? 'i' : '' );

		if ( ! in_array( $variant, $family['variants'], true ) ) {
			throw new FontProviderException( __(
				'Bunny Fonts family ":family" has no :weight :style face.',
				[ 'family' => $family['family'], 'weight' => $weight, 'style' => $style ]
			) );
		}

		$css     = $this->fetchFaceCss( $slug, (int) $weight, $isItalic );
		$fileUrl = $this->resolveFaceUrl( $css );

		if ( null === $fileUrl ) {
			throw new FontProviderException( __(
				'Bunny Fonts returned no WOFF2 URL for ":family" :weight :style.',
				[ 'family' => $family['family'], 'weight' => $weight, 'style' => $style ]
			) );
		}

		$this->assertDownloadable( $fileUrl );

		return $this->download( $fileUrl );
	}

	/**
	 * Fetch and normalize the Bunny Fonts catalog, keyed by family slug.
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
		return $this->catalog ??= Cache::remember( $this->catalogCacheKey(), $this->cacheTtl, function (): array {
			return $this->fetchCatalog();
		} );
	}

	/**
	 * The catalog cache key, scoped to the active list endpoint.
	 *
	 * @since 1.7.0
	 */
	protected function catalogCacheKey(): string
	{
		return self::CACHE_KEY . ':' . md5( $this->listUrl );
	}

	/**
	 * Hit the keyless list endpoint and normalize its slug-keyed families.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function fetchCatalog(): array
	{
		try {
			$response = Http::timeout( $this->timeout )
				->withOptions( [ 'allow_redirects' => false, 'stream' => true ] )
				->get( $this->listUrl );
		} catch ( Throwable $e ) {
			throw new FontProviderException(
				'Failed to reach the Bunny Fonts list endpoint.',
				0,
				$e
			);
		}

		if ( ! $response->successful() ) {
			throw new FontProviderException( sprintf(
				'The Bunny Fonts list endpoint returned HTTP %d.',
				$response->status()
			) );
		}

		$list = json_decode(
			trim( $this->readBounded( $response, 'the Bunny Fonts list endpoint' ) ),
			true
		);

		if ( ! is_array( $list ) || [] === $list ) {
			throw new FontProviderException(
				'The Bunny Fonts list response did not contain a font list.'
			);
		}

		$catalog = [];

		foreach ( $list as $slug => $item ) {
			if ( ! is_array( $item ) || empty( $item['familyName'] ) ) {
				continue;
			}

			$slug             = trim( (string) $slug );
			$catalog[ $slug ] = $this->normalizeFamily( $slug, $item );
		}

		// Never return (and therefore never cache) an empty catalog: an empty
		// array is a non-null cache hit, so a one-off bad response would
		// otherwise blank the Font Library for the full cache_ttl.
		if ( [] === $catalog ) {
			throw new FontProviderException(
				'The Bunny Fonts list response contained no usable fonts.'
			);
		}

		return $catalog;
	}

	/**
	 * Normalize one list entry into the provider's family shape.
	 *
	 * Bunny reports `weights` and `styles` as independent lists, so the
	 * installable faces are their product. Variant tokens (`400`, `400i`)
	 * mirror the Google provider's shape so the installer and picker can treat
	 * both sources identically.
	 *
	 * @since 1.7.0
	 *
	 * @param  string                $slug  The family slug (the list key).
	 * @param  array<string, mixed>  $item  A raw list font entry.
	 *
	 * @return array<string, mixed>
	 */
	protected function normalizeFamily( string $slug, array $item ): array
	{
		$weights = $this->normalizeWeights( $item['weights'] ?? [] );
		$styles  = $this->normalizeStyles( $item['styles'] ?? [] );

		$variants = [];
		$faces    = [];

		foreach ( $weights as $weight ) {
			foreach ( $styles as $style ) {
				$isItalic   = 'italic' === $style;
				$variants[] = $weight . ( $isItalic ? 'i' : '' );
				$faces[]    = [ 'weight' => $weight, 'style' => $style ];
			}
		}

		return [
			'slug'        => $slug,
			'family'      => (string) $item['familyName'],
			'category'    => isset( $item['category'] ) ? Str::slug( (string) $item['category'] ) : null,
			'variants'    => $variants,
			'is_variable' => (bool) ( $item['isVariable'] ?? false ),
			'axes'        => [],
			'faces'       => $faces,
		];
	}

	/**
	 * Reduce the raw `weights` list to a sorted list of positive integers.
	 *
	 * @since 1.7.0
	 *
	 * @param  mixed  $weights  The raw `weights` value from the list entry.
	 *
	 * @return array<int, int>
	 */
	protected function normalizeWeights( mixed $weights ): array
	{
		if ( ! is_array( $weights ) ) {
			return [];
		}

		$normalized = array_values( array_unique( array_filter(
			array_map( 'intval', $weights ),
			static fn ( int $weight ): bool => $weight > 0
		) ) );

		sort( $normalized );

		return $normalized;
	}

	/**
	 * Reduce the raw `styles` list to `normal`/`italic`, normal first.
	 *
	 * Ordering normal before italic keeps the generated faces stable
	 * regardless of the order Bunny happens to list the styles in.
	 *
	 * @since 1.7.0
	 *
	 * @param  mixed  $styles  The raw `styles` value from the list entry.
	 *
	 * @return array<int, string>
	 */
	protected function normalizeStyles( mixed $styles ): array
	{
		if ( ! is_array( $styles ) ) {
			return [ 'normal' ];
		}

		$styles = array_map( static fn ( $style ): string => strtolower( (string) $style ), $styles );

		$ordered = array_values( array_filter(
			[ 'normal', 'italic' ],
			static fn ( string $style ): bool => in_array( $style, $styles, true )
		) );

		return [] === $ordered ? [ 'normal' ] : $ordered;
	}

	/**
	 * Request the CSS `@font-face` payload for a single face.
	 *
	 * Bunny's keyless `/css` endpoint keys families by their slug and denotes
	 * italics with a trailing `i` on the weight (e.g. `abeezee:400i`).
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $slug      The family slug.
	 * @param  int     $weight    The face weight.
	 * @param  bool    $isItalic  Whether the italic style is wanted.
	 */
	protected function fetchFaceCss( string $slug, int $weight, bool $isItalic ): string
	{
		$url = $this->cssUrl . '?' . http_build_query( [
			'family'  => $slug . ':' . $weight . ( $isItalic ? 'i' : '' ),
			'display' => 'swap',
		] );

		try {
			$response = Http::timeout( $this->timeout )
				->withHeaders( [ 'User-Agent' => $this->userAgent ] )
				->withOptions( [ 'allow_redirects' => false, 'stream' => true ] )
				->get( $url );
		} catch ( Throwable $e ) {
			throw new FontProviderException(
				__( 'Failed to resolve the Bunny Fonts face CSS for ":slug".', [ 'slug' => $slug ] ),
				0,
				$e
			);
		}

		if ( ! $response->successful() ) {
			throw new FontProviderException( __(
				'Bunny Fonts returned HTTP :status resolving the face CSS for ":slug".',
				[ 'status' => $response->status(), 'slug' => $slug ]
			) );
		}

		return $this->readBounded( $response, sprintf( 'the Bunny Fonts face CSS for "%s"', $slug ) );
	}

	/**
	 * Pick the WOFF2 URL for the configured subset out of a CSS payload,
	 * falling back to the first WOFF2 `src` when the subset is unlabeled.
	 *
	 * @since 1.7.0
	 *
	 * @param  string  $css  The CSS response body.
	 */
	protected function resolveFaceUrl( string $css ): ?string
	{
		// Subset labels are short slugs (`latin`, `latin-ext`); bounding the
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
		// take the first WOFF2 in document order. Bunny always comments its
		// subsets, so this only fires on a malformed response and may not be
		// the latin subset.
		return preg_match( '#url\((?<url>[^)]+?\.woff2)\)#', $css, $match )
			? $match['url']
			: null;
	}

	/**
	 * Guard a parsed face URL before it is fetched.
	 *
	 * The URL comes out of a regex over the CSS response, so it is treated as
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
				'Refusing to download a Bunny Fonts face from the untrusted URL "%s".',
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
	 * @param  string  $fileUrl  The Bunny WOFF2 URL.
	 */
	protected function download( string $fileUrl ): string
	{
		try {
			$response = Http::timeout( $this->timeout )
				->withOptions( [ 'allow_redirects' => false, 'stream' => true ] )
				->get( $fileUrl );
		} catch ( Throwable $e ) {
			throw new FontProviderException(
				__( 'Failed to download the Bunny Fonts face at ":url".', [ 'url' => $fileUrl ] ),
				0,
				$e
			);
		}

		if ( ! $response->successful() ) {
			throw new FontProviderException( __(
				'Bunny Fonts returned HTTP :status downloading the face at ":url".',
				[ 'status' => $response->status(), 'url' => $fileUrl ]
			) );
		}

		$body = $this->readBounded( $response, sprintf( 'the Bunny Fonts face at "%s"', $fileUrl ) );

		if ( ! str_starts_with( $body, 'wOF2' ) ) {
			throw new FontProviderException( __(
				'The file downloaded from ":url" is not a WOFF2 font.',
				[ 'url' => $fileUrl ]
			) );
		}

		return $body;
	}

	/**
	 * Read a response body in bounded chunks, aborting once it exceeds the
	 * configured ceiling so a compromised or MITM'd upstream returning a
	 * multi-gigabyte body cannot exhaust the worker. Paired with `stream => true`
	 * on the request so the transport does not buffer the whole body first.
	 *
	 * @since 1.7.0
	 */
	protected function readBounded( Response $response, string $context ): string
	{
		$stream = $response->toPsrResponse()->getBody();
		$body   = '';

		while ( ! $stream->eof() ) {
			$body .= $stream->read( 65536 );

			if ( strlen( $body ) > $this->maxBytes ) {
				throw new FontProviderException( sprintf(
					'The response from %s exceeded the maximum allowed size of %d bytes.',
					$context,
					$this->maxBytes
				) );
			}
		}

		return $body;
	}
}
