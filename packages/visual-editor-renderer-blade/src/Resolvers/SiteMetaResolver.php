<?php

/**
 * Site-meta resolver for the Blade renderer.
 *
 * Returns the values consumed by `core/site-title`, `core/site-tagline`,
 * and `core/site-logo` block partials. Reads from `apGetSetting('site.*')`
 * when cms-framework's settings helper is loaded (G2a), falling back to
 * `config('artisanpack.visual-editor.site_meta')` otherwise so the editor
 * keeps rendering against `null` placeholders even on a visual-editor-only
 * install.
 *
 * Logo/icon ids are converted to URLs through `apGetMediaUrl()` when the
 * media-library helper is available; without it the URL stays empty and
 * the block renderer emits the Gutenberg-shaped empty shell.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditorRendererBlade
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditorRendererBlade\Resolvers;

class SiteMetaResolver
{
	/**
	 * Cached resolved meta for the current request.
	 *
	 * @var array{title: string, description: string, url: string, logoId: ?int, iconId: ?int, logoUrl: string}|null
	 */
	protected ?array $cache = null;

	/**
	 * Returns the resolved site-meta envelope.
	 *
	 * @since 1.0.0
	 *
	 * @return array{title: string, description: string, url: string, logoId: ?int, iconId: ?int, logoUrl: string}
	 */
	public function resolve(): array
	{
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$title       = $this->lookup( 'site.title', 'title' );
		$description = $this->lookup( 'site.tagline', 'description' );
		$url         = $this->lookup( 'site.url', 'url' );
		$logoId      = $this->lookupId( 'site.logo_id', 'logo_id' );
		$iconId      = $this->lookupId( 'site.icon_id', 'icon_id' );
		$logoUrl     = $this->resolveMediaUrl( $logoId );

		$this->cache = [
			'title'       => $this->coerceString( $title ),
			'description' => $this->coerceString( $description ),
			'url'         => $this->coerceString( $url ),
			'logoId'      => $logoId,
			'iconId'      => $iconId,
			'logoUrl'     => $this->coerceString( $logoUrl ),
		];

		return $this->cache;
	}

	/**
	 * Clears the resolver's cache. Useful in long-running workers where a
	 * `site.*` setting was changed mid-process.
	 *
	 * @since 1.0.0
	 */
	public function flush(): void
	{
		$this->cache = null;
	}

	/**
	 * Reads a string-shaped setting, preferring `apGetSetting()` when present.
	 *
	 * @since 1.0.0
	 */
	protected function lookup( string $settingKey, string $configKey ): mixed
	{
		if ( function_exists( 'apGetSetting' ) ) {
			$value = apGetSetting( $settingKey );

			if ( null !== $value && '' !== $value ) {
				return $value;
			}
		}

		return config( 'artisanpack.visual-editor.site_meta.' . $configKey );
	}

	/**
	 * Reads an integer-shaped setting (logo / icon ids).
	 *
	 * @since 1.0.0
	 */
	protected function lookupId( string $settingKey, string $configKey ): ?int
	{
		$value = $this->lookup( $settingKey, $configKey );

		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
			return (int) $value;
		}

		return null;
	}

	/**
	 * Resolves a media id to a public URL via `apGetMediaUrl()`. Returns
	 * the empty string when the helper is unavailable or the id is missing.
	 *
	 * @since 1.0.0
	 */
	protected function resolveMediaUrl( ?int $id ): string
	{
		if ( null === $id || ! function_exists( 'apGetMediaUrl' ) ) {
			return '';
		}

		$url = apGetMediaUrl( $id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Coerce arbitrary scalar values to a string, preserving the empty
	 * string for null / non-scalar input so the block renderer's
	 * `is_string` guard treats unresolved values as "no value".
	 *
	 * @since 1.0.0
	 */
	protected function coerceString( mixed $value ): string
	{
		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
