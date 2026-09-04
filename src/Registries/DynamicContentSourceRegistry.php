<?php

/**
 * Registry of host-registered Dynamic Content sources.
 *
 * Where cms-framework's own `DynamicContentTypeRegistry` catalogs types
 * authored in the admin UI (DB-backed), this registry catalogs sources
 * a host app, package, or cms-framework plugin/theme registers in code
 * via {@see \ArtisanPackUI\VisualEditor\VisualEditor::registerDynamicContentSource()}.
 * The two registries are merged at query time — host sources take
 * precedence on slug collisions so a host app can intentionally shadow
 * a cms-framework type.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.9.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Registries;

use ArtisanPackUI\VisualEditor\DynamicContent\HostDynamicContentSource;

class DynamicContentSourceRegistry
{
	/**
	 * @var array<string, HostDynamicContentSource>
	 */
	protected array $sources = [];

	/**
	 * Register a source. Re-registering under the same slug overwrites
	 * the previous entry so a host app can intentionally replace one a
	 * package registered earlier.
	 *
	 * @since 1.9.0
	 */
	public function register( HostDynamicContentSource $source ): void
	{
		$this->sources[ $source->slug ] = $source;
	}

	/**
	 * Look up a source by slug. Null when nothing is registered.
	 *
	 * @since 1.9.0
	 */
	public function get( string $slug ): ?HostDynamicContentSource
	{
		return $this->sources[ trim( $slug ) ] ?? null;
	}

	/**
	 * True when a source is registered under the given slug.
	 *
	 * @since 1.9.0
	 */
	public function has( string $slug ): bool
	{
		return isset( $this->sources[ trim( $slug ) ] );
	}

	/**
	 * Remove a registration. No-op when nothing is registered.
	 *
	 * @since 1.9.0
	 */
	public function unregister( string $slug ): void
	{
		unset( $this->sources[ trim( $slug ) ] );
	}

	/**
	 * Return every registered source, keyed by slug.
	 *
	 * @since 1.9.0
	 *
	 * @return array<string, HostDynamicContentSource>
	 */
	public function all(): array
	{
		return $this->sources;
	}
}
