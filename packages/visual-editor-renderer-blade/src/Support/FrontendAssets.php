<?php

/**
 * The files `<x-ve-blocks-styles />` links to, keyed by their public path.
 *
 * `style.css` and `theme.css` come from the bundled block library and every
 * `frontend/*` file from the frontend directory. Both flatten into the same
 * URL prefix, which the asset route, the publisher and the manifest all
 * share through this class (#1033).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.19.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class FrontendAssets
{
	/**
	 * Directory under `public/` that assets are published to, and the URL
	 * prefix the asset route answers.
	 *
	 * @since 1.19.0
	 *
	 * @var string
	 */
	public const PUBLIC_DIRECTORY = 'vendor/visual-editor-renderer-blade';

	/**
	 * Length of the content hash in fingerprinted file names.
	 *
	 * @since 1.19.0
	 *
	 * @var int
	 */
	public const HASH_LENGTH = 12;

	/**
	 * Absolute path of the bundled assets directory.
	 *
	 * @since 1.19.0
	 */
	public static function root(): string
	{
		return dirname( __DIR__, 2 ) . '/resources/assets';
	}

	/**
	 * Every servable asset: public path => absolute source path.
	 *
	 * @since 1.19.0
	 *
	 * @return array<string, string>
	 */
	public static function sources(): array
	{
		$root    = self::root();
		$sources = [];

		foreach ( glob( $root . '/block-library/*.css' ) ?: [] as $file ) {
			$sources[ basename( $file ) ] = $file;
		}

		// Two globs, not `GLOB_BRACE`: Alpine's libc doesn't define it.
		foreach ( [ 'css', 'js' ] as $extension ) {
			foreach ( glob( $root . '/frontend/*.' . $extension ) ?: [] as $file ) {
				$sources[ 'frontend/' . basename( $file ) ] = $file;
			}
		}

		ksort( $sources );

		return $sources;
	}

	/**
	 * Source file a public path maps to, relative to {@see self::root()}.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $path  Public path, e.g. `style.css` or `frontend/tabs.css`.
	 */
	public static function relativeSource( string $path ): string
	{
		return str_starts_with( $path, 'frontend/' ) ? $path : 'block-library/' . $path;
	}

	/**
	 * Content hash of a file, as it appears in fingerprinted names.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $file  Absolute path.
	 */
	public static function hash( string $file ): string
	{
		return substr( (string) hash_file( 'sha256', $file ), 0, self::HASH_LENGTH );
	}

	/**
	 * Insert a hash before a path's extension: `frontend/tabs.css` becomes
	 * `frontend/tabs.3f2a9c1e77ab.css`.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $path  Public path.
	 * @param  string  $hash  Content hash.
	 */
	public static function fingerprintedPath( string $path, string $hash ): string
	{
		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		return substr( $path, 0, -strlen( $extension ) ) . $hash . '.' . $extension;
	}

	/**
	 * Split a fingerprinted path back into its public path and hash, or
	 * null when the path carries no fingerprint.
	 *
	 * @since 1.19.0
	 *
	 * @param  string  $path  Requested path.
	 *
	 * @return array{0: string, 1: string}|null Public path and hash.
	 */
	public static function parseFingerprint( string $path ): ?array
	{
		$pattern = sprintf( '/^(.+)\.([a-f0-9]{%d})\.(css|js)$/D', self::HASH_LENGTH );

		if ( 1 !== preg_match( $pattern, $path, $matches ) ) {
			return null;
		}

		return [ $matches[1] . '.' . $matches[3], $matches[2] ];
	}

	/**
	 * Cheap signature of the bundled files, built from names, sizes and
	 * modification times so it can run on every request. The manifest
	 * stores it, so a package update that nobody published afterwards is
	 * noticed, even one that keeps a file's size (#1033). A deploy that
	 * resets the times costs only the fallback to the asset route.
	 *
	 * @since 1.19.0
	 */
	public static function signature(): string
	{
		$parts = [];

		foreach ( self::sources() as $path => $file ) {
			$parts[] = $path . ':' . ( (int) filesize( $file ) ) . ':' . ( (int) filemtime( $file ) );
		}

		return hash( 'sha256', implode( "\n", $parts ) );
	}
}
