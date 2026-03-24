<?php

/**
 * Site Identity Resolver Service.
 *
 * Resolves site identity values (title, tagline, logo, home URL) from
 * configuration with filter hooks for developer customization. Supports
 * multiple data sources depending on the application's architecture.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      2.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Services;

/**
 * Service for resolving site identity values from config and filter hooks.
 *
 * Provides a unified interface for accessing site title, tagline, logo URL,
 * and home URL. Each value follows a resolution cascade:
 *   1. Visual editor config (`artisanpack.visual-editor.site_identity.*`)
 *   2. Laravel app config fallback (`app.name`, `app.url`)
 *   3. Filter hook override (`ve.site-identity.*`)
 *
 * Developers can customize the data source by either setting config values
 * or registering filter callbacks for full control.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Services
 *
 * @since      2.0.0
 */
class SiteIdentityResolver
{
	/**
	 * Allowed URL schemes for site identity URLs.
	 *
	 * @since 2.0.0
	 *
	 * @var array<int, string>
	 */
	private const SAFE_SCHEMES = [ '', 'http', 'https' ];

	/**
	 * Get the site title.
	 *
	 * Resolution order:
	 *   1. `artisanpack.visual-editor.site_identity.title` config value
	 *   2. `app.name` config fallback
	 *   3. `ve.site-identity.title` filter hook
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getTitle(): string
	{
		$title = config( 'artisanpack.visual-editor.site_identity.title' );

		if ( null === $title || '' === $title ) {
			$title = config( 'app.name', 'Laravel' );
		}

		return (string) veApplyFilters( 've.site-identity.title', $title );
	}

	/**
	 * Get the site tagline/description.
	 *
	 * Resolution order:
	 *   1. `artisanpack.visual-editor.site_identity.tagline` config value
	 *   2. `ve.site-identity.tagline` filter hook
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getTagline(): string
	{
		$tagline = config( 'artisanpack.visual-editor.site_identity.tagline', '' );

		return (string) veApplyFilters( 've.site-identity.tagline', $tagline );
	}

	/**
	 * Get the site logo URL.
	 *
	 * Resolution order:
	 *   1. `artisanpack.visual-editor.site_identity.logo_url` config value
	 *   2. `ve.site-identity.logo-url` filter hook
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getLogoUrl(): string
	{
		$logoUrl = config( 'artisanpack.visual-editor.site_identity.logo_url', '' );

		return $this->sanitizeUrl( (string) veApplyFilters( 've.site-identity.logo-url', $logoUrl ) );
	}

	/**
	 * Get the site home URL.
	 *
	 * Resolution order:
	 *   1. `artisanpack.visual-editor.site_identity.home_url` config value
	 *   2. `app.url` config fallback
	 *   3. `ve.site-identity.home-url` filter hook
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getHomeUrl(): string
	{
		$homeUrl = config( 'artisanpack.visual-editor.site_identity.home_url' );

		if ( null === $homeUrl || '' === $homeUrl ) {
			$homeUrl = config( 'app.url', '/' );
		}

		return $this->sanitizeUrl( (string) veApplyFilters( 've.site-identity.home-url', $homeUrl ), '/' );
	}

	/**
	 * Get the alt text for the site logo.
	 *
	 * Defaults to the site title for accessibility.
	 *
	 * Resolution order:
	 *   1. `artisanpack.visual-editor.site_identity.logo_alt` config value
	 *   2. Site title fallback
	 *   3. `ve.site-identity.logo-alt` filter hook
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function getLogoAlt(): string
	{
		$logoAlt = config( 'artisanpack.visual-editor.site_identity.logo_alt' );

		if ( null === $logoAlt || '' === $logoAlt ) {
			$logoAlt = $this->getTitle();
		}

		return (string) veApplyFilters( 've.site-identity.logo-alt', $logoAlt );
	}

	/**
	 * Get all site identity values as an array.
	 *
	 * @since 2.0.0
	 *
	 * @return array{title: string, tagline: string, logoUrl: string, logoAlt: string, homeUrl: string}
	 */
	public function toArray(): array
	{
		return [
			'title'   => $this->getTitle(),
			'tagline' => $this->getTagline(),
			'logoUrl' => $this->getLogoUrl(),
			'logoAlt' => $this->getLogoAlt(),
			'homeUrl' => $this->getHomeUrl(),
		];
	}

	/**
	 * Validate that a URL uses a safe scheme.
	 *
	 * Returns the URL unchanged if the scheme is safe (http, https,
	 * or relative). Returns the fallback for unsafe schemes like
	 * javascript: or data:.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url      The URL to validate.
	 * @param string $fallback The fallback value for unsafe URLs.
	 *
	 * @return string
	 */
	private function sanitizeUrl( string $url, string $fallback = '' ): string
	{
		if ( '' === $url ) {
			return $fallback;
		}

		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		if ( in_array( $scheme, self::SAFE_SCHEMES, true ) ) {
			return $url;
		}

		return $fallback;
	}
}
