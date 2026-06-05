<?php

/**
 * BlockType registry.
 *
 * In-memory registry storing block type definitions. Blocks are registered
 * via their block.json metadata (read by VisualEditor::registerBlock()) or
 * programmatically via VisualEditor::registerBlockType().
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

use InvalidArgumentException;

class BlockTypeRegistry
{
	/**
	 * Canonical block name pattern: `namespace/name` using lowercase
	 * alphanumerics and hyphens (e.g. `artisanpack/paragraph`).
	 */
	protected const NAME_PATTERN = '/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/';

	/**
	 * @var array<string, array<string, mixed>>
	 */
	protected array $blocks = [];

	/**
	 * Registers a block type by name.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $name        The block name (e.g. `artisanpack/paragraph`).
	 * @param  array<string, mixed>  $definition  Metadata describing the block (typically from block.json).
	 */
	public function register( string $name, array $definition ): void
	{
		$normalized = trim( $name );

		if ( '' === $normalized ) {
			throw new InvalidArgumentException( 'Block type name cannot be empty.' );
		}

		if ( 1 !== preg_match( self::NAME_PATTERN, $normalized ) ) {
			throw new InvalidArgumentException( sprintf(
				'Block type name "%s" is invalid. Expected format: "namespace/name" using lowercase letters, numbers, and hyphens.',
				$name
			) );
		}

		$this->blocks[ $normalized ] = array_merge( ['name' => $normalized], $definition );
	}

	/**
	 * Returns a single registered block type by name, or null if not found.
	 *
	 * @since 1.0.0
	 *
	 * @param  string  $name  The block name.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( string $name ): ?array
	{
		return $this->blocks[ trim( $name ) ] ?? null;
	}

	/**
	 * Removes a block type from the registry.
	 *
	 * @since 1.0.0
	 */
	public function unregister( string $name ): void
	{
		unset( $this->blocks[ trim( $name ) ] );
	}

	/**
	 * Returns all registered block types as a list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function all(): array
	{
		return array_values( $this->blocks );
	}
}
