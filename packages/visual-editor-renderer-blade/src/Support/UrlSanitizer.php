<?php

/**
 * URL sanitization helpers for rendered block attributes.
 *
 * Gutenberg editors can persist arbitrary strings in `url`/`href`-shaped
 * attributes. Before those strings end up in `href` / `src`, we drop
 * dangerous schemes (`javascript:`, `data:`, `vbscript:`) so a stored block
 * tree can't smuggle script execution into the rendered page.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

class UrlSanitizer
{
	/**
	 * Schemes a URL may carry. Anything else is dropped.
	 *
	 * @since 1.0.0
	 *
	 * @var array<int, string>
	 */
	public const SAFE_SCHEMES = [ 'http', 'https', 'mailto', 'tel', 'ftp', 'sms' ];

	/**
	 * Return $url if it's relative or uses a safe scheme, otherwise an empty string.
	 *
	 * The scheme is read the way a browser's URL parser reads it (WHATWG
	 * URL): ASCII tab and newline characters are removed anywhere, leading
	 * C0 controls and spaces are skipped, and a scheme is an ASCII letter
	 * followed by letters, digits, `+`, `-` or `.`, then `:`. Reading it
	 * with `parse_url()` instead let URLs a browser runs as `javascript:`
	 * through: `parse_url()` fails outright on `javascript:///…`, reads
	 * `javascript:1/…` as a host and port, and doesn't see the scheme in
	 * `java<TAB>script:…`.
	 *
	 * Mirrored byte for byte by `safeUrl()` in the React and Vue renderers'
	 * `support/urlSanitizer.ts`.
	 *
	 * @since 1.0.0
	 * @since 1.15.0 Detects the scheme the way browsers do.
	 *
	 * @param  string  $url  Stored URL.
	 *
	 * @return string The trimmed URL, or '' when its scheme isn't allowed.
	 */
	public static function safe( string $url ): string
	{
		$trimmed = trim( $url );

		if ( '' === $trimmed ) {
			return '';
		}

		$scheme = self::scheme( $trimmed );

		if ( null === $scheme ) {
			return $trimmed;
		}

		return in_array( $scheme, self::SAFE_SCHEMES, true ) ? $trimmed : '';
	}

	/**
	 * The lower-cased scheme a browser would read from $url, or null for a
	 * relative URL.
	 *
	 * @since 1.15.0
	 *
	 * @param  string  $url  URL to inspect.
	 */
	public static function scheme( string $url ): ?string
	{
		$probe = ltrim( str_replace( [ "\t", "\n", "\r" ], '', $url ), "\x00..\x20" );

		if ( 1 !== preg_match( '/^([A-Za-z][A-Za-z0-9+.\-]*):/', $probe, $matches ) ) {
			return null;
		}

		return strtolower( $matches[1] );
	}
}
