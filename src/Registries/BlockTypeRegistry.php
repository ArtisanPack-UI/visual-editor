<?php

/**
 * BlockType registry.
 *
 * In-memory registry describing the block types the editor is aware of.
 * Packages and applications can extend this via the service container.
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

class BlockTypeRegistry
{
	/**
	 * @var array<string, array<string, mixed>>
	 */
	protected array $blocks = [];

	public function __construct()
	{
		$this->register( 'core/paragraph', [
			'title'      => 'Paragraph',
			'category'   => 'text',
			'attributes' => [
				'content' => ['type' => 'string', 'default' => ''],
			],
		] );

		$this->register( 'core/heading', [
			'title'      => 'Heading',
			'category'   => 'text',
			'attributes' => [
				'content' => ['type' => 'string', 'default' => ''],
				'level'   => ['type' => 'integer', 'default' => 2],
			],
		] );
	}

	/**
	 * Registers a block type by name.
	 *
	 * @since 1.0.0
	 *
	 * @param  string                $name        The block name (e.g. `core/paragraph`).
	 * @param  array<string, mixed>  $definition  Metadata describing the block.
	 */
	public function register( string $name, array $definition ): void
	{
		$this->blocks[ $name ] = array_merge( ['name' => $name], $definition );
	}

	/**
	 * Removes a block type from the registry.
	 *
	 * @since 1.0.0
	 */
	public function unregister( string $name ): void
	{
		unset( $this->blocks[ $name ] );
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
