<?php

/**
 * Finds the image files the media library already made, and decides how each
 * `<img>` loads (#1031).
 *
 * The block partials hold a URL and, usually, a media id. This class turns
 * those into a {@see ResponsiveImage}: a `srcset` built from the media
 * record's size variants, dimensions from its metadata, modern formats for
 * `<picture>`, and a loading hint. Nothing here generates files, and every
 * lookup degrades to the plain URL: no media library, no record, or a block
 * whose URL no longer belongs to its media id all print what they always did.
 * So does a failure along the way: a broken lookup must not cost the page its
 * image.
 *
 * Records are read in one query per render ({@see self::prime()}), and the
 * memo is why this is bound per request.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Services;

use ArtisanPackUI\VisualEditorRendererBlade\Support\ResponsiveImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ResponsiveImages
{
	/**
	 * A hero candidate: may take the page's one `fetchpriority="high"`.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const ROLE_HERO = 'hero';

	/**
	 * An ordinary content image.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const ROLE_CONTENT = 'content';

	/**
	 * Loaded normally, with a raised fetch priority.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const LOADING_PRIORITY = 'priority';

	/**
	 * Loaded normally.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const LOADING_EAGER = 'eager';

	/**
	 * Deferred until near the viewport.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const LOADING_LAZY = 'lazy';

	/**
	 * MIME type of each modern format, best first.
	 *
	 * @since 1.19.0
	 *
	 * @var array<string, string>
	 */
	protected const MODERN_FORMATS = [
		'avif' => 'image/avif',
		'webp' => 'image/webp',
	];

	/**
	 * The media library's model. It's an optional dependency.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	protected const MEDIA_CLASS = '\\ArtisanPackUI\\MediaLibrary\\Models\\Media';

	/**
	 * How long a storage probe's result is kept, for records whose metadata
	 * doesn't list their variants.
	 *
	 * @since 1.19.0
	 *
	 * @var int
	 */
	protected const PROBE_CACHE_SECONDS = 86400;

	/**
	 * Records read so far: id => record, or null when none exists.
	 *
	 * @var array<int, object|null>
	 */
	protected array $media = [];

	/**
	 * Records found by URL: URL => record, or null when none exists.
	 *
	 * @var array<string, object|null>
	 */
	protected array $mediaByUrl = [];

	/**
	 * Depth of query-loop-like blocks currently rendering.
	 */
	protected int $loopDepth = 0;

	/**
	 * Whether the page's `fetchpriority="high"` has gone to an image.
	 */
	protected bool $priorityTaken = false;

	/**
	 * Whether an image has rendered in this request yet.
	 */
	protected bool $imageSeen = false;

	/**
	 * Read many records at once, so a tree of images costs one query.
	 *
	 * @since 1.19.0
	 *
	 * @param  array<int, int>  $ids  Media ids.
	 */
	public function prime( array $ids ): void
	{
		$ids = array_values( array_diff( array_unique( $ids ), array_keys( $this->media ) ) );

		if ( [] === $ids ) {
			return;
		}

		try {
			$records = $this->fetchMedia( $ids );
		} catch ( Throwable $e ) {
			report( $e );

			$records = [];
		}

		$this->media += $records + array_fill_keys( $ids, null );
	}

	/**
	 * Mark the start of a block that repeats its children per post. Images
	 * inside can't be the page's hero.
	 *
	 * @since 1.19.0
	 */
	public function enterLoop(): void
	{
		$this->loopDepth++;
	}

	/**
	 * Mark the end of a block {@see self::enterLoop()} opened.
	 *
	 * @since 1.19.0
	 */
	public function leaveLoop(): void
	{
		$this->loopDepth = max( 0, $this->loopDepth - 1 );
	}

	/**
	 * Start a new document's loading order, for a caller that renders
	 * several documents in one request.
	 *
	 * @since 1.19.0
	 */
	public function resetLoadingOrder(): void
	{
		$this->imageSeen     = false;
		$this->priorityTaken = false;
	}

	/**
	 * Build the extra output for an image.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $url           The URL the block will print as `src`.
	 * @param  mixed   $mediaId       The block's media id, if it has one.
	 * @param  string  $role          {@see self::ROLE_HERO} or {@see self::ROLE_CONTENT}.
	 * @param  ?int    $displayWidth  Rendered width in pixels, when the block sets one.
	 * @param  bool    $fullWidth     Whether the image spans the viewport.
	 */
	public function forImage(
		string $url,
		mixed $mediaId,
		string $role = self::ROLE_CONTENT,
		?int $displayWidth = null,
		bool $fullWidth = false,
	): ResponsiveImage {
		$loading = $this->loadingFor( $role );

		try {
			return $this->describe( $url, $mediaId, $loading, $displayWidth, $fullWidth );
		} catch ( Throwable $e ) {
			report( $e );

			return new ResponsiveImage( $loading );
		}
	}

	/**
	 * The smallest square variant sharp at the given size on a 2x screen,
	 * or the URL it was given when there's nothing better.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $url   Avatar URL.
	 * @param  int     $size  Rendered size in pixels.
	 */
	public function avatarUrl( string $url, int $size ): string
	{
		try {
			$media = '' === $url ? null : $this->findByUrl( $url );

			if ( null === $media ) {
				return $url;
			}

			$needed = $size * 2;
			$best   = null;

			foreach ( $this->variants( $media ) as $variant ) {
				if ( $variant['width'] === $variant['height']
					&& $variant['width'] >= $needed
					&& ( null === $best || $variant['width'] < $best['width'] )
				) {
					$best = $variant;
				}
			}

			return null === $best ? $url : $best['url'];
		} catch ( Throwable $e ) {
			report( $e );

			return $url;
		}
	}

	/**
	 * Match an image to its media record and build its output.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $url           The URL the block will print as `src`.
	 * @param  mixed   $mediaId       The block's media id, if it has one.
	 * @param  string  $loading       The image's loading value.
	 * @param  ?int    $displayWidth  Rendered width in pixels, when the block sets one.
	 * @param  bool    $fullWidth     Whether the image spans the viewport.
	 */
	protected function describe( string $url, mixed $mediaId, string $loading, ?int $displayWidth, bool $fullWidth ): ResponsiveImage
	{
		$media = $this->find( $mediaId );

		if ( null === $media || '' === $url ) {
			return new ResponsiveImage( $loading );
		}

		$candidates = $this->candidates( $media );
		$current    = $this->matchCandidate( $candidates, $url );

		// The URL isn't one of this record's files, so the id is stale and
		// nothing about the record describes what the block shows.
		if ( null === $current ) {
			return new ResponsiveImage( $loading );
		}

		$modern = $this->modernUrls( $media );
		$srcset = $this->srcset( $candidates );
		$sizes  = '';

		if ( '' !== $srcset || [] !== $modern ) {
			$width = $displayWidth ?? $current['width'];
			$sizes = $fullWidth ? '100vw' : sprintf( '(max-width: %1$dpx) 100vw, %1$dpx', $width );

			// `auto` lets the browser use the laid-out size of a lazy image,
			// which is the only real answer for a card in a grid. Browsers
			// without it skip the entry and read the rest of the list.
			if ( self::LOADING_LAZY === $loading ) {
				$sizes = 'auto, ' . $sizes;
			}
		}

		return new ResponsiveImage(
			$loading,
			$current['width'],
			$current['height'],
			$srcset,
			$sizes,
			$this->pictureSources( $candidates, $modern ),
		);
	}

	/**
	 * Decide how the next image in document order loads. A hero takes the
	 * page's one priority slot. The first image of any kind isn't deferred,
	 * since it may well be above the fold. Everything else is lazy.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $role  {@see self::ROLE_HERO} or {@see self::ROLE_CONTENT}.
	 */
	protected function loadingFor( string $role ): string
	{
		$isFirst         = ! $this->imageSeen;
		$this->imageSeen = true;

		if ( self::ROLE_HERO === $role && 0 === $this->loopDepth && ! $this->priorityTaken ) {
			$this->priorityTaken = true;

			return self::LOADING_PRIORITY;
		}

		return $isFirst ? self::LOADING_EAGER : self::LOADING_LAZY;
	}

	/**
	 * Look up a record by id.
	 *
	 * @since 1.19.0
	 *
	 * @param  mixed  $mediaId  The block's media id.
	 */
	protected function find( mixed $mediaId ): ?object
	{
		if ( ! is_numeric( $mediaId ) || (int) $mediaId < 1 ) {
			return null;
		}

		$this->prime( [ (int) $mediaId ] );

		return $this->media[ (int) $mediaId ] ?? null;
	}

	/**
	 * Look up a record by its URL, for blocks that only hold a URL, such as
	 * an author's avatar.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $url  File URL.
	 */
	protected function findByUrl( string $url ): ?object
	{
		if ( array_key_exists( $url, $this->mediaByUrl ) ) {
			return $this->mediaByUrl[ $url ];
		}

		$path     = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
		$segments = explode( '/', $path );
		$suffixes = [];

		// A stored path is some tail of the URL's path; what comes before
		// it depends on the disk.
		while ( [] !== $segments ) {
			$suffixes[] = implode( '/', $segments );

			array_shift( $segments );
		}

		$media = '' === $path ? null : $this->fetchMediaByPaths( $suffixes );

		return $this->mediaByUrl[ $url ] = null !== $media && $this->isSameFile( $url, (string) $media->url() ) ? $media : null;
	}

	/**
	 * Whether a URL points at a record's file. Its stored path only has to
	 * be a tail of the URL's, so an off-site image or a theme asset can
	 * match by accident. A root-relative URL is on the app's own host.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $url        URL to check.
	 * @param  string  $recordUrl  The record's file URL.
	 */
	protected function isSameFile( string $url, string $recordUrl ): bool
	{
		if ( parse_url( $url, PHP_URL_PATH ) !== parse_url( $recordUrl, PHP_URL_PATH ) ) {
			return false;
		}

		$appHost = parse_url( (string) config( 'app.url' ), PHP_URL_HOST );

		return ( parse_url( $url, PHP_URL_HOST ) ?? $appHost ) === ( parse_url( $recordUrl, PHP_URL_HOST ) ?? $appHost );
	}

	/**
	 * Read records by id. A seam for tests, and the only place that knows
	 * the media library's model.
	 *
	 * @since 1.19.0
	 *
	 * @param  array<int, int>  $ids  Media ids.
	 *
	 * @return array<int, object> Id => record.
	 */
	protected function fetchMedia( array $ids ): array
	{
		if ( ! class_exists( self::MEDIA_CLASS ) ) {
			return [];
		}

		return ( self::MEDIA_CLASS )::query()->whereKey( $ids )->get()->keyBy( 'id' )->all();
	}

	/**
	 * Read the record stored at one of the given paths.
	 *
	 * @since 1.19.0
	 *
	 * @param  array<int, string>  $paths  Candidate `file_path` values.
	 */
	protected function fetchMediaByPaths( array $paths ): ?object
	{
		if ( ! class_exists( self::MEDIA_CLASS ) ) {
			return null;
		}

		return ( self::MEDIA_CLASS )::query()->whereIn( 'file_path', $paths )->first();
	}

	/**
	 * The full file plus every variant with the same shape, narrowest
	 * first. A cropped thumbnail of a wide image would show a different
	 * picture, so it can't stand in for it in a `srcset`.
	 *
	 * @since 1.19.0
	 *
	 * @param  object  $media  Media record.
	 *
	 * @return array<int, array{url: string, width: int, height: int}>
	 */
	protected function candidates( object $media ): array
	{
		$width  = (int) ( $media->width ?? 0 );
		$height = (int) ( $media->height ?? 0 );

		if ( $width < 1 || $height < 1 ) {
			return [];
		}

		$candidates = [ [ 'url' => (string) $media->url(), 'width' => $width, 'height' => $height ] ];
		$ratio      = $width / $height;

		foreach ( $this->variants( $media ) as $variant ) {
			if ( $variant['width'] < $width && abs( $variant['width'] / $variant['height'] - $ratio ) < 0.02 ) {
				$candidates[] = $variant;
			}
		}

		usort( $candidates, static fn ( array $a, array $b ): int => $a['width'] <=> $b['width'] );

		return $candidates;
	}

	/**
	 * The candidate a URL points at. Compared by path, since a block may
	 * hold an absolute URL for a file the disk reports as relative.
	 *
	 * @since 1.19.0
	 *
	 * @param  array<int, array{url: string, width: int, height: int}>  $candidates  Candidates.
	 * @param  string                                                   $url         URL to match.
	 *
	 * @return array{url: string, width: int, height: int}|null
	 */
	protected function matchCandidate( array $candidates, string $url ): ?array
	{
		$path = parse_url( $url, PHP_URL_PATH );

		foreach ( $candidates as $candidate ) {
			if ( parse_url( $candidate['url'], PHP_URL_PATH ) === $path ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Build a `srcset`, or '' when one file is all there is.
	 *
	 * @since 1.19.0
	 *
	 * @param  array<int, array{url: string, width: int, height: int}>  $candidates  Candidates.
	 */
	protected function srcset( array $candidates ): string
	{
		if ( count( $candidates ) < 2 ) {
			return '';
		}

		return implode( ', ', array_map(
			static fn ( array $candidate ): string => $candidate['url'] . ' ' . $candidate['width'] . 'w',
			$candidates,
		) );
	}

	/**
	 * Build the `<source>` list. The library converts only the full-size
	 * file, and a browser takes the first `<source>` it supports, so a
	 * bare `x.webp` would hand a phone the 1600px file over the 300px JPEG.
	 * Each source therefore keeps the smaller variants and swaps the
	 * modern file in at the top of the list.
	 *
	 * @since 1.19.0
	 *
	 * @param  array<int, array{url: string, width: int, height: int}>  $candidates  Candidates, narrowest first.
	 * @param  array<string, string>                                    $modern      Format => full-size URL.
	 *
	 * @return array<string, string> MIME type => `srcset`.
	 */
	protected function pictureSources( array $candidates, array $modern ): array
	{
		$sources = [];

		foreach ( self::MODERN_FORMATS as $format => $type ) {
			if ( ! isset( $modern[ $format ] ) ) {
				continue;
			}

			$swapped                                 = $candidates;
			$swapped[ count( $swapped ) - 1 ]['url'] = $modern[ $format ];
			$sources[ $type ]                        = 1 === count( $swapped )
				? $modern[ $format ]
				: $this->srcset( $swapped );
		}

		return $sources;
	}

	/**
	 * Size variants of a record with their pixel dimensions. Sizes no
	 * longer in the media config are skipped, since their width is unknown.
	 *
	 * @since 1.19.0
	 *
	 * @param  object  $media  Media record.
	 *
	 * @return array<int, array{url: string, width: int, height: int}>
	 */
	protected function variants( object $media ): array
	{
		$sizes    = array_merge(
			(array) config( 'artisanpack.media.image_sizes', [] ),
			(array) config( 'artisanpack.media.custom_image_sizes', [] ),
		);
		$variants = [];

		foreach ( $this->files( $media )['sizes'] as $name => $url ) {
			$dimensions = isset( $sizes[ $name ] ) ? $this->variantDimensions( $media, $sizes[ $name ] ) : null;

			if ( null !== $dimensions ) {
				$variants[] = [ 'url' => $url, 'width' => $dimensions[0], 'height' => $dimensions[1] ];
			}
		}

		return $variants;
	}

	/**
	 * Pixel size of a variant, following the media library's resize rules:
	 * a cropped size is exact, any other fits inside its box.
	 *
	 * @since 1.19.0
	 *
	 * @param  object                $media  Media record.
	 * @param  array<string, mixed>  $size   Size config.
	 *
	 * @return array{0: int, 1: int}|null
	 */
	protected function variantDimensions( object $media, array $size ): ?array
	{
		$boxWidth  = (int) ( $size['width'] ?? 0 );
		$boxHeight = (int) ( $size['height'] ?? 0 );
		$width     = (int) ( $media->width ?? 0 );
		$height    = (int) ( $media->height ?? 0 );

		if ( ! empty( $size['crop'] ) && $boxWidth > 0 && $boxHeight > 0 ) {
			return [ $boxWidth, $boxHeight ];
		}

		if ( $width < 1 || $height < 1 || ( $boxWidth < 1 && $boxHeight < 1 ) ) {
			return null;
		}

		$scale = min(
			$boxWidth > 0 ? $boxWidth / $width : PHP_FLOAT_MAX,
			$boxHeight > 0 ? $boxHeight / $height : PHP_FLOAT_MAX,
		);

		return [ (int) round( $width * $scale ), (int) round( $height * $scale ) ];
	}

	/**
	 * Modern-format URLs for a record's full-size file.
	 *
	 * @since 1.19.0
	 *
	 * @param  object  $media  Media record.
	 *
	 * @return array<string, string> Format => URL.
	 */
	protected function modernUrls( object $media ): array
	{
		return $this->files( $media )['modern'];
	}

	/**
	 * The record's generated files. Its metadata lists them without touching
	 * storage. Records whose metadata doesn't (imports, older uploads) are
	 * probed once and remembered, because a probe on a remote disk is a
	 * network call per file.
	 *
	 * @since 1.19.0
	 *
	 * @param  object  $media  Media record.
	 *
	 * @return array{sizes: array<string, string>, modern: array<string, string>}
	 */
	protected function files( object $media ): array
	{
		// Under GD, resizing or converting a GIF keeps one frame, so an
		// animated one would freeze in every browser that took a variant.
		if ( 'image/gif' === ( $media->mime_type ?? null ) ) {
			return [ 'sizes' => [], 'modern' => [] ];
		}

		$metadata = is_array( $media->metadata ?? null ) ? $media->metadata : [];

		if ( ! empty( $metadata['thumbnails'] ) ) {
			return [
				'sizes'  => $this->urlsFromPaths( $media, (array) $metadata['thumbnails'] ),
				'modern' => $this->urlsFromPaths( $media, (array) ( $metadata['modern_formats'] ?? [] ) ),
			];
		}

		$version = (string) ( $media->updated_at ?? '' );

		return Cache::remember(
			'visual-editor.media-files.' . (int) ( $media->id ?? 0 ) . '.' . md5( $version ),
			self::PROBE_CACHE_SECONDS,
			fn (): array => $this->probeFiles( $media ),
		);
	}

	/**
	 * Find a record's generated files on its disk.
	 *
	 * @since 1.19.0
	 *
	 * @param  object  $media  Media record.
	 *
	 * @return array{sizes: array<string, string>, modern: array<string, string>}
	 */
	protected function probeFiles( object $media ): array
	{
		$sizes = method_exists( $media, 'getImageSizes' ) ? (array) $media->getImageSizes() : [];
		$path  = (string) ( $media->file_path ?? '' );
		$disk  = (string) applyFilters( 'ap.mediaLibrary.storageDisk', (string) ( $media->disk ?? '' ), $media );
		$paths = [];

		foreach ( array_keys( self::MODERN_FORMATS ) as $format ) {
			$candidate = substr( $path, 0, -strlen( (string) pathinfo( $path, PATHINFO_EXTENSION ) ) ) . $format;

			if ( $candidate !== $path && Storage::disk( $disk )->exists( $candidate ) ) {
				$paths[ $format ] = $candidate;
			}
		}

		return [ 'sizes' => $sizes, 'modern' => $this->urlsFromPaths( $media, $paths ) ];
	}

	/**
	 * Turn stored paths into URLs. Generated files sit next to the
	 * original, so each URL comes from the original's, without asking the
	 * disk. A URL that doesn't end in the file name (signed, say) can't be
	 * adapted that way, and its record offers no variants.
	 *
	 * @since 1.19.0
	 *
	 * @param  object                 $media  Media record.
	 * @param  array<string, string>  $paths  Key => stored path.
	 *
	 * @return array<string, string> Key => URL.
	 */
	protected function urlsFromPaths( object $media, array $paths ): array
	{
		$url  = (string) $media->url();
		$name = basename( (string) ( $media->file_path ?? '' ) );

		if ( '' === $name || ! str_ends_with( $url, $name ) ) {
			return [];
		}

		$directory = substr( $url, 0, -strlen( $name ) );

		return array_map( static fn ( string $path ): string => $directory . basename( $path ), $paths );
	}
}
