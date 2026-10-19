<?php

/**
 * Current-page detection for navigation links and submenus (#975).
 *
 * WordPress marks the menu item for the page being viewed with
 * `current-menu-item` on its `<li>` and `aria-current="page"` on its
 * link, and the items that lead to it with `current-menu-ancestor`.
 * This helper decides which of those a `core/navigation-link` /
 * `core/navigation-submenu` URL earns against the request URL:
 *
 * - **current** — same path, ignoring a trailing slash, the query
 *   string and the fragment. A relative link matches on path alone;
 *   an absolute (or protocol-relative) link must also name the request's
 *   host and port. The scheme is ignored.
 * - **ancestor** — the link's path is a parent segment of the request
 *   path (`/blog` while on `/blog/hello`). The home path `/` is never an
 *   ancestor, so it is only ever current on `/` itself.
 *
 * Only `http(s)` URLs, protocol-relative URLs and root-relative paths
 * (`/…`) are compared; anything else (`#anchor`, `mailto:`, `about`)
 * never matches.
 *
 * The string matching is mirrored line for line by
 * `support/navigationCurrent.ts` in the React and Vue renderers; the
 * shared cases live in
 * `packages/renderer-markup-parity/navigation-current-cases.json`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.2
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Support;

use Throwable;

class NavigationCurrentState
{
	/**
	 * The link points at the page being viewed.
	 *
	 * @since 1.15.2
	 *
	 * @var string
	 */
	public const CURRENT = 'current';

	/**
	 * The link's path is a parent of the page being viewed.
	 *
	 * @since 1.15.2
	 *
	 * @var string
	 */
	public const ANCESTOR = 'ancestor';

	/**
	 * Blocks whose `url` takes part in current-page detection.
	 *
	 * @since 1.15.2
	 *
	 * @var array<int, string>
	 */
	public const LINK_BLOCKS = [ 'core/navigation-link', 'core/navigation-submenu' ];

	/**
	 * The URL of the request being rendered, or '' outside a request.
	 *
	 * @since 1.15.2
	 *
	 * @return string Scheme, host and path of the current request.
	 */
	public static function requestUrl(): string
	{
		try {
			return (string) request()->url();
		} catch ( Throwable ) {
			return '';
		}
	}

	/**
	 * Match a link URL against the current URL.
	 *
	 * @since 1.15.2
	 *
	 * @param  string  $linkUrl     The link's (sanitized) URL.
	 * @param  string  $currentUrl  The URL being viewed.
	 *
	 * @return string {@see self::CURRENT}, {@see self::ANCESTOR} or ''.
	 */
	public static function match( string $linkUrl, string $currentUrl ): string
	{
		$link    = self::parse( $linkUrl );
		$current = self::parse( $currentUrl );

		if ( null === $link || null === $current ) {
			return '';
		}

		if ( null !== $link['host'] ) {
			if ( null === $current['host'] || $link['host'] !== $current['host'] || $link['port'] !== $current['port'] ) {
				return '';
			}
		}

		if ( $link['path'] === $current['path'] ) {
			return self::CURRENT;
		}

		if ( '/' !== $link['path'] && str_starts_with( $current['path'], $link['path'] . '/' ) ) {
			return self::ANCESTOR;
		}

		return '';
	}

	/**
	 * Whether any navigation link or submenu below $innerBlocks is current
	 * or an ancestor of the current URL — the submenu's
	 * `current-menu-ancestor` condition.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<int, mixed>  $innerBlocks  The submenu's child block tree.
	 * @param  string             $currentUrl   The URL being viewed.
	 */
	public static function hasMatchingDescendant( array $innerBlocks, string $currentUrl ): bool
	{
		foreach ( $innerBlocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name       = isset( $block['name'] ) && is_string( $block['name'] ) ? $block['name'] : '';
			$attributes = isset( $block['attributes'] ) && is_array( $block['attributes'] ) ? $block['attributes'] : [];

			if ( in_array( $name, self::LINK_BLOCKS, true ) ) {
				$url = isset( $attributes['url'] ) && is_string( $attributes['url'] ) ? UrlSanitizer::safe( $attributes['url'] ) : '';

				if ( '' !== self::match( $url, $currentUrl ) ) {
					return true;
				}
			}

			$children = isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ? $block['innerBlocks'] : [];

			if ( [] !== $children && self::hasMatchingDescendant( $children, $currentUrl ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Split a URL into the parts current-page matching compares.
	 *
	 * Plain string handling rather than `parse_url()` so the TypeScript
	 * twin can follow the exact same steps.
	 *
	 * @since 1.15.2
	 *
	 * @param  string  $url  URL to split.
	 *
	 * @return array{host: string|null, port: string, path: string}|null Null when the URL can't match.
	 */
	protected static function parse( string $url ): ?array
	{
		$url = trim( $url );

		foreach ( [ '#', '?' ] as $delimiter ) {
			$position = strpos( $url, $delimiter );

			if ( false !== $position ) {
				$url = substr( $url, 0, $position );
			}
		}

		if ( '' === $url ) {
			return null;
		}

		$host = null;
		$port = '';

		if ( 1 === preg_match( '/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $url, $matches ) ) {
			$scheme = strtolower( $matches[1] );

			if ( 'http' !== $scheme && 'https' !== $scheme ) {
				return null;
			}

			$url = substr( $url, strlen( $matches[0] ) );

			if ( ! str_starts_with( $url, '//' ) ) {
				return null;
			}
		}

		if ( str_starts_with( $url, '//' ) ) {
			$rest      = substr( $url, 2 );
			$slash     = strpos( $rest, '/' );
			$authority = false === $slash ? $rest : substr( $rest, 0, $slash );
			$url       = false === $slash ? '' : substr( $rest, $slash );
			$at        = strrpos( $authority, '@' );

			if ( false !== $at ) {
				$authority = substr( $authority, $at + 1 );
			}

			$authority = strtolower( $authority );
			$colon     = strrpos( $authority, ':' );

			if ( false !== $colon && false === strpos( substr( $authority, $colon ), ']' ) ) {
				$port      = substr( $authority, $colon + 1 );
				$authority = substr( $authority, 0, $colon );
			}

			if ( '80' === $port || '443' === $port ) {
				$port = '';
			}

			if ( '' === $authority ) {
				return null;
			}

			$host = $authority;
		} elseif ( ! str_starts_with( $url, '/' ) ) {
			return null;
		}

		$path = rtrim( $url, '/' );

		return [
			'host' => $host,
			'port' => $port,
			'path' => '' === $path ? '/' : $path,
		];
	}
}
