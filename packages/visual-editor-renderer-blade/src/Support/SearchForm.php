<?php

/**
 * Form action and prefill helpers for the search blocks.
 *
 * `artisanpack/search` (and its `core/search` alias) and
 * `artisanpack/search-filters` both emit a `<form method="get">`. Where it
 * submits to is set by the block's `action` attribute (#961):
 *
 *  - `''` (the default) submits to the site search URL,
 *    `config('artisanpack.visual-editor.search.url')` (`/search`).
 *  - `'current'` submits to the page being rendered, without its query
 *    string or a trailing `/page/N` segment, so a search on `/blog/page/3`
 *    lands on `/blog?s=…` rather than an out-of-range page.
 *  - Anything else is a custom path, honoured only when it is a same-site
 *    relative path starting with `/`. Absolute, protocol-relative and
 *    otherwise suspect values fall back to the site search URL.
 *
 * A host-stamped `_resolvedFormAction` always wins over the attribute.
 *
 * It also normalises the Search block's `buttonPosition`.
 *
 * Mirrored by `searchFormAction()` in the React and Vue renderers'
 * `support/searchForm.ts`, which have no request to read: they omit the
 * action for `'current'` (the browser then submits to the current
 * document) and use the `/search` default for `''`. Hosts with a
 * different search URL stamp `_resolvedFormAction` there.
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

class SearchForm
{
	/**
	 * The `action` attribute value that submits to the current page.
	 *
	 * @since 1.15.2
	 *
	 * @var string
	 */
	public const ACTION_CURRENT = 'current';

	/**
	 * `buttonPosition` values the Search block accepts, as WordPress does.
	 * The first is the default.
	 *
	 * @since 1.15.2
	 *
	 * @var array<int, string>
	 */
	public const BUTTON_POSITIONS = [ 'button-outside', 'button-inside', 'no-button', 'button-only' ];

	/**
	 * The `buttonPosition` value that renders no submit button.
	 *
	 * @since 1.15.2
	 *
	 * @var string
	 */
	public const BUTTON_NONE = 'no-button';

	/**
	 * Site search URL used when the config key is unset or unusable.
	 *
	 * @since 1.15.2
	 *
	 * @var string
	 */
	public const DEFAULT_SEARCH_URL = '/search';

	/**
	 * Resolve the URL a search form submits to.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attributes  The block's attributes.
	 *
	 * @return string A URL safe to print in the form's `action` attribute.
	 */
	public static function action( array $attributes ): string
	{
		$stamped = $attributes['_resolvedFormAction'] ?? null;

		if ( is_string( $stamped ) ) {
			$stamped = UrlSanitizer::safe( $stamped );

			if ( '' !== $stamped ) {
				return $stamped;
			}
		}

		$action = isset( $attributes['action'] ) && is_string( $attributes['action'] )
			? trim( $attributes['action'] )
			: '';

		if ( self::ACTION_CURRENT === $action ) {
			return self::currentUrl();
		}

		if ( '' !== $action ) {
			$path = self::sameSitePath( $action );

			if ( '' !== $path ) {
				return $path;
			}
		}

		return self::siteSearchUrl();
	}

	/**
	 * The Search block's button position: its `buttonPosition` attribute
	 * when it is one of {@see BUTTON_POSITIONS}, otherwise `button-outside`.
	 *
	 * Mirrored by `searchButtonPosition()` in the React and Vue renderers.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attributes  The block's attributes.
	 */
	public static function buttonPosition( array $attributes ): string
	{
		$position = $attributes['buttonPosition'] ?? null;

		return is_string( $position ) && in_array( $position, self::BUTTON_POSITIONS, true )
			? $position
			: self::BUTTON_POSITIONS[0];
	}

	/**
	 * Return $path when it is a same-site relative path, otherwise ''.
	 *
	 * The path has to start with a single `/`. Protocol-relative URLs
	 * (`//evil.test`), backslashes (browsers read `/\evil.test` as
	 * protocol-relative too), whitespace and control characters are all
	 * rejected, which also rules out any scheme.
	 *
	 * Mirrored by `sameSitePath()` in the React and Vue renderers.
	 *
	 * @since 1.15.2
	 *
	 * @param  string  $path  Stored custom path.
	 *
	 * @return string The trimmed path, or '' when it isn't a same-site path.
	 */
	public static function sameSitePath( string $path ): string
	{
		$path = trim( $path );

		if ( ! str_starts_with( $path, '/' ) || str_starts_with( $path, '//' ) ) {
			return '';
		}

		if ( str_contains( $path, '\\' ) || 1 === preg_match( '/[\x00-\x20\x7F]/', $path ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * The configured site search URL.
	 *
	 * `artisanpack.visual-editor.search.url` may be a relative path or an
	 * absolute URL; an unsafe value falls back to {@see DEFAULT_SEARCH_URL}.
	 *
	 * @since 1.15.2
	 */
	public static function siteSearchUrl(): string
	{
		$configured = function_exists( 'config' )
			? config( 'artisanpack.visual-editor.search.url', self::DEFAULT_SEARCH_URL )
			: self::DEFAULT_SEARCH_URL;

		$url = is_string( $configured ) ? UrlSanitizer::safe( $configured ) : '';

		return '' !== $url ? $url : self::DEFAULT_SEARCH_URL;
	}

	/**
	 * The current request's path, without its query string or a trailing
	 * `/page/N` pagination segment. `?page=N` style pagination lives in the
	 * query string, so it is dropped with it.
	 *
	 * @since 1.15.2
	 *
	 * @return string The path, or `/` outside a request.
	 */
	public static function currentUrl(): string
	{
		if ( ! function_exists( 'request' ) ) {
			return '/';
		}

		$path = parse_url( request()->url(), PHP_URL_PATH );
		$path = is_string( $path ) && '' !== $path ? $path : '/';
		$path = (string) preg_replace( '#/page/\d+/?$#', '', $path );

		return '' !== $path ? $path : '/';
	}

	/**
	 * The search term to prefill the input with: a host-stamped
	 * `_resolvedSearchValue`, otherwise `?{$queryName}` from the request.
	 *
	 * The value is returned raw; the partial escapes it on output.
	 *
	 * @since 1.15.2
	 *
	 * @param  array<string, mixed>  $attributes  The block's attributes.
	 * @param  string                $queryName   The input's `name`.
	 */
	public static function searchValue( array $attributes, string $queryName ): string
	{
		if ( array_key_exists( '_resolvedSearchValue', $attributes ) ) {
			return is_scalar( $attributes['_resolvedSearchValue'] )
				? (string) $attributes['_resolvedSearchValue']
				: '';
		}

		if ( '' === $queryName || ! function_exists( 'request' ) ) {
			return '';
		}

		$value = request()->query( $queryName, '' );

		return is_scalar( $value ) ? (string) $value : '';
	}
}
