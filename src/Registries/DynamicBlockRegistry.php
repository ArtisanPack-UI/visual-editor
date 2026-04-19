<?php

/**
 * DynamicBlock registry.
 *
 * In-memory store of server-rendered block implementations keyed by
 * fully-qualified block name. The generic preview endpoint and future
 * frontend renderers pull blocks out of this registry at request time.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Registries;

use ArtisanPackUI\VisualEditor\Blocks\DynamicBlock;
use InvalidArgumentException;

class DynamicBlockRegistry
{
	/**
	 * @var array<string, DynamicBlock>
	 */
	protected array $blocks = [];

	/**
	 * Register a dynamic block instance under its declared name.
	 *
	 * @since 1.0.0
	 */
	public function register( DynamicBlock $block ): void
	{
		$name = trim( $block->name() );

		if ( '' === $name ) {
			throw new InvalidArgumentException( 'Dynamic block name cannot be empty.' );
		}

		$this->blocks[ $name ] = $block;
	}

	/**
	 * Resolve a dynamic block by name. Returns null when nothing is registered.
	 *
	 * @since 1.0.0
	 */
	public function get( string $name ): ?DynamicBlock
	{
		return $this->blocks[ trim( $name ) ] ?? null;
	}

	/**
	 * True when a block is registered under the given name.
	 *
	 * @since 1.0.0
	 */
	public function has( string $name ): bool
	{
		return isset( $this->blocks[ trim( $name ) ] );
	}

	/**
	 * Remove a registration. No-op when the block is not registered.
	 *
	 * @since 1.0.0
	 */
	public function unregister( string $name ): void
	{
		unset( $this->blocks[ trim( $name ) ] );
	}

	/**
	 * Return every registered block, keyed by name.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, DynamicBlock>
	 */
	public function all(): array
	{
		return $this->blocks;
	}
}
