<?php

/**
 * The active theme's stylesheets, ready to inline into an editor surface.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.15.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\SiteEditor;

use ArtisanPackUI\VisualEditor\Support\CssUrlAbsolutizer;
use ArtisanPackUI\VisualEditor\Support\CssValue;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Reads the active theme's stylesheets for surfaces that inline them — the
 * site-editor canvas (`/global-styles/css`) and pattern previews — instead
 * of linking them. An inlined stylesheet resolves relative `url()`s against
 * the editor page, so every source is run through {@see CssUrlAbsolutizer}
 * against the URL its file is served from (#832, #879).
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @since      1.15.0
 */
class ThemeStylesheets
{
	/**
	 * cms-framework's path-contained top-level stylesheet reader.
	 *
	 * @since 1.15.0
	 */
	public const STYLESHEET_READER_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\SiteEditor\\Support\\ThemeStylesheetReader';

	/**
	 * cms-framework's theme manager, for the active slug and themes root.
	 *
	 * @since 1.15.0
	 */
	public const THEME_MANAGER_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Managers\\ThemeManager';

	/**
	 * cms-framework's loader for the active theme's `Theme.php`.
	 *
	 * @since 1.15.0
	 */
	public const THEME_LOADER_FQCN = 'ArtisanPackUI\\CMSFramework\\Modules\\Themes\\Support\\ThemeLoader';

	/**
	 * cms-framework's public theme-asset route (`/themes/{slug}/assets/{path}`).
	 *
	 * @since 1.15.0
	 */
	public const THEME_ASSETS_ROUTE = 'themes.assets';

	/**
	 * Read a top-level theme stylesheet (`style.css`, `editor.css`) with
	 * its relative `url()`s rewritten against the theme root URL. Empty
	 * when cms-framework, the theme or the file is missing.
	 *
	 * @since 1.15.0
	 *
	 * @param  string  $filename  Bare filename inside the theme root.
	 */
	public function stylesheet( string $filename ): string
	{
		if ( ! class_exists( self::STYLESHEET_READER_FQCN ) || ! app()->bound( self::STYLESHEET_READER_FQCN ) ) {
			return '';
		}

		$css = app( self::STYLESHEET_READER_FQCN )->read( $filename );

		if ( ! is_string( $css ) || '' === $css ) {
			return '';
		}

		$baseUrl = $this->baseUrl();

		return null === $baseUrl ? $css : CssUrlAbsolutizer::absolutize( $css, $baseUrl );
	}

	/**
	 * The stylesheets the active theme declares through
	 * `Theme::editorStyles()` (after the `ap.themes.editorStyles` filter),
	 * read from the theme's `assets/` directory and inlined in order, each
	 * with its `url()`s rewritten against its own directory. This is the
	 * canvas twin of cms-framework's `@themeEditorStyles` directive.
	 *
	 * Entries that point off the theme (absolute or root-relative URLs),
	 * aren't `.css` files, or escape `assets/` are skipped. An entry's
	 * `media` wraps it in an `@media` block. Handles dedupe the same way
	 * the directive does: the first entry wins.
	 *
	 * @since 1.15.0
	 */
	public function editorStyles(): string
	{
		$theme = $this->loadedTheme();

		if ( null === $theme ) {
			return '';
		}

		$slug    = $theme->slug();
		$entries = $theme->editorStyles();

		if ( function_exists( 'applyFilters' ) ) {
			$entries = applyFilters( 'ap.themes.editorStyles', $entries, $slug );
		}

		if ( ! is_array( $entries ) ) {
			return '';
		}

		$parts   = [];
		$handles = [];

		foreach ( $entries as $key => $entry ) {
			$src   = is_array( $entry ) ? ( $entry['src'] ?? null ) : $entry;
			$media = is_array( $entry ) && is_string( $entry['media'] ?? null ) ? trim( $entry['media'] ) : '';

			if ( ! is_string( $src ) || '' === trim( $src ) ) {
				continue;
			}

			$path   = $this->assetPath( trim( $src ) );
			$handle = is_string( $key ) ? $key : pathinfo( trim( $src ), PATHINFO_FILENAME );

			if ( null === $path || '' === $handle || isset( $handles[ $handle ] ) ) {
				continue;
			}

			$handles[ $handle ] = true;

			$css = $this->readAsset( $slug, $path );

			if ( '' === $css ) {
				continue;
			}

			$baseUrl = $this->assetDirectoryUrl( $slug, $path );
			$css     = null === $baseUrl ? $css : CssUrlAbsolutizer::absolutize( $css, $baseUrl );

			if ( '' !== $media && 'all' !== strtolower( $media ) && CssValue::isPrintable( $media ) ) {
				$css = '@media ' . $media . " {\n" . $css . "\n}";
			}

			$parts[] = '/* === editorStyles: ' . str_replace( '*/', '', $path ) . " === */\n" . $css;
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Absolute URL of the active theme's root directory — the directory
	 * `style.css` lives in — derived from cms-framework's theme assets
	 * route, so `url(./assets/bg.jpg)` resolves to the URL that route
	 * serves. Null without an active theme or the route.
	 *
	 * @since 1.15.0
	 */
	public function baseUrl(): ?string
	{
		$slug = $this->activeSlug();

		if ( null === $slug ) {
			return null;
		}

		$assetUrl = $this->assetUrl( $slug, '__ve__' );
		$suffix   = '/assets/__ve__';

		if ( null === $assetUrl || ! str_ends_with( $assetUrl, $suffix ) ) {
			return null;
		}

		return substr( $assetUrl, 0, -strlen( $suffix ) ) . '/';
	}

	/**
	 * The active theme's slug, or null.
	 *
	 * @since 1.15.0
	 */
	protected function activeSlug(): ?string
	{
		if ( ! class_exists( self::THEME_MANAGER_FQCN ) || ! app()->bound( self::THEME_MANAGER_FQCN ) ) {
			return null;
		}

		$theme = app( self::THEME_MANAGER_FQCN )->getActiveTheme();
		$slug  = is_array( $theme ) ? ( $theme['slug'] ?? null ) : null;

		return is_string( $slug ) && '' !== $slug ? $slug : null;
	}

	/**
	 * The active theme's loaded `Theme.php` instance, or null when the
	 * theme ships none or cms-framework predates the theme API.
	 *
	 * @since 1.15.0
	 */
	protected function loadedTheme(): ?object
	{
		if ( ! class_exists( self::THEME_LOADER_FQCN ) ) {
			return null;
		}

		try {
			$theme = app( self::THEME_LOADER_FQCN )->activeTheme();
		} catch ( Throwable ) {
			return null;
		}

		return is_object( $theme ) && method_exists( $theme, 'editorStyles' ) && method_exists( $theme, 'slug' )
			? $theme
			: null;
	}

	/**
	 * Normalise an `editorStyles()` source to a path inside the theme's
	 * `assets/` directory, or null when it points elsewhere or isn't CSS.
	 *
	 * @since 1.15.0
	 */
	protected function assetPath( string $src ): ?string
	{
		if ( str_starts_with( $src, '/' ) || str_starts_with( $src, '\\' ) || 1 === preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $src ) ) {
			return null;
		}

		$path = (string) preg_replace( '/[?#].*$/s', '', $src );
		$path = (string) preg_replace( '#^(\./)+#', '', str_replace( '\\', '/', $path ) );

		if ( '' === $path || str_contains( $path, "\0" ) || 1 === preg_match( '#(^|/)\.\.(/|$)#', $path ) ) {
			return null;
		}

		return 'css' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ? $path : null;
	}

	/**
	 * Read a theme asset, contained to the theme's `assets/` directory.
	 *
	 * @since 1.15.0
	 */
	protected function readAsset( string $slug, string $path ): string
	{
		if ( ! class_exists( self::THEME_MANAGER_FQCN ) || ! app()->bound( self::THEME_MANAGER_FQCN ) ) {
			return '';
		}

		$base = realpath( app( self::THEME_MANAGER_FQCN )->getThemesPath() . '/' . $slug . '/assets' );

		if ( false === $base ) {
			return '';
		}

		$file = realpath( $base . '/' . $path );

		if ( false === $file || ! str_starts_with( $file, $base . DIRECTORY_SEPARATOR ) || ! File::isFile( $file ) ) {
			return '';
		}

		return File::get( $file );
	}

	/**
	 * Absolute URL of the directory an asset is served from.
	 *
	 * @since 1.15.0
	 */
	protected function assetDirectoryUrl( string $slug, string $path ): ?string
	{
		$url = $this->assetUrl( $slug, $path );

		if ( null === $url ) {
			return null;
		}

		return substr( $url, 0, (int) strrpos( $url, '/' ) + 1 );
	}

	/**
	 * The theme-asset route URL for a path, or null without the route.
	 *
	 * @since 1.15.0
	 */
	protected function assetUrl( string $slug, string $path ): ?string
	{
		if ( ! Route::has( self::THEME_ASSETS_ROUTE ) ) {
			return null;
		}

		try {
			return route( self::THEME_ASSETS_ROUTE, [ 'slug' => $slug, 'path' => $path ] );
		} catch ( Throwable ) {
			return null;
		}
	}
}
