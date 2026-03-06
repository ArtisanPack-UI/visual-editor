<?php

/**
 * Block interface for the visual editor.
 *
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare(strict_types=1);

namespace ArtisanPackUI\VisualEditor\Blocks\Contracts;

interface BlockInterface
{
    /**
     * Get the block type identifier.
     *
     * @since 1.0.0
     */
    public function getType(): string;

    /**
     * Get the block display name.
     *
     * @since 1.0.0
     */
    public function getName(): string;

    /**
     * Get the block description.
     *
     * @since 1.0.0
     */
    public function getDescription(): string;

    /**
     * Get the block icon name.
     *
     * @since 1.0.0
     */
    public function getIcon(): string;

    /**
     * Get the block category.
     *
     * @since 1.0.0
     */
    public function getCategory(): string;

    /**
     * Get searchable keywords for the block.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function getKeywords(): array;

    /**
     * Get the content schema for the block.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function getContentSchema(): array;

    /**
     * Get the style schema for the block.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function getStyleSchema(): array;

    /**
     * Get the advanced settings schema for the block.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAdvancedSchema(): array;

    /**
     * Get default content values.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function getDefaultContent(): array;

    /**
     * Get default style values.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function getDefaultStyles(): array;

    /**
     * Get allowed parent block types, or null for any parent.
     *
     * @since 1.0.0
     *
     * @return array<int, string>|null
     */
    public function getAllowedParents(): ?array;

    /**
     * Get allowed child block types, or null for any child.
     *
     * @since 1.0.0
     *
     * @return array<int, string>|null
     */
    public function getAllowedChildren(): ?array;

    /**
     * Whether this block is publicly visible in the block inserter.
     *
     * @since 1.0.0
     */
    public function isPublic(): bool;

    /**
     * Get supported alignment options.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function getSupportedAlignments(): array;

    /**
     * Whether this block supports inner blocks.
     *
     * @since 1.0.0
     */
    public function supportsInnerBlocks(): bool;

    /**
     * Get the inner blocks orientation for container blocks.
     *
     * @since 1.0.0
     *
     * @return string 'vertical' or 'horizontal'
     */
    public function getInnerBlocksOrientation(): string;

    /**
     * Whether this block has a custom JavaScript renderer.
     *
     * @since 1.0.0
     */
    public function hasJsRenderer(): bool;

    /**
     * Whether this block has a custom inspector panel.
     *
     * @since 1.0.0
     */
    public function hasCustomInspector(): bool;

    /**
     * Render the custom inspector panel HTML.
     *
     * @since 1.0.0
     */
    public function renderInspector(): string;

    /**
     * Whether this block has a custom toolbar.
     *
     * @since 1.0.0
     */
    public function hasCustomToolbar(): bool;

    /**
     * Render the custom toolbar HTML.
     *
     * @since 1.0.0
     */
    public function renderToolbar(): string;

    /**
     * Get toolbar control definitions.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>>
     */
    public function getToolbarControls(): array;

    /**
     * Render the block for the frontend.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $content  Block content/attributes.
     * @param  array<string, mixed>  $styles  Block styles.
     * @param  array<string, mixed>  $context  Additional context.
     */
    public function render(array $content, array $styles, array $context = []): string;

    /**
     * Render the block for the editor canvas.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $content  Block content/attributes.
     * @param  array<string, mixed>  $styles  Block styles.
     * @param  array<string, mixed>  $context  Additional context.
     */
    public function renderEditor(array $content, array $styles, array $context = []): string;

    /**
     * Get the block schema version.
     *
     * @since 1.0.0
     */
    public function getVersion(): int;

    /**
     * Migrate block content from an older version.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $content  Block content to migrate.
     * @param  int  $fromVersion  The version to migrate from.
     * @return array<string, mixed>
     */
    public function migrate(array $content, int $fromVersion): array;

    /**
     * Get block metadata as an array for serialization.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
