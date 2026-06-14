<?php

/**
 * Block binding source registry.
 *
 * In-memory store of {@see BlockBindingSource} drivers keyed by their
 * declared name. Mirrors {@see DynamicBlockRegistry}: third-party
 * packages register their drivers during a service provider's `boot()`
 * pass and the resolver pulls them out at render time. Re-registering a
 * driver under an existing name overwrites the previous binding so a
 * host application can intentionally replace a built-in driver.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Registries;

use ArtisanPackUI\VisualEditor\Services\Bindings\BlockBindingSource;
use InvalidArgumentException;

class BlockBindingSourceRegistry
{
	/**
	 * Canonical source name pattern: lowercase snake_case slug.
	 *
	 * Anchored to keep names friendly to JSON storage, JavaScript object
	 * keys, and the editor's inspector picker. Pattern intentionally
	 * disallows the `/` namespace separator used by block names so a
	 * source name can never collide with a block name in mixed contexts.
	 */
	public const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

	/**
	 * @var array<string, BlockBindingSource>
	 */
	protected array $sources = [];

	/**
	 * Register a binding source under its declared name.
	 *
	 * @since 1.1.0
	 */
	public function register( BlockBindingSource $source ): void
	{
		$name = trim( $source->name() );

		if ( '' === $name ) {
			throw new InvalidArgumentException( 'Block binding source name cannot be empty.' );
		}

		if ( 1 !== preg_match( self::NAME_PATTERN, $name ) ) {
			throw new InvalidArgumentException( sprintf(
				'Block binding source name "%s" is invalid. Expected lowercase snake_case (letters, digits, underscores).',
				$name
			) );
		}

		$this->sources[ $name ] = $source;
	}

	/**
	 * Resolve a source by name. Returns null when nothing is registered.
	 *
	 * @since 1.1.0
	 */
	public function get( string $name ): ?BlockBindingSource
	{
		return $this->sources[ trim( $name ) ] ?? null;
	}

	/**
	 * True when a source is registered under the given name.
	 *
	 * @since 1.1.0
	 */
	public function has( string $name ): bool
	{
		return isset( $this->sources[ trim( $name ) ] );
	}

	/**
	 * Remove a registration. No-op when nothing is registered.
	 *
	 * @since 1.1.0
	 */
	public function unregister( string $name ): void
	{
		unset( $this->sources[ trim( $name ) ] );
	}

	/**
	 * Return every registered source, keyed by name.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, BlockBindingSource>
	 */
	public function all(): array
	{
		return $this->sources;
	}
}
