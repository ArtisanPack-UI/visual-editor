<?php

/**
 * Font source registry.
 *
 * In-memory store of {@see FontProvider} instances keyed by their declared
 * {@see FontProvider::key()}. The Font Library modal reads the registry to
 * list available sources, and the installer resolves a provider by key to
 * fetch catalog data and face files.
 *
 * Built-in providers (Google, Bunny, custom upload) are seeded by
 * {@see \ArtisanPackUI\VisualEditor\VisualEditorServiceProvider}; packages add
 * their own by hooking the `ap.visualEditor.registerFontSources` filter, which
 * receives the registry instance and returns it after registering. Follows the
 * conventions of {@see \ArtisanPackUI\VisualEditor\Registries\BlockTypeRegistry}
 * and its object-keyed sibling `BlockBindingSourceRegistry`.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.7.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Fonts\Registries;

use ArtisanPackUI\VisualEditor\Fonts\Contracts\FontProvider;
use InvalidArgumentException;

class FontSourceRegistry
{
	/**
	 * Canonical provider key pattern: lowercase alphanumerics, hyphens, and
	 * underscores, starting with a letter (e.g. `google`, `bunny`, `custom`).
	 *
	 * The key is persisted verbatim as a font's `provider` column, so it is
	 * kept storage- and URL-friendly and free of the `/` separator used by
	 * block names.
	 */
	public const KEY_PATTERN = '/^[a-z][a-z0-9_-]*$/';

	/**
	 * @var array<string, FontProvider>
	 */
	protected array $providers = [];

	/**
	 * Register a font provider under its declared key.
	 *
	 * Re-registering the same key replaces the previous provider (last write
	 * wins), matching the sibling registries.
	 *
	 * @since 1.7.0
	 */
	public function register( FontProvider $provider ): void
	{
		$key = trim( $provider->key() );

		if ( '' === $key ) {
			throw new InvalidArgumentException( 'Font provider key cannot be empty.' );
		}

		if ( 1 !== preg_match( self::KEY_PATTERN, $key ) ) {
			throw new InvalidArgumentException( sprintf(
				'Font provider key "%s" is invalid. Expected lowercase letters, digits, hyphens, and underscores, starting with a letter.',
				$provider->key()
			) );
		}

		$this->providers[ $key ] = $provider;
	}

	/**
	 * Resolve a provider by key. Returns null when nothing is registered.
	 *
	 * @since 1.7.0
	 */
	public function get( string $key ): ?FontProvider
	{
		return $this->providers[ trim( $key ) ] ?? null;
	}

	/**
	 * True when a provider is registered under the given key.
	 *
	 * @since 1.7.0
	 */
	public function has( string $key ): bool
	{
		return isset( $this->providers[ trim( $key ) ] );
	}

	/**
	 * Remove a registration. No-op when the key is not registered.
	 *
	 * @since 1.7.0
	 */
	public function unregister( string $key ): void
	{
		unset( $this->providers[ trim( $key ) ] );
	}

	/**
	 * Return every registered provider, keyed by provider key.
	 *
	 * @since 1.7.0
	 *
	 * @return array<string, FontProvider>
	 */
	public function all(): array
	{
		return $this->providers;
	}
}
