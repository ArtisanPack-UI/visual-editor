<?php

/**
 * Block Registry.
 *
 * Centralized registry for managing block type registrations,
 * lookups, and category filtering. Automatically registers view
 * namespaces and Livewire components when blocks are added.
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
use Livewire\Livewire;

/**
 * Registry for visual editor block types.
 *
 * When a block is registered, its co-located views directory is
 * automatically added as a namespaced view path, and dynamic blocks
 * have their Livewire component registered. The `ap.visualEditor.blocks`
 * filter allows third-party code to add or remove blocks from the
 * final collection returned by `all()`.
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
	 * Automatically registers the block's co-located view namespace
	 * and, for dynamic blocks, their Livewire component.
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

		$this->registerBlockViewNamespace( $block );
		$this->registerDynamicComponent( $block );

		veDoAction( 'ap.visualEditor.block.registered', $block );
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
	 * Applies the `ap.visualEditor.blocks` filter hook so that
	 * third-party code can add, remove, or reorder blocks.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, BlockInterface>
	 */
	public function all(): array
	{
		return veApplyFilters( 'ap.visualEditor.blocks', $this->blocks );
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
	 * Applies the `ap.visualEditor.blockCategories` filter so that
	 * third-party code can add custom categories or reorder them.
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

		$categories = array_values( array_unique( $categories ) );

		return veApplyFilters( 'ap.visualEditor.blockCategories', $categories );
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

	/**
	 * Register the view namespace for a block's co-located views.
	 *
	 * If the block has a views/ subdirectory alongside its class,
	 * it is registered as `visual-editor-block-{type}`. Published
	 * (overridden) views take priority.
	 *
	 * @since 2.1.0
	 *
	 * @param BlockInterface $block The block to register views for.
	 *
	 * @return void
	 */
	protected function registerBlockViewNamespace( BlockInterface $block ): void
	{
		$blockDir = $block->getBlockDir();

		if ( null === $blockDir ) {
			return;
		}

		$viewsDir = $blockDir . '/views';

		if ( ! is_dir( $viewsDir ) ) {
			return;
		}

		$type         = $block->getType();
		$namespace    = 'visual-editor-block-' . $type;
		$publishedDir = resource_path( 'views/vendor/visual-editor/blocks/' . $type );

		if ( is_dir( $publishedDir ) ) {
			view()->addNamespace( $namespace, $publishedDir );
		}

		view()->addNamespace( $namespace, $viewsDir );
	}

	/**
	 * Register the Livewire component for a dynamic block.
	 *
	 * @since 2.1.0
	 *
	 * @param BlockInterface $block The block to check and register.
	 *
	 * @return void
	 */
	protected function registerDynamicComponent( BlockInterface $block ): void
	{
		if ( ! ( $block instanceof DynamicBlock ) ) {
			return;
		}

		if ( ! app()->bound( 'livewire' ) ) {
			return;
		}

		Livewire::component( $block->getComponentTag(), $block->getComponent() );
	}
}
