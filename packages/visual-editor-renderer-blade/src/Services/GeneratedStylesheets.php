<?php

/**
 * Stores generated CSS at fingerprinted URLs (#1032).
 *
 * The file name is a hash of the content, so when the global styles change
 * the next page links to a new file and nothing needs invalidating. The CSS
 * lives in the application cache, and the renderer's asset route serves it
 * from there with an immutable `Cache-Control`, so a browser asks for it
 * once. A copy under `storage/` lets the route answer after the cache is
 * cleared, since a page cache or CDN can keep HTML that links to this file
 * for longer. Hosts that published their assets (#1033) also get a static
 * copy, so the web server answers without PHP. Nothing is written to
 * `public/` for a host that hasn't published.
 *
 * The URL is only handed out once something can serve it. When CSS can't be
 * moved safely, {@see self::urlFor()} returns null and the caller keeps it
 * inline.
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

use ArtisanPackUI\VisualEditorRendererBlade\Support\FrontendAssets;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GeneratedStylesheets
{
	/**
	 * Directory under the public assets directory, and the URL segment the
	 * asset route answers.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const DIRECTORY = 'generated';

	/**
	 * How long a stylesheet stays in the cache after it was last missing.
	 * Bounds the leftovers from old versions; a stale page or CDN copy is
	 * the only thing still asking for those.
	 *
	 * @since 1.19.0
	 *
	 * @var int
	 */
	protected const CACHE_DAYS = 30;

	public function __construct( protected FrontendAssetPublisher $publisher )
	{
	}

	/**
	 * Store the CSS and return its URL, or null when it should stay inline.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $css  Compiled CSS.
	 */
	public function urlFor( string $css ): ?string
	{
		if ( '' === $css || ! $this->isPortable( $css ) ) {
			return null;
		}

		$hash = substr( hash( 'sha256', $css ), 0, 20 );

		try {
			$cached = $this->cache( $hash, $css );
		} catch ( Throwable $e ) {
			report( $e );

			$cached = false;
		}

		if ( $cached ) {
			$this->writeFile( $this->backupDirectory(), $hash, $css );
		}

		if ( ! $this->writeStaticCopy( $hash, $css ) && ! $cached ) {
			return null;
		}

		return '/' . FrontendAssets::PUBLIC_DIRECTORY . '/' . self::DIRECTORY . '/' . $hash . '.css';
	}

	/**
	 * The stored CSS for a hash, or null when it's gone.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $hash  Content hash from the URL.
	 */
	public function get( string $hash ): ?string
	{
		try {
			$css = Cache::get( $this->cacheKey( $hash ) );
		} catch ( Throwable $e ) {
			report( $e );

			$css = null;
		}

		if ( is_string( $css ) ) {
			return $css;
		}

		$backup = $this->backupDirectory() . '/' . $hash . '.css';
		$css    = is_file( $backup ) ? file_get_contents( $backup ) : false;

		// An empty body would be served as immutable, so a failed read is a miss.
		return false === $css ? null : $css;
	}

	/**
	 * Whether the CSS means the same thing from another URL. A relative
	 * `url()` resolves against the document inline but against the file
	 * once moved, and `@import` / `@charset` are only valid at the start of
	 * a stylesheet, which merged tags can't guarantee.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $css  Compiled CSS.
	 */
	protected function isPortable( string $css ): bool
	{
		if ( 1 === preg_match( '/@(?:import|charset)\b/i', $css ) ) {
			return false;
		}

		return 1 !== preg_match( '/url\(\s*["\']?+(?![a-z][a-z0-9+.-]*:|\/|#)/i', $css );
	}

	/**
	 * Put the CSS in the cache unless it's there already.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $hash  Content hash.
	 * @param  string  $css   Compiled CSS.
	 *
	 * @return bool Whether a later request can read it. An array store
	 *              lasts one request, so it can't.
	 */
	protected function cache( string $hash, string $css ): bool
	{
		if ( Cache::getStore() instanceof ArrayStore ) {
			return false;
		}

		$key = $this->cacheKey( $hash );

		return Cache::has( $key ) || Cache::put( $key, $css, now()->addDays( self::CACHE_DAYS ) );
	}

	/**
	 * Write the static copy for a host that published its assets.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $hash  Content hash.
	 * @param  string  $css   Compiled CSS.
	 *
	 * @return bool Whether a static copy exists.
	 */
	protected function writeStaticCopy( string $hash, string $css ): bool
	{
		if ( ! $this->publisher->isPublished() ) {
			return false;
		}

		return $this->writeFile( public_path( FrontendAssets::PUBLIC_DIRECTORY . '/' . self::DIRECTORY ), $hash, $css );
	}

	/**
	 * Write a stylesheet to a directory unless it's there already.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $directory  Absolute directory.
	 * @param  string  $hash       Content hash.
	 * @param  string  $css        Compiled CSS.
	 *
	 * @return bool Whether the file exists.
	 */
	protected function writeFile( string $directory, string $hash, string $css ): bool
	{
		$file = $directory . '/' . $hash . '.css';

		if ( is_file( $file ) ) {
			return true;
		}

		if ( ! is_dir( $directory ) && ! @mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
			return false;
		}

		// Renamed into place so a concurrent request never serves half a file.
		$temporary = $file . '.' . bin2hex( random_bytes( 4 ) ) . '.tmp';

		return false !== @file_put_contents( $temporary, $css ) && @rename( $temporary, $file );
	}

	/**
	 * Directory of the copies that outlive the cache.
	 *
	 * @since 1.19.0
	 */
	protected function backupDirectory(): string
	{
		return storage_path( 'app/visual-editor/' . self::DIRECTORY );
	}

	/**
	 * Cache key for a stylesheet.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $hash  Content hash.
	 */
	protected function cacheKey( string $hash ): string
	{
		return 'visual-editor.generated-stylesheet.' . $hash;
	}
}
