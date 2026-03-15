<?php

/**
 * Block Registry.
 *
 * Centralized registry for managing block type registrations,
 * lookups, and category filtering.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\VisualEditor\Blocks;

use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;
use InvalidArgumentException;

/**
 * Registry for visual editor block types.
 *
 * @package    ArtisanPack_UI
 * @subpackage VisualEditor\Blocks
 *
 * @since      1.0.0
 */
class BlockRegistry
{
	/**
	 * Registered block instances keyed by type.
	 *
	 * @since 1.0.0
	 *
	 * @var array<string, BlockInterface>
	 */
	protected array $blocks = [];

	/**
	 * Register a block type.
	 *
	 * @since 1.0.0
	 *
	 * @param BlockInterface $block The block instance to register.
	 *
	 * @return void
	 */
	public function register( BlockInterface $block ): void
	{
		$type = $block->getType();

		if ( '' === $type ) {
			throw new InvalidArgumentException( 'Block type must be a non-empty string.' );
		}

		$this->blocks[ $type ] = $block;

		if ( function_exists( 'doAction' ) ) {
			doAction( 'ap.visualEditor.block.registered', $block );
		}
	}

	/**
	 * Unregister one or more block types.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string>|string $type The block type(s) to unregister.
	 *
	 * @return void
	 */
	public function unregister( string|array $type ): void
	{
		$types = is_array( $type ) ? $type : [ $type ];

		foreach ( $types as $t ) {
			unset( $this->blocks[ $t ] );
		}
	}

	/**
	 * Unregister all blocks in a category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to unregister.
	 *
	 * @return void
	 */
	public function unregisterCategory( string $category ): void
	{
		$this->blocks = array_filter(
			$this->blocks,
			fn ( BlockInterface $block ) => $category !== $block->getCategory(),
		);
	}

	/**
	 * Get a registered block by type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return BlockInterface|null
	 */
	public function get( string $type ): ?BlockInterface
	{
		return $this->blocks[ $type ] ?? null;
	}

	/**
	 * Check if a block type is registered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The block type identifier.
	 *
	 * @return bool
	 */
	public function exists( string $type ): bool
	{
		return isset( $this->blocks[ $type ] );
	}

	/**
	 * Get all registered blocks.
	 *
	 * Applies the blocksRegister filter hook to allow third-party modification.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, BlockInterface>
	 */
	public function all(): array
	{
		$blocks = $this->blocks;

		if ( function_exists( 'applyFilters' ) ) {
			$blocks = applyFilters( 'ap.visualEditor.blocksRegister', $blocks );
		}

		return $blocks;
	}

	/**
	 * Get all registered blocks in a specific category.
	 *
	 * @since 1.0.0
	 *
	 * @param string $category The category to filter by.
	 *
	 * @return array<string, BlockInterface>
	 */
	public function getByCategory( string $category ): array
	{
		return array_filter(
			$this->all(),
			fn ( BlockInterface $block ) => $category === $block->getCategory(),
		);
	}

	/**
	 * Get all unique registered categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function getCategories(): array
	{
		$categories = array_map(
			fn ( BlockInterface $block ) => $block->getCategory(),
			$this->all(),
		);

		return array_values( array_unique( $categories ) );
	}

	/**
	 * Remove all registered blocks.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function clear(): void
	{
		$this->blocks = [];
	}

	/**
	 * Get all blocks that support inner blocks (container blocks).
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, BlockInterface>
	 */
	public function getContainerBlocks(): array
	{
		return array_filter(
			$this->all(),
			fn ( BlockInterface $block ) => $block->supportsInnerBlocks(),
		);
	}

	/**
	 * Get all blocks that are dynamic (server-rendered via Livewire).
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, BlockInterface>
	 */
	public function getDynamicBlocks(): array
	{
		return array_filter(
			$this->all(),
			fn ( BlockInterface $block ) => $block->isDynamic(),
		);
	}

	/**
	 * Get all blocks that have a custom JavaScript renderer.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, BlockInterface>
	 */
	public function getJsRendererBlocks(): array
	{
		return array_filter(
			$this->all(),
			fn ( BlockInterface $block ) => $block->hasJsRenderer(),
		);
	}

	/**
	 * Get block metadata as arrays for passing to JavaScript.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function toArray(): array
	{
		$result = [];

		foreach ( $this->all() as $type => $block ) {
			$result[ $type ] = $block->toArray();
		}

		return $result;
	}
}
