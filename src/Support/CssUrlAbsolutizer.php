<?php

/**
 * Relative `url()` → absolute URL rewriting for inlined stylesheets.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.13.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Support;

/**
 * Rewrites the relative `url(...)` references in a stylesheet so they
 * resolve against a given base URL instead of the document that inlines
 * the CSS. Pattern previews inline the theme's `style.css` into an
 * `<iframe srcdoc>`, whose base URL is the editor page, so a theme's
 * `url(./assets/bg.jpg)` would otherwise point at the wrong path (#832).
 *
 * Absolute (`https:`, `data:`, any other scheme), protocol-relative
 * (`//cdn…`), root-relative (`/…`) and fragment (`#…`) references are left
 * untouched. Quoted and unquoted forms are both handled, and the original
 * quote character is kept.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.13.0
 */
class CssUrlAbsolutizer
{
	/**
	 * Matches `url( "…" )`, `url( '…' )` and `url( … )`.
	 *
	 * @since 1.13.0
	 */
	protected const URL_PATTERN = '/url\(\s*(?:(["\'])(.*?)\1|([^)"\'\s]*))\s*\)/is';

	/**
	 * Rewrite every relative `url()` in `$css` against `$baseUrl`.
	 *
	 * @since 1.13.0
	 *
	 * @param  string  $css      The stylesheet source.
	 * @param  string  $baseUrl  Absolute URL of the directory the stylesheet lives in.
	 *
	 * @return string The stylesheet with relative references absolutized.
	 */
	public static function absolutize( string $css, string $baseUrl ): string
	{
		if ( '' === $css || '' === $baseUrl || false === stripos( $css, 'url(' ) ) {
			return $css;
		}

		$base = parse_url( $baseUrl );

		if ( ! is_array( $base ) || empty( $base['scheme'] ) || empty( $base['host'] ) ) {
			return $css;
		}

		$origin   = $base['scheme'] . '://' . $base['host'] . ( isset( $base['port'] ) ? ':' . $base['port'] : '' );
		$basePath = $base['path'] ?? '/';

		if ( ! str_ends_with( $basePath, '/' ) ) {
			$basePath .= '/';
		}

		return (string) preg_replace_callback(
			self::URL_PATTERN,
			static function ( array $match ) use ( $origin, $basePath ): string {
				$quote = $match[1] ?? '';
				$url   = '' !== $quote ? $match[2] : ( $match[3] ?? '' );

				if ( ! self::isRelative( $url ) ) {
					return $match[0];
				}

				return 'url(' . $quote . $origin . self::resolvePath( $basePath, $url ) . $quote . ')';
			},
			$css,
		);
	}

	/**
	 * Whether a `url()` value is a document-relative reference that needs
	 * rewriting.
	 *
	 * @since 1.13.0
	 */
	protected static function isRelative( string $url ): bool
	{
		$url = trim( $url );

		if ( '' === $url || str_starts_with( $url, '/' ) || str_starts_with( $url, '#' ) ) {
			return false;
		}

		// Any scheme: https:, data:, blob:, about:, …
		return 1 !== preg_match( '/^[a-z][a-z0-9+.\-]*:/i', $url );
	}

	/**
	 * Resolve a relative reference against a directory path, collapsing
	 * `.` and `..` segments. `..` never climbs above the root.
	 *
	 * @since 1.13.0
	 *
	 * @param  string  $basePath  Directory path ending in `/`.
	 * @param  string  $relative  The relative reference, query and fragment included.
	 */
	protected static function resolvePath( string $basePath, string $relative ): string
	{
		$relative = trim( $relative );
		$suffix   = '';
		$cut      = strcspn( $relative, '?#' );

		if ( $cut < strlen( $relative ) ) {
			$suffix   = substr( $relative, $cut );
			$relative = substr( $relative, 0, $cut );
		}

		$segments = [];

		foreach ( explode( '/', $basePath . $relative ) as $index => $segment ) {
			if ( '..' === $segment ) {
				array_pop( $segments );
				continue;
			}

			if ( '.' === $segment || ( '' === $segment && 0 !== $index ) ) {
				continue;
			}

			$segments[] = $segment;
		}

		$path = implode( '/', $segments );

		if ( ! str_starts_with( $path, '/' ) ) {
			$path = '/' . $path;
		}

		// Keep a trailing slash the reference itself asked for.
		if ( ( str_ends_with( $relative, '/' ) || '' === $relative ) && ! str_ends_with( $path, '/' ) ) {
			$path .= '/';
		}

		return $path . $suffix;
	}
}
