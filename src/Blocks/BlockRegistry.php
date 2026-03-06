<?php

/**
 * Block registry for the visual editor.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\VisualEditor\Blocks;

use ArtisanPackUI\VisualEditor\Blocks\Contracts\BlockInterface;
use InvalidArgumentException;

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
     * Register a block.
     *
     * @since 1.0.0
     *
     * @param  BlockInterface  $block  The block to register.
     */
    public function register(BlockInterface $block): void
    {
        $type = $block->getType();

        if ('' === $type) {
            throw new InvalidArgumentException('Block type must be a non-empty string.');
        }

        $this->blocks[$type] = $block;
    }

    /**
     * Unregister a block by type.
     *
     * @since 1.0.0
     *
     * @param  string|array<int, string>  $types  Block type(s) to remove.
     */
    public function unregister(string|array $types): void
    {
        $types = is_array($types) ? $types : [$types];
        foreach ($types as $type) {
            unset($this->blocks[$type]);
        }
    }

    /**
     * Unregister all blocks in a category.
     *
     * @since 1.0.0
     *
     * @param  string  $category  The category to remove.
     */
    public function unregisterCategory(string $category): void
    {
        $this->blocks = array_filter(
            $this->blocks,
            fn (BlockInterface $block) => $block->getCategory() !== $category,
        );
    }

    /**
     * Get a block by type.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The block type.
     */
    public function get(string $type): ?BlockInterface
    {
        return $this->blocks[$type] ?? null;
    }

    /**
     * Check if a block type is registered.
     *
     * @since 1.0.0
     *
     * @param  string  $type  The block type.
     */
    public function has(string $type): bool
    {
        return isset($this->blocks[$type]);
    }

    /**
     * Remove all registered blocks.
     *
     * @since 1.0.0
     */
    public function clear(): void
    {
        $this->blocks = [];
    }

    /**
     * Get all registered blocks.
     *
     * @since 1.0.0
     *
     * @return array<string, BlockInterface>
     */
    public function all(): array
    {
        return $this->blocks;
    }

    /**
     * Get blocks filtered by category.
     *
     * @since 1.0.0
     *
     * @param  string  $category  The category to filter by.
     * @return array<string, BlockInterface>
     */
    public function getByCategory(string $category): array
    {
        return array_filter(
            $this->blocks,
            fn (BlockInterface $block) => $block->getCategory() === $category,
        );
    }

    /**
     * Get all unique categories from registered blocks.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function getCategories(): array
    {
        $categories = array_unique(
            array_map(
                fn (BlockInterface $block) => $block->getCategory(),
                $this->blocks,
            ),
        );

        return array_values($categories);
    }

    /**
     * Get all blocks that support inner blocks (container blocks).
     *
     * @since 1.0.0
     *
     * @return array<string, BlockInterface>
     */
    public function getContainerBlocks(): array
    {
        return array_filter(
            $this->blocks,
            fn (BlockInterface $block) => $block->supportsInnerBlocks(),
        );
    }

    /**
     * Get all blocks that have a custom JS renderer.
     *
     * @since 1.0.0
     *
     * @return array<string, BlockInterface>
     */
    public function getDynamicBlocks(): array
    {
        return array_filter(
            $this->blocks,
            fn (BlockInterface $block) => $block->hasJsRenderer(),
        );
    }

    /**
     * Get block metadata as arrays for passing to JavaScript.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->blocks as $type => $block) {
            $result[$type] = $block->toArray();
        }

        return $result;
    }
}
